<?php

namespace App\Http\Controllers;

use App\Models\Game\Account;
use App\Services\Seo;
use App\Services\Treasury;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Treasury: the store's grid (/treasury), each product's page (/treasury/{slug}) and buying it. Everything bought
 * here shows up in the player area's Orders tab, which the buyer is sent to.
 */
class TreasuryController extends Controller
{
    /** The store's sidebar: an unknown address falls back to the front page. */
    private const CATEGORIES = ['main', 'lcoin', 'gifts', 'passes', 'packages', 'supplies'];

    public function index(Request $request, Treasury $treasury): Response
    {
        $category = $request->string('category')->toString();

        return Inertia::render('treasury/index', [
            'products' => $treasury->cards(),
            'filter' => [
                'category' => in_array($category, self::CATEGORIES, true) ? $category : 'main',
                'group' => $request->string('group')->toString() ?: null,
                'q' => $request->string('q')->limit(60, '')->toString(),
            ],
            // Signed in: what the account already holds, and the class packages to feature for its main character.
            'owned' => function () use ($treasury): array {
                $account = $this->account();

                return $account ? array_keys($treasury->owned($account)) : [];
            },
            'suggested' => function () use ($treasury): ?string {
                $account = $this->account();

                return $account ? $treasury->suggestedArchetype($account) : null;
            },
            'lcoin' => $treasury->lcoinSources(),
            'seo' => Seo::make(
                __('Treasury: weekly gift box, Kage Pass, class packages and supply kits'),
                __('The L2 Kage Treasury: a free weekly gift box, the Kage Pass and the in-game Hunt Pass, full class gear packages and supply kits, paid with L-Coin or Adena.'),
                canonical: url('/treasury'),
            ),
        ]);
    }

    public function show(string $slug, Treasury $treasury): Response
    {
        $product = $treasury->find($slug) ?? abort(404);
        $language = app()->isLocale('pt_BR') ? 'pt' : 'en';

        return Inertia::render('treasury/show', [
            'product' => $product,
            // Signed in: the characters that can receive it, and whether the account already holds it; null for guests.
            'buyer' => function () use ($treasury, $product): ?array {
                $account = $this->account();

                return $account ? [
                    'characters' => $treasury->characters($account, $product),
                    'owned' => $treasury->owned($account)[$product['slug']] ?? null,
                ] : null;
            },
            'seo' => Seo::make($product['name'][$language], $product['description'][0][$language]),
        ]);
    }

    public function buy(Request $request, string $slug, Treasury $treasury): RedirectResponse
    {
        $product = $treasury->find($slug) ?? abort(404);
        $account = $this->account();

        if ($account === null) {
            return redirect()->route('account');
        }

        $data = $request->validate(
            ['character_id' => ['required', 'integer'], 'quantity' => ['nullable', 'integer', 'min:1', "max:{$product['maxQuantity']}"]],
            ['character_id.*' => __('Choose one of your characters.'), 'quantity.*' => __('Choose between 1 and :max kits.', ['max' => $product['maxQuantity']])],
        );
        $status = $treasury->buy($account, $product, (int) $data['character_id'], (int) ($data['quantity'] ?? 1));

        return redirect()->route('account.orders')->with('status', $status);
    }

    private function account(): ?Account
    {
        /** @var Account|null $account */
        $account = Auth::guard('player')->user();

        return $account;
    }
}
