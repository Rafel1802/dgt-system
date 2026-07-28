<?php

namespace App\Services;

use App\Exceptions\GoogleSheetsNotConfiguredException;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\Permission;
use Google\Service\Sheets;
use Google\Service\Sheets\AddBandingRequest;
use Google\Service\Sheets\AutoResizeDimensionsRequest;
use Google\Service\Sheets\BandedRange;
use Google\Service\Sheets\BandingProperties;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\CellData;
use Google\Service\Sheets\CellFormat;
use Google\Service\Sheets\Color;
use Google\Service\Sheets\DimensionRange;
use Google\Service\Sheets\GridProperties;
use Google\Service\Sheets\GridRange;
use Google\Service\Sheets\NumberFormat;
use Google\Service\Sheets\RepeatCellRequest;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\SpreadsheetProperties;
use Google\Service\Sheets\TextFormat;
use Google\Service\Sheets\UpdateSheetPropertiesRequest;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Collection;
use Throwable;

class GoogleSheetsExportService
{
    /**
     * Export the given Call Reports as a new, cleanly-formatted Google Sheet.
     * Returns the live spreadsheet URL.
     *
     * @throws GoogleSheetsNotConfiguredException
     */
    public function exportCallReports(Collection $reports, string $title): string
    {
        $client = $this->makeClient();
        $sheets = new Sheets($client);
        $drive  = new Drive($client);

        $headers = ['#', 'Name', 'Phone', 'Email', 'Type', 'Details / Note', 'Answered By', 'Date', 'Time'];
        $rows = $reports->values()->map(function ($report, $i) {
            return [
                $i + 1,
                $report->name,
                $report->phone ?? '—',
                $report->email ?? '—',
                $report->inquiry_type,
                $report->details ?? '',
                $report->answeredBy?->name ?? '—',
                $report->occurred_at?->format('Y-m-d') ?? '',
                $report->occurred_at?->format('H:i') ?? '',
            ];
        })->all();

        $spreadsheet = new Spreadsheet([
            'properties' => new SpreadsheetProperties(['title' => $title]),
        ]);

        $created = $sheets->spreadsheets->create($spreadsheet, ['fields' => 'spreadsheetId,spreadsheetUrl']);
        $spreadsheetId = $created->getSpreadsheetId();
        $sheetId = 0; // the default first sheet on a newly created spreadsheet

        // Write header + data rows in one call.
        $sheets->spreadsheets_values->update(
            $spreadsheetId,
            'A1',
            new ValueRange(['values' => array_merge([$headers], $rows)]),
            ['valueInputOption' => 'RAW']
        );

        $lastColumn = count($headers);
        $lastRow = count($rows) + 1;

        $sheets->spreadsheets->batchUpdate($spreadsheetId, new BatchUpdateSpreadsheetRequest([
            'requests' => [
                // Bold white-on-indigo header row.
                new SheetsRequest([
                    'repeatCell' => new RepeatCellRequest([
                        'range' => new GridRange([
                            'sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1,
                            'startColumnIndex' => 0, 'endColumnIndex' => $lastColumn,
                        ]),
                        'cell' => new CellData([
                            'userEnteredFormat' => new CellFormat([
                                'backgroundColor' => new Color(['red' => 0.16, 'green' => 0.22, 'blue' => 0.63]),
                                'textFormat' => new TextFormat([
                                    'bold' => true,
                                    'foregroundColor' => new Color(['red' => 1, 'green' => 1, 'blue' => 1]),
                                ]),
                            ]),
                        ]),
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat)',
                    ]),
                ]),
                // Freeze the header row and size the grid to the data.
                new SheetsRequest([
                    'updateSheetProperties' => new UpdateSheetPropertiesRequest([
                        'properties' => new SheetProperties([
                            'sheetId' => $sheetId,
                            'gridProperties' => new GridProperties(['frozenRowCount' => 1]),
                        ]),
                        'fields' => 'gridProperties.frozenRowCount',
                    ]),
                ]),
                // Clean alternating row banding across the data range.
                new SheetsRequest([
                    'addBanding' => new AddBandingRequest([
                        'bandedRange' => new BandedRange([
                            'range' => new GridRange([
                                'sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => max($lastRow, 1),
                                'startColumnIndex' => 0, 'endColumnIndex' => $lastColumn,
                            ]),
                            'rowProperties' => new BandingProperties([
                                'headerColor' => new Color(['red' => 0.16, 'green' => 0.22, 'blue' => 0.63]),
                                'firstBandColor' => new Color(['red' => 1, 'green' => 1, 'blue' => 1]),
                                'secondBandColor' => new Color(['red' => 0.95, 'green' => 0.96, 'blue' => 0.98]),
                            ]),
                        ]),
                    ]),
                ]),
                // Auto-resize columns to fit their content.
                new SheetsRequest([
                    'autoResizeDimensions' => new AutoResizeDimensionsRequest([
                        'dimensions' => new DimensionRange([
                            'sheetId' => $sheetId, 'dimension' => 'COLUMNS',
                            'startIndex' => 0, 'endIndex' => $lastColumn,
                        ]),
                    ]),
                ]),
                // Plain date formatting on the Date column.
                new SheetsRequest([
                    'repeatCell' => new RepeatCellRequest([
                        'range' => new GridRange([
                            'sheetId' => $sheetId, 'startRowIndex' => 1, 'endRowIndex' => max($lastRow, 1),
                            'startColumnIndex' => $lastColumn - 1, 'endColumnIndex' => $lastColumn,
                        ]),
                        'cell' => new CellData([
                            'userEnteredFormat' => new CellFormat([
                                'numberFormat' => new NumberFormat(['type' => 'DATE', 'pattern' => 'yyyy-mm-dd']),
                            ]),
                        ]),
                        'fields' => 'userEnteredFormat.numberFormat',
                    ]),
                ]),
            ],
        ]));

        // Make the sheet accessible via link without requiring individual sharing.
        $drive->permissions->create($spreadsheetId, new Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]));

        return $created->getSpreadsheetUrl();
    }

    /**
     * @throws GoogleSheetsNotConfiguredException
     */
    private function makeClient(): Client
    {
        $path = config('services.google.service_account_path');

        if (empty($path) || ! is_readable($path)) {
            throw new GoogleSheetsNotConfiguredException();
        }

        try {
            $client = new Client();
            $client->setAuthConfig($path);
            $client->setScopes([Sheets::SPREADSHEETS, Drive::DRIVE_FILE]);
            $client->setApplicationName(config('app.name', 'CRM'));

            return $client;
        } catch (Throwable $e) {
            throw new GoogleSheetsNotConfiguredException();
        }
    }
}
