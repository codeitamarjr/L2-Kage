<?php

namespace App\Services;

use App\Models\Game\Account;
use App\Models\Game\Character;
use App\Models\GameAction;
use App\Models\GiftBoxTemplate;
use App\Models\GiftClaim;
use App\Services\L2j\GameData;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The weekly gift box (config l2j.gift_box): one per game account per week, rolled here and delivered as a single
 * in-game mail by the WebBridge (game_actions "send_mail"). Weeks start on Monday 00:00 in the app time zone,
 * which is the game server's.
 */
class GiftBox
{
    public function __construct(private GameData $gameData) {}

    public function weekStart(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->startOfWeek(CarbonImmutable::MONDAY);
    }

    /**
     * What the player area shows: the box's contents, which characters can receive it and this week's claim.
     *
     * @return array<string, mixed>
     */
    public function status(Account $account): array
    {
        $week = $this->weekStart();
        $claim = GiftClaim::with('gameAction')->where('account', $account->login)->whereDate('week_start', $week)->first();
        $template = $this->activeTemplate();

        return [
            'enabled' => (bool) config('l2j.gift_box.enabled'),
            'minLevel' => $this->minLevel(),
            'nextAt' => $week->addWeek()->toIso8601String(),
            'name' => $template['name'] ?? null,
            'description' => $template['description'] ?? null,
            'contents' => $this->contents(),
            'eligible' => $this->eligibleCharacters($account)
                ->map(fn (Character $c): array => ['id' => $c->charId, 'name' => $c->char_name, 'level' => $c->level])
                ->all(),
            'claim' => $claim ? [
                'characterName' => $claim->character_name,
                'items' => $claim->items,
                'claimedAt' => $claim->created_at->toIso8601String(),
                'status' => $claim->gameAction->status ?? GameAction::STATUS_FAILED,
                'result' => $claim->gameAction?->result,
            ] : null,
        ];
    }

    /**
     * Claims this week's box for one of the account's characters: rolls the bonus, records the claim and queues
     * the mail, all in one transaction.
     *
     * @throws ValidationException when the box is disabled, already claimed or the character can't receive it
     */
    public function claim(Account $account, int $characterId, ?CarbonImmutable $now = null): GiftClaim
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['character_id' => $message]);

        if (! config('l2j.gift_box.enabled')) {
            $fail(__('The gift box is closed for now.'));
        }

        $character = $this->eligibleCharacters($account)->firstWhere('charId', $characterId);

        if ($character === null) {
            $fail(__('Choose one of your characters of level :level or higher.', ['level' => $this->minLevel()]));
        }

        $items = $this->roll($character->level);
        $week = $this->weekStart($now);

        try {
            return DB::transaction(function () use ($account, $character, $items, $week): GiftClaim {
                $action = GameAction::create([
                    'action' => GameAction::ACTION_SEND_MAIL,
                    'character_id' => $character->charId,
                    'character_name' => $character->char_name,
                    'item_name' => 'Weekly Gift Box',
                    'count' => count($items),
                    'items' => implode(';', array_map(fn (array $item): string => "{$item['id']}:{$item['count']}:0", $items)),
                    'mail_subject' => config('l2j.gift_box.mail_subject'),
                    'mail_text' => config('l2j.gift_box.mail_text'),
                    'note' => implode(', ', array_map(fn (array $item): string => number_format($item['count'])." x {$item['name']}", $items)),
                    'requested_by' => "gift box ({$account->login})",
                ]);

                return GiftClaim::create([
                    'account' => $account->login,
                    'week_start' => $week->toDateString(),
                    'character_id' => $character->charId,
                    'character_name' => $character->char_name,
                    'items' => $items,
                    'game_action_id' => $action->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $fail(__('You have already opened this week\'s gift box.'));
        }
    }

    /**
     * The box for a character of this level: every guaranteed item, then one bonus rolled by weight.
     *
     * @return list<array{id: int, name: string, count: int, bonus: bool}>
     */
    public function roll(int $level): array
    {
        $template = $this->activeTemplate();
        $guaranteed = $template['guaranteed'];
        $bonus = $template['bonus'];
        $items = array_map(fn (array $entry): array => $this->item($entry, $level, false), $guaranteed);

        if ($bonus === []) {
            return $items;
        }

        $ticket = random_int(1, (int) array_sum(array_column($bonus, 'weight')));

        foreach ($bonus as $entry) {
            $ticket -= $entry['weight'];

            if ($ticket <= 0) {
                $items[] = $this->item($entry, $level, true);
                break;
            }
        }

        return $items;
    }

    /**
     * What's inside, for the player area: guaranteed items and each bonus with its chance.
     *
     * @return array{guaranteed: list<array{id: int|null, name: string, count: int}>, bonus: list<array{id: int|null, name: string, count: int, chance: float}>}
     */
    public function contents(): array
    {
        $template = $this->activeTemplate();
        $bonus = $template['bonus'];
        $total = array_sum(array_column($bonus, 'weight'));

        return [
            'guaranteed' => array_map(
                fn (array $entry): array => ['id' => is_string($entry['item']) ? null : (int) $entry['item'], 'name' => $this->entryName($entry), 'count' => (int) $entry['count']],
                $template['guaranteed'],
            ),
            'bonus' => array_map(fn (array $entry): array => [
                'id' => is_string($entry['item']) ? null : (int) $entry['item'],
                'name' => $this->entryName($entry),
                'count' => (int) $entry['count'],
                'chance' => $total === 0 ? 0.0 : round($entry['weight'] / $total * 100, 1),
            ], $bonus),
        ];
    }

    /**
     * The active template's name and description, when the admin panel has one running.
     *
     * @return array{name: string|null, description: string|null}
     */
    public function templateDetails(): array
    {
        $template = $this->activeTemplate();

        return ['name' => $template['name'] ?? null, 'description' => $template['description'] ?? null];
    }

    /**
     * The account's characters that can receive the box (not being deleted, min_level or higher), highest first.
     *
     * @return Collection<int, Character>
     */
    private function eligibleCharacters(Account $account)
    {
        return Character::query()
            ->where('account_name', $account->login)
            ->where('level', '>=', $this->minLevel())
            ->where('deletetime', 0)
            ->orderByDesc('level')
            ->get(['charId', 'char_name', 'level']);
    }

    /**
     * @param  array{item: int|string, count: int}  $entry
     * @return array{id: int, name: string, count: int, bonus: bool}
     */
    private function item(array $entry, int $level, bool $bonus): array
    {
        $id = is_string($entry['item']) ? $this->grade($level)[$entry['item']] : (int) $entry['item'];

        return ['id' => $id, 'name' => $this->gameData->item($id)['name'], 'count' => (int) $entry['count'], 'bonus' => $bonus];
    }

    /**
     * The scrolls for a level's equipment grade.
     *
     * @return array<string, int>
     */
    private function grade(int $level): array
    {
        $grades = config('l2j.gift_box.grades');
        $match = reset($grades);

        foreach ($grades as $grade) {
            if ($level >= $grade['from']) {
                $match = $grade;
            }
        }

        return $match;
    }

    /**
     * An entry's name for the contents list: the item's name, or "(your grade)" for a grade key.
     *
     * @param  array{item: int|string}  $entry
     */
    private function entryName(array $entry): string
    {
        return is_string($entry['item']) ? $this->gradeLabel($entry['item']) : $this->gameData->item($entry['item'])['name'];
    }

    private function gradeLabel(string $key): string
    {
        return match ($key) {
            'enchant_weapon' => __('Scroll: Enchant Weapon (your grade)'),
            'enchant_armor' => __('Scroll: Enchant Armor (your grade)'),
            'blessed_enchant_weapon' => __('Protecting Scroll: Enchant Weapon (your grade)'),
            default => $key,
        };
    }

    private function minLevel(): int
    {
        return (int) config('l2j.gift_box.min_level');
    }

    /**
     * @return array{guaranteed: array<int, array{item: int|string, count: int}>, bonus: array<int, array{item: int|string, count: int, weight: int}>, name: string|null, description: string|null}
     */
    private function activeTemplate(): array
    {
        $template = GiftBoxTemplate::query()
            ->activeAt(CarbonImmutable::now())
            ->first();

        if ($template) {
            return $template->data();
        }

        return [
            'guaranteed' => config('l2j.gift_box.guaranteed'),
            'bonus' => config('l2j.gift_box.bonus'),
            'name' => null,
            'description' => null,
        ];
    }
}
