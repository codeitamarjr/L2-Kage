<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Something the Store sells or gives away, edited in the admin panel (see App\Services\Store). Deleting one only hides it:
 * its orders keep pointing to it and the seeder won't bring it back.
 *
 * @property int $id
 * @property string $slug
 * @property int $store_category_id
 * @property string $type
 * @property string $name_en
 * @property string $name_pt
 * @property string $description_en
 * @property string $description_pt
 * @property list<string>|null $images
 * @property string $art
 * @property string|null $emblem
 * @property string|null $tier
 * @property string|null $class_group
 * @property bool $class_only
 * @property int|null $price_item
 * @property int|null $price_count
 * @property list<array{item: int|string, count: int|string, enchant?: int|string|null}>|null $items
 * @property list<array{item: int|string, count: int|string, enchant?: int|string|null, weight: int|string}>|null $bonus
 * @property string|null $limit_period
 * @property string|null $season
 * @property int $max_per_order
 * @property int|null $min_level
 * @property string|null $mail_subject
 * @property string|null $mail_text
 * @property bool $is_active
 * @property bool $is_featured
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 * @property int $sort
 * @property-read StoreCategory $category
 */
class Product extends Model
{
    use SoftDeletes;

    /** Items delivered by in-game mail: anything an admin adds. */
    public const TYPE_MAIL = 'mail';

    /** Like mail, but an active Gift Boxes rotation replaces its contents. */
    public const TYPE_WEEKLY_GIFT = 'weekly_gift';

    /** Unlocked inside the game: its page only explains it, with the server's own numbers. */
    public const TYPE_HUNT_PASS = 'hunt_pass';

    public const LIMIT_WEEK = 'week';

    public const LIMIT_MONTH = 'month';

    public const LIMIT_SEASON = 'season';

    public const LIMIT_ONCE = 'once';

    public const LIMITS = [
        self::LIMIT_WEEK => 'One per week',
        self::LIMIT_MONTH => 'One per month',
        self::LIMIT_SEASON => 'One per season',
        self::LIMIT_ONCE => 'One per account, ever',
    ];

    /** The drawn pictures in public/assets/store, for a product without an image. */
    public const ARTS = [
        'gift' => 'Gift chest',
        'pass' => 'Pass ticket',
        'hunt' => 'Pass ticket (blue)',
        'package' => 'Treasure chest',
        'supplies' => 'Supply case',
    ];

    /** @var list<string> */
    protected $fillable = [
        'slug', 'store_category_id', 'type', 'name_en', 'name_pt', 'description_en', 'description_pt', 'images', 'art', 'emblem', 'tier',
        'class_group', 'class_only', 'price_item', 'price_count', 'items', 'bonus', 'limit_period', 'season', 'max_per_order', 'min_level',
        'mail_subject', 'mail_text', 'is_active', 'is_featured', 'starts_at', 'ends_at', 'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'items' => 'array',
            'bonus' => 'array',
            'price_item' => 'integer',
            'price_count' => 'integer',
            'max_per_order' => 'integer',
            'min_level' => 'integer',
            'class_only' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Product $product): void {
            $before = json_decode((string) $product->getRawOriginal('images'), true) ?: [];
            $removed = array_diff($before, $product->images ?? []);

            if ($removed !== []) {
                Storage::disk('public')->delete(array_values($removed));
            }
        });
    }

    /**
     * @return BelongsTo<StoreCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * What players can see: published and started. A product past its end date stays listed, shown as closed.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeListed(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now ?? now()))
            ->orderBy('sort')
            ->orderBy('id');
    }

    public function isFree(): bool
    {
        return $this->price_item === null || ! $this->price_count;
    }

    public function hasEnded(?CarbonInterface $now = null): bool
    {
        return $this->ends_at !== null && $this->ends_at->lt($now ?? now());
    }

    /**
     * The key an order takes under this product's limit (see orders.limit_key), or null when it has none. Weeks start on
     * Monday 00:00 in the app time zone, which is the game server's.
     */
    public function limitKey(?CarbonImmutable $now = null): ?string
    {
        $now ??= CarbonImmutable::now();

        return match ($this->limit_period) {
            self::LIMIT_WEEK => 'week:'.$now->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            self::LIMIT_MONTH => 'month:'.$now->format('Y-m'),
            self::LIMIT_SEASON => 'season:'.($this->season ?? ''),
            self::LIMIT_ONCE => 'once',
            default => null,
        };
    }

    /**
     * When the limit lets the account buy it again, for the limits that end by themselves.
     */
    public function limitEndsAt(?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $now ??= CarbonImmutable::now();

        return match ($this->limit_period) {
            self::LIMIT_WEEK => $now->startOfWeek(CarbonImmutable::MONDAY)->addWeek(),
            self::LIMIT_MONTH => $now->startOfMonth()->addMonth(),
            default => null,
        };
    }

    /**
     * @return array{en: string, pt: string}
     */
    public function localizedName(): array
    {
        return ['en' => $this->name_en, 'pt' => $this->name_pt];
    }

    /**
     * The uploaded images' addresses, the cover first.
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        return array_values(array_map(fn (string $path): string => Storage::disk('public')->url($path), $this->images ?? []));
    }
}
