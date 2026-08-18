<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Card;
use App\Models\SocialMediaClass;

class FixSmmLabelsCommand extends Command
{
    protected $signature = 'smm:fix-labels';
    protected $description = 'Fix SMM class and cluster labels that were swapped by the import script';

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
        
        $titleMap = [
            'TYPH-0113 + PA02' => 'ImpossibleMachinery',
            'TYPH-0505 + TYPH-5015M' => 'ImpossibleMachinery',
            'TYPH-V900B' => 'ImpossibleMachinery',
            'TYPH-0501R' => 'ImpossibleMachinery',
            'TYPH-0901' => 'ImpossibleMachinery',
            'TYPH-0503R vs TYPH-1701proY' => 'ImpossibleMachinery',
            'TYPH-0113 SMM Content' => 'ImpossibleMachinery',
            'TYPH-0701 + TYPH-2013' => 'MachineryAsia.Online',
            'TYPH-0121' => 'MachineryAsia.Online',
            'TYPH-V900R' => 'MachineryAsia.Online',
            'TYPH-1902 PRO + TYPH-2006' => 'MachineryAsia.Online',
            'TYPH-0502R' => 'MachineryAsia.Online',
            'TYPH-0702 PRO + TYPH-2008' => 'MachineryAsia.Online',
            'TYPH-1701proR' => 'MachineryAsia.Online',
            'TYPH-1702 Ebay Content' => 'ImpossibleMachinery',
            'TYPH-1703' => 'MachineryAsia',
        ];

        $fixedCount = 0;
        foreach ($cards as $card) {
            $classLabel = strtolower(trim($card->smm_class_label ?? ''));
            $clusterLabel = strtolower(trim($card->smm_cluster_label ?? ''));
            $cardTitle = trim($card->title);

            // Attempt to deduce the actual class from the title
            $matchedClass = null;
            foreach ($titleMap as $titlePart => $cluster) {
                if (stripos($cardTitle, $titlePart) !== false) {
                    $matchedClass = $cluster;
                    break;
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
}
