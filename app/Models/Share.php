<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Share extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'password',
        'encryption_key',
        'encryption_salt',
        'expires_at',
        'max_downloads',
        'download_count',
        'total_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'max_downloads' => 'integer',
            'download_count' => 'integer',
            'total_size' => 'integer',
            'encryption_key' => 'encrypted',
        ];
    }

    /**
     * @return HasMany<ShareFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ShareFile::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPasswordProtected(): bool
    {
        return ! is_null($this->password);
    }

    public function hasReachedDownloadLimit(): bool
    {
        return $this->max_downloads && $this->download_count >= $this->max_downloads;
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
