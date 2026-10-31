<?php

/*
 * The L2J Mobius server this website belongs to (Lineage II Essence, Samurai Crow). In Docker these come from
 * docker-compose.yml / .env.
 */
return [
    'server_name' => env('L2J_SERVER_NAME', 'Kage'),

    'chronicle' => 'Essence: Samurai Crow',

    // Ports players connect to: 2106 / 7777, or others when a second server shares the host (shown on the About
    // page; the game port is also where the online check looks, the login server always listens on 2106 inside).
    'login_port' => (int) env('L2J_LOGIN_PORT', 2106),
    'game_port' => (int) env('L2J_GAME_PORT', 7777),

    // Hosts of the login and game servers, used for the online/offline checks.
    'login_host' => env('L2J_LOGIN_HOST', 'login'),
    'game_host' => env('L2J_GAME_HOST', 'game'),

    // The address players connect to (shown on the About page).
    'public_host' => env('L2J_PUBLIC_HOST'),

    // overrides/game/config mounted read-only: the settings changed for this server (Rates.ini, ...).
    'config_dir' => env('L2J_CONFIG_DIR', '/config'),

    // The server's default config (dist/game/config of the sources), used for every key the overrides don't set.
    'config_defaults_dir' => env('L2J_CONFIG_DEFAULTS_DIR', '/config-defaults'),

    // The datapack's seasonal events (data/scripts/events) mounted read-only: which are running and where.
    'events_dir' => env('L2J_EVENTS_DIR', '/l2j/events'),
    'events_overrides_dir' => env('L2J_EVENTS_OVERRIDES_DIR', '/l2j/events-overrides'),

    // data/geodata mounted read-only, only to count the loaded regions.
    'geodata_dir' => env('L2J_GEODATA_DIR', '/geodata'),

    // Item names/types/grades, class names, XP table and boss names, generated from the datapack.
    'gamedata' => resource_path('data/gamedata.json'),

    // How long live server data (status, events) is cached.
    'cache_seconds' => (int) env('L2J_CACHE_SECONDS', 15),

    /*
     * Game accounts created on the website (register, confirm by email, reset password). Names follow what the
     * game client accepts: up to 14 letters or digits (the login server lowercases them); passwords up to 16.
     */
    'accounts' => [
        'max_per_email' => (int) env('ACCOUNTS_MAX_PER_EMAIL', 5),
        // Mirrors the login server's AutoCreateAccounts (docker-compose passes the same AUTO_CREATE_ACCOUNTS).
        'auto_create' => filter_var(env('AUTO_CREATE_ACCOUNTS', true), FILTER_VALIDATE_BOOL),
        'register_link_hours' => 24,
        'reset_link_minutes' => 60,
    ],

    /*
     * Weekly gift box (player area). One box per game account per week, sent by in-game mail to a character of the
     * player's choice (min_level or higher, so fresh alt accounts can't farm it). Each box holds every "guaranteed"
     * item plus one "bonus" rolled by weight. A bonus item can be a fixed item id or a grade key, which becomes the
     * scroll for the character's equipment grade (see "grades"). Item ids are Essence's (resources/data/gamedata.json).
     * A mail carries at most 8 attachments, so keep guaranteed + 1 bonus <= 8.
     */
    'gift_box' => [
        'enabled' => (bool) env('GIFT_BOX_ENABLED', true),
        'min_level' => (int) env('GIFT_BOX_MIN_LEVEL', 20),
        'mail_subject' => 'Your weekly gift box',
        'mail_text' => 'A gift from the Kage staff for this week\'s adventures. Take the items from the attachments. Good hunting!',

        'guaranteed' => [
            ['item' => 57, 'count' => 250_000],   // Adena
            ['item' => 1538, 'count' => 3],       // Improved Scroll of Escape
            ['item' => 1539, 'count' => 30],      // Top-grade HP Replenishing Potion
            ['item' => 1374, 'count' => 5],       // Greater Wind Walk Potion
            ['item' => 91663, 'count' => 1_000],  // L-Coin
        ],

        'bonus' => [
            ['item' => 'enchant_armor', 'count' => 2, 'weight' => 30],
            ['item' => 'enchant_weapon', 'count' => 1, 'weight' => 25],
            ['item' => 57, 'count' => 1_000_000, 'weight' => 18],  // Adena jackpot
            ['item' => 5592, 'count' => 20, 'weight' => 10],       // Greater CP Potion
            ['item' => 3031, 'count' => 200, 'weight' => 10],      // Spirit Ore
            ['item' => 'blessed_enchant_weapon', 'count' => 1, 'weight' => 7],
        ],

        // Equipment grade by character level, and that grade's scrolls (the "blessed" one is Essence's Protecting Scroll).
        'grades' => [
            'D' => ['from' => 20, 'enchant_weapon' => 955, 'enchant_armor' => 956, 'blessed_enchant_weapon' => 6575],
            'C' => ['from' => 40, 'enchant_weapon' => 951, 'enchant_armor' => 952, 'blessed_enchant_weapon' => 6573],
            'B' => ['from' => 52, 'enchant_weapon' => 947, 'enchant_armor' => 948, 'blessed_enchant_weapon' => 6571],
            'A' => ['from' => 61, 'enchant_weapon' => 729, 'enchant_armor' => 730, 'blessed_enchant_weapon' => 6569],
            'S' => ['from' => 76, 'enchant_weapon' => 959, 'enchant_armor' => 960, 'blessed_enchant_weapon' => 6577],
        ],
    ],

    // L-Coin every new character starts with, shown on the Passes page. It must match the game server's
    // overrides/game/data/stats/players/initialEquipment.xml (item 91663).
    'welcome_lcoin' => 1_000,

    /*
     * Kage Pass (player area): once per game account per season, a player spends L-Coin from one of their characters'
     * bags and that character receives the season's rewards by in-game mail. The game server takes the L-Coin and
     * sends the mail in one step (the WebBridge "buy_pass" action), so a character without enough L-Coin gets nothing
     * and the player can try again. Modeled on the official L2PASS Season 1 free track. A mail carries at most 8
     * items. A new season is a new "season" key: every account can claim again.
     */
    'pass' => [
        'enabled' => (bool) env('KAGE_PASS_ENABLED', true),
        'season' => 'season-1',
        'name' => 'Kage Pass: Season 1',
        // The last day it can be claimed (app time zone).
        'ends' => '2027-01-19',
        'price' => ['item' => 91663, 'count' => 1_000],  // L-Coin
        'mail_subject' => 'Your Kage Pass: Season 1',
        'mail_text' => 'Thank you for joining the Kage Pass, Season 1. Take your rewards from the attachments. Good hunting!',

        'rewards' => [
            ['item' => 3031, 'count' => 5_000],   // Spirit Ore
            ['item' => 90907, 'count' => 3_000],  // Soulshot Ticket
            ['item' => 90405, 'count' => 400],    // My Teleport Scroll
            ['item' => 49662, 'count' => 5],      // Stylish Color Title
            ['item' => 49674, 'count' => 10],     // XP Growth Scroll
            ['item' => 94269, 'count' => 10],     // Scroll: Boost Attack
            ['item' => 94271, 'count' => 10],     // Scroll: Boost Defense
            ['item' => 91641, 'count' => 5],      // Sayha's Blessing
        ],
    ],

    /*
     * Supply kits (Treasury): consumables anyone can buy again and again, priced in any item the game knows (Adena
     * here). Paid and mailed like the Kage Pass (the WebBridge "buy_pass" action). Every item must be stackable, because
     * quantity multiplies the counts. Keep each price at or above the sum of the items' list prices in the datapack, so
     * a kit can't be bought and resold to an NPC for a profit.
     */
    'supplies' => [
        'enabled' => (bool) env('SUPPLY_KITS_ENABLED', true),
        'max_per_order' => 10,
        'mail_subject' => 'Your L2 Kage supply kit',
        'mail_text' => 'Thank you for your purchase. Take your supplies from the attachments. Good hunting!',

        'kits' => [
            'traveler' => [
                'name' => ['en' => 'Traveler\'s Kit', 'pt' => 'Kit do Viajante'],
                'description' => [
                    'en' => 'For the long road across Aden: scrolls to get home and potions to move faster.',
                    'pt' => 'Para a longa estrada por Aden: pergaminhos para voltar para casa e poções para andar mais rápido.',
                ],
                'price' => ['item' => 57, 'count' => 20_000],  // Adena; list price 19,000
                'items' => [
                    ['item' => 736, 'count' => 10],   // Scroll of Escape
                    ['item' => 1374, 'count' => 5],   // Greater Wind Walk Potion
                ],
            ],
            'hunter' => [
                'name' => ['en' => 'Hunter\'s Kit', 'pt' => 'Kit do Caçador'],
                'description' => [
                    'en' => 'Everything for a long hunt: healing potions, CP potions and Spirit Ore for your skills.',
                    'pt' => 'Tudo para uma longa caçada: poções de cura, poções de CP e Minério Espiritual para suas habilidades.',
                ],
                'price' => ['item' => 57, 'count' => 100_000],  // Adena; list price 95,000
                'items' => [
                    ['item' => 1539, 'count' => 50],  // Top-grade HP Replenishing Potion
                    ['item' => 5592, 'count' => 20],  // Greater CP Potion
                    ['item' => 3031, 'count' => 100], // Spirit Ore
                ],
            ],
        ],
    ],

    /*
     * Class packages (player area, shown on the landing page and /packages): a full set for one archetype (weapon,
     * armor, jewelry) in three tiers, bought with L-Coin from a character's bag like the Kage Pass (the WebBridge
     * "buy_pass" action, which splits more than 8 items into several mails). Each package (tier + archetype) once per
     * game account per season. Items are the Sealed versions (they can't be traded), picked from the datapack's own
     * B-grade, +6 A-grade and boss weapon packs. "classes" are the class ids (the whole class tree) the site suggests
     * the archetype for.
     */
    'packages' => [
        'enabled' => (bool) env('CLASS_PACKAGES_ENABLED', true),
        'season' => 'season-1',
        'price_item' => 91663,  // L-Coin
        'mail_subject' => 'Your L2 Kage class package',
        'mail_text' => 'Thank you for your purchase. Take your gear from the attachments; a package arrives in more than one mail. Good hunting!',

        'tiers' => [
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
        ],

        // weapons: tier => item id (Sealed); armor: heavy, light or robe.
        'archetypes' => [
            'knight' => [
                'name' => ['en' => 'Knight', 'pt' => 'Cavaleiro'],
                'role' => ['en' => 'Sword and shield', 'pt' => 'Espada e escudo'],
                'armor' => 'heavy',
                'shield' => true,
                'weapons' => ['starter' => 93422, 'advanced' => 92955, 'elite' => 93291],  // Damascus Sword, Sirra's Blade, Zaken's Blood Sword
                'classes' => [0, 4, 5, 6, 18, 19, 20, 21, 31, 32, 33, 90, 91, 99, 100, 106, ...range(196, 207), ...range(240, 243)],
            ],
            'samurai' => [
                'name' => ['en' => 'Samurai', 'pt' => 'Samurai'],
                'role' => ['en' => 'One-handed sword', 'pt' => 'Espada de uma mão'],
                'armor' => 'heavy',
                'weapons' => ['starter' => 93422, 'advanced' => 92955, 'elite' => 93291],
                'classes' => range(260, 263),
            ],
            'duelist' => [
                'name' => ['en' => 'Duelist', 'pt' => 'Duelista'],
                'role' => ['en' => 'Dual swords', 'pt' => 'Espadas duplas'],
                'armor' => 'heavy',
                'weapons' => ['starter' => 93430, 'advanced' => 92966, 'elite' => 97963],  // Samurai Long Sword*Samurai Long Sword, Tallum Blade*Damascus Sword, Juriel's Dual Swords
                'classes' => [2, 34, 88, 107],
            ],
            'titan' => [
                'name' => ['en' => 'Titan', 'pt' => 'Titã'],
                'role' => ['en' => 'Two-handed sword', 'pt' => 'Espada de duas mãos'],
                'armor' => 'heavy',
                'weapons' => ['starter' => 93431, 'advanced' => 92956, 'elite' => 93293],  // Guardian's Sword, Sword of Ipos, Orfen's Venom Sword
                'classes' => [44, 45, 46, 113],
            ],
            'lancer' => [
                'name' => ['en' => 'Lancer', 'pt' => 'Lanceiro'],
                'role' => ['en' => 'Polearm', 'pt' => 'Lança'],
                'armor' => 'heavy',
                'weapons' => ['starter' => 93423, 'advanced' => 92960, 'elite' => 97302],  // Lance, Tiphon's Spear, Gorde's Flame Spear
                'classes' => [1, 3, 89, ...range(217, 220)],
            ],
            'artisan' => [
                'name' => ['en' => 'Dwarf', 'pt' => 'Anão'],
                'role' => ['en' => 'Blunt weapon', 'pt' => 'Arma de impacto'],
                'armor' => 'heavy',
                'weapons' => ['starter' => 93425, 'advanced' => 92957, 'elite' => 93295],  // Art of Battle Axe, Barakiel's Axe, Queen Ant's Stone Breaker
                'classes' => [53, 54, 55, 56, 57, 117, 118],
            ],
            'rogue' => [
                'name' => ['en' => 'Rogue', 'pt' => 'Ladino'],
                'role' => ['en' => 'Dagger', 'pt' => 'Adaga'],
                'armor' => 'light',
                'weapons' => ['starter' => 93427, 'advanced' => 92959, 'elite' => 93294],  // Demon's Dagger, Naga's Storm, Baium's Thunder Breaker
                'classes' => [7, 8, 22, 23, 35, 36, 93, 101, 108, ...range(221, 228), ...range(251, 254)],
            ],
            'archer' => [
                'name' => ['en' => 'Archer', 'pt' => 'Arqueiro'],
                'role' => ['en' => 'Bow', 'pt' => 'Arco'],
                'armor' => 'light',
                'weapons' => ['starter' => 93429, 'advanced' => 92961, 'elite' => 93292],  // Bow of Peril, Shyeed's Bow, Core's Plasmic Bow
                'classes' => [9, 24, 37, 92, 102, 109, 130, 134],
            ],
            'brawler' => [
                'name' => ['en' => 'Brawler', 'pt' => 'Lutador'],
                'role' => ['en' => 'Fists', 'pt' => 'Punhos'],
                'armor' => 'light',
                'weapons' => ['starter' => 93428, 'advanced' => 92962, 'elite' => 97961],  // Bellion Cestus, Sobekk's Hurricane, Anais' Fists
                'classes' => [47, 48, 114, ...range(247, 250)],
            ],
            'kamael' => [
                'name' => ['en' => 'Kamael', 'pt' => 'Kamael'],
                'role' => ['en' => 'Ancient sword', 'pt' => 'Espada antiga'],
                'armor' => 'light',
                'weapons' => ['starter' => 93434, 'advanced' => 92967, 'elite' => 97957],  // Dismantler, Durendal, Galaxia's Ancient Sword
                'classes' => [125, 126, 127, 131, ...range(192, 195)],
            ],
            'gunner' => [
                'name' => ['en' => 'Gunner', 'pt' => 'Atirador'],
                'role' => ['en' => 'Pistols', 'pt' => 'Pistolas'],
                'armor' => 'light',
                'weapons' => ['starter' => 95418, 'advanced' => 95420, 'elite' => 95422],  // Volcano, Zephyrus, Anakim's Divine Pistols
                'classes' => range(208, 211),
            ],
            'wizard' => [
                'name' => ['en' => 'Wizard', 'pt' => 'Mago'],
                'role' => ['en' => 'Staff and robe', 'pt' => 'Cajado e manto'],
                'armor' => 'robe',
                'weapons' => ['starter' => 93426, 'advanced' => 92965, 'elite' => 96269],  // Staff of Evil Spirits, Daimon Crystal, Beleth's Soul Eater
                'classes' => [10, 11, 12, 13, 14, 25, 26, 27, 28, 38, 39, 40, 41, 94, 95, 96, 103, 104, 110, 111, ...range(236, 239)],
            ],
            'healer' => [
                'name' => ['en' => 'Healer', 'pt' => 'Curandeiro'],
                'role' => ['en' => 'Healing and support', 'pt' => 'Cura e suporte'],
                'armor' => 'robe',
                'weapons' => ['starter' => 95307, 'advanced' => 92964, 'elite' => 93295],  // Kaim Vanul's Bones, Cabrio's Hand, Queen Ant's Stone Breaker
                'classes' => [15, 16, 17, 29, 30, 42, 43, 49, 50, 51, 52, 97, 98, 105, 112, 115, 116],
            ],
        ],
    ],
];
