<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    protected $fillable = [
        'user_id',
        'consent_type',
        'version',
        'granted_at',
        'revoked_at',
        'ui_surface',
        'proof_json',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'proof_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this consent is currently active (granted and not revoked).
     */
    public function isActive(): bool
    {
        return !is_null($this->granted_at) && is_null($this->revoked_at);
    }
}
