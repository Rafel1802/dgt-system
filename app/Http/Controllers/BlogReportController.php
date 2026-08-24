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
     * Generate the Blog Report from a Google Sheet link.
     */
    public function generate(Request $request)
    {
        abort_unless(auth()->user()->can('view-blog-reports'), 403);

        $request->validate([
            'sheet_url' => 'required|url',
            'date_filter' => 'nullable|string',
            'class_filter' => 'nullable|string',
            'month_label' => 'nullable|string', // e.g., "July"
        ]);

        $url = $request->input('sheet_url');
        $dateFilter = $request->input('date_filter');
        $classFilter = $request->input('class_filter');
        $monthLabel = $request->input('month_label', 'Month');

        // Extract Spreadsheet ID and GID
        // Example URL: https://docs.google.com/spreadsheets/d/1X727wTcYSEdybFppqsoqDPmbt3igZ4a844U6ncZh8JQ/edit#gid=1970379363
        preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches);
        $spreadsheetId = $matches[1] ?? null;

        preg_match('/gid=([0-9]+)/', $url, $gidMatches);
        $gid = $gidMatches[1] ?? '0';

        if (!$spreadsheetId) {
            return back()->with('error', 'Invalid Google Sheet URL. Could not extract Spreadsheet ID.');
        }

        // Construct CSV export URL
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";

        try {
            $response = Http::get($csvUrl);
            
            if (!$response->successful()) {
                return back()->with('error', 'Failed to fetch data from the Google Sheet. Make sure the sheet is accessible (Anyone with the link can view).');
            }

            $csvData = $response->body();
            
            // Parse CSV
            $lines = explode(PHP_EOL, $csvData);
            $parsedRows = array_map('str_getcsv', $lines);
            
            if (count($parsedRows) < 2) {
                return back()->with('error', 'The Google Sheet is empty or improperly formatted.');
            }

            // Assume first row is header
            $headers = array_shift($parsedRows);
            
            // The columns in the image are: 
            // 0: Class, 1: Doc Link, 2: Public Link, 3: Dated, 4: Website link
            // Or maybe a different order, so let's find the indexes dynamically if possible, or fallback to fixed.
            $classIdx = array_search('Class', $headers) !== false ? array_search('Class', $headers) : 0;
            $docLinkIdx = array_search('Doc Link', $headers) !== false ? array_search('Doc Link', $headers) : 1;
            $publicLinkIdx = array_search('Public Link', $headers) !== false ? array_search('Public Link', $headers) : 2;
            $datedIdx = array_search('Dated', $headers) !== false ? array_search('Dated', $headers) : 3;
            $websiteLinkIdx = array_search('Website link', $headers) !== false ? array_search('Website link', $headers) : 4;

            $filteredData = [];

            foreach ($parsedRows as $row) {
                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowClass = $row[$classIdx] ?? '';
                $rowDated = $row[$datedIdx] ?? '';

                // 1. Date Filter
                if ($dateFilter && !str_contains($rowDated, $dateFilter)) {
                    continue; // Skip if date doesn't match
                }

                // 2. Class Filter
                if ($classFilter && $classFilter !== 'all') {
                    // Match exactly or loosely
                    if (trim($rowClass) !== trim($classFilter)) {
                        continue;
                    }
                }

                $filteredData[] = [
                    'class' => trim($rowClass),
                    'doc_link' => trim($row[$docLinkIdx] ?? ''),
                    'public_link' => trim($row[$publicLinkIdx] ?? ''),
                    'dated' => trim($rowDated),
                    'website_link' => trim($row[$websiteLinkIdx] ?? ''),
                ];
            }

            // Sort by Class ascending (1 on top)
            usort($filteredData, function($a, $b) {
                return (int)$a['class'] <=> (int)$b['class'];
            });

            return view('blog-reports.report', [
                'data' => $filteredData,
                'monthLabel' => $monthLabel
            ]);

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while fetching the Google Sheet: ' . $e->getMessage());
        }
    }
}
