<?php

namespace App\Http\Controllers;

use App\Models\Game\Account;
use App\Models\Order;
use App\Models\Product;
use App\Services\Seo;
use App\Services\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Store: its grid (/store), each product's page (/store/{slug}) and buying it. Everything bought here shows up in the
 * player area's Orders tab, which the buyer is sent to, as does what an admin sent, delivered from there.
 */
class StoreController extends Controller
{
    /** The banner's own buttons, before the categories from the database. */
    private const FIXED_CATEGORIES = ['main', 'lcoin'];

    public function index(Request $request, Store $store): Response
    {
        $category = $request->string('category')->toString();
        $categories = $store->categories();

        return Inertia::render('store/index', [
            'products' => $store->cards(),
            'categories' => $categories,
            'filter' => [
                // An unknown address falls back to the front page.
                'category' => in_array($category, [...self::FIXED_CATEGORIES, ...array_column($categories, 'key')], true) ? $category : 'main',
                'group' => $request->string('group')->toString() ?: null,
                'q' => $request->string('q')->limit(60, '')->toString(),
            ],
            // Signed in: what the account already holds, and the class packages to feature for its main character.
            'owned' => function () use ($store): array {
                $account = $this->account();

                return $account ? array_keys($store->owned($account)) : [];
            },
            'suggested' => function () use ($store): ?string {
                $account = $this->account();

                return $account ? $store->suggestedGroup($account) : null;
            },
            'lcoin' => $store->lcoinSources(),
            'seo' => Seo::make(
                __('Store: weekly gift box, Kage Pass, class packages and supply kits'),
                __('The L2 Kage Store: a free weekly gift box, the Kage Pass and the in-game Hunt Pass, full class gear packages and supply kits, paid with L-Coin or Adena.'),
                canonical: url('/store'),
            ),
        ]);
    }

    public function show(string $slug, Store $store): Response
    {
        $product = $store->find($slug) ?? abort(404);
        $details = $store->present($product);
        $language = app()->isLocale('pt_BR') ? 'pt' : 'en';

        return Inertia::render('store/show', [
            'product' => $details,
            // Signed in: the characters that can receive it, and whether the account already holds it; null for guests.
            'buyer' => function () use ($store, $product): ?array {
                $account = $this->account();

                return $account ? [
                    'characters' => $store->characters($account, $product),
                    'owned' => $store->owned($account)[$product->slug] ?? null,
                ] : null;
            },
            'seo' => Seo::make($details['name'][$language], explode("\n\n", $details['description'][$language])[0], image: $details['image']),
        ]);
    }

    public function buy(Request $request, string $slug, Store $store): RedirectResponse
    {
        $product = $store->find($slug) ?? abort(404);
        $account = $this->account();

        if ($account === null) {
            return redirect()->route('account');
        }

        $data = $request->validate(
            ['character_id' => ['required', 'integer'], 'quantity' => ['nullable', 'integer', 'min:1', "max:{$product->max_per_order}"]],
            ['character_id.*' => __('Choose one of your characters.'), 'quantity.*' => __('Choose between 1 and :max.', ['max' => $product->max_per_order])],
        );
        $store->buy($account, $product, (int) $data['character_id'], (int) ($data['quantity'] ?? 1));

        return redirect()->route('account.orders')->with('status', $product->type === Product::TYPE_WEEKLY_GIFT ? 'gift-opened' : 'order-placed');
    }

    /**
     * Delivers something an admin sent to the character the player chose.
     */
    public function redeem(Request $request, int $order, Store $store): RedirectResponse
    {
        $account = $this->account();

        if ($account === null) {
            return redirect()->route('account');
        }

        $waiting = Order::query()->where('account', $account->login)->whereNull('character_id')->findOrFail($order);
        $data = $request->validate(['character_id' => ['required', 'integer']], ['character_id.*' => __('Choose one of your characters.')]);
        $store->redeem($account, $waiting, (int) $data['character_id']);

        return redirect()->route('account.orders')->with('status', 'order-placed');
    }

    private function account(): ?Account
    {
        /** @var Account|null $account */
        $account = Auth::guard('player')->user();

        return $account;
    }
}
