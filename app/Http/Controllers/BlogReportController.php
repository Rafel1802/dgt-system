<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BlogReportController extends Controller
{
    /**
     * Show the Blog Report generator form.
     */
    public function index()
    {
        abort_unless(auth()->user()->can('view-blog-reports'), 403);
        
        return view('blog-reports.index');
    }

    /**
     * Preview the Blog Report.
     */
    public function preview(Request $request)
    {
        abort_unless(auth()->user()->can('view-blog-reports'), 403);

        $request->validate([
            'sheet_url' => 'required|string',
            'date_from' => 'nullable|string',
            'date_to' => 'nullable|string',
            'month_label' => 'nullable|string',
        ]);

        $filteredData = $this->fetchAndFilterData($request);

        if (!is_array($filteredData)) {
            return $filteredData; // This is a redirect back with error
        }

        return view('blog-reports.report', [
            'data' => $filteredData['records'] ?? $filteredData,
            'monthLabel' => $request->input('month_label', 'Month'),
            'debug' => $filteredData['debug'] ?? []
        ]);
    }

    /**
     * Export/Print the final Blog Report.
     */
    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('view-blog-reports'), 403);
        
        $request->validate([
            'sheet_url' => 'required|string',
            'date_from' => 'nullable|string',
            'date_to' => 'nullable|string',
            'month_label' => 'nullable|string',
        ]);

        $filteredData = $this->fetchAndFilterData($request);

        if (!is_array($filteredData) || !isset($filteredData['records'])) {
            return $filteredData; // This is a redirect back with error or old format
        }

        return view('blog-reports.report', [
            'data' => $filteredData['records'],
            'monthLabel' => $request->input('month_label', 'Month'),
            'debug' => $filteredData['debug'] ?? []
        ]);
    }

    private function fetchAndFilterData(Request $request)
    {
        $url = trim($request->input('sheet_url'));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Parse boundaries in Asia/Phnom_Penh timezone
        $carbonFrom = $dateFrom ? \Carbon\Carbon::parse($dateFrom, 'Asia/Phnom_Penh')->startOfDay() : null;
        $carbonTo = $dateTo ? \Carbon\Carbon::parse($dateTo, 'Asia/Phnom_Penh')->endOfDay() : null;

        preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches);
        $spreadsheetId = $matches[1] ?? null;

        if (!$spreadsheetId) {
            return back()->with('error', 'Invalid Google Sheet URL. Could not extract Spreadsheet ID.');
        }

        // Explicitly request the "Blogs" sheet as required
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=Blogs";

        try {
            $response = Http::get($csvUrl);
            
            if (!$response->successful()) {
                $status = $response->status();
                $bodyPreview = substr($response->body(), 0, 150);
                return back()->with('error', "Failed to fetch data from the Google Sheet (Status: {$status}). Make sure it is public and accessible. Response: {$bodyPreview}");
            }
            
            $csvData = $response->body();
            
            // Handle cases where the sheet doesn't exist
            if (stripos($csvData, 'Invalid query') !== false || stripos($csvData, 'table has no columns') !== false) {
                 return back()->with('error', 'The "Blogs" worksheet was not found in the source Google Sheet or is completely empty.');
            }

            $lines = explode(PHP_EOL, $csvData);
            $parsedRows = array_map('str_getcsv', $lines);
            
            if (count($parsedRows) < 2) {
                return back()->with('error', 'The Google Sheet is empty or improperly formatted.');
            }

            // Find the header row that contains 'Dated'
            $headerRowIndex = -1;
            $headers = [];
            foreach ($parsedRows as $index => $row) {
                $rowLower = array_map(function($c) { return strtolower(trim($c)); }, $row);
                if (in_array('dated', $rowLower)) {
                    $headerRowIndex = $index;
                    $headers = $rowLower;
                    break;
                }
            }

            if ($headerRowIndex === -1) {
                return back()->with('error', 'Could not find any "Dated" column in the Blogs worksheet.');
            }

            // Group columns into blocks/sections based on 'dated'
            $blocks = [];
            $currentBlock = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') {
                    // Empty column separator
                    if (isset($currentBlock['dated'])) {
                        $blocks[] = $currentBlock;
                    }
                    $currentBlock = [];
                    continue;
                }
                
                // If we see a header we already have in the current block, it means a new block started without an empty column
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

            $dataRows = array_slice($parsedRows, $headerRowIndex + 1);
            $filteredData = [];

            // Determine the year to use for dates without a year
            $reportYear = $carbonFrom ? $carbonFrom->year : ($carbonTo ? $carbonTo->year : date('Y'));

            $formatsWithYear = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd-m-Y', 'd M Y', 'M d Y'];
            // m/d prioritized as per prompt example "08/13"
            $formatsWithoutYear = ['m/d', 'd/m', 'm-d', 'd-m', 'M d', 'd M'];

            foreach ($dataRows as $row) {
                foreach ($blocks as $block) {
                    $rowClass = isset($block['class']) && isset($row[$block['class']]) ? trim($row[$block['class']]) : '';
                    $rowDocLink = isset($block['doc link']) && isset($row[$block['doc link']]) ? trim($row[$block['doc link']]) : '';
                    $rowPublicLink = isset($block['public link']) && isset($row[$block['public link']]) ? trim($row[$block['public link']]) : '';
                    $rowWebsiteLink = isset($block['website link']) && isset($row[$block['website link']]) ? trim($row[$block['website link']]) : '';
                    
                    $rowDated = isset($block['dated']) && isset($row[$block['dated']]) ? trim($row[$block['dated']]) : '';
                    
                    // A valid blog record should contain a valid Dated value. Skip empty rows.
                    if (empty($rowDated)) {
                        continue;
                    }

                    $parsedRowDate = null;
                    
                    // Try parsing full date first
                    foreach ($formatsWithYear as $fmt) {
                        try {
                            $parsedRowDate = \Carbon\Carbon::createFromFormat($fmt, $rowDated, 'Asia/Phnom_Penh')->startOfDay();
                            break;
                        } catch (\Exception $e) {}
                    }
                    
                    // Try parsing date without year
                    if (!$parsedRowDate) {
                        foreach ($formatsWithoutYear as $fmt) {
                            try {
                                $tempDate = \Carbon\Carbon::createFromFormat($fmt, $rowDated, 'Asia/Phnom_Penh');
                                $parsedRowDate = $tempDate->year($reportYear)->startOfDay();
                                break;
                            } catch (\Exception $e) {}
                        }
                    }
                    
                    // Fallback to standard parse
                    if (!$parsedRowDate) {
                        try {
                            $parsedRowDate = \Carbon\Carbon::parse($rowDated, 'Asia/Phnom_Penh')->startOfDay();
                            if (date('Y', strtotime($rowDated)) == date('Y') && !preg_match('/\d{4}/', $rowDated)) {
                                $parsedRowDate->year($reportYear);
                            }
                        } catch (\Exception $e) {}
                    }

                    // Apply Date Filter
                    if ($parsedRowDate) {
                        if ($carbonFrom && $parsedRowDate->copy()->startOfDay()->lt($carbonFrom)) {
                            continue;
                        }
                        if ($carbonTo && $parsedRowDate->copy()->startOfDay()->gt($carbonTo)) {
                            continue;
                        }
                    } else {
                        // If we can't parse the date at all, skip it because it says "rows without a valid date"
                        continue;
                    }

                    $filteredData[] = [
                        'class' => $rowClass,
                        'doc_link' => $rowDocLink,
                        'public_link' => $rowPublicLink,
                        'dated' => $rowDated,
                        'website_link' => $rowWebsiteLink,
                    ];
                }
            }

            usort($filteredData, function($a, $b) {
                return (int)$a['class'] <=> (int)$b['class'];
            });
            
            if (empty($filteredData)) {
                $fromStr = $dateFrom ?? 'start';
                $toStr = $dateTo ?? 'end';
                return back()->with('error', "No blog records found between {$fromStr} and {$toStr}.");
            }

            $datedColumns = [];
            foreach ($blocks as $b) {
                if (isset($b['dated'])) {
                    // Convert numeric index to column letter (A, B, C...)
                    $col = '';
                    $n = $b['dated'];
                    while ($n >= 0) {
                        $col = chr($n % 26 + 65) . $col;
                        $n = intdiv($n, 26) - 1;
                    }
                    $datedColumns[] = $col;
                }
            }

            $debug = [
                'worksheet' => 'Blogs',
                'detected_dated_columns' => implode(', ', $datedColumns),
                'total_blog_records_read' => $totalRecordsRead ?? count($dataRows) * count($blocks),
                'sections_detected' => count($blocks),
                'filtered_records' => count($filteredData)
            ];

            return [
                'records' => $filteredData,
                'debug' => $debug
            ];

        } catch (\Exception $e) {
            return back()->with('error', 'Unable to read the Blogs worksheet. Please check Google Sheet permissions. Error: ' . $e->getMessage());
        }
    }
}
