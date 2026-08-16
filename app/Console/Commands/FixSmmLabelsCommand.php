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
        
        $fixedCount = 0;
        foreach ($cards as $card) {
            $classLabel = strtolower(trim($card->smm_class_label ?? ''));
            $clusterLabel = strtolower(trim($card->smm_cluster_label ?? ''));
            
            // We WANT:
            // smm_class_label = Class (e.g. "SkidSteers", from $validClusters)
            // smm_cluster_label = Content Type (e.g. "Poster Design", from $contentTypes)
            
            // If the current smm_class_label is a Content Type, OR the current smm_cluster_label is a Class,
            // they are backwards and need to be swapped!
            $isBackward = false;
            
            if (in_array($classLabel, $contentTypes)) {
                $isBackward = true;
            }
            if (in_array($clusterLabel, $validClusters)) {
                $isBackward = true;
            }
            
            if ($isBackward) {
                // Swap them back to correct DB usage!
                $wrongClass = $card->smm_class_label; // This currently holds the content type
                $wrongCluster = $card->smm_cluster_label; // This currently holds the class
                
                $card->smm_class_label = $wrongCluster;
                $card->smm_cluster_label = $wrongClass;
                $card->save();
                
                $fixedCount++;
                $this->line("Fixed card {$card->id}: Set smm_class_label to '{$wrongCluster}', smm_cluster_label to '{$wrongClass}'");
            }
        }
        
        $this->info("Done! Fixed $fixedCount cards.");
        return 0;
    }
}
