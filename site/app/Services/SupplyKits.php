<?php

namespace App\Services;

use App\Models\Game\Account;
use App\Models\Game\Character;
use App\Models\GameAction;
use App\Models\SupplyPurchase;
use App\Services\L2j\GameData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Supply kits (config l2j.supplies): consumables bought with any item from one of the account's characters' bags, as
 * many times as the player likes. Like the Kage Pass, the website only queues the purchase (game_actions "buy_pass");
 * the WebBridge inside the game server takes the price and mails the items in one step, or does nothing when the
 * character no longer has enough.
 */
class SupplyKits
{
    public function __construct(private GameData $gameData) {}

    /**
     * Every kit with its price and contents.
     *
     * @return list<array{key: string, name: array{en: string, pt: string}, description: array{en: string, pt: string}, price: array{item: int, count: int, name: string}, items: list<array{id: int, name: string, count: int}>}>
     */
    public function offer(): array
    {
        $kits = config('l2j.supplies.kits');

        return array_map(fn (string $key, array $kit): array => [
            'key' => $key,
            'name' => $kit['name'],
            'description' => $kit['description'],
            'price' => ['item' => (int) $kit['price']['item'], 'count' => (int) $kit['price']['count'], 'name' => $this->gameData->item((int) $kit['price']['item'])['name']],
            'items' => array_map(
                fn (array $entry): array => ['id' => (int) $entry['item'], 'name' => $this->gameData->item((int) $entry['item'])['name'], 'count' => (int) $entry['count']],
                $kit['items'],
            ),
        ], array_keys($kits), $kits);
    }

    /**
     * Buys a kit for one of the account's characters: records the purchase and queues the in-game charge and mail, in
     * one transaction.
     *
     * @throws ValidationException when kits are closed, the quantity is out of range or the character can't pay
     */
    public function buy(Account $account, int $characterId, string $key, int $quantity): SupplyPurchase
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);
        $kit = config("l2j.supplies.kits.{$key}");

        if (! config('l2j.supplies.enabled') || ! is_array($kit)) {
            $fail('character_id', __('Supply kits are closed for now.'));
        }

        if ($quantity < 1 || $quantity > (int) config('l2j.supplies.max_per_order')) {
            $fail('quantity', __('Choose between 1 and :max kits.', ['max' => config('l2j.supplies.max_per_order')]));
        }

        $character = Character::query()->where('account_name', $account->login)->where('deletetime', 0)->find($characterId, ['charId', 'char_name']);

        if ($character === null) {
            $fail('character_id', __('Choose one of your characters.'));
        }

        $priceItem = (int) $kit['price']['item'];
        $total = (int) $kit['price']['count'] * $quantity;
        $currency = $this->gameData->item($priceItem)['name'];

        if ((Character::bagCounts($account->login, $priceItem)[$character->charId] ?? 0) < $total) {
            $fail('character_id', __(':name needs :count :currency in the bag to buy this.', ['name' => $character->char_name, 'count' => number_format($total), 'currency' => $currency]));
        }

        return DB::transaction(function () use ($account, $character, $key, $kit, $quantity, $priceItem, $total, $currency): SupplyPurchase {
            $action = GameAction::create([
                'action' => GameAction::ACTION_BUY_PASS,
                'character_id' => $character->charId,
                'character_name' => $character->char_name,
                'item_id' => $priceItem,
                'item_name' => $kit['name']['en'],
                'count' => $total,
                'items' => implode(';', array_map(fn (array $entry): string => "{$entry['item']}:".($entry['count'] * $quantity).':0', $kit['items'])),
                'mail_subject' => config('l2j.supplies.mail_subject'),
                'mail_text' => config('l2j.supplies.mail_text'),
                'note' => number_format($total)." {$currency}",
                'requested_by' => "supply kit ({$account->login})",
            ]);

            return SupplyPurchase::create([
                'account' => $account->login,
                'kit' => $key,
                'quantity' => $quantity,
                'character_id' => $character->charId,
                'character_name' => $character->char_name,
                'game_action_id' => $action->id,
            ]);
        });
    }
}
