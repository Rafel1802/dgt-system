<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Card;
use App\Models\SocialMediaClass;

use Illuminate\Support\Facades\Http;

class FixSmmLabelsCommand extends Command
{
    protected $signature = 'smm:fix-labels {--url= : Google Sheets URL to dynamically fetch the correct class mappings}';
    protected $description = 'Fix SMM class and cluster labels that were swapped or overwritten by the import script';

    public function handle()
    {
        $this->info("Starting SMM label fix...");
        
        $validClusters = SocialMediaClass::pluck('name')->map(function($name) {
            return strtolower(trim($name));
        })->toArray();
        
        if (empty($validClusters)) {
            $this->error("No Social Media Classes found.");
            return 1;
        }

        $contentTypes = ['poster design', 'short reel', 'long landscape', 'share blog', 'reel', 'tips & tricks'];
        
        $cards = Card::whereNotNull('smm_class_label')->orWhereNotNull('smm_cluster_label')->get();

        $url = $this->option('url') ?: 'https://docs.google.com/spreadsheets/d/1MWtQwI-Xd0-SPBGYbmRerAEaUPXoDcsmzeaRBCdyfoY/edit?usp=sharing';
        $titleMap = [];

        if ($url) {
            $this->info("Fetching data from Google Sheets...");
            $csvContent = $this->fetchGoogleSheetsCsv($url);
            if ($csvContent) {
                $rows = $this->parseCsv($csvContent);
                if (!empty($rows)) {
                    $headerRow = array_map(function($h) { return strtolower(trim($h)); }, $rows[0]);
                    
                    // Find indices
                    $classIdx = false;
                    $titleIdx = false;
                    $contentTypeIdx = false;
                    
                    foreach ($headerRow as $idx => $colName) {
                        if ($colName === 'class' || $colName === 'cluster') $classIdx = $idx;
                        if ($colName === 'title') $titleIdx = $idx;
                        if (str_contains($colName, 'work task') || str_contains($colName, 'content type')) $contentTypeIdx = $idx;
                    }
                    
                    if ($classIdx !== false) {
                        foreach (array_slice($rows, 1) as $row) {
                            $clusterName = trim($row[$classIdx] ?? '');
                            $rawTitle = trim($row[$titleIdx] ?? '');
                            $contentType = trim($row[$contentTypeIdx] ?? '');
                            
                            if (empty($rawTitle) && empty($contentType)) continue;
                            
                            if (empty($rawTitle)) {
                                $title = $contentType;
                            } elseif (stripos($rawTitle, $contentType) === false) {
                                $title = $rawTitle . ' - ' . $contentType;
                            } else {
                                $title = $rawTitle;
                            }
                            
                            if (!empty($title) && !empty($clusterName)) {
                                $titleMap[strtolower($title)] = $clusterName;
                                if (!empty($rawTitle)) {
                                    $titleMap[strtolower($rawTitle)] = $clusterName;
                                }
                            }
                        }
                        $this->info("Successfully built mapping for " . count($titleMap) . " titles from the Google Sheet.");
                    } else {
                        $this->error("Could not find 'Class' or 'Cluster' column in the sheet.");
                    }
                }
            } else {
                $this->error("Failed to fetch CSV from the URL.");
            }
        }

        $fixedCount = 0;
        foreach ($cards as $card) {
            $classLabel = strtolower(trim($card->smm_class_label ?? ''));
            $clusterLabel = strtolower(trim($card->smm_cluster_label ?? ''));
            $cardTitle = trim($card->title);

            $cardTitleLower = strtolower($cardTitle);

            // Attempt to deduce the actual class from the title
            $matchedClass = null;
            
            // 1. Exact match
            if (isset($titleMap[$cardTitleLower])) {
                $matchedClass = $titleMap[$cardTitleLower];
            } else {
                // 2. Partial match if exact fails
                foreach ($titleMap as $titleLower => $cluster) {
                    if (str_contains($cardTitleLower, $titleLower)) {
                        $matchedClass = $cluster;
                        // Don't break immediately, find the longest match if possible, or just accept the first good one.
                        break;
                    }
                }
            }

            if ($matchedClass && strtolower($card->smm_class_label) !== strtolower($matchedClass)) {
                $oldClass = $card->smm_class_label;
                $card->smm_class_label = $matchedClass;
                // Leave smm_cluster_label as is (it correctly holds the content type)
                $card->save();
                
                $fixedCount++;
                $this->line("Fixed card {$card->id} ({$cardTitle}): Set smm_class_label to '{$matchedClass}' (was '{$oldClass}')");
                continue;
            }
            
            // Fallback: If it couldn't be matched by title, check if they are simply swapped
            $isBackward = false;
            if (in_array($classLabel, $contentTypes) && in_array($clusterLabel, $validClusters)) {
                $isBackward = true;
            }
            
            if ($isBackward) {
                $wrongClass = $card->smm_class_label;
                $wrongCluster = $card->smm_cluster_label;
                
                $card->smm_class_label = $wrongCluster;
                $card->smm_cluster_label = $wrongClass;
                $card->save();
                
                $fixedCount++;
                $this->line("Swapped card {$card->id}: Set smm_class_label to '{$wrongCluster}'");
            }
        }
        
        $this->info("Done! Fixed $fixedCount cards.");
        return 0;
    }

    private function fetchGoogleSheetsCsv(string $url): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $fileId = $matches[1];
            
            $gidStr = '';
            if (preg_match('/[#&]gid=([0-9]+)/', $url, $gidMatches)) {
                $gidStr = '&gid=' . $gidMatches[1];
            }
            
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$fileId}/export?format=csv{$gidStr}";
            $response = Http::get($csvUrl);
            if ($response->successful()) {
                return $response->body();
            }
        }
        return null;
    }

    private function parseCsv(string $content): array
    {
        $rows = [];
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $rows[] = str_getcsv($line);
        }
        return $rows;
    }
}
