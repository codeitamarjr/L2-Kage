# L2 Kage website

The website of the L2 Kage server (Lineage II Essence: Samurai Crow on L2J Mobius): Laravel 13 + Inertia + React 19
(TypeScript) + Filament admin, served by FrankenPHP. It runs in Docker next to the game (see `../README-KAGE.md` for
the server, the Pi and the tunnel). Public at https://l2-kage.itjunior.dev; on the Mac at http://192.168.0.247:8082.

It started as a copy of the Bartz (High Five) website and was ported to the L2J Mobius database and datapack.

## Pages

| Page | File | Data |
|---|---|---|
| Landing `/` | `resources/js/pages/home.tsx` | hero video `public/assets/video/samurai-crow-hero.webm`, Happening Now (live events), rates, races |
| Lore `/lore` | `resources/js/pages/lore.tsx` + `components/lore/lore-en.tsx`, `lore-pt.tsx` | 13 chapters up to Samurai Crow, timeline, pantheon |
| Map `/map` | `resources/js/pages/map.tsx`, `components/world-map.tsx` | bosses, sieges, Olympiad, events and raid bosses on the map of Aden |
| Items `/items`, `/items/{id}` | `resources/js/pages/items/` | 25,129 Essence items: stats, drops and spoils at the server's rates, merchants, recipes |
| About `/about` | `resources/js/pages/about.tsx` | status, events, rates, statistics, rankings, castles, how to connect (clean client link) |
| Account `/account` | `resources/js/pages/account.tsx` | sign in with the game account: characters, items, order history, security |
| Store `/store`, `/store/{slug}` | `resources/js/pages/store/` | a grid by category, then a page per product with its images and purchase panel |
| Register / password | `register.tsx`, `forgot-password.tsx`, `reset-password.tsx` | open only when email is configured |
| Admin `/admin` | `app/Filament/` | Store: products, categories, orders, gift box rotation. Item Deliveries: send an item to a character in game |

## Where the data comes from

| Data | Service | Source |
|---|---|---|
| Server status, rankings, castles | `app/Services/L2j/ServerStatus.php` | game database (read-only user `web`), ports from `L2J_LOGIN_PORT` / `L2J_GAME_PORT` |
| Rates | `ServerStatus::rates()`, `ItemDatabase` | `Rates.ini`: `overrides/game/config` over the sources' `dist/game/config` (`GameConfig`) |
| Happening Now | `app/Services/L2j/EventFeed.php` | `grandboss_data` (6 epic bosses), `npc_respawns` (raid bosses), `castle`, `olympiad_data` + `Olympiad.ini`, seasonal events from `data/scripts/events/*/config.xml` |
| Map pins | `app/Services/L2j/WorldMap.php` | raid boss spawns projected on the map image |
| Characters and items | `app/Services/L2j/CharacterSheet.php` | `characters`, `items`, `clan_data` |
| Item, class, boss names | `app/Services/L2j/GameData.php` | `resources/data/gamedata.json` (generated, see below) |
| Item database | `app/Services/L2j/ItemDatabase.php` | `resources/data/itemdb.ndjson.gz`, imported on every start (`php artisan itemdb:import`) |
| Store | `app/Services/Store.php`, `Orders.php`, `StoreContents.php`, `database/seeders/StoreSeeder.php` | products and categories from the database, buying, and sending to accounts. See `../docs/STORE.md` |
| Deliveries | `l2web.game_actions` | everything the Store sells is queued here and delivered by the WebBridge script in the game server. A price can be any item the game knows |
| Game accounts | `app/Services/GameAccounts.php` | `accounts` (same database as the game); passwords are base64(sha1), like L2J |

The website's own database is `l2web` (admins, sessions, cache, the item database, the game action queue).

## Languages (English / Português)

The site is in English and Brazilian Portuguese. `app/Http/Middleware/SetLocale.php` picks the language: the EN | PT
switch in the header (`/locale/en`, `/locale/pt_BR`, remembered in the `locale` cookie), else Portuguese for browsers
that ask for it, else English. The page's language is shared as the `locale` prop.

- **Pages**: `t('Map', 'Mapa')` from `resources/js/i18n.ts`, or `{ en, pt }` objects in data files. The Lore is two
  files with the same structure (`components/lore/lore-en.tsx`, `lore-pt.tsx`); the event "Learn more" texts are
  `data/event-lore.ts` and `data/event-lore-pt.ts`.
- **Server text** (event headlines, page titles, errors, emails): `__('English text')`, translated in
  `lang/pt_BR.json`; validation messages in `lang/pt_BR/validation.php`.
- Boss, item, monster and class names stay as in the game. The Portuguese lore and headlines are written in a
  storyteller's voice.

## Game data

Built from the L2J Mobius datapack (`../L2J_Mobius_Essence_10.1_SamuraiCrow/dist/game/data`). Re-run after a datapack
update, then rebuild the image (the import runs by itself when the file changes):

```bash
node resources/data/build-gamedata.mjs   # items, classes, XP table, bosses -> resources/data/gamedata.json
node resources/data/build-itemdb.mjs     # items, NPCs, drops, shops, recipes -> resources/data/itemdb.ndjson.gz
```

## Develop

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
npm run build            # or npm run dev
php artisan test         # 39 tests
```

In Docker the site is built and started by the root `docker-compose.yml` (`docker compose up -d --build site`). The
container waits for the database, runs the migrations and the item import, then serves on port 80 (`SITE_PORT` on the
host).

## Admins and email

```bash
docker compose exec site php artisan make:filament-user   # website admin for /admin
docker compose exec site php artisan mail:test you@example.com
```

Email is off while `MAIL_MAILER=log`: registration and "forgot password" stay closed, and players create accounts by
logging in to the game. The Pi uses Amazon SES (see `../README-KAGE.md`).
