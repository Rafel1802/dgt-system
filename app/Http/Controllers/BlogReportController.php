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

        return view('blog-reports.preview', [
            'data' => $filteredData,
            'monthLabel' => $request->input('month_label', 'Month'),
            'sheetUrl' => $request->input('sheet_url'),
            'dateFrom' => $request->input('date_from'),
            'dateTo' => $request->input('date_to'),
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

        if (!is_array($filteredData)) {
            return $filteredData; // This is a redirect back with error
        }

        return view('blog-reports.report', [
            'data' => $filteredData,
            'monthLabel' => $request->input('month_label', 'Month')
        ]);
    }

    private function fetchAndFilterData(Request $request)
    {
        $url = trim($request->input('sheet_url'));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $carbonFrom = $dateFrom ? \Carbon\Carbon::parse($dateFrom)->startOfDay() : null;
        $carbonTo = $dateTo ? \Carbon\Carbon::parse($dateTo)->endOfDay() : null;

        preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches);
        $spreadsheetId = $matches[1] ?? null;

        preg_match('/gid=([0-9]+)/', $url, $gidMatches);
        
        if (!$spreadsheetId) {
            return back()->with('error', 'Invalid Google Sheet URL. Could not extract Spreadsheet ID.');
        }

        if (!empty($gidMatches[1])) {
            $gid = $gidMatches[1];
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&gid={$gid}";
        } else {
            // If no gid is provided, explicitly request the 'Blogs' sheet
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=Blogs";
        }

        try {
            $response = Http::get($csvUrl);
            
            if (!$response->successful()) {
                $status = $response->status();
                $bodyPreview = substr($response->body(), 0, 150);
                return back()->with('error', "Failed to fetch data from the Google Sheet (Status: {$status}). Make sure it is public and accessible. Response: {$bodyPreview}");
            }
            $csvData = $response->body();
            $lines = explode(PHP_EOL, $csvData);
            $parsedRows = array_map('str_getcsv', $lines);
            
            if (count($parsedRows) < 2) {
                return back()->with('error', 'The Google Sheet is empty or improperly formatted.');
            }

            $headers = array_shift($parsedRows);
            
            $blocks = [];
            foreach ($headers as $idx => $headerName) {
                if (trim($headerName) === 'Class') {
                    $blocks[] = [
                        'classIdx' => $idx,
                        'docLinkIdx' => $idx + 1,
                        'publicLinkIdx' => $idx + 2,
                        'datedIdx' => $idx + 3,
                        'websiteLinkIdx' => $idx + 4,
                    ];
                }
            }

            if (empty($blocks)) {
                // Fallback to indices 0,1,2,3,4 if header is missing
                $blocks[] = [
                    'classIdx' => 0,
                    'docLinkIdx' => 1,
                    'publicLinkIdx' => 2,
                    'datedIdx' => 3,
                    'websiteLinkIdx' => 4,
                ];
            }

            $filteredData = [];

            foreach ($parsedRows as $row) {
                foreach ($blocks as $block) {
                    $rowClass = $row[$block['classIdx']] ?? '';
                    $rowDated = $row[$block['datedIdx']] ?? '';
                    
                    // Skip if both class and date are empty
                    if (empty(trim($rowClass)) && empty(trim($rowDated))) {
                        continue;
                    }

                    // Date Filter logic
                    if ($carbonFrom || $carbonTo) {
                        $parsedRowDate = null;
                        
                        if (!empty($rowDated)) {
                            // Common formats in the sheet
                            $formats = ['d/m', 'm/d', 'n/j', 'j/n', 'd/m/Y', 'm/d/Y', 'n/j/Y', 'j/n/Y', 'd M', 'M d', 'd-m-Y', 'Y-m-d', 'd-M'];
                            foreach ($formats as $fmt) {
                                if (\Carbon\Carbon::hasFormat(trim($rowDated), $fmt)) {
                                    try {
                                        $parsedRowDate = \Carbon\Carbon::createFromFormat($fmt, trim($rowDated));
                                        break;
                                    } catch (\Exception $e) {
                                        $parsedRowDate = null;
                                    }
                                }
                            }

                            // Fallback to standard parse if hasFormat fails but it might still be a valid standard date
                            if (!$parsedRowDate) {
                                try {
                                    $parsedRowDate = \Carbon\Carbon::parse(trim($rowDated));
                                } catch (\Exception $e) {
                                    $parsedRowDate = null;
                                }
                            }
                        }

                        if ($parsedRowDate) {
                            if ($carbonFrom && $parsedRowDate->copy()->endOfDay()->lt($carbonFrom)) {
                                continue;
                            }
                            if ($carbonTo && $parsedRowDate->copy()->startOfDay()->gt($carbonTo)) {
                                continue;
                            }
                        } else {
                            // If we can't parse the date from the sheet but a filter is applied, skip this row
                            continue;
                        }
                    }

                    $filteredData[] = [
                        'class' => trim($rowClass),
                        'doc_link' => trim($row[$block['docLinkIdx']] ?? ''),
                        'public_link' => trim($row[$block['publicLinkIdx']] ?? ''),
                        'dated' => trim($rowDated),
                        'website_link' => trim($row[$block['websiteLinkIdx']] ?? ''),
                    ];
                }
            }

            usort($filteredData, function($a, $b) {
                return (int)$a['class'] <=> (int)$b['class'];
            });
            
            if (empty($filteredData)) {
                return back()->with('error', 'No records matched the selected date criteria.');
            }

            return $filteredData;

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while fetching the Google Sheet: ' . $e->getMessage());
        }
    }
}
