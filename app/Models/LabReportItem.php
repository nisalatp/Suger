<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabReportItem extends Model
{
    protected $fillable = [
        'lab_report_id',
        'test_name',
        'test_key',
        'value',
        'unit',
        'ref_min',
        'ref_max',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value'   => 'decimal:4',
            'ref_min' => 'decimal:4',
            'ref_max' => 'decimal:4',
        ];
    }

    public function labReport()
    {
        return $this->belongsTo(LabReport::class);
    }

    /**
     * Compute status from value vs reference range.
     */
    public static function computeStatus(?float $value, ?float $refMin, ?float $refMax): string
    {
        if ($value === null) return 'unknown';
        if ($refMin !== null && $value < $refMin) return 'low';
        if ($refMax !== null && $value > $refMax) return 'high';
        if ($refMin !== null || $refMax !== null) return 'normal';
        return 'unknown';
    }
}
