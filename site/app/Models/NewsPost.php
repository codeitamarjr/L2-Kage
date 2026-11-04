<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A post edited in the admin panel (News), written in English and Portuguese. Without a publish date it is a draft.
 * A featured post also fills the home page's banner until its "featured until" date.
 *
 * @property string $slug
 * @property string $category
 * @property string $title_en
 * @property string $title_pt
 * @property string $summary_en
 * @property string $summary_pt
 * @property string $body_en
 * @property string $body_pt
 * @property string|null $image
 * @property CarbonInterface|null $published_at
 * @property bool $is_featured
 * @property CarbonInterface|null $featured_until
 * @property string $banner_theme
 */
class NewsPost extends Model
{
    public const CATEGORIES = ['event' => 'Event', 'quest' => 'Quest', 'update' => 'Update', 'announcement' => 'Announcement'];

    public const BANNER_THEMES = ['gold' => 'Gold', 'halloween' => 'Halloween', 'winter' => 'Winter'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug', 'category', 'title_en', 'title_pt', 'summary_en', 'summary_pt', 'body_en', 'body_pt',
        'image', 'published_at', 'is_featured', 'featured_until', 'banner_theme',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'featured_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Posts are listed in the sitemap, which is cached for a day.
        static::saved(fn () => Cache::forget('seo.sitemap'));
        static::updated(function (self $post): void {
            if ($post->wasChanged('image') && $post->getOriginal('image')) {
                Storage::disk('public')->delete($post->getOriginal('image'));
            }
        });
        static::deleted(function (self $post): void {
            Cache::forget('seo.sitemap');

            if ($post->image) {
                Storage::disk('public')->delete($post->image);
            }
        });
    }

    /**
     * @param  Builder<NewsPost>  $query
     * @return Builder<NewsPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at');
    }

    /**
     * @param  Builder<NewsPost>  $query
     * @return Builder<NewsPost>
     */
    public function scopeOnBanner(Builder $query): Builder
    {
        return $query->published()
            ->where('is_featured', true)
            ->where(fn ($q) => $q->whereNull('featured_until')->orWhere('featured_until', '>=', now()));
    }

    /**
     * The post in the visitor's language, for lists and the banner.
     *
     * @return array{slug: string, category: string, title: string, summary: string, image: string|null, publishedAt: string|null}
     */
    public function card(): array
    {
        return [
            'slug' => $this->slug,
            'category' => $this->category,
            'title' => $this->localized('title'),
            'summary' => $this->localized('summary'),
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'publishedAt' => $this->published_at?->toIso8601String(),
        ];
    }

    /**
     * The body as HTML. Raw HTML in the text is removed, so only Markdown formatting reaches the page.
     */
    public function bodyHtml(): string
    {
        return Str::markdown($this->localized('body'), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    private function localized(string $field): string
    {
        return $this->{$field.'_'.(app()->getLocale() === 'pt_BR' ? 'pt' : 'en')};
    }
}
