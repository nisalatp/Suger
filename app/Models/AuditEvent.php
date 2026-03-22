<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'actor_type',
        'event_type',
        'entity_type',
        'entity_id',
        'request_id',
        'ip_hash',
        'user_agent_hash',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
