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

    // L-Coin every new character starts with, shown in the Store's L-Coin tab. It must match the game server's
    // overrides/game/data/stats/players/initialEquipment.xml (item 91663).
    'welcome_lcoin' => 1_000,

    /*
     * The Store's fixed data. Its categories and products are in the database, edited in the admin panel (Store) and
     * first filled by database/seeders/StoreSeeder.php.
     */
    'store' => [
        // Equipment grade by character level, and that grade's scrolls (the "blessed" one is Essence's Protecting Scroll).
        // A product's item can be one of these keys instead of an id: the receiving character gets their grade's scroll.
        'grades' => [
            'D' => ['from' => 20, 'enchant_weapon' => 955, 'enchant_armor' => 956, 'blessed_enchant_weapon' => 6575],
            'C' => ['from' => 40, 'enchant_weapon' => 951, 'enchant_armor' => 952, 'blessed_enchant_weapon' => 6573],
            'B' => ['from' => 52, 'enchant_weapon' => 947, 'enchant_armor' => 948, 'blessed_enchant_weapon' => 6571],
            'A' => ['from' => 61, 'enchant_weapon' => 729, 'enchant_armor' => 730, 'blessed_enchant_weapon' => 6569],
            'S' => ['from' => 76, 'enchant_weapon' => 959, 'enchant_armor' => 960, 'blessed_enchant_weapon' => 6577],
        ],

        // A product of a class group lists the characters of these class ids (the whole class tree) first, and a signed-in
        // player's main Store page lists the group of their highest level character early.
        'class_groups' => [
            'knight' => ['name' => ['en' => 'Knight', 'pt' => 'Cavaleiro'], 'classes' => [0, 4, 5, 6, 18, 19, 20, 21, 31, 32, 33, 90, 91, 99, 100, 106, ...range(196, 207), ...range(240, 243)]],
            'samurai' => ['name' => ['en' => 'Samurai', 'pt' => 'Samurai'], 'classes' => range(260, 263)],
            'duelist' => ['name' => ['en' => 'Duelist', 'pt' => 'Duelista'], 'classes' => [2, 34, 88, 107]],
            'titan' => ['name' => ['en' => 'Titan', 'pt' => 'Titã'], 'classes' => [44, 45, 46, 113]],
            'lancer' => ['name' => ['en' => 'Lancer', 'pt' => 'Lanceiro'], 'classes' => [1, 3, 89, ...range(217, 220)]],
            'artisan' => ['name' => ['en' => 'Dwarf', 'pt' => 'Anão'], 'classes' => [53, 54, 55, 56, 57, 117, 118]],
            'rogue' => ['name' => ['en' => 'Rogue', 'pt' => 'Ladino'], 'classes' => [7, 8, 22, 23, 35, 36, 93, 101, 108, ...range(221, 228), ...range(251, 254)]],
            'archer' => ['name' => ['en' => 'Archer', 'pt' => 'Arqueiro'], 'classes' => [9, 24, 37, 92, 102, 109, 130, 134]],
            'brawler' => ['name' => ['en' => 'Brawler', 'pt' => 'Lutador'], 'classes' => [47, 48, 114, ...range(247, 250)]],
            'kamael' => ['name' => ['en' => 'Kamael', 'pt' => 'Kamael'], 'classes' => [125, 126, 127, 131, ...range(192, 195)]],
            'gunner' => ['name' => ['en' => 'Gunner', 'pt' => 'Atirador'], 'classes' => range(208, 211)],
            'wizard' => ['name' => ['en' => 'Wizard', 'pt' => 'Mago'], 'classes' => [10, 11, 12, 13, 14, 25, 26, 27, 28, 38, 39, 40, 41, 94, 95, 96, 103, 104, 110, 111, ...range(236, 239)]],
            'healer' => ['name' => ['en' => 'Healer', 'pt' => 'Curandeiro'], 'classes' => [15, 16, 17, 29, 30, 42, 43, 49, 50, 51, 52, 97, 98, 105, 112, 115, 116]],
        ],
    ],
];
