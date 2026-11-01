<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A button in the Store's banner (Gifts, Passes, Chests...), edited in the admin panel.
 *
 * @property int $id
 * @property string $slug
 * @property string $name_en
 * @property string $name_pt
 * @property string|null $image
 * @property int $sort
 */
class StoreCategory extends Model
{
    /** @var list<string> */
    protected $fillable = ['slug', 'name_en', 'name_pt', 'image', 'sort'];

    protected static function booted(): void
    {
        static::updated(function (StoreCategory $category): void {
            if ($category->wasChanged('image') && $category->getOriginal('image')) {
                Storage::disk('public')->delete($category->getOriginal('image'));
            }
        });

        static::deleted(function (StoreCategory $category): void {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
        });
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return array{en: string, pt: string}
     */
    public function localizedName(): array
    {
        return ['en' => $this->name_en, 'pt' => $this->name_pt];
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
