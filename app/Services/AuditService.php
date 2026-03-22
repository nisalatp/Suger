<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Log an audit event.
     */
    public static function log(
        ?User $user,
        string $eventType,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null
    ): AuditEvent {
        return AuditEvent::create([
            'user_id' => $user?->id,
            'actor_type' => $user ? 'user' : 'system',
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'request_id' => (string) Str::uuid(),
            'ip_hash' => self::hashValue(Request::ip()),
            'user_agent_hash' => self::hashValue(Request::userAgent()),
            'meta_json' => $meta,
        ]);
    }

    /**
     * Hash a value for privacy (IP, user agent).
     */
    private static function hashValue(?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        return hash('sha256', $value, true);
    }
}
