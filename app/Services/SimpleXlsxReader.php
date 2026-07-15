<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal, dependency-free .xlsx reader for the Process Trucking import.
 * Deliberately avoids phpoffice/phpspreadsheet: adding a new Composer
 * package can't reach production through the existing deploy pipeline —
 * vendor/ is excluded from every rsync deploy, and there's no composer
 * install step run on the server. .xlsx is just a zip of XML files, and
 * reading plain string/number cell values from a single sheet doesn't need
 * a full spreadsheet library.
 */
class SimpleXlsxReader
{
    /**
     * @return array<int, array<int, string|null>> rows, each a 0-indexed array of cell values (by column position)
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the file as a .xlsx archive.');
        }

        $sharedStrings = self::readSharedStrings($zip);
        $sheetPath = self::resolveFirstSheetPath($zip);
        $xml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Could not read worksheet data from the file.');
        }

        $sheetXml = simplexml_load_string($xml);
        if ($sheetXml === false || ! isset($sheetXml->sheetData)) {
            throw new RuntimeException('The worksheet appears to be empty or invalid.');
        }

        $rows = [];
        $lastRowNumber = 0;
        foreach ($sheetXml->sheetData->row as $rowXml) {
            // A row with zero populated cells often has no <row> element at
            // all in the XML — Excel/Sheets omit fully-empty rows rather
            // than writing e.g. <row r="56" /> — so gaps in the r="" row
            // number must be backfilled with blank rows here, or a blank
            // separator row silently vanishes and two logical blocks in a
            // single-column export merge into one.
            $rowNumber = $rowXml['r'] !== null && (string) $rowXml['r'] !== '' ? (int) $rowXml['r'] : $lastRowNumber + 1;
            for ($i = $lastRowNumber + 1; $i < $rowNumber; $i++) {
                $rows[] = [];
            }
            $lastRowNumber = $rowNumber;

            $cells = [];
            foreach ($rowXml->c as $cellXml) {
                $ref = (string) $cellXml['r'];
                $colLetters = preg_replace('/\d+/', '', $ref);
                $colIndex = self::columnLetterToIndex($colLetters);
                $type = (string) $cellXml['t'];

                if ($type === 's') {
                    $value = $sharedStrings[(int) $cellXml->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($cellXml->is->t) ? (string) $cellXml->is->t : '';
                } else {
                    $value = isset($cellXml->v) ? (string) $cellXml->v : '';
                }

                $cells[$colIndex] = $value;
            }

            if (empty($cells)) {
                $rows[] = [];
                continue;
            }

            $maxCol = max(array_keys($cells));
            $rows[] = array_map(fn ($i) => $cells[$i] ?? null, range(0, $maxCol));
        }

        return $rows;
    }

    /** @return array<int, string> */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $sharedXml = simplexml_load_string($xml);
        if ($sharedXml === false) {
            return [];
        }

        $strings = [];
        foreach ($sharedXml->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }

            // Rich text runs: <si><r><t>...</t></r><r><t>...</t></r></si>
            $text = '';
            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private static function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $workbookRaw = $zip->getFromName('xl/workbook.xml');
        $relsRaw = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookRaw === false || $relsRaw === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbookXml = simplexml_load_string($workbookRaw);
        $relsXml = simplexml_load_string($relsRaw);

        $firstSheet = $workbookXml->sheets->sheet[0] ?? null;
        if (! $firstSheet) {
            return 'xl/worksheets/sheet1.xml';
        }

        $rId = (string) $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        foreach ($relsXml->Relationship as $rel) {
            if ((string) $rel['Id'] === $rId) {
                $target = (string) $rel['Target'];
                // Target is either package-root-absolute ("/xl/worksheets/sheet1.xml")
                // or relative to the xl/ folder ("worksheets/sheet1.xml") — both are
                // valid per the OOXML spec depending on which tool wrote the file.
                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function columnLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
