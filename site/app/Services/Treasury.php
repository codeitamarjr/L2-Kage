<?php

namespace App\Services;

use App\Models\Game\Account;
use App\Models\Game\Character;
use App\Models\GameAction;
use App\Services\L2j\GameConfig;
use App\Services\L2j\GameData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The Treasury's catalogue: the weekly gift box, the Kage Pass, the in-game Hunt Pass, supply kits and class packages
 * as one list of products, each with its own address (/treasury/{slug}). Prices and contents come from each kind's own
 * config and service; this class only describes them the same way, works out who can buy them and hands a purchase to
 * the service that owns it. Every price names its item, so a product can cost L-Coin, Adena or anything else.
 */
class Treasury
{
    public const GIFT = 'weekly-gift-box';

    public const PASS = 'kage-pass';

    public const HUNT = 'hunt-pass';

    private const LCOIN = 91663;

    /** What the store grid doesn't need: only a product's own page shows these. */
    private const DETAIL_FIELDS = ['description', 'contents', 'steps', 'limit', 'maxQuantity', 'minLevel', 'hunt', 'endsAt'];

    public function __construct(
        private GameData $gameData,
        private GameConfig $config,
        private GiftBox $gift,
        private KagePass $pass,
        private ClassPackages $packages,
        private SupplyKits $supplies,
        private Orders $orders,
    ) {}

    /**
     * Every product with everything its page shows, in the order the store lists them.
     *
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        return [$this->giftProduct(), $this->passProduct(), $this->huntProduct(), ...$this->supplyProducts(), ...$this->packageProducts()];
    }

    /**
     * The products as the store grid shows them.
     *
     * @return list<array<string, mixed>>
     */
    public function cards(): array
    {
        return array_map(fn (array $product): array => Arr::except($product, self::DETAIL_FIELDS), $this->products());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return collect($this->products())->firstWhere('slug', $slug);
    }

    /**
     * The account's characters, for a product's purchase panel: the ones its class suits first, then by level, each with
     * what it holds of the product's price (as last saved by the game server).
     *
     * @param  array<string, mixed>  $product
     * @return list<array{id: int, name: string, level: int, className: string, archetype: string|null, balance: int|null}>
     */
    public function characters(Account $account, array $product): array
    {
        $price = $product['price'];
        $balances = $price ? Character::bagCounts($account->login, $price['item']) : [];

        return $this->accountCharacters($account)
            ->map(fn (array $character): array => [...$character, 'balance' => $price ? ($balances[$character['id']] ?? 0) : null])
            ->sortByDesc(fn (array $character): bool => $product['group'] !== null && $character['archetype'] === $product['group'])
            ->values()
            ->all();
    }

    /**
     * What the account already holds and can't buy again for now: this week's gift box, this season's Kage Pass and class
     * packages. A pass or package whose charge failed in game can be bought again, so it isn't listed.
     *
     * @return array<string, array{characterName: string, status: string, nextAt: string|null}> by product slug
     */
    public function owned(Account $account): array
    {
        $owned = [];
        $gift = $this->gift->status($account);

        if ($gift['claim']) {
            $owned[self::GIFT] = ['characterName' => $gift['claim']['characterName'], 'status' => $gift['claim']['status'], 'nextAt' => $gift['nextAt']];
        }

        $pass = $this->pass->status($account)['claim'];

        if ($pass && $pass['status'] !== GameAction::STATUS_FAILED) {
            $owned[self::PASS] = ['characterName' => $pass['characterName'], 'status' => $pass['status'], 'nextAt' => null];
        }

        foreach ($this->packages->status($account)['claims'] as $claim) {
            if ($claim['status'] !== GameAction::STATUS_FAILED) {
                [$tier, $archetype] = explode(':', $claim['package']);
                $owned[$this->packageSlug($archetype, $tier)] = ['characterName' => $claim['characterName'], 'status' => $claim['status'], 'nextAt' => null];
            }
        }

        return $owned;
    }

    /**
     * The class of packages the account's highest level character can use, for the store's front page.
     */
    public function suggestedArchetype(Account $account): ?string
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
        return [
            'welcome' => (int) config('l2j.welcome_lcoin'),
            'giftBox' => (int) (collect($this->gift->contents()['guaranteed'])->firstWhere('id', self::LCOIN)['count'] ?? 0),
        ];
    }

    /**
     * Buys a product for one of the account's characters, through the service that owns it, and emails the confirmation
     * when something was paid for.
     *
     * @param  array<string, mixed>  $product
     * @return string the status to flash on the Orders tab
     *
     * @throws ValidationException when it can't be bought; see each service
     */
    public function buy(Account $account, array $product, int $characterId, int $quantity): string
    {
        $key = $product['key'];

        if ($product['kind'] === 'gift') {
            $this->gift->claim($account, $characterId);

            return 'gift-opened';
        }

        $claim = match ($product['kind']) {
            'pass' => $this->pass->claim($account, $characterId),
            'package' => $this->packages->claim($account, $characterId, $product['tier'], $product['group']),
            'supplies' => $this->supplies->buy($account, $characterId, $key, $quantity),
            default => throw ValidationException::withMessages(['character_id' => __('This can only be bought in the game.')]),
        };

        $this->orders->confirm($account, $product['name']['en'], (int) $claim->gameAction->count, $product['price']['name'], $claim->character_name, $claim->gameAction);

        return 'order-placed';
    }

    /**
     * @return array<string, mixed>
     */
    private function giftProduct(): array
    {
        $minLevel = (int) config('l2j.gift_box.min_level');
        $contents = $this->gift->contents();
        $status = $this->gift->templateDetails();
        $description = [[
            'en' => "Free, once per account every week, for a character of level {$minLevel} or higher. Every box holds the same supplies plus one bonus, rolled when you open it.",
            'pt' => "Grátis, uma vez por conta toda semana, para um personagem de nível {$minLevel} ou mais. Toda caixa traz os mesmos suprimentos e mais um bônus, sorteado ao abrir.",
        ]];

        if ($status['name']) {
            $text = $status['name'].($status['description'] ? " — {$status['description']}" : '');
            $description[] = ['en' => $text, 'pt' => $text];
        }

        return $this->product([
            'slug' => self::GIFT,
            'key' => 'gift',
            'kind' => 'gift',
            'category' => 'gifts',
            'name' => ['en' => 'Weekly Gift Box', 'pt' => 'Caixa de Presente Semanal'],
            'open' => (bool) config('l2j.gift_box.enabled'),
            'description' => $description,
            'contents' => [
                ['title' => ['en' => 'Every box', 'pt' => 'Toda caixa'], 'items' => $contents['guaranteed']],
                [
                    'title' => ['en' => 'Plus one bonus', 'pt' => 'Mais um bônus'],
                    'items' => $contents['bonus'],
                    'note' => [
                        'en' => '“Your grade” scrolls match the receiving character’s level: D from 20, C from 40, B from 52, A from 61, S from 76.',
                        'pt' => 'Os pergaminhos “do seu grau” seguem o nível do personagem que recebe: D a partir do 20, C do 40, B do 52, A do 61, S do 76.',
                    ],
                ],
            ],
            'steps' => $this->mailSteps(),
            'limit' => ['en' => '1 per week', 'pt' => '1 por semana'],
            'minLevel' => $minLevel,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function passProduct(): array
    {
        $pass = $this->pass->offer();

        return $this->product([
            'slug' => self::PASS,
            'key' => (string) $pass['season'],
            'kind' => 'pass',
            'category' => 'passes',
            'name' => ['en' => $pass['name'], 'pt' => $pass['name']],
            'price' => ['item' => (int) config('l2j.pass.price.item'), 'count' => $pass['price']['count'], 'name' => $pass['price']['name']],
            'open' => $pass['enabled'],
            'endsAt' => CarbonImmutable::parse($pass['ends'])->endOfDay()->toIso8601String(),
            'description' => [[
                'en' => 'A season bundle of supplies and scrolls, once per account each season. Every new hero starts with enough L-Coin to claim it on day one.',
                'pt' => 'Um pacote da temporada com suprimentos e pergaminhos, uma vez por conta a cada temporada. Todo herói novo já começa com L-Coin suficiente para resgatá-lo no primeiro dia.',
            ]],
            'contents' => [['title' => ['en' => 'Rewards', 'pt' => 'Recompensas'], 'items' => $pass['rewards']]],
            'steps' => $this->mailSteps(),
            'limit' => ['en' => '1 per season', 'pt' => '1 por temporada'],
        ]);
    }

    /**
     * The Hunt Pass is Essence's own and is unlocked inside the game: its page explains it, with the server's numbers.
     *
     * @return array<string, mixed>
     */
    private function huntProduct(): array
    {
        $huntPass = $this->gameData->huntPass();
        $item = (int) $this->config->number('HuntPass.ini', 'PremiumItemId', self::LCOIN);
        $count = (int) $this->config->number('HuntPass.ini', 'PremiumItemCount', 3600);
        $points = number_format((int) $this->config->number('HuntPass.ini', 'PointsForStep', 2400));
        $steps = number_format(count($huntPass));
        $day = (int) $this->config->number('HuntPass.ini', 'DayOfMonth', 1);
        $name = $this->gameData->item($item)['name'];
        $cost = number_format($count)." {$name}";

        return $this->product([
            'slug' => self::HUNT,
            'key' => 'hunt',
            'kind' => 'hunt',
            'category' => 'passes',
            'name' => ['en' => 'Hunt Pass Premium', 'pt' => 'Hunt Pass Premium'],
            'price' => ['item' => $item, 'count' => $count, 'name' => $name],
            'open' => $this->config->flag('HuntPass.ini', 'EnabledHuntPass', false),
            'description' => [
                [
                    'en' => 'Essence’s own season pass. Every monster you defeat moves it forward, and every step pays a reward.',
                    'pt' => 'O passe de temporada do próprio Essence. Cada monstro derrotado faz ele avançar, e cada etapa paga uma recompensa.',
                ],
                [
                    'en' => "Each monster gives 1 point, and 2 inside timed hunting zones; party kills count for the members too. {$points} points complete a step. There are {$steps} steps, each with a free reward.",
                    'pt' => "Cada monstro dá 1 ponto, e 2 dentro das zonas de caça com tempo; abates em grupo também contam para os membros. {$points} pontos completam uma etapa. São {$steps} etapas, cada uma com uma recompensa grátis.",
                ],
                [
                    'en' => "Premium ({$cost}) makes every step also pay a Premium reward, including the steps you already completed.",
                    'pt' => "O Premium ({$cost}) faz toda etapa pagar também uma recompensa Premium, inclusive as que você já completou.",
                ],
            ],
            'hunt' => ['firstSteps' => array_slice($huntPass, 0, 10), 'steps' => count($huntPass)],
            'steps' => [
                ['en' => 'Open the Hunt Pass window in the game.', 'pt' => 'Abra a janela do Hunt Pass no jogo.'],
                ['en' => "Unlock Premium there for {$cost}: this one can’t be bought on the website.", 'pt' => "Desbloqueie o Premium lá por {$cost}: este não pode ser comprado no site."],
                ['en' => "Collect your rewards before the pass starts over, on day {$day} of every month.", 'pt' => "Retire suas recompensas antes de o passe recomeçar, no dia {$day} de cada mês."],
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function supplyProducts(): array
    {
        return array_map(fn (array $kit): array => $this->product([
            'slug' => "supplies-{$kit['key']}",
            'key' => $kit['key'],
            'kind' => 'supplies',
            'category' => 'supplies',
            'name' => $kit['name'],
            'price' => $kit['price'],
            'open' => (bool) config('l2j.supplies.enabled'),
            'description' => [$kit['description']],
            'contents' => [['title' => ['en' => 'In each kit', 'pt' => 'Em cada kit'], 'items' => $kit['items']]],
            'steps' => $this->mailSteps(),
            'maxQuantity' => (int) config('l2j.supplies.max_per_order'),
        ]), $this->supplies->offer());
    }

    /**
     * One product per archetype and tier.
     *
     * @return list<array<string, mixed>>
     */
    private function packageProducts(): array
    {
        $offer = $this->packages->offer();
        $priceItem = (int) config('l2j.packages.price_item');
        $products = [];

        foreach ($offer['archetypes'] as $archetype) {
            foreach ($offer['tiers'] as $tier) {
                $products[] = $this->product([
                    'slug' => $this->packageSlug($archetype['key'], $tier['key']),
                    'key' => "{$tier['key']}:{$archetype['key']}",
                    'kind' => 'package',
                    'category' => 'packages',
                    'group' => $archetype['key'],
                    'groupName' => $archetype['name'],
                    'tier' => $tier['key'],
                    'name' => [
                        'en' => "{$archetype['name']['en']} Package ({$tier['name']['en']})",
                        'pt' => "Pacote {$archetype['name']['pt']} ({$tier['name']['pt']})",
                    ],
                    'price' => ['item' => $priceItem, 'count' => (int) $tier['price'], 'name' => $offer['currency']],
                    'open' => $offer['enabled'],
                    'description' => [
                        [
                            'en' => "Weapon, armor and jewelry for the {$archetype['name']['en']}: {$archetype['role']['en']}. Grade {$tier['grade']}, weapon and armor enchanted to +{$tier['enchant']}.",
                            'pt' => "Arma, armadura e joias para {$archetype['name']['pt']}: {$archetype['role']['pt']}. Grau {$tier['grade']}, arma e armadura encantadas em +{$tier['enchant']}.",
                        ],
                        [
                            'en' => 'The items are sealed, so they can’t be traded.',
                            'pt' => 'Os itens são selados, então não podem ser negociados.',
                        ],
                    ],
                    'contents' => [['title' => ['en' => 'The gear', 'pt' => 'O equipamento'], 'items' => $archetype['contents'][$tier['key']] ?? []]],
                    'steps' => [
                        ...$this->mailSteps(),
                        ['en' => 'A package arrives in two mails, so take the attachments from both.', 'pt' => 'Um pacote chega em dois correios, então retire os anexos dos dois.'],
                    ],
                    'limit' => ['en' => '1 per season', 'pt' => '1 por temporada'],
                ]);
            }
        }

        return $products;
    }

    /**
     * Every field a product has, whatever its kind.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function product(array $fields): array
    {
        return [
            'group' => null,
            'groupName' => null,
            'tier' => null,
            'price' => null,
            'endsAt' => null,
            'contents' => [],
            'limit' => null,
            'maxQuantity' => 1,
            'minLevel' => null,
            'hunt' => null,
            ...$fields,
        ];
    }

    /**
     * What to do after buying anything that arrives by mail.
     *
     * @return list<array{en: string, pt: string}>
     */
    private function mailSteps(): array
    {
        return [
            ['en' => 'Log in to the game with the character you chose.', 'pt' => 'Entre no jogo com o personagem escolhido.'],
            ['en' => 'Open your mailbox (the envelope icon).', 'pt' => 'Abra sua caixa de correio (o ícone de envelope).'],
            ['en' => 'Take the attachments within 15 days.', 'pt' => 'Retire os anexos em até 15 dias.'],
        ];
    }

    private function packageSlug(string $archetype, string $tier): string
    {
        return "package-{$archetype}-{$tier}";
    }

    /**
     * The account's characters, highest level first, with the class they suit.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, level: int, className: string, archetype: string|null}>
     */
    private function accountCharacters(Account $account)
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
                'archetype' => $this->packages->archetypeFor($c->classid),
            ]);
    }
}
