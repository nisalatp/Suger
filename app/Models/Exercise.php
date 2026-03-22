<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasPublicId;
use App\Traits\EncryptsAttributes;

class Exercise extends Model
{
    use SoftDeletes, HasPublicId, EncryptsAttributes;

    protected $fillable = [
        'user_id',
        'performed_at_utc',
        'activity_type',
        'duration_minutes',
        'calories_burned',
        'intensity',
        'notes_enc',
    ];

    protected array $encryptedAttributes = [
        'notes_enc',
    ];

    protected function casts(): array
    {
        return [
            'performed_at_utc' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
