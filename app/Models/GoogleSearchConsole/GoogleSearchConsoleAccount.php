<?php

namespace App\Models\GoogleSearchConsole;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleSearchConsoleAccount extends Model
{
    use HasFactory;

    protected $table = 'google_search_console_accounts';

    protected $fillable = [
        'google_account_id',
        'email',
        'access_token',
        'refresh_token',
        'token_type',
        'expires_at',
        'scopes',
        'is_active',
        'last_connected_by',
        'last_error',
        'last_error_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token'  => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at'    => 'datetime',
            'last_error_at' => 'datetime',
            'is_active'     => 'boolean',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_connected_by');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleSite::class, 'account_id');
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    public function hasRefreshToken(): bool
    {
        return !empty($this->refresh_token);
    }

    public function isValid(): bool
    {
        return $this->is_active && (!empty($this->access_token) || $this->hasRefreshToken());
    }
}
