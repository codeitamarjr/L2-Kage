<?php

namespace App\Services;

use App\Models\Game\Account;
use App\Models\Game\Character;
use App\Models\GameAction;
use App\Models\PassClaim;
use App\Services\L2j\GameData;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Kage Pass (config l2j.pass): once per game account per season, a player spends L-Coin from one of their
 * characters' bags and that character receives the season's rewards by in-game mail. The website only queues the
 * request (game_actions "buy_pass"); the WebBridge inside the game server takes the L-Coin and sends the mail in one
 * step, so the game's memory and the database always agree. If the character doesn't have enough L-Coin by then, the
 * action fails, nothing is taken, and the player can claim again.
 */
class KagePass
{
    public function __construct(private GameData $gameData) {}

    /**
     * For the landing page banner and the player area: the season, its price and rewards.
     *
     * @return array<string, mixed>
     */
    public function offer(): array
    {
        $price = config('l2j.pass.price');

        return [
            'enabled' => $this->isOpen(),
            'season' => config('l2j.pass.season'),
            'name' => config('l2j.pass.name'),
            'ends' => config('l2j.pass.ends'),
            'price' => ['name' => $this->gameData->item((int) $price['item'])['name'], 'count' => (int) $price['count']],
            'rewards' => array_map(
                fn (array $entry): array => ['id' => (int) $entry['item'], 'name' => $this->gameData->item((int) $entry['item'])['name'], 'count' => (int) $entry['count']],
                config('l2j.pass.rewards'),
            ),
        ];
    }

    /**
     * The player area panel: the offer, the account's characters with their L-Coin, and this season's claim.
     *
     * @return array<string, mixed>
     */
    public function status(Account $account): array
    {
        $claim = PassClaim::with('gameAction')->where('account', $account->login)->where('season', config('l2j.pass.season'))->first();
        $coins = $this->coins($account);

        return [
            ...$this->offer(),
            'characters' => Character::query()
                ->where('account_name', $account->login)
                ->where('deletetime', 0)
                ->orderByDesc('level')
                ->get(['charId', 'char_name', 'level'])
                ->map(fn (Character $c): array => ['id' => $c->charId, 'name' => $c->char_name, 'level' => $c->level, 'coins' => $coins[$c->charId] ?? 0])
                ->all(),
            'claim' => $claim ? [
                'characterName' => $claim->character_name,
                'claimedAt' => $claim->created_at->toIso8601String(),
                'status' => $claim->gameAction->status ?? GameAction::STATUS_FAILED,
                'result' => $claim->gameAction?->result,
            ] : null,
        ];
    }

    /**
     * Claims this season's pass for one of the account's characters: records the claim and queues the in-game
     * purchase, in one transaction.
     *
     * @throws ValidationException when the pass is closed, already claimed, or the character can't pay
     */
    public function claim(Account $account, int $characterId): PassClaim
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['character_id' => $message]);

        if (! $this->isOpen()) {
            $fail(__('The Kage Pass is closed for now.'));
        }

        $character = Character::query()->where('account_name', $account->login)->where('deletetime', 0)->find($characterId, ['charId', 'char_name']);

        if ($character === null) {
            $fail(__('Choose one of your characters.'));
        }

        $price = config('l2j.pass.price');

        if (($this->coins($account)[$character->charId] ?? 0) < $price['count']) {
            $fail(__(':name needs :count L-Coin in the bag to claim the pass.', ['name' => $character->char_name, 'count' => number_format($price['count'])]));
        }

        $season = (string) config('l2j.pass.season');
        $previous = PassClaim::with('gameAction')->where('account', $account->login)->where('season', $season)->first();

        // A claim whose purchase failed in game (not enough L-Coin by then) gives way to a new one.
        if ($previous && ($previous->gameAction === null || $previous->gameAction->status === GameAction::STATUS_FAILED)) {
            $previous->delete();
        }

        try {
            return DB::transaction(function () use ($account, $character, $price, $season): PassClaim {
                $action = GameAction::create([
                    'action' => GameAction::ACTION_BUY_PASS,
                    'character_id' => $character->charId,
                    'character_name' => $character->char_name,
                    'item_id' => (int) $price['item'],
                    'item_name' => (string) config('l2j.pass.name'),
                    'count' => (int) $price['count'],
                    'items' => implode(';', array_map(fn (array $entry): string => "{$entry['item']}:{$entry['count']}:0", config('l2j.pass.rewards'))),
                    'mail_subject' => config('l2j.pass.mail_subject'),
                    'mail_text' => config('l2j.pass.mail_text'),
                    'note' => number_format($price['count']).' L-Coin',
                    'requested_by' => "kage pass ({$account->login})",
                ]);

                return PassClaim::create([
                    'account' => $account->login,
                    'season' => $season,
                    'character_id' => $character->charId,
                    'character_name' => $character->char_name,
                    'game_action_id' => $action->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $fail(__('You have already claimed this season\'s Kage Pass.'));
        }
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        return config('l2j.pass.enabled')
            && ($now ?? CarbonImmutable::now())->lte(CarbonImmutable::parse((string) config('l2j.pass.ends'))->endOfDay());
    }

    /**
     * The price item (L-Coin) in each of the account's characters' bags, as last saved by the game server.
     *
     * @return array<int, int> character id => count
     */
    private function coins(Account $account): array
    {
        return Character::bagCounts($account->login, (int) config('l2j.pass.price.item'));
    }
}
