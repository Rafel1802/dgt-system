<?php

namespace App\Jobs;

use App\Services\GoogleBlogsSheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GoogleBlogsDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $classNumber,
        public readonly int $sheetRow,
        public readonly ?string $publicLink = null
    ) {}

    public function handle(GoogleBlogsSheetService $service): void
    {
        if (empty($this->classNumber) || empty($this->sheetRow)) {
            return;
        }

        Log::info("GoogleBlogsDeleteJob: Removing link from Google Sheet.", [
            'class' => $this->classNumber,
            'row'   => $this->sheetRow,
            'link'  => $this->publicLink,
        ]);

        $result = $service->deleteBlog($this->classNumber, $this->sheetRow, $this->publicLink);

        if (!$result['success']) {
            Log::warning("GoogleBlogsDeleteJob: Delete failed.", $result);
            $this->release($this->backoff);
        } else {
            Log::info("GoogleBlogsDeleteJob: Delete successful.");
        }
    }
}
