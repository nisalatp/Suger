<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->public_id)) {
                $model->public_id = strtolower((string) Str::ulid());
            }
        });
    }

    /**
     * Find a model by its public_id or fail.
     */
    public static function findByPublicIdOrFail(string $publicId): static
    {
        return static::where('public_id', $publicId)->firstOrFail();
    }

    /**
     * Get the route key name for implicit model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
