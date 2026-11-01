<?php

namespace App\Http\Middleware;

use App\Models\Game\Account;
use App\Models\Order;
use App\Rules\Turnstile;
use App\Services\GameAccounts;
use App\Services\Premium;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'serverName' => config('l2j.server_name'),
            // "en" or "pt_BR" (see SetLocale); the pages pick their text with it.
            'locale' => app()->getLocale(),
            // One-off messages after a redirect ("account-created", "reset-sent"...), shown by the page.
            'flash' => fn (): array => [
                'status' => $request->session()->get('status'),
                'sentTo' => $request->session()->get('sentTo'),
                'login' => $request->session()->get('login'),
            ],
            'emailReady' => fn (): bool => GameAccounts::emailReady(),
            'turnstileSiteKey' => fn (): ?string => Turnstile::enabled() ? config('services.turnstile.site_key') : null,
            'player' => function (): ?array {
                /** @var Account|null $account */
                $account = Auth::guard('player')->user();

                if (! $account) {
                    return null;
                }

                $premium = app(Premium::class);
                $until = $premium->until($account->login);

                // premium: when the account's premium ends and what it gives (the header star, the player area).
                return [
                    'login' => $account->login,
                    'gm' => $account->isGameMaster(),
                    'premium' => $until ? ['until' => $until->toIso8601String(), 'bonuses' => $premium->bonuses()] : null,
                    // What an admin sent that still waits for a character (the header's badge).
                    'gifts' => Order::query()->where('account', $account->login)->whereNull('character_id')->count(),
                ];
            },
        ];
    }
}
