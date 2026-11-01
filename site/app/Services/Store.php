<?php

namespace App\Services;

use App\Models\Game\Account;
use App\Models\Game\Character;
use App\Models\GameAction;
use App\Models\GiftBoxTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Services\L2j\GameConfig;
use App\Services\L2j\GameData;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Store: products from the database, each with its own address (/store/{slug}), and everything that happens to them.
 * Buying only queues the delivery (game_actions): the WebBridge inside the game server takes the price and mails the
 * items in one step, or does nothing when the character no longer has enough, so the game's memory and the database always
 * agree. Every price names its item, so a product can cost L-Coin, Adena or anything else. What an admin sends to an
 * account waits in its Orders tab until the player chooses the character that receives it.
 */
class Store
{
    public const LCOIN = 91663;

    /** What the store grid doesn't need: only a product's own page shows these. */
    private const DETAIL_FIELDS = ['images', 'description', 'contents', 'steps', 'limitPeriod', 'maxQuantity', 'minLevel', 'hunt', 'endsAt'];

    public function __construct(
        private GameData $gameData,
        private GameConfig $config,
        private StoreContents $contents,
        private Orders $orders,
    ) {}

    /**
     * The categories that have something to show, in order.
     *
     * @return list<array{key: string, name: array{en: string, pt: string}}>
     */
    public function categories(): array
    {
        $used = Product::query()->listed()->pluck('store_category_id')->unique();

        return StoreCategory::query()
            ->whereIn('id', $used)
            ->orderBy('sort')
            ->get()
            ->map(fn (StoreCategory $category): array => ['key' => $category->slug, 'name' => $category->localizedName()])
            ->all();
    }

    /**
     * The products as the store grid shows them.
     *
     * @return list<array<string, mixed>>
     */
    public function cards(): array
    {
        return Product::query()->listed()->with('category')->get()
            ->map(fn (Product $product): array => Arr::except($this->present($product), self::DETAIL_FIELDS))
            ->all();
    }

    public function find(string $slug): ?Product
    {
        return Product::query()->listed()->with('category')->where('slug', $slug)->first();
    }

    /**
     * Everything a product's page shows.
     *
     * @return array<string, mixed>
     */
    public function present(Product $product): array
    {
        $images = $product->imageUrls();
        $group = $product->class_group ? config("l2j.store.class_groups.{$product->class_group}.name") : null;
        $isHunt = $product->type === Product::TYPE_HUNT_PASS;
        $description = $this->description($product);

        return [
            'slug' => $product->slug,
            'type' => $product->type,
            'category' => $product->category->slug,
            'categoryName' => $product->category->localizedName(),
            'featured' => $product->is_featured,
            'name' => $product->localizedName(),
            'price' => $this->price($product),
            'open' => $this->isOpen($product),
            'image' => $images[0] ?? $product->category->imageUrl(),
            'images' => $images,
            'art' => $product->art,
            'emblem' => $product->emblem,
            'tier' => $product->tier,
            'group' => $product->class_group,
            'groupName' => $group,
            'classOnly' => $product->class_only,
            'summary' => array_map(fn (string $text): string => explode("\n\n", $text)[0], $description),
            'endsAt' => $product->ends_at?->toIso8601String(),
            'description' => $description,
            'contents' => $isHunt ? [] : $this->contentGroups($product),
            'steps' => $isHunt ? $this->huntSteps($product) : $this->mailSteps($product),
            'limit' => $this->limitLabel($product),
            'limitPeriod' => $product->limit_period,
            'maxQuantity' => $product->max_per_order,
            'minLevel' => $product->min_level,
            'hunt' => $isHunt ? $this->huntDetails() : null,
        ];
    }

    /**
     * The account's characters, for a product's purchase panel: the ones its class suits first, then by level, each with
     * what it holds of the product's price (as last saved by the game server).
     *
     * @return list<array{id: int, name: string, level: int, className: string, archetype: string|null, balance: int|null}>
     */
    public function characters(Account $account, Product $product): array
    {
        $price = $this->price($product);
        $balances = $price ? Character::bagCounts($account->login, $price['item']) : [];

        return $this->accountCharacters($account)
            ->map(fn (array $character): array => [...$character, 'balance' => $price ? ($balances[$character['id']] ?? 0) : null])
            ->sortByDesc(fn (array $character): bool => $product->class_group !== null && $character['archetype'] === $product->class_group)
            ->values()
            ->all();
    }

    /**
     * Who can receive what an admin sent: any of the account's characters, highest level first.
     *
     * @return list<array{id: int, name: string, level: int}>
     */
    public function recipients(Account $account): array
    {
        return $this->accountCharacters($account)
            ->map(fn (array $character): array => Arr::only($character, ['id', 'name', 'level']))
            ->all();
    }

    /**
     * What the account already holds and can't buy again for now (this week's gift box, this season's Kage Pass...). A
     * purchase whose charge failed in game can be bought again, so it isn't listed.
     *
     * @return array<string, array{characterName: string|null, status: string, nextAt: string|null}> by product slug
     */
    public function owned(Account $account): array
    {
        $owned = [];
        $orders = Order::with(['gameAction', 'product'])->where('account', $account->login)->whereNotNull('limit_key')->orderBy('id')->get();

        foreach ($orders as $order) {
            $product = $order->product;

            if ($product === null || $order->limit_key !== $product->limitKey() || ($order->status() === GameAction::STATUS_FAILED && $order->price_count)) {
                continue;
            }

            $owned[$product->slug] = [
                'characterName' => $order->character_name,
                'status' => $order->status(),
                'nextAt' => $product->limitEndsAt()?->toIso8601String(),
            ];
        }

        return $owned;
    }

    /**
     * The class group of the account's highest level character, for the store's front page.
     */
    public function suggestedGroup(Account $account): ?string
    {
        return $this->accountCharacters($account)->pluck('archetype')->filter()->first();
    }

    /**
     * Where L-Coin comes from for free: every new character's welcome gift and the weekly gift box.
     *
     * @return array{welcome: int, giftBox: int}
     */
    public function lcoinSources(): array
    {
        $gift = Product::query()->listed()->where('type', Product::TYPE_WEEKLY_GIFT)->first();

        return [
            'welcome' => (int) config('l2j.welcome_lcoin'),
            'giftBox' => $gift ? (int) (collect($this->contentsOf($gift)['items'])->firstWhere('item', self::LCOIN)['count'] ?? 0) : 0,
        ];
    }

    /**
     * For the home page's store section; a product that's gone leaves its card out.
     *
     * @return array{gift: array{open: bool, minLevel: int}|null, pass: array{open: bool, name: array{en: string, pt: string}, endsAt: string|null, price: array{item: int, count: int, name: string}|null}|null, packages: array{open: bool, groups: int, min: int, max: int, currency: string}|null}
     */
    public function homeOffer(): array
    {
        $gift = Product::query()->listed()->where('type', Product::TYPE_WEEKLY_GIFT)->first();
        $pass = Product::query()->listed()->where('slug', 'kage-pass')->first();
        $packages = Product::query()->listed()->whereNotNull('class_group')->whereNotNull('price_count')->get();

        return [
            'gift' => $gift ? ['open' => $this->isOpen($gift), 'minLevel' => (int) $gift->min_level] : null,
            'pass' => $pass ? ['open' => $this->isOpen($pass), 'name' => $pass->localizedName(), 'endsAt' => $pass->ends_at?->toIso8601String(), 'price' => $this->price($pass)] : null,
            'packages' => $packages->isEmpty() ? null : [
                'open' => $packages->contains(fn (Product $product): bool => $this->isOpen($product)),
                'groups' => $packages->pluck('class_group')->unique()->count(),
                'min' => (int) $packages->min('price_count'),
                'max' => (int) $packages->max('price_count'),
                'currency' => $this->gameData->item((int) $packages->first()->price_item)['name'],
            ],
        ];
    }

    /**
     * Buys a product for one of the account's characters: records the order and queues the in-game charge and mail, in
     * one transaction, and emails the confirmation when something was paid for.
     *
     * @throws ValidationException when it can't be bought
     */
    public function buy(Account $account, Product $product, int $characterId, int $quantity, ?CarbonImmutable $now = null): Order
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($product->type === Product::TYPE_HUNT_PASS) {
            $fail('character_id', __('This can only be bought in the game.'));
        }

        if (! $this->isOpen($product, $now)) {
            $fail('character_id', __('This is closed for now.'));
        }

        if ($quantity < 1 || $quantity > $product->max_per_order) {
            $fail('quantity', __('Choose between 1 and :max.', ['max' => $product->max_per_order]));
        }

        $character = $this->ownCharacter($account, $characterId);

        if ($character === null) {
            $fail('character_id', __('Choose one of your characters.'));
        }

        if ($character->level < (int) $product->min_level) {
            $fail('character_id', __('Choose one of your characters of level :level or higher.', ['level' => $product->min_level]));
        }

        if (! $this->suits($product, $character)) {
            $fail('character_id', __('Only :class characters can receive this.', ['class' => $this->className($product)]));
        }

        $price = $this->price($product);
        $total = $price ? $price['count'] * $quantity : null;

        if ($price && (Character::bagCounts($account->login, $price['item'])[$character->charId] ?? 0) < $total) {
            $fail('character_id', __(':name needs :count :currency in the bag to buy this.', ['name' => $character->char_name, 'count' => number_format($total), 'currency' => $price['name']]));
        }

        $limitKey = $product->limitKey($now);

        if ($limitKey !== null) {
            $this->releaseFailedPurchase($account, $product, $limitKey);
        }

        $items = $this->contents->roll($this->contentsOf($product), (int) $character->level, $quantity);

        try {
            $order = DB::transaction(fn (): Order => $this->place($account, $product, $character, $quantity, $price, $total, $items, $limitKey));
        } catch (UniqueConstraintViolationException) {
            $fail('character_id', __('Your account already has this one.'));
        }

        if ($price) {
            $this->orders->confirm($account, $order);
        }

        return $order;
    }

    /**
     * Sends a product to game accounts, whatever it costs and whatever they already hold: each gets a free order that waits
     * in its Orders tab until the player chooses the character that receives it. The contents are kept as they are now,
     * and rolled for that character when chosen.
     *
     * @param  list<string>  $logins
     * @return int how many accounts it went to
     */
    public function send(Product $product, array $logins, string $sentBy, ?string $note): int
    {
        if ($product->type === Product::TYPE_HUNT_PASS) {
            throw ValidationException::withMessages(['target' => 'The Hunt Pass is unlocked in the game, so it can\'t be sent.']);
        }

        $contents = $this->contentsOf($product);
        $logins = array_values(array_unique($logins));

        DB::transaction(function () use ($product, $logins, $sentBy, $note, $contents): void {
            foreach ($logins as $login) {
                Order::create([
                    'account' => $login,
                    'product_id' => $product->id,
                    'source' => Order::SOURCE_ADMIN,
                    'category' => $product->category->slug,
                    'category_name' => $product->category->localizedName(),
                    'name' => $product->localizedName(),
                    'contents' => $contents,
                    'sent_by' => $sentBy,
                    'note' => $note,
                ]);
            }
        });

        $this->orders->announce($logins, $product->localizedName()['en']);

        return count($logins);
    }

    /**
     * Delivers something an admin sent to the character the player chose.
     *
     * @throws ValidationException when the character isn't theirs, or it was already delivered
     */
    public function redeem(Account $account, Order $order, int $characterId): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['character_id' => $message]);
        $character = $this->ownCharacter($account, $characterId);

        if ($character === null) {
            $fail(__('Choose one of your characters.'));
        }

        $product = $order->product;

        if ($product && ! $this->suits($product, $character)) {
            $fail(__('Only :class characters can receive this.', ['class' => $this->className($product)]));
        }

        $items = $this->contents->roll($order->contents ?? ['items' => [], 'bonus' => []], (int) $character->level);

        if ($items === []) {
            $fail(__('This is closed for now.'));
        }

        DB::transaction(function () use ($order, $character, $items, $product, $fail): void {
            $action = GameAction::create([
                'action' => GameAction::ACTION_SEND_MAIL,
                'character_id' => $character->charId,
                'character_name' => $character->char_name,
                'item_name' => $order->name['en'],
                'count' => count($items),
                'items' => $this->contents->encode($items),
                'mail_subject' => $product?->mail_subject,
                'mail_text' => $product?->mail_text,
                'note' => $this->itemsNote($items),
                'requested_by' => (string) $order->sent_by,
            ]);

            // Only the first of two presses at the same moment finds it still waiting.
            $updated = Order::query()->whereKey($order->id)->whereNull('character_id')->update([
                'character_id' => $character->charId,
                'character_name' => $character->char_name,
                'items' => json_encode($items),
                'game_action_id' => $action->id,
                'updated_at' => now(),
            ]);

            if ($updated === 0) {
                $fail(__('This was already delivered.'));
            }
        });
    }

    /**
     * What the product holds right now: an active Gift Boxes rotation replaces the weekly gift's own contents.
     *
     * @return array{items: list<array<string, mixed>>, bonus: list<array<string, mixed>>}
     */
    public function contentsOf(Product $product): array
    {
        $rotation = $this->rotation($product);

        return $rotation
            ? $this->contents->normalize($rotation->guaranteed, $rotation->bonus)
            : $this->contents->normalize($product->items, $product->bonus);
    }

    public function isOpen(Product $product, ?CarbonImmutable $now = null): bool
    {
        return $product->type === Product::TYPE_HUNT_PASS
            ? $this->config->flag('HuntPass.ini', 'EnabledHuntPass', false)
            : ! $product->hasEnded($now);
    }

    /**
     * @return array{item: int, count: int, name: string}|null null when free
     */
    public function price(Product $product): ?array
    {
        if ($product->type === Product::TYPE_HUNT_PASS) {
            $item = (int) $this->config->number('HuntPass.ini', 'PremiumItemId', self::LCOIN);

            return ['item' => $item, 'count' => (int) $this->config->number('HuntPass.ini', 'PremiumItemCount', 3600), 'name' => $this->gameData->item($item)['name']];
        }

        return $product->isFree() ? null : ['item' => (int) $product->price_item, 'count' => (int) $product->price_count, 'name' => $this->gameData->item((int) $product->price_item)['name']];
    }

    /**
     * @param  array{item: int, count: int, name: string}|null  $price
     * @param  list<array{id: int, name: string, count: int, enchant: int, bonus: bool}>  $items
     */
    private function place(Account $account, Product $product, Character $character, int $quantity, ?array $price, ?int $total, array $items, ?string $limitKey): Order
    {
        $action = GameAction::create([
            'action' => $price ? GameAction::ACTION_BUY_PASS : GameAction::ACTION_SEND_MAIL,
            'character_id' => $character->charId,
            'character_name' => $character->char_name,
            'item_id' => $price['item'] ?? null,
            'item_name' => $product->name_en,
            'count' => $total ?? count($items),
            'items' => $this->contents->encode($items),
            'mail_subject' => $product->mail_subject,
            'mail_text' => $product->mail_text,
            'note' => $price ? number_format($total)." {$price['name']}" : $this->itemsNote($items),
            'requested_by' => "store ({$account->login})",
        ]);

        return Order::create([
            'account' => $account->login,
            'product_id' => $product->id,
            'source' => Order::SOURCE_PURCHASE,
            'category' => $product->category->slug,
            'category_name' => $product->category->localizedName(),
            'name' => $product->localizedName(),
            'quantity' => $quantity,
            'price_item' => $price['item'] ?? null,
            'price_count' => $total,
            'price_name' => $price['name'] ?? null,
            'character_id' => $character->charId,
            'character_name' => $character->char_name,
            'items' => $items,
            'game_action_id' => $action->id,
            'limit_key' => $limitKey,
        ]);
    }

    /**
     * A purchase whose charge failed in game (not enough by then) gives way to a new one, and stays in the history. What
     * was free stays: without a charge to fail, a failure there is one a staff member should look at.
     */
    private function releaseFailedPurchase(Account $account, Product $product, string $limitKey): void
    {
        $previous = Order::with('gameAction')->where('account', $account->login)->where('product_id', $product->id)->where('limit_key', $limitKey)->first();

        if ($previous && $previous->price_count && $previous->status() === GameAction::STATUS_FAILED) {
            $previous->update(['limit_key' => null]);
        }
    }

    /**
     * @param  list<array{name: string, count: int}>  $items
     */
    private function itemsNote(array $items): string
    {
        return implode(', ', array_map(fn (array $item): string => number_format($item['count'])." x {$item['name']}", $items));
    }

    private function ownCharacter(Account $account, int $characterId): ?Character
    {
        return Character::query()->where('account_name', $account->login)->where('deletetime', 0)->find($characterId, ['charId', 'char_name', 'level', 'classid']);
    }

    private function suits(Product $product, Character $character): bool
    {
        return ! $product->class_only || $this->classGroupFor((int) $character->classid) === $product->class_group;
    }

    /**
     * The name of the product's class group, in the visitor's language.
     */
    private function className(Product $product): string
    {
        $name = config("l2j.store.class_groups.{$product->class_group}.name");

        return $name[app()->isLocale('pt_BR') ? 'pt' : 'en'] ?? (string) $product->class_group;
    }

    /**
     * The Gift Boxes rotation running now, for the weekly gift.
     */
    private function rotation(Product $product): ?GiftBoxTemplate
    {
        return $product->type === Product::TYPE_WEEKLY_GIFT
            ? GiftBoxTemplate::query()->activeAt(CarbonImmutable::now())->first()
            : null;
    }

    /**
     * @return array{en: string, pt: string}
     */
    private function description(Product $product): array
    {
        if ($product->type === Product::TYPE_HUNT_PASS) {
            return $this->huntDescription();
        }

        $text = ['en' => $product->description_en, 'pt' => $product->description_pt];
        $rotation = $this->rotation($product);

        if ($rotation) {
            $extra = $rotation->name.($rotation->description ? " — {$rotation->description}" : '');
            $text = ['en' => "{$text['en']}\n\n{$extra}", 'pt' => "{$text['pt']}\n\n{$extra}"];
        }

        return $text;
    }

    /**
     * The product's items as its page lists them.
     *
     * @return list<array{title: array{en: string, pt: string}, items: list<array<string, mixed>>, note?: array{en: string, pt: string}}>
     */
    private function contentGroups(Product $product): array
    {
        $contents = $this->contentsOf($product);
        $described = $this->contents->describe($contents);
        $groups = [];

        if ($described['items'] !== []) {
            $groups[] = ['title' => ['en' => 'What’s included', 'pt' => 'O que vem incluso'], 'items' => $described['items']];
        }

        if ($described['bonus'] !== []) {
            $groups[] = ['title' => ['en' => 'Plus one random bonus', 'pt' => 'Mais um bônus aleatório'], 'items' => $described['bonus']];
        }

        if ($groups !== [] && $this->contents->hasGradeItems($contents)) {
            $groups[array_key_last($groups)]['note'] = [
                'en' => '“Your grade” scrolls match the receiving character’s level: D from 20, C from 40, B from 52, A from 61, S from 76.',
                'pt' => 'Os pergaminhos “do seu grau” seguem o nível do personagem que recebe: D a partir do 20, C do 40, B do 52, A do 61, S do 76.',
            ];
        }

        return $groups;
    }

    /**
     * @return array{en: string, pt: string}|null
     */
    private function limitLabel(Product $product): ?array
    {
        return match ($product->limit_period) {
            Product::LIMIT_WEEK => ['en' => '1 per week', 'pt' => '1 por semana'],
            Product::LIMIT_MONTH => ['en' => '1 per month', 'pt' => '1 por mês'],
            Product::LIMIT_SEASON => ['en' => '1 per season', 'pt' => '1 por temporada'],
            Product::LIMIT_ONCE => ['en' => 'Once', 'pt' => 'Uma vez'],
            default => null,
        };
    }

    /**
     * What to do after buying anything that arrives by mail.
     *
     * @return list<array{en: string, pt: string}>
     */
    private function mailSteps(Product $product): array
    {
        $steps = [
            ['en' => 'Log in to the game with the character you chose.', 'pt' => 'Entre no jogo com o personagem escolhido.'],
            ['en' => 'Open your mailbox (the envelope icon).', 'pt' => 'Abra sua caixa de correio (o ícone de envelope).'],
            ['en' => 'Take the attachments within 15 days.', 'pt' => 'Retire os anexos em até 15 dias.'],
        ];

        // A mail carries at most 8 items.
        $contents = $this->contentsOf($product);
        $count = count($contents['items']) + ($contents['bonus'] === [] ? 0 : 1);

        if ($count > 8) {
            $steps[] = ['en' => 'It arrives in more than one mail, so take the attachments from all of them.', 'pt' => 'Chega em mais de um correio, então retire os anexos de todos.'];
        }

        return $steps;
    }

    /**
     * @return array{en: string, pt: string}
     */
    private function huntDescription(): array
    {
        $points = number_format((int) $this->config->number('HuntPass.ini', 'PointsForStep', 2400));
        $steps = number_format(count($this->gameData->huntPass()));
        $cost = $this->huntCost();

        return [
            'en' => "Essence’s own season pass. Every monster you defeat moves it forward, and every step pays a reward.\n\nEach monster gives 1 point, and 2 inside timed hunting zones; party kills count for the members too. {$points} points complete a step. There are {$steps} steps, each with a free reward.\n\nPremium ({$cost}) makes every step also pay a Premium reward, including the steps you already completed.",
            'pt' => "O passe de temporada do próprio Essence. Cada monstro derrotado faz ele avançar, e cada etapa paga uma recompensa.\n\nCada monstro dá 1 ponto, e 2 dentro das zonas de caça com tempo; abates em grupo também contam para os membros. {$points} pontos completam uma etapa. São {$steps} etapas, cada uma com uma recompensa grátis.\n\nO Premium ({$cost}) faz toda etapa pagar também uma recompensa Premium, inclusive as que você já completou.",
        ];
    }

    /**
     * @return list<array{en: string, pt: string}>
     */
    private function huntSteps(Product $product): array
    {
        $cost = $this->huntCost();
        $day = (int) $this->config->number('HuntPass.ini', 'DayOfMonth', 1);

        return [
            ['en' => 'Open the Hunt Pass window in the game.', 'pt' => 'Abra a janela do Hunt Pass no jogo.'],
            ['en' => "Unlock Premium there for {$cost}: this one can’t be bought on the website.", 'pt' => "Desbloqueie o Premium lá por {$cost}: este não pode ser comprado no site."],
            ['en' => "Collect your rewards before the pass starts over, on day {$day} of every month.", 'pt' => "Retire suas recompensas antes de o passe recomeçar, no dia {$day} de cada mês."],
        ];
    }

    /**
     * @return array{firstSteps: list<array<string, mixed>>, steps: int}
     */
    private function huntDetails(): array
    {
        $huntPass = $this->gameData->huntPass();

        return ['firstSteps' => array_slice($huntPass, 0, 10), 'steps' => count($huntPass)];
    }

    private function huntCost(): string
    {
        $item = (int) $this->config->number('HuntPass.ini', 'PremiumItemId', self::LCOIN);

        return number_format((int) $this->config->number('HuntPass.ini', 'PremiumItemCount', 3600)).' '.$this->gameData->item($item)['name'];
    }

    /**
     * The class group a class belongs to, or null when none lists it.
     */
    private function classGroupFor(int $classId): ?string
    {
        foreach (config('l2j.store.class_groups') as $key => $group) {
            if (in_array($classId, $group['classes'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The account's characters, highest level first, with the class group they suit.
     *
     * @return Collection<int, array{id: int, name: string, level: int, className: string, archetype: string|null}>
     */
    private function accountCharacters(Account $account): Collection
    {
        return Character::query()
            ->where('account_name', $account->login)
            ->where('deletetime', 0)
            ->orderByDesc('level')
            ->get(['charId', 'char_name', 'level', 'classid'])
            ->map(fn (Character $c): array => [
                'id' => $c->charId,
                'name' => $c->char_name,
                'level' => $c->level,
                'className' => $this->gameData->className($c->classid),
                'archetype' => $this->classGroupFor($c->classid),
            ])
            ->toBase();
    }
}
