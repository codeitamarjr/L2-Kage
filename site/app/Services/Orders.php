<?php

namespace App\Services;

use App\Mail\AccountMail;
use App\Models\Game\Account;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Support\defer;

/**
 * A game account's orders: everything it bought in the Store or an admin sent it, as one history for the player area's
 * Orders tab, plus the emails about them.
 */
class Orders
{
    public function __construct(private StoreContents $contents) {}

    /**
     * Every order, what's waiting for a character first, then newest first. The items are what was actually sent, so they
     * stay right after the product changes.
     *
     * @return list<array{id: int, type: string, typeName: array{en: string, pt: string}, name: array{en: string, pt: string}, characterName: string|null, price: array{count: int, currency: string}|null, status: string, createdAt: string, fromStaff: bool, note: string|null, randomBonus: bool, items: list<array{name: string, count: int, enchant: int, bonus?: bool}>}>
     */
    public function history(Account $account): array
    {
        return Order::with('gameAction')
            ->where('account', $account->login)
            ->orderByRaw('character_id IS NULL DESC')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Order $order): array {
                $suffix = $order->quantity > 1 ? " × {$order->quantity}" : '';
                $waiting = $order->isWaiting();

                return [
                    'id' => $order->id,
                    'type' => $order->category,
                    'typeName' => $order->category_name,
                    'name' => ['en' => $order->name['en'].$suffix, 'pt' => $order->name['pt'].$suffix],
                    'characterName' => $order->character_name,
                    'price' => $order->price_count ? ['count' => $order->price_count, 'currency' => (string) $order->price_name] : null,
                    'status' => $order->status(),
                    'createdAt' => $order->created_at->toIso8601String(),
                    'fromStaff' => $order->source === Order::SOURCE_ADMIN,
                    'note' => $order->source === Order::SOURCE_ADMIN ? $order->note : null,
                    'randomBonus' => $waiting && ($order->contents['bonus'] ?? []) !== [],
                    'items' => $waiting
                        ? $this->contents->describe($order->contents ?? ['items' => [], 'bonus' => []])['items']
                        : array_values($order->items ?? []),
                ];
            })
            ->all();
    }

    /**
     * Emails the order confirmation to the account's address, when it has one and email is set up. A failed send is
     * logged, never shown: the order itself is already placed.
     */
    public function confirm(Account $account, Order $order): void
    {
        if (! $account->email || ! GameAccounts::emailReady()) {
            return;
        }

        $items = implode(', ', array_map(
            fn (array $item): string => ($item['enchant'] ? "+{$item['enchant']} " : '').$item['name'].($item['count'] > 1 ? ' x'.number_format($item['count']) : ''),
            $order->items ?? [],
        ));

        $this->mail($account, new AccountMail(
            __('Order placed'),
            [
                __('Thank you for your order, :login.', ['login' => $account->login]),
                __(':name for :price :currency, paid from :character\'s bag.', ['name' => $order->name['en'], 'price' => number_format((int) $order->price_count), 'currency' => $order->price_name, 'character' => $order->character_name]),
                __('Items: :items.', ['items' => $items]),
                __('The game server takes the payment and mails the items to :character within seconds. If the character no longer has enough by then, nothing is taken and the order shows as failed.', ['character' => $order->character_name]),
            ],
            [['label' => __('See your orders'), 'url' => route('account.orders')]],
            [__('You get this email because an order was placed with your game account on :site.', ['site' => config('l2j.server_name')])],
        ));
    }

    /**
     * Tells each account by email, after the admin's request has answered, that something is waiting for it.
     *
     * @param  list<string>  $logins
     */
    public function announce(array $logins, string $name): void
    {
        if (! GameAccounts::emailReady()) {
            return;
        }

        defer(function () use ($logins, $name): void {
            Account::query()->whereIn('login', $logins)->whereNotNull('email')->get(Account::READABLE_COLUMNS)->each(fn (Account $account) => $this->mail($account, new AccountMail(
                __('A gift from the staff'),
                [
                    __('The :site staff sent you :name, :login.', ['site' => config('l2j.server_name'), 'name' => $name, 'login' => $account->login]),
                    __('Choose the character that receives it in your orders, and it arrives by in-game mail within seconds.'),
                ],
                [['label' => __('See your orders'), 'url' => route('account.orders')]],
                [__('You get this email because the staff sent something to your game account on :site.', ['site' => config('l2j.server_name')])],
            )));
        });
    }

    private function mail(Account $account, AccountMail $mail): void
    {
        try {
            Mail::to($account->email)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('Store email failed.', ['account' => $account->login, 'error' => $e->getMessage()]);
        }
    }
}
