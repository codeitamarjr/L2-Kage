<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StoreCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * The Store's starting catalogue: its categories and every product it launched with. Safe to run again at any time with
 * php artisan db:seed --force: it adds what is missing, by web address, and never changes a product the admin panel has
 * edited or brings back one it deleted. Item ids are Essence's (resources/data/gamedata.json).
 */
class StoreSeeder extends Seeder
{
    private const LCOIN = 91663;

    private const ADENA = 57;

    private const SEASON = 'season-1';

    private const CATEGORIES = [
        'gifts' => ['Gifts', 'Presentes'],
        'passes' => ['Passes', 'Passes'],
        'packages' => ['Class Packages', 'Pacotes de Classe'],
        // Empty for now: the Store shows a category once it has a product.
        'chests' => ['Chests', 'Baús'],
        'items' => ['Items', 'Itens'],
        'supplies' => ['Supplies', 'Suprimentos'],
    ];

    /**
     * Class packages: items are the Sealed versions (they can't be traded), picked from the datapack's own B-grade, +6
     * A-grade and boss weapon packs.
     */
    private const TIERS = [
        'starter' => [
            'name' => ['en' => 'Starter', 'pt' => 'Iniciante'],
            'grade' => 'B',
            'price' => 5_000,
            'enchant' => 4,
            'shield' => 94934,  // Doom Shield
            'armor' => [
                'heavy' => [93502, 93506, 93550, 93526, 93538],  // Blue Wolf Breastplate, Gaiters, Helmet, Gloves, Boots (Heavy)
                'light' => [93511, 93551, 93527, 93539],         // Blue Wolf Leather Armor, Helmet, Gloves, Boots (Light)
                'robe' => [93514, 93517, 93552, 93528, 93540],   // Blue Wolf Tunic, Stockings, Helmet, Gloves, Boots (Robe)
            ],
            // Black Ore Earring x2, Ring x2, Necklace
            'jewelry' => [92384, 92384, 92385, 92385, 92386],
        ],
        'advanced' => [
            'name' => ['en' => 'Advanced', 'pt' => 'Avançado'],
            'grade' => 'A',
            'price' => 20_000,
            'enchant' => 6,
            'shield' => 92276,  // Shield of Nightmare
            'armor' => [
                'heavy' => [92262, 92310, 92286, 92298],  // Majestic Plate Armor, Circlet, Gauntlets, Boots (Heavy)
                'light' => [92267, 92311, 92287, 92299],  // Majestic Leather Armor, Circlet, Gauntlets, Boots (Light)
                'robe' => [92272, 92312, 92288, 92300],   // Majestic Robe, Circlet, Gauntlets, Boots (Robe)
            ],
            // Majestic Earring x2, Ring x2, Necklace
            'jewelry' => [95425, 95425, 95394, 95394, 95429],
        ],
        'elite' => [
            'name' => ['en' => 'Elite', 'pt' => 'Elite'],
            'grade' => 'A',
            'price' => 60_000,
            'enchant' => 8,
            'shield' => 95666,  // Shield of Protection
            'armor' => [
                'heavy' => [100329, 100330, 95935, 100341, 100342],  // Elegia Breastplate, Gaiters, Helmet of Protection, Elegia Gauntlets, Boots
                'light' => [100333, 100334, 95935, 100341, 100342],  // Elegia Leather Armor, Leggings, ...
                'robe' => [100337, 100338, 95935, 100341, 100342],   // Elegia Tunic, Stockings, ...
            ],
            // Zaken's Earring, Orfen's Earring, Baium's Ring, Queen Ant's Ring, Frintezza's Necklace
            'jewelry' => [95331, 95328, 95330, 95327, 95333],
        ],
    ];

    /** Each class group's package (see config l2j.store.class_groups): weapons by tier, armor heavy, light or robe. */
    private const ARCHETYPES = [
        'knight' => [
            'role' => ['en' => 'Sword and shield', 'pt' => 'Espada e escudo'],
            'armor' => 'heavy',
            'shield' => true,
            'weapons' => ['starter' => 93422, 'advanced' => 92955, 'elite' => 93291],  // Damascus Sword, Sirra's Blade, Zaken's Blood Sword
        ],
        'samurai' => [
            'role' => ['en' => 'One-handed sword', 'pt' => 'Espada de uma mão'],
            'armor' => 'heavy',
            'weapons' => ['starter' => 93422, 'advanced' => 92955, 'elite' => 93291],
        ],
        'duelist' => [
            'role' => ['en' => 'Dual swords', 'pt' => 'Espadas duplas'],
            'armor' => 'heavy',
            'weapons' => ['starter' => 93430, 'advanced' => 92966, 'elite' => 97963],  // Samurai Long Sword*Samurai Long Sword, Tallum Blade*Damascus Sword, Juriel's Dual Swords
        ],
        'titan' => [
            'role' => ['en' => 'Two-handed sword', 'pt' => 'Espada de duas mãos'],
            'armor' => 'heavy',
            'weapons' => ['starter' => 93431, 'advanced' => 92956, 'elite' => 93293],  // Guardian's Sword, Sword of Ipos, Orfen's Venom Sword
        ],
        'lancer' => [
            'role' => ['en' => 'Polearm', 'pt' => 'Lança'],
            'armor' => 'heavy',
            'weapons' => ['starter' => 93423, 'advanced' => 92960, 'elite' => 97302],  // Lance, Tiphon's Spear, Gorde's Flame Spear
        ],
        'artisan' => [
            'role' => ['en' => 'Blunt weapon', 'pt' => 'Arma de impacto'],
            'armor' => 'heavy',
            'weapons' => ['starter' => 93425, 'advanced' => 92957, 'elite' => 93295],  // Art of Battle Axe, Barakiel's Axe, Queen Ant's Stone Breaker
        ],
        'rogue' => [
            'role' => ['en' => 'Dagger', 'pt' => 'Adaga'],
            'armor' => 'light',
            'weapons' => ['starter' => 93427, 'advanced' => 92959, 'elite' => 93294],  // Demon's Dagger, Naga's Storm, Baium's Thunder Breaker
        ],
        'archer' => [
            'role' => ['en' => 'Bow', 'pt' => 'Arco'],
            'armor' => 'light',
            'weapons' => ['starter' => 93429, 'advanced' => 92961, 'elite' => 93292],  // Bow of Peril, Shyeed's Bow, Core's Plasmic Bow
        ],
        'brawler' => [
            'role' => ['en' => 'Fists', 'pt' => 'Punhos'],
            'armor' => 'light',
            'weapons' => ['starter' => 93428, 'advanced' => 92962, 'elite' => 97961],  // Bellion Cestus, Sobekk's Hurricane, Anais' Fists
        ],
        'kamael' => [
            'role' => ['en' => 'Ancient sword', 'pt' => 'Espada antiga'],
            'armor' => 'light',
            'weapons' => ['starter' => 93434, 'advanced' => 92967, 'elite' => 97957],  // Dismantler, Durendal, Galaxia's Ancient Sword
        ],
        'gunner' => [
            'role' => ['en' => 'Pistols', 'pt' => 'Pistolas'],
            'armor' => 'light',
            'weapons' => ['starter' => 95418, 'advanced' => 95420, 'elite' => 95422],  // Volcano, Zephyrus, Anakim's Divine Pistols
        ],
        'wizard' => [
            'role' => ['en' => 'Staff and robe', 'pt' => 'Cajado e manto'],
            'armor' => 'robe',
            'weapons' => ['starter' => 93426, 'advanced' => 92965, 'elite' => 96269],  // Staff of Evil Spirits, Daimon Crystal, Beleth's Soul Eater
        ],
        'healer' => [
            'role' => ['en' => 'Healing and support', 'pt' => 'Cura e suporte'],
            'armor' => 'robe',
            'weapons' => ['starter' => 95307, 'advanced' => 92964, 'elite' => 93295],  // Kaim Vanul's Bones, Cabrio's Hand, Queen Ant's Stone Breaker
        ],
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::CATEGORIES as $slug => [$en, $pt]) {
            StoreCategory::firstOrCreate(['slug' => $slug], ['name_en' => $en, 'name_pt' => $pt, 'sort' => $sort += 10]);
        }

        $sort = 0;

        foreach ([$this->weeklyGift(), $this->kagePass(), $this->huntPass(), ...$this->supplyKits(), ...$this->classPackages()] as $product) {
            $category = StoreCategory::where('slug', $product['category'])->firstOrFail();

            Product::withTrashed()->firstOrCreate(['slug' => $product['slug']], [
                ...collect($product)->except(['slug', 'category'])->all(),
                'store_category_id' => $category->id,
                'sort' => $sort += 10,
            ]);
        }
    }

    /**
     * One box per account per week, for a character of level 20 or higher so fresh alt accounts can't farm it. Every box
     * holds the items plus one bonus rolled by weight; a grade key becomes the scroll for the character's equipment grade.
     * The admin panel's Gift Boxes rotation replaces these contents while a box is scheduled. A mail carries at most 8.
     *
     * @return array<string, mixed>
     */
    private function weeklyGift(): array
    {
        return [
            'slug' => 'weekly-gift-box',
            'category' => 'gifts',
            'type' => Product::TYPE_WEEKLY_GIFT,
            'name_en' => 'Weekly Gift Box',
            'name_pt' => 'Caixa de Presente Semanal',
            'description_en' => 'Free, once per account every week, for a character of level 20 or higher. Every box holds the same supplies plus one bonus, rolled when you open it.',
            'description_pt' => 'Grátis, uma vez por conta toda semana, para um personagem de nível 20 ou mais. Toda caixa traz os mesmos suprimentos e mais um bônus, sorteado ao abrir.',
            'art' => 'gift',
            'items' => [
                ['item' => self::ADENA, 'count' => 250_000],
                ['item' => 1538, 'count' => 3],             // Improved Scroll of Escape
                ['item' => 1539, 'count' => 30],            // Top-grade HP Replenishing Potion
                ['item' => 1374, 'count' => 5],             // Greater Wind Walk Potion
                ['item' => self::LCOIN, 'count' => 1_000],
            ],
            'bonus' => [
                ['item' => 'enchant_armor', 'count' => 2, 'weight' => 30],
                ['item' => 'enchant_weapon', 'count' => 1, 'weight' => 25],
                ['item' => self::ADENA, 'count' => 1_000_000, 'weight' => 18],  // Adena jackpot
                ['item' => 5592, 'count' => 20, 'weight' => 10],                // Greater CP Potion
                ['item' => 3031, 'count' => 200, 'weight' => 10],               // Spirit Ore
                ['item' => 'blessed_enchant_weapon', 'count' => 1, 'weight' => 7],
            ],
            'limit_period' => Product::LIMIT_WEEK,
            'min_level' => 20,
            'mail_subject' => 'Your weekly gift box',
            'mail_text' => 'A gift from the Kage staff for this week\'s adventures. Take the items from the attachments. Good hunting!',
            'is_featured' => true,
        ];
    }

    /**
     * Modeled on the official L2PASS Season 1 free track. A new season is a new season key: every account can claim again.
     *
     * @return array<string, mixed>
     */
    private function kagePass(): array
    {
        return [
            'slug' => 'kage-pass',
            'category' => 'passes',
            'name_en' => 'Kage Pass: Season 1',
            'name_pt' => 'Kage Pass: Season 1',
            'description_en' => 'A season bundle of supplies and scrolls, once per account each season. Every new hero starts with enough L-Coin to claim it on day one.',
            'description_pt' => 'Um pacote da temporada com suprimentos e pergaminhos, uma vez por conta a cada temporada. Todo herói novo já começa com L-Coin suficiente para resgatá-lo no primeiro dia.',
            'art' => 'pass',
            'price_item' => self::LCOIN,
            'price_count' => 1_000,
            'items' => [
                ['item' => 3031, 'count' => 5_000],   // Spirit Ore
                ['item' => 90907, 'count' => 3_000],  // Soulshot Ticket
                ['item' => 90405, 'count' => 400],    // My Teleport Scroll
                ['item' => 49662, 'count' => 5],      // Stylish Color Title
                ['item' => 49674, 'count' => 10],     // XP Growth Scroll
                ['item' => 94269, 'count' => 10],     // Scroll: Boost Attack
                ['item' => 94271, 'count' => 10],     // Scroll: Boost Defense
                ['item' => 91641, 'count' => 5],      // Sayha's Blessing
            ],
            'limit_period' => Product::LIMIT_SEASON,
            'season' => self::SEASON,
            'ends_at' => CarbonImmutable::parse('2027-01-19')->endOfDay(),
            'mail_subject' => 'Your Kage Pass: Season 1',
            'mail_text' => 'Thank you for joining the Kage Pass, Season 1. Take your rewards from the attachments. Good hunting!',
            'is_featured' => true,
        ];
    }

    /**
     * Essence's own pass, unlocked inside the game. Its price, text and steps come from the server's HuntPass.ini, so only
     * its listing lives here.
     *
     * @return array<string, mixed>
     */
    private function huntPass(): array
    {
        return [
            'slug' => 'hunt-pass',
            'category' => 'passes',
            'type' => Product::TYPE_HUNT_PASS,
            'name_en' => 'Hunt Pass Premium',
            'name_pt' => 'Hunt Pass Premium',
            'description_en' => 'Essence’s own season pass, unlocked in the game.',
            'description_pt' => 'O passe de temporada do próprio Essence, desbloqueado no jogo.',
            'art' => 'hunt',
            'is_featured' => true,
        ];
    }

    /**
     * Consumables anyone can buy again and again. Every item must be stackable, because quantity multiplies the counts.
     * Each Adena price is at or above the sum of the items' list prices in the datapack, so a kit can't be bought and
     * resold to an NPC for a profit.
     *
     * @return list<array<string, mixed>>
     */
    private function supplyKits(): array
    {
        $kit = fn (string $key, string $en, string $pt, string $textEn, string $textPt, int $price, array $items): array => [
            'slug' => "supplies-{$key}",
            'category' => 'supplies',
            'name_en' => $en,
            'name_pt' => $pt,
            'description_en' => $textEn,
            'description_pt' => $textPt,
            'art' => 'supplies',
            'emblem' => $key,
            'price_item' => self::ADENA,
            'price_count' => $price,
            'items' => $items,
            'max_per_order' => 10,
            'mail_subject' => 'Your L2 Kage supply kit',
            'mail_text' => 'Thank you for your purchase. Take your supplies from the attachments. Good hunting!',
            'is_featured' => true,
        ];

        return [
            $kit(
                'traveler', 'Traveler\'s Kit', 'Kit do Viajante',
                'For the long road across Aden: scrolls to get home and potions to move faster.',
                'Para a longa estrada por Aden: pergaminhos para voltar para casa e poções para andar mais rápido.',
                20_000,  // list price 19,000
                [
                    ['item' => 736, 'count' => 10],   // Scroll of Escape
                    ['item' => 1374, 'count' => 5],   // Greater Wind Walk Potion
                ],
            ),
            $kit(
                'hunter', 'Hunter\'s Kit', 'Kit do Caçador',
                'Everything for a long hunt: healing potions, CP potions and Spirit Ore for your skills.',
                'Tudo para uma longa caçada: poções de cura, poções de CP e Minério Espiritual para suas habilidades.',
                100_000,  // list price 95,000
                [
                    ['item' => 1539, 'count' => 50],  // Top-grade HP Replenishing Potion
                    ['item' => 5592, 'count' => 20],  // Greater CP Potion
                    ['item' => 3031, 'count' => 100], // Spirit Ore
                ],
            ),
        ];
    }

    /**
     * A full set for one class group (weapon, armor, jewelry) in each tier, each once per account per season.
     *
     * @return list<array<string, mixed>>
     */
    private function classPackages(): array
    {
        $products = [];

        foreach (self::ARCHETYPES as $key => $archetype) {
            $name = config("l2j.store.class_groups.{$key}.name");

            foreach (self::TIERS as $tierKey => $tier) {
                $products[] = [
                    'slug' => "package-{$key}-{$tierKey}",
                    'category' => 'packages',
                    'name_en' => "{$name['en']} Package ({$tier['name']['en']})",
                    'name_pt' => "Pacote {$name['pt']} ({$tier['name']['pt']})",
                    'description_en' => "Weapon, armor and jewelry for the {$name['en']}: {$archetype['role']['en']}. Grade {$tier['grade']}, weapon and armor enchanted to +{$tier['enchant']}.\n\nThe items are sealed, so they can’t be traded.",
                    'description_pt' => "Arma, armadura e joias para {$name['pt']}: {$archetype['role']['pt']}. Grau {$tier['grade']}, arma e armadura encantadas em +{$tier['enchant']}.\n\nOs itens são selados, então não podem ser negociados.",
                    'art' => 'package',
                    'emblem' => $key,
                    'tier' => $tierKey,
                    'class_group' => $key,
                    'price_item' => self::LCOIN,
                    'price_count' => $tier['price'],
                    'items' => $this->gear($archetype, $tier, $tierKey),
                    'limit_period' => Product::LIMIT_SEASON,
                    'season' => self::SEASON,
                    'mail_subject' => 'Your L2 Kage class package',
                    'mail_text' => 'Thank you for your purchase. Take your gear from the attachments; a package arrives in more than one mail. Good hunting!',
                ];
            }
        }

        return $products;
    }

    /**
     * A package's gear: the weapon (and shield) and armor at the tier's enchant, then the jewelry.
     *
     * @param  array<string, mixed>  $archetype
     * @param  array<string, mixed>  $tier
     * @return list<array{item: int, count: int, enchant: int}>
     */
    private function gear(array $archetype, array $tier, string $tierKey): array
    {
        $gear = [$archetype['weapons'][$tierKey]];

        if ($archetype['shield'] ?? false) {
            $gear[] = $tier['shield'];
        }

        return [
            ...array_map(fn (int $item): array => ['item' => $item, 'count' => 1, 'enchant' => $tier['enchant']], [...$gear, ...$tier['armor'][$archetype['armor']]]),
            ...array_map(fn (int $item): array => ['item' => $item, 'count' => 1, 'enchant' => 0], $tier['jewelry']),
        ];
    }
}
