<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailAccount extends Model
{
    protected $fillable = [
        'name', 'email_address', 'provider',
        'imap_host', 'imap_port', 'imap_encryption',
        'smtp_host', 'smtp_port', 'smtp_encryption',
        'username', 'password', 'oauth_token', 'oauth_refresh_token',
        'is_active', 'last_synced_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'last_synced_at'     => 'datetime',
            'password'           => 'encrypted',
            'oauth_token'        => 'encrypted',
            'oauth_refresh_token'=> 'encrypted',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function unreadCount(): int
    {
        return $this->messages()->where('is_read', false)->count();
    }
}
