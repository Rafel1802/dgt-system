<?php

namespace App\Services\GoogleSearchConsole;

use ZipArchive;
use Exception;

class XlsxExportService
{
    /**
     * Generate an Excel (.xlsx) file with multiple worksheets
     *
     * @param array $sheets Array of ['name' => string, 'headers' => array, 'rows' => array]
     * @param string|null $outputPath Optional destination filepath. If null, a temporary file is created.
     * @return string Path to the created .xlsx file
     */
    public function generate(array $sheets, ?string $outputPath = null): string
    {
        $filePath = $outputPath ?: tempnam(sys_get_temp_dir(), 'gsc_') . '.xlsx';

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Unable to create Excel zip archive at {$filePath}");
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
        $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        foreach ($sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $contentTypes .= "<Override PartName=\"/xl/worksheets/sheet{$sheetNum}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $rootRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $rootRels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $rootRels .= '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/styles.xml
        $styles = $this->buildStylesXml();
        $zip->addFromString('xl/styles.xml', $styles);

        // 4. xl/workbook.xml & xl/_rels/workbook.xml.rels
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $workbookXml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $workbookXml .= '<sheets>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $wbRels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        foreach ($sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $relId = 'rId' . ($idx + 10);
            $safeName = htmlspecialchars(substr($sheet['name'] ?? "Sheet{$sheetNum}", 0, 31), ENT_XML1);
            $workbookXml .= "<sheet name=\"{$safeName}\" sheetId=\"{$sheetNum}\" r:id=\"{$relId}\"/>";
            $wbRels .= "<Relationship Id=\"{$relId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$sheetNum}.xml\"/>";
        }

        $workbookXml .= '</sheets></workbook>';
        $wbRels .= '</Relationships>';

        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 5. xl/worksheets/sheetN.xml
        foreach ($sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $sheetXml = $this->buildSheetXml($sheet['headers'] ?? [], $sheet['rows'] ?? []);
            $zip->addFromString("xl/worksheets/sheet{$sheetNum}.xml", $sheetXml);
        }

        $zip->close();
        return $filePath;
    }

    /**
     * Build styles.xml for Excel styling (header fill, fonts, borders)
     */
    protected function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2">' .
                '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>' .
                '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>' .
            '</fonts>' .
            '<fills count="3">' .
                '<fill><patternFill patternType="none"/></fill>' .
                '<fill><patternFill patternType="gray125"/></fill>' .
                '<fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/></patternFill></fill>' . // Slate-800
            '</fills>' .
            '<borders count="2">' .
                '<border><left/><right/><top/><bottom/></border>' .
                '<border>' .
                    '<left style="thin"><color rgb="FFE2E8F0"/></left>' .
                    '<right style="thin"><color rgb="FFE2E8F0"/></right>' .
                    '<top style="thin"><color rgb="FFE2E8F0"/></top>' .
                    '<bottom style="thin"><color rgb="FFE2E8F0"/></bottom>' .
                '</border>' .
            '</borders>' .
            '<cellStyleXfs count="1">' .
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' .
            '</cellStyleXfs>' .
            '<cellXfs count="3">' .
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' . // Normal data cell (s="0")
                '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' . // Header cell (s="1")
                '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>' . // Numeric formatted (s="2")
            '</cellXfs>' .
        '</styleSheet>';
    }

    /**
     * Build worksheet XML for a given table
     */
    protected function buildSheetXml(array $headers, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        $rowIndex = 1;

        // Header Row (if provided)
        if (!empty($headers)) {
            $xml .= "<row r=\"{$rowIndex}\" customHeight=\"1\" ht=\"24\">";
            foreach ($headers as $colIndex => $headerText) {
                $cellRef = $this->columnLetter($colIndex) . $rowIndex;
                $val = htmlspecialchars((string) $headerText, ENT_XML1);
                $xml .= "<c r=\"{$cellRef}\" t=\"inlineStr\" s=\"1\"><is><t>{$val}</t></is></c>";
            }
            $xml .= '</row>';
            $rowIndex++;
        }

        // Data Rows
        foreach ($rows as $row) {
            $xml .= "<row r=\"{$rowIndex}\">";
            foreach (array_values($row) as $colIndex => $val) {
                $cellRef = $this->columnLetter($colIndex) . $rowIndex;

                if (is_numeric($val) && !is_string($val)) {
                    $xml .= "<c r=\"{$cellRef}\" s=\"0\"><v>{$val}</v></c>";
                } elseif (is_numeric($val) && !str_starts_with((string)$val, '0') && !str_contains((string)$val, '-') && strlen((string)$val) < 15) {
                    $xml .= "<c r=\"{$cellRef}\" s=\"0\"><v>{$val}</v></c>";
                } else {
                    $escaped = htmlspecialchars((string) ($val ?? ''), ENT_XML1);
                    $xml .= "<c r=\"{$cellRef}\" t=\"inlineStr\" s=\"0\"><is><t>{$escaped}</t></is></c>";
                }
            }
            $xml .= '</row>';
            $rowIndex++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    /**
     * Convert 0-based column index to Excel column letter (A, B, C, ... AA, AB)
     */
    protected function columnLetter(int $colIndex): string
    {
        $letter = '';
        $colIndex += 1;
        while ($colIndex > 0) {
            $modulo = ($colIndex - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $colIndex = (int) (($colIndex - $modulo) / 26);
        }
        return $letter;
    }
}
