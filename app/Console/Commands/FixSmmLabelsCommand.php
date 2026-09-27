<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Card;
use App\Models\SocialMediaClass;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FixSmmLabelsCommand extends Command
{
    protected $signature = 'smm:fix-labels {--url= : Google Sheets URL to dynamically fetch the correct class mappings} {--dry-run : Report fixes without saving} {--week= : Limit to specific week number, e.g. 4} {--board= : Limit to specific board ID or name}';
    protected $description = 'Intelligently fix SMM class and cluster labels that were swapped or overwritten by the import script';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $targetWeek = $this->option('week');
        $targetBoard = $this->option('board');

        $this->info("Starting intelligent SMM label fix..." . ($isDryRun ? " (DRY RUN)" : ""));
        
        $validClasses = array_values(array_unique(array_filter(
            array_map(fn($n) => SocialMediaClass::canonicalName($n), SocialMediaClass::pluck('name')->toArray()),
            fn($n) => !in_array($n, ['Machinery.Bargains', 'SkidSteer'])
        )));
        $validClassesLower = array_map(fn($n) => strtolower(trim($n)), $validClasses);
        
        if (empty($validClassesLower)) {
            $this->error("No Social Media Classes found.");
            return 1;
        }

        $contentTypes = ['poster design', 'short reel', 'long landscape', 'share blog', 'reel', 'tips & tricks'];

        $url = $this->option('url') ?: 'https://docs.google.com/spreadsheets/d/1MWtQwI-Xd0-SPBGYbmRerAEaUPXoDcsmzeaRBCdyfoY/gviz/tq?tqx=out:csv';
        $sheetRows = [];

        if ($url) {
            $this->info("Fetching data from Google Sheets...");
            $csvContent = $this->fetchGoogleSheetsCsv($url);
            if ($csvContent) {
                $sheetRows = $this->parseSheetData($csvContent);
                $this->info("Successfully parsed " . count($sheetRows) . " valid content rows from Google Sheets.");
            } else {
                $this->error("Failed to fetch CSV from the URL. Falling back to swap-only detection.");
            }
        }

        // Query cards to inspect
        $cardQuery = Card::where(function ($q) {
            $q->whereNotNull('smm_class_label')
              ->orWhereNotNull('smm_cluster_label')
              ->orWhereNotNull('sync_group_id');
        })->whereNull('deleted_at')->with(['boardList', 'assignees', 'board']);

        if ($targetBoard) {
            $cardQuery->whereHas('board', function ($q) use ($targetBoard) {
                if (is_numeric($targetBoard)) {
                    $q->where('id', (int)$targetBoard);
                } else {
                    $q->where('name', 'like', "%{$targetBoard}%");
                }
            });
        }

        $cards = $cardQuery->get();
        $this->info("Inspecting " . $cards->count() . " cards across active boards...");

        $cleanAlpha = fn(string $s) => preg_replace('/[^a-z0-9]/', '', strtolower($s));
        $fixedCount = 0;
        $swappedCount = 0;

        foreach ($cards as $card) {
            $cardList = $card->boardList;
            $cardListName = $cardList ? $cardList->name : '';
            
            // Extract week number from card's list name (e.g. "Week 4" -> 4)
            $cardWeekNum = null;
            if (preg_match('/(?:week|w)\s*[\-_]?\s*(\d+)/i', $cardListName, $m)) {
                $cardWeekNum = (int)$m[1];
            }

            if ($targetWeek !== null && $cardWeekNum !== (int)$targetWeek) {
                continue;
            }

            $cardTitle = trim($card->title);
            $normCardTitle = $cleanAlpha($cardTitle);
            
            $cardDate = null;
            $rawDate = $card->content_public_date ?? $card->start_date;
            if ($rawDate instanceof \Carbon\CarbonInterface) {
                $cardDate = $rawDate->format('Y-m-d');
            } elseif (!empty($rawDate)) {
                $cardDate = substr(trim((string)$rawDate), 0, 10);
            }

            $cardAssignees = $card->assignees->pluck('name')->toArray();
            $cardDesc = trim((string)$card->description);

            // 1. Try intelligent multi-factor matching against Google Sheet rows
            $matchedClass = null;
            if (!empty($sheetRows) && !empty($normCardTitle)) {
                $scored = [];

                foreach ($sheetRows as $sr) {
                    $normFull = $sr['norm_full_title'];
                    $normRaw = $sr['norm_raw_title'];

                    $titleMatch = false;
                    $titleScore = 0;

                    if ($normFull === $normCardTitle) {
                        $titleMatch = true;
                        $titleScore = 50;
                    } elseif ($normRaw === $normCardTitle) {
                        $titleMatch = true;
                        $titleScore = 40;
                    } elseif (strlen($normRaw) >= 4 && str_starts_with($normCardTitle, $normRaw)) {
                        $titleMatch = true;
                        $titleScore = 30;
                    } elseif (!empty($sr['content_type']) && str_contains($normCardTitle, $cleanAlpha($sr['content_type']))) {
                        // Check if model code tokens overlap significantly (e.g. TYPH-GRX1 and TYPH-5017M)
                        $srTokens = preg_split('/[^a-z0-9]+/', strtolower($sr['raw_title']), -1, PREG_SPLIT_NO_EMPTY);
                        $cardTokens = preg_split('/[^a-z0-9]+/', strtolower($cardTitle), -1, PREG_SPLIT_NO_EMPTY);
                        $common = array_intersect($srTokens, $cardTokens);
                        // Filter out common non-unique words
                        $significant = array_filter($common, fn($t) => strlen($t) >= 4 && !in_array($t, ['poster', 'design', 'short', 'reel', 'landscape', 'video']));
                        if (count($significant) >= 2) {
                            $titleMatch = true;
                            $titleScore = 35;
                        }
                    }

                    if (!$titleMatch) {
                        continue;
                    }

                    // STRICT DATE & WEEK CHECKS:
                    if ($cardDate && $sr['public_date']) {
                        // If both have dates and they differ, they belong to different days/schedules!
                        if ($cardDate !== $sr['public_date']) {
                            continue;
                        }
                        // If dates match exactly, that is definitive ground truth!
                    } else {
                        // If either lacks a date, ensure planning week numbers don't conflict
                        if ($cardWeekNum !== null && $sr['week_num'] !== null && $cardWeekNum !== $sr['week_num']) {
                            continue;
                        }
                    }

                    $score = $titleScore;

                    // Date match (+100 for exact date)
                    if ($cardDate && $sr['public_date'] && $cardDate === $sr['public_date']) {
                        $score += 100;
                    }

                    // Week match (+30 for matching week)
                    if ($cardWeekNum !== null && $sr['week_num'] !== null && $cardWeekNum === $sr['week_num']) {
                        $score += 30;
                    }

                    // Assignee match (+40)
                    if (!empty($sr['assigned_to']) && !empty($cardAssignees)) {
                        $rawAssign = $cleanAlpha($sr['assigned_to']);
                        foreach ($cardAssignees as $ca) {
                            $normCa = $cleanAlpha($ca);
                            if (str_contains(strtolower($ca), strtolower($sr['assigned_to'])) || str_contains($normCa, $rawAssign) || str_contains($rawAssign, $normCa)) {
                                $score += 40;
                                break;
                            }
                        }
                    }

                    // Description match (+50)
                    if (!empty($cardDesc) && !empty($sr['description'])) {
                        if (strcasecmp($cardDesc, $sr['description']) === 0 || str_contains(strtolower($cardDesc), strtolower($sr['description']))) {
                            $score += 50;
                        }
                    }

                    // Content type match (+20)
                    if (!empty($card->smm_cluster_label) && !empty($sr['content_type'])) {
                        if (strcasecmp(trim($card->smm_cluster_label), trim($sr['content_type'])) === 0) {
                            $score += 20;
                        }
                    }

                    $scored[] = ['row' => $sr, 'score' => $score];
                }

                if (!empty($scored)) {
                    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
                    $best = $scored[0];

                    // Check if title is unique to a single cluster in the sheet with valid positive score
                    $matchingClusters = array_unique(array_map(fn($item) => $item['row']['cluster'], $scored));
                    if (count($matchingClusters) === 1 && $best['score'] >= 30) {
                        $matchedClass = reset($matchingClusters);
                    } elseif ($best['score'] >= 50) {
                        // High confidence disambiguation via date/assignee/week
                        $matchedClass = $best['row']['cluster'];
                    }
                }
            }

            // Apply Sheet Match if class is wrong
            if ($matchedClass) {
                $matchedClass = SocialMediaClass::canonicalName($matchedClass);
            }
            if ($matchedClass && strtolower(trim($card->smm_class_label ?? '')) !== strtolower(trim($matchedClass))) {
                $oldClass = $card->smm_class_label ?: 'NULL';
                
                if (!$isDryRun) {
                    $card->smm_class_label = $matchedClass;
                    $card->save();

                    // Cascade to twin cards in sync group
                    if ($card->sync_group_id) {
                        Card::where('sync_group_id', $card->sync_group_id)
                            ->where('id', '!=', $card->id)
                            ->update(['smm_class_label' => $matchedClass]);
                    }
                }

                $fixedCount++;
                $this->line("Fixed card {$card->id} [{$card->title}] on {$cardListName}: Set smm_class_label to '{$matchedClass}' (was '{$oldClass}')");
                continue;
            }

            // 2. Fallback: Check if smm_class_label and smm_cluster_label were simply swapped
            $classLabel = strtolower(trim($card->smm_class_label ?? ''));
            $clusterLabel = strtolower(trim($card->smm_cluster_label ?? ''));

            $isBackward = (in_array($classLabel, $contentTypes) && in_array($clusterLabel, $validClassesLower));
            if ($isBackward) {
                $wrongClass = $card->smm_class_label;
                $wrongCluster = SocialMediaClass::canonicalName($card->smm_cluster_label);

                if (!$isDryRun) {
                    $card->smm_class_label = $wrongCluster;
                    $card->smm_cluster_label = $wrongClass;
                    $card->save();

                    if ($card->sync_group_id) {
                        Card::where('sync_group_id', $card->sync_group_id)
                            ->where('id', '!=', $card->id)
                            ->update([
                                'smm_class_label' => $wrongCluster,
                                'smm_cluster_label' => $wrongClass,
                            ]);
                    }
                }

                $swappedCount++;
                $this->line("Swapped card {$card->id} [{$card->title}]: Fixed smm_class_label to '{$wrongCluster}' and cluster to '{$wrongClass}'");
            }
        }

        // 3. Synchronize Assign By (created_by) across all twin cards in each sync group
        $this->info("Verifying Assign By (created_by) consistency across synced cards...");
        $syncGroups = Card::whereNotNull('sync_group_id')
            ->where('sync_group_id', '!=', '')
            ->select('sync_group_id')
            ->distinct()
            ->pluck('sync_group_id');

        $creatorFixCount = 0;
        foreach ($syncGroups as $groupId) {
            $twins = Card::where('sync_group_id', $groupId)->whereNull('deleted_at')->with(['creator', 'board'])->get();
            if ($twins->count() <= 1) continue;

            $creators = $twins->pluck('created_by')->unique();
            if ($creators->count() <= 1) continue;

            // Find the authoritative card (SMM Planning board first, or Planning board)
            $smmCard = $twins->first(fn($c) => $c->board && ($c->board->type === 'smm' || stripos($c->board->name, 'smm') !== false));
            $planningCard = $twins->first(fn($c) => $c->board && stripos($c->board->name, 'planning') !== false);
            $authCard = ($smmCard && $smmCard->created_by) ? $smmCard : (($planningCard && $planningCard->created_by) ? $planningCard : $twins->sortBy('id')->first());

            $trueCreatorId = $authCard->created_by;
            $trueCreatorName = $authCard->creator?->name ?? "User #{$trueCreatorId}";

            foreach ($twins as $twin) {
                if ($twin->created_by !== $trueCreatorId) {
                    $oldCreatorName = $twin->creator?->name ?? "User #{$twin->created_by}";
                    if (!$isDryRun) {
                        $twin->created_by = $trueCreatorId;
                        $twin->saveQuietly();
                    }
                    $creatorFixCount++;
                    $this->line("Fixed Assign By for card {$twin->id} [{$twin->title}] on {$twin->board?->name}: Set to '{$trueCreatorName}' (was '{$oldCreatorName}')");
                }
            }
        }

        $this->info("Completed SMM label fix! Corrected Labels: {$fixedCount}, Swapped: {$swappedCount}, Synced Assign By: {$creatorFixCount}.");
        return 0;
    }

    private function parseSheetData(string $content): array
    {
        $rows = $this->parseCsv($content);
        if (empty($rows)) return [];

        $headerRow = array_map(fn($h) => strtolower(trim($h)), $rows[0]);
        $colMap = [];

        foreach ($headerRow as $idx => $colName) {
            if ($colName === 'class' || $colName === 'cluster' || str_contains($colName, 'cluster') || str_contains($colName, 'class') || str_contains($colName, 'brand')) {
                if (!isset($colMap['cluster'])) $colMap['cluster'] = $idx;
            }
            if ($colName === 'team') $colMap['team'] = $idx;
            if (str_contains($colName, 'work task') || str_contains($colName, 'content type')) $colMap['content_type'] = $idx;
            if ($colName === 'title') $colMap['title'] = $idx;
            if ($colName === 'description') $colMap['description'] = $idx;
            if (str_contains($colName, 'assigned to') || $colName === 'assign to') $colMap['assigned_to'] = $idx;
            if (str_contains($colName, 'public date') || str_contains($colName, 'publish date')) $colMap['public_date'] = $idx;
            if (str_contains($colName, 'week')) $colMap['weeks'] = $idx;
        }

        if (!isset($colMap['cluster']) || !isset($colMap['title'])) {
            $this->warn("Required columns (Cluster/Class, Title) not found in CSV header.");
            return [];
        }

        $cleanAlpha = fn(string $s) => preg_replace('/[^a-z0-9]/', '', strtolower($s));
        $data = [];

        foreach (array_slice($rows, 1) as $r) {
            $cluster = SocialMediaClass::canonicalName(trim($r[$colMap['cluster']] ?? ''));
            $title = trim($r[$colMap['title']] ?? '');
            $contentType = isset($colMap['content_type']) ? trim($r[$colMap['content_type']] ?? '') : '';
            $assignedTo = isset($colMap['assigned_to']) ? trim($r[$colMap['assigned_to']] ?? '') : '';
            $pubDateRaw = isset($colMap['public_date']) ? trim($r[$colMap['public_date']] ?? '') : '';
            $weeksRaw = isset($colMap['weeks']) ? trim($r[$colMap['weeks']] ?? '') : '';
            $desc = isset($colMap['description']) ? trim($r[$colMap['description']] ?? '') : '';

            if (empty($cluster) || (empty($title) && empty($contentType))) {
                continue;
            }

            $fullTitle = $title;
            if (empty($title)) {
                $fullTitle = $contentType;
            } elseif (stripos($title, $contentType) === false) {
                $fullTitle = $title . ' - ' . $contentType;
            }

            $parsedDate = null;
            if (!empty($pubDateRaw)) {
                try {
                    $cleaned = preg_replace('/-?\s*(\d{1,2}:\d{2}\s*(?:AM|PM))/i', ' $1', $pubDateRaw);
                    $parsedDate = Carbon::parse($cleaned)->format('Y-m-d');
                } catch (\Exception $e) {}
            }

            $weekNum = null;
            if (preg_match('/(\d+)/', $weeksRaw, $wm)) {
                $weekNum = (int)$wm[1];
            }

            $data[] = [
                'cluster' => $cluster,
                'raw_title' => $title,
                'full_title' => $fullTitle,
                'norm_raw_title' => $cleanAlpha($title),
                'norm_full_title' => $cleanAlpha($fullTitle),
                'content_type' => $contentType,
                'assigned_to' => $assignedTo,
                'public_date' => $parsedDate,
                'week_str' => $weeksRaw,
                'week_num' => $weekNum,
                'description' => $desc,
            ];
        }

        return $data;
    }

    private function fetchGoogleSheetsCsv(string $url): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $fileId = $matches[1];
            $gidStr = '';
            if (preg_match('/[#&]gid=([0-9]+)/', $url, $gidMatches)) {
                $gidStr = '&gid=' . $gidMatches[1];
            }
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$fileId}/gviz/tq?tqx=out:csv{$gidStr}";
            $response = Http::timeout(15)->get($csvUrl);
            if ($response->successful()) {
                return $response->body();
            }
        }
        return null;
    }

    private function parseCsv(string $content): array
    {
        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        while (($data = fgetcsv($stream)) !== false) {
            if (count($data) === 1 && $data[0] === null) continue;
            $isEmpty = true;
            foreach ($data as $field) {
                if (trim((string)$field) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) continue;
            $rows[] = $data;
        }
        fclose($stream);
        return $rows;
    }
}
