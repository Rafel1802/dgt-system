<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class SyncEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync emails from all active IMAP accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accounts = EmailAccount::where('is_active', true)->where('provider', 'imap')->get();

        foreach ($accounts as $account) {
            $this->info("Syncing account: {$account->email_address}");

            try {
                $client = Client::make([
                    'host'          => $account->imap_host,
                    'port'          => $account->imap_port,
                    'encryption'    => $account->imap_encryption === 'none' ? false : $account->imap_encryption,
                    'validate_cert' => true,
                    'username'      => $account->username ?: $account->email_address,
                    'password'      => $account->password,
                    'protocol'      => 'imap',
                ]);

                $client->connect();
                
                // Fetch INBOX
                $folder = $client->getFolder('INBOX');
                
                // Only sync messages from the last 7 days to avoid massive first-time syncs
                $messages = $folder->query()->since(now()->subDays(7))->get();

                $count = 0;
                foreach ($messages as $message) {
                    // Save message if not exists
                    EmailMessage::firstOrCreate(
                        [
                            'email_account_id' => $account->id,
                            'message_uid'      => $message->getUid(),
                        ],
                        [
                            'message_id'      => $message->getMessageId(),
                            'from_name'       => $message->getFrom()[0]->personal ?? null,
                            'from_email'      => $message->getFrom()[0]->mail ?? 'unknown@example.com',
                            'to_emails'       => array_map(fn($t) => $t->mail, $message->getTo()->toArray() ?? []),
                            'subject'         => $message->getSubject(),
                            'body_html'       => $message->getHTMLBody() ?: null,
                            'body_text'       => $message->getTextBody() ?: null,
                            'folder'          => 'INBOX',
                            'is_read'         => $message->getFlags()->has('\\Seen'),
                            'has_attachments' => $message->getAttachments()->count() > 0,
                            'received_at'     => $message->getDate() ? \Carbon\Carbon::parse($message->getDate()->first())->toDateTimeString() : now(),
                        ]
                    );
                    $count++;
                }

                $account->update(['last_synced_at' => now()]);
                $this->info("Synced {$count} messages from INBOX.");

                // Check Trash/Bin for deleted emails to sync deletions
                try {
                    $trashFolderName = null;
                    $folders = $client->getFolders();
                    foreach ($folders as $f) {
                        $name = strtolower($f->name);
                        if (str_contains($name, 'trash') || str_contains($name, 'bin')) {
                            $trashFolderName = $f->path;
                            break;
                        }
                    }

                    if ($trashFolderName) {
                        $trashFolder = $client->getFolder($trashFolderName);
                        $trashedMessages = $trashFolder->query()->since(now()->subDays(7))->get();
                        
                        $deletedCount = 0;
                        foreach ($trashedMessages as $trashed) {
                            $msgId = $trashed->getMessageId();
                            if ($msgId) {
                                $deleted = EmailMessage::where('email_account_id', $account->id)
                                             ->where('message_id', $msgId)
                                             ->delete();
                                if ($deleted) $deletedCount++;
                            }
                        }
                        if ($deletedCount > 0) {
                            $this->info("Synced deletions: removed {$deletedCount} emails found in Trash.");
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to sync Trash for {$account->email_address}: " . $e->getMessage());
                }

            } catch (\Exception $e) {
                $this->error("Failed syncing {$account->email_address}: " . $e->getMessage());
            }
        }
    }
}
