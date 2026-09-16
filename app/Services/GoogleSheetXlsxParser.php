<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class GoogleSheetXlsxParser
{
    /**
     * Parse an XLSX file (from file path or binary content) and extract cell values and hyperlinks.
     *
     * @param string $source File path or binary string of .xlsx
     * @param string|null $preferredSheetName Name of the sheet to target (e.g. 'Blogs')
     * @return array{
     *     sheet_name: string,
     *     headers: array<int, string>,
     *     blocks: array<int, array<string, int>>,
     *     rows: array<int, array<int, array{value: string, link: string}>>,
     *     total_rows: int
     * }
     */
    public static function parse(string $source, ?string $preferredSheetName = 'Blogs'): array
    {
        $isTemp = false;
        if (!is_file($source)) {
            $tempPath = tempnam(sys_get_temp_dir(), 'gsh_xlsx_');
            file_put_contents($tempPath, $source);
            $filePath = $tempPath;
            $isTemp = true;
        } else {
            $filePath = $source;
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                throw new RuntimeException('Could not open the XLSX archive.');
            }

            // 1. Read shared strings
            $sharedStrings = self::readSharedStrings($zip);

            // 2. Locate target worksheet from workbook.xml
            $sheetInfo = self::locateWorksheet($zip, $preferredSheetName);
            $sheetPath = $sheetInfo['path'];
            $resolvedSheetName = $sheetInfo['name'];

            // 3. Read hyperlink relationships for this worksheet
            $sheetDir = dirname($sheetPath);
            $sheetBase = basename($sheetPath);
            $sheetRelsPath = $sheetDir . '/_rels/' . $sheetBase . '.rels';
            $hyperlinkTargets = [];
            $sheetRelsXml = $zip->getFromName($sheetRelsPath);
            if ($sheetRelsXml) {
                $sRelsObj = @simplexml_load_string($sheetRelsXml);
                if ($sRelsObj && isset($sRelsObj->Relationship)) {
                    foreach ($sRelsObj->Relationship as $r) {
                        $hyperlinkTargets[(string)$r['Id']] = (string)$r['Target'];
                    }
                }
            }

            // 4. Read worksheet XML
            $sheetXmlRaw = $zip->getFromName($sheetPath);
            $zip->close();

            if ($sheetXmlRaw === false) {
                throw new RuntimeException("Could not read worksheet data for '{$resolvedSheetName}'.");
            }

            $sheetXml = @simplexml_load_string($sheetXmlRaw);
            if ($sheetXml === false || !isset($sheetXml->sheetData)) {
                throw new RuntimeException("The worksheet '{$resolvedSheetName}' is empty or invalid.");
            }

            // Map cell references to hyperlinks
            $cellLinks = [];
            if (isset($sheetXml->hyperlinks)) {
                foreach ($sheetXml->hyperlinks->hyperlink as $hl) {
                    $ref = (string)$hl['ref'];
                    $rId = (string)$hl->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                    if ($rId && isset($hyperlinkTargets[$rId])) {
                        $cellLinks[$ref] = $hyperlinkTargets[$rId];
                    }
                }
            }

            // 5. Parse cell values & hyperlinks
            $rows = [];
            $maxColIdx = 0;
            foreach ($sheetXml->sheetData->row as $rowXml) {
                $rNum = (int)$rowXml['r'];
                $cells = [];
                foreach ($rowXml->c as $c) {
                    $ref = (string)$c['r'];
                    $colStr = preg_replace('/\d+/', '', $ref);
                    $cIdx = self::columnLetterToIndex($colStr);
                    if ($cIdx > $maxColIdx) {
                        $maxColIdx = $cIdx;
                    }

                    $type = (string)$c['t'];
                    if ($type === 's') {
                        $val = $sharedStrings[(int)$c->v] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $val = isset($c->is->t) ? (string)$c->is->t : '';
                    } else {
                        $val = isset($c->v) ? (string)$c->v : '';
                    }

                    // Extract hyperlink from cell relationships, formula, or raw url value
                    $link = $cellLinks[$ref] ?? null;
                    if (!$link && isset($c->f) && preg_match('/HYPERLINK\(\s*\"([^\"]+)\"/i', (string)$c->f, $fm)) {
                        $link = $fm[1];
                    }
                    if (!$link && preg_match('~^https?://~i', trim($val))) {
                        $link = trim($val);
                    }

                    $cells[$cIdx] = [
                        'value' => trim((string)$val),
                        'link'  => $link ? trim((string)$link) : '',
                    ];
                }
                $rows[$rNum] = $cells;
            }

            // 6. Find header row containing 'dated'
            $headerRowIndex = -1;
            $headers = [];
            foreach ($rows as $rNum => $cells) {
                $rowHeaders = [];
                for ($i = 0; $i <= $maxColIdx; $i++) {
                    $rowHeaders[$i] = strtolower($cells[$i]['value'] ?? '');
                }
                if (in_array('dated', $rowHeaders, true)) {
                    $headerRowIndex = $rNum;
                    $headers = $rowHeaders;
                    break;
                }
            }

            // 7. Group column headers into blocks (sections)
            $blocks = [];
            $currentBlock = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') {
                    if (isset($currentBlock['dated'])) {
                        $blocks[] = $currentBlock;
                    }
                    $currentBlock = [];
                    continue;
                }
                if (isset($currentBlock[$header])) {
                    if (isset($currentBlock['dated'])) {
                        $blocks[] = $currentBlock;
                    }
                    $currentBlock = [];
                }
                $currentBlock[$header] = $idx;
            }
            if (isset($currentBlock['dated'])) {
                $blocks[] = $currentBlock;
            }

            return [
                'sheet_name'       => $resolvedSheetName,
                'header_row_index' => $headerRowIndex,
                'headers'          => $headers,
                'blocks'           => $blocks,
                'rows'             => $rows,
                'total_rows'       => count($rows),
                'max_col'          => $maxColIdx,
            ];
        } finally {
            if ($isTemp && file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    /**
     * Read shared strings table from XLSX.
     */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $sharedXml = @simplexml_load_string($xml);
        if ($sharedXml === false) {
            return [];
        }

        $strings = [];
        foreach ($sharedXml->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string)$si->t;
                continue;
            }

            $text = '';
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    $text .= (string)$run->t;
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * Locate the targeted worksheet path in xl/workbook.xml.
     */
    private static function locateWorksheet(ZipArchive $zip, ?string $preferredSheetName): array
    {
        $wbRaw = $zip->getFromName('xl/workbook.xml');
        $wbRelsRaw = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($wbRaw === false || $wbRelsRaw === false) {
            return ['name' => 'Sheet1', 'path' => 'xl/worksheets/sheet1.xml'];
        }

        $wbXml = @simplexml_load_string($wbRaw);
        $wbRelsXml = @simplexml_load_string($wbRelsRaw);

        if (!$wbXml || !$wbRelsXml) {
            return ['name' => 'Sheet1', 'path' => 'xl/worksheets/sheet1.xml'];
        }

        $selectedSheet = null;
        if ($preferredSheetName !== null && isset($wbXml->sheets->sheet)) {
            // 1. Exact case-insensitive match
            foreach ($wbXml->sheets->sheet as $s) {
                if (strcasecmp((string)$s['name'], $preferredSheetName) === 0) {
                    $selectedSheet = $s;
                    break;
                }
            }
            // 2. Substring match (e.g. 'Blogs' inside 'Blogs September')
            if (!$selectedSheet) {
                foreach ($wbXml->sheets->sheet as $s) {
                    if (stripos((string)$s['name'], $preferredSheetName) !== false) {
                        $selectedSheet = $s;
                        break;
                    }
                }
            }
        }

        // Fallback to first sheet
        if (!$selectedSheet && isset($wbXml->sheets->sheet[0])) {
            $selectedSheet = $wbXml->sheets->sheet[0];
        }

        if (!$selectedSheet) {
            return ['name' => 'Sheet1', 'path' => 'xl/worksheets/sheet1.xml'];
        }

        $sheetName = (string)$selectedSheet['name'];
        $rId = (string)$selectedSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        $sheetPath = 'xl/worksheets/sheet1.xml';
        foreach ($wbRelsXml->Relationship as $rel) {
            if ((string)$rel['Id'] === $rId) {
                $target = (string)$rel['Target'];
                $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
                break;
            }
        }

        return ['name' => $sheetName, 'path' => $sheetPath];
    }

    /**
     * Convert Excel column letters (A, B, ..., Z, AA, AB) to 0-indexed integer.
     */
    public static function columnLetterToIndex(string $letters): int
    {
        $letters = strtoupper(trim($letters));
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
