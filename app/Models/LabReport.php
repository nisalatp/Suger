<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasPublicId;
use App\Traits\EncryptsAttributes;

class LabReport extends Model
{
    use SoftDeletes, HasPublicId, EncryptsAttributes;

    protected $fillable = [
        'user_id',
        'reported_on',
        'lab_name',
        'panel_name',
        'ordered_by',
        'notes_enc',
    ];

    protected array $encryptedAttributes = [
        'notes_enc',
    ];

    protected function casts(): array
    {
        return [
            'reported_on' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(LabReportItem::class);
    }
}
