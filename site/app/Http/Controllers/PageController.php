<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Services\L2j\EventFeed;
use App\Services\L2j\ServerStatus;
use App\Services\L2j\WorldMap;
use App\Services\Seo;
use App\Services\Store;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public pages. Live data is passed as closures so the pages' polling (only: [...]) reloads just those props.
 * Game-database reads are rescued (logged, then null) so the pages still render when the game DB is down.
 */
class PageController extends Controller
{
    public function home(EventFeed $events, ServerStatus $status, Store $store): Response
    {
        $rates = $status->rates();
        $brand = Seo::brand();

        return Inertia::render('home', [
            'events' => fn (): ?array => rescue(fn (): array => $events->get()),
            'rates' => $rates,
            // The store section: the weekly gift box, the Kage Pass and the class packages.
            'store' => rescue(fn (): array => $store->homeOffer(), ['gift' => null, 'pass' => null, 'packages' => null]),
            // Rescued like the game-database reads: the home page still renders without its news.
            'news' => rescue(fn () => NewsPost::published()->limit(3)->get()->map(fn (NewsPost $post): array => $post->card()), []),
            'banner' => rescue(function (): ?array {
                $banner = NewsPost::onBanner()->first();

                return $banner ? [
                    ...$banner->card(),
                    'until' => $banner->featured_until?->toIso8601String(),
                    'theme' => $banner->banner_theme,
                ] : null;
            }),
            'seo' => Seo::make(
                null,
                __(':brand: a free Lineage II Essence server on the Samurai Crow update. x:xp XP, x:drop drop, x:adena Adena. Live bosses and sieges, a world map and an item database. Join now.', [
                    'brand' => $brand, 'xp' => $rates['xp'], 'drop' => $rates['drop'], 'adena' => $rates['adena'],
                ]),
                jsonLd: [[
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => $brand,
                    'url' => url('/'),
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/items').'?q={search_term_string}'],
                        'query-input' => 'required name=search_term_string',
                    ],
                ]],
            ),
        ]);
    }

    public function lore(): Response
    {
        return Inertia::render('lore', [
            'seo' => Seo::make(
                __('Lore of Aden: Lineage II History to Essence Samurai Crow'),
                __('The history of Aden from Einhasad and Gran Kain, the Giants and Shilen\'s betrayal to Essence: the Sylphs, the Warg, the Blood Rose and the Kamael Samurai.'),
            ),
        ]);
    }

    public function map(EventFeed $events, WorldMap $map): Response
    {
        return Inertia::render('map', [
            'events' => fn (): ?array => rescue(fn (): array => $events->get()),
            'map' => fn (): ?array => rescue(fn (): array => $map->get()),
            'seo' => Seo::make(
                __('Live World Map of Aden'),
                __('Live map of Aden: epic and raid bosses, castle sieges, the Grand Olympiad and events, plotted where they happen, with the story of every place.'),
            ),
        ]);
    }

    public function about(ServerStatus $status, EventFeed $events): Response
    {
        return Inertia::render('about', [
            'status' => fn (): array => $status->get(),
            'events' => fn (): ?array => rescue(fn (): array => $events->get()),
            'seo' => Seo::make(
                __('Server Status, Rates and Rankings'),
                __('Live status of the :brand Lineage II Essence server: players online, rates, top players, castle owners, boss timers and upcoming sieges. How to connect.', ['brand' => Seo::brand()]),
            ),
        ]);
    }
}
