<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasPublicId;
use App\Traits\EncryptsAttributes;

class ReportExport extends Model
{
    use HasPublicId, EncryptsAttributes;

    protected $fillable = [
        'user_id',
        'format',
        'range_start',
        'range_end',
        'include_sections_json',
        'status',
        'file_path_enc',
        'expires_at',
    ];

    protected array $encryptedAttributes = [
        'file_path_enc',
    ];

    protected function casts(): array
    {
        return [
            'range_start' => 'date',
            'range_end' => 'date',
            'include_sections_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
