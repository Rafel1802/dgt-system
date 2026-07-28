<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $fillable = [
        'email_account_id', 'message_uid', 'message_id',
        'from_name', 'from_email', 'to_emails', 'cc_emails',
        'subject', 'body_html', 'body_text', 'folder',
        'is_read', 'is_starred', 'has_attachments', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'to_emails'       => 'array',
            'cc_emails'       => 'array',
            'is_read'         => 'boolean',
            'is_starred'      => 'boolean',
            'has_attachments'  => 'boolean',
            'received_at'     => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeInbox($query)
    {
        return $query->where('folder', 'INBOX');
    }

    /**
     * Get a plain-text preview (first 150 chars).
     */
    public function getPreviewAttribute(): string
    {
        $text = $this->body_text ?: strip_tags($this->body_html ?? '');
        return \Str::limit(trim($text), 150);
    }
}
