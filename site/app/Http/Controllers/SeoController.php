<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Services\Store;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * robots.txt and sitemap.xml for search engines, built from the site's own address (APP_URL).
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            // Private or one-time pages: nothing to index there.
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /register/confirm/',
            'Disallow: /reset-password/',
            '',
            // AI training and answer bots, and SEO scrapers (config/crawlers.php): the whole site is off limits.
            ...array_map(fn (string $bot): string => "User-agent: {$bot}", config('crawlers.enabled') ? config('crawlers.robots') : []),
            ...(config('crawlers.enabled') ? ['Disallow: /', ''] : []),
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * The public pages and every item page (25,129: well under the 50,000 a sitemap may hold). Cached for a day.
     */
    public function sitemap(Store $store): Response
    {
        $xml = Cache::remember('seo.sitemap', now()->addDay(), function () use ($store): string {
            $itemsUpdated = DB::table('itemdb_meta')->where('key', 'imported_at')->value('value');
            $itemsDate = $itemsUpdated ? substr($itemsUpdated, 0, 10) : null;
            $urls = [
                ['/', 'daily', '1.0', null], ['/about', 'hourly', '0.8', null], ['/map', 'hourly', '0.8', null],
                ['/lore', 'monthly', '0.7', null], ['/store', 'weekly', '0.8', null], ['/news', 'weekly', '0.8', null], ['/items', 'weekly', '0.8', $itemsDate], ['/register', 'monthly', '0.6', null],
            ];

            foreach ($store->cards() as $product) {
                $urls[] = ["/store/{$product['slug']}", 'weekly', '0.6', null];
            }

            foreach (NewsPost::published()->get(['slug', 'updated_at']) as $post) {
                $urls[] = ["/news/{$post->slug}", 'monthly', '0.6', $post->updated_at?->toDateString()];
            }

            foreach (DB::table('itemdb_items')->orderBy('id')->pluck('id') as $id) {
                $urls[] = ["/items/{$id}", 'monthly', '0.5', $itemsDate];
            }

            $body = '';

            foreach ($urls as [$path, $frequency, $priority, $date]) {
                $body .= '<url><loc>'.e(url($path)).'</loc>'.($date ? "<lastmod>{$date}</lastmod>" : '')
                    ."<changefreq>{$frequency}</changefreq><priority>{$priority}</priority></url>\n";
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$body.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
