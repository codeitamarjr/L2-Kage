<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A week of the gift box rotation, edited in the admin panel (Store > Gift Box Rotation). The active one replaces the
 * Weekly Gift Box product's contents (see App\Services\Store); with none active, the product's own are used.
 *
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property list<array{item: int|string, count: int|string}> $guaranteed
 * @property list<array{item: int|string, count: int|string, weight: int|string}> $bonus
 */
class GiftBoxTemplate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'description', 'is_active', 'starts_at', 'ends_at', 'guaranteed', 'bonus'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'guaranteed' => 'array',
            'bonus' => 'array',
        ];
    }

    /**
     * @param  Builder<GiftBoxTemplate>  $query
     * @return Builder<GiftBoxTemplate>
     */
    public function scopeActiveAt(Builder $query, CarbonImmutable $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $date))
            ->orderBy('starts_at');
    }
}
