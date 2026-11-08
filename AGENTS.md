# L2 Kage: AI handoff

Lineage II Essence private server (L2J Mobius, Samurai Crow, protocol 542) with a Laravel website.
This is a short current-state note. Details live in `README-KAGE.md` (server) and `site/README.md` (website).
`site/AGENTS.md` holds the Laravel rules for work inside `site/`.

Last updated: 31 Oct 2026. Check anything below against the code before relying on it.

## Layout

| Path | Purpose |
|---|---|
| `L2J_Mobius_Essence_10.1_SamuraiCrow/` | Upstream server. Do not edit; use `overrides/` |
| `overrides/game/` | Files copied over the game server each time it starts |
| `site/` | Laravel 13, Inertia, React 19, Filament 5 |
| `LISTING-KIT.md` | Text and site list for server toplists |
| `docs/` | Guides for recurring work: writing news, the website Store, the L-Coin Store |
| `tools/lcoin-store/` | Builds and checks the client's L-Coin Store list |

Production is the Raspberry Pi. The Mac runs a second copy of the stack. Website: https://l2-kage.itjunior.dev

## Current state

**Rates** (`overrides/game/config/Rates.ini`, a full copy of the default file with these values changed):

| Setting | Value |
|---|---|
| XP and SP, solo and party | x7 |
| Adena (`DropAmountMultiplierByItemId = 57,4`) | x4 |
| Drop and Spoil chance | x3 |
| Raid, herbs, quest rewards | x1 |
| Premium (`Custom/PremiumSystem.ini`) | Doubles XP, SP, Adena; drop and spoil amount x2 |

The website reads these files through `ServerStatus::rates()`, so it needs no change when rates change.
Rates moved from x5/x3/x2 to these values on 4 Nov 2026 after comparing current Essence server listings and choosing a modest progression increase.
That count is of listings, not players: l2list publishes no player numbers.

**Home page**
- Weekly Gift Box corner banner: `GiftBanner` in `site/resources/js/pages/home.tsx`, styled by `.gift-banner` in `site/resources/css/app.css`. Closing it is stored in `localStorage` under `gift-banner-closed`. Hidden below 768px.
- l2list button: in the footer of `site/resources/js/layouts/site-layout.tsx`. The image is a local copy, `site/public/assets/l2list-button.png`.

**Halloween event: Pumpkin Harvest** (31 Oct to 14 Nov 2026; the config's end date, `15 11 2026`, closes it at 00:00 UTC on the 15th)

Upstream's `SquashEvent`, reactivated through `overrides/game/data/scripts/events/SquashEvent/`:

| File | Change from upstream |
|---|---|
| `config.xml` | New dates and join and end messages |
| `SquashEvent.java` | Large pumpkins can drop the Halloween Kit (item 72074): chance 60 if ripe, 30 if withered. The script halves these rolls |
| `31860.htm` | Halloween text; the NPC is named Puss the Cat |
| `overrides/game/data/multisell/90318600.xml` | Now sells squash seeds. Upstream sold watermelon seeds, which this event can't use |

- Play: monsters drop Nectar (50% per kill). Puss the Cat, in 13 towns, trades it for seeds (10 or 20 Nectar) or a chrono instrument (100 to 300). Nectar grows a planted pumpkin. Large pumpkins can only be broken with a chrono instrument.
- Website: `SEASONAL` in `site/app/Services/L2j/EventFeed.php`, `lang/pt_BR.json`, `resources/js/data/event-lore.ts` and `event-lore-pt.ts`. `EventFeed` now reads `/l2j/events-overrides` over `/l2j/events`; this mount is in `docker-compose.yml`.
- Map: the event's 13 markers are pumpkins (`PUMPKIN_EVENT` in `site/resources/js/components/world-map.tsx`, `.is-pumpkin` in `app.css`), and each marks a real Puss the Cat. Player-planted pumpkins aren't shown, because the website can't see them.
- Home page: its banner is the seeded post `halloween-pumpkin-harvest` (`site/database/seeders/HalloweenNewsSeeder.php`), which ends by itself on 14 Nov.
- Status: loads on both servers with no errors in the logs. Not yet played through in the game client, so the NPC spawns, trades, planting and drops are unconfirmed.

**Winter event: Santa's Visit** (12 Dec 2026 to 6 Jan 2027; the config's end date, `07 01 2027`, closes it at 00:00 UTC on the 7th)

Upstream's Essence event `HolidayOutOfSchedule`, reactivated through `overrides/game/data/scripts/events/HolidayOutOfSchedule/`:

| File | Change from upstream |
|---|---|
| `config.xml` | New dates and join and end messages |
| `HolidayOutOfSchedule.java` | Santa visits at 15:00 and 22:00 UTC (12:00 and 19:00 in Brasília) instead of 11:00 and 21:00 |
| `*.htm` | Rewritten Rudolph and Santa text, with the new times |
| `overrides/game/data/stats/skills/48500-48599.xml` | Holiday Blessing (48596), the Christmas tree buff, had no effects upstream. Now +100 HP and MP, +10% XP and SP, 30 min |

- Play: Humanized Rudolph in 13 towns teleports players to Santa, who spends one hour in a random hunting ground at each visit. Santa gives one gift per account per day (the day changes at 00:00 UTC): L-Coin Pouch, Giant's Shining Relic Pouch, New Year Gift Box, Dye Powder, 10 each of Scroll: Boost Attack and Boost Defense. Trees in Aden, Giran and beside Santa cast the Holiday Blessing on players within 300.
- Item 97147 is Dye Powder. Capsule comments elsewhere in the datapack call it Hidden Power Booster; they're wrong.
- Website: `SEASONAL` in `EventFeed.php`, `lang/pt_BR.json`, `event-lore.ts` and `event-lore-pt.ts`. Map markers use the default event icon. News post: `ChristmasNewsSeeder` (slug `santa-visits-aden`, published 12 Dec, banner look `winter` until 6 Jan).
- Times: English text gives UTC only, because players may be in Europe. Portuguese gives Brasília time. The About page's Server Time card shows the server clock (`server.timezone` from `ServerStatus`) beside the visitor's local time.
- Status: deployed to the Pi on 3 Nov 2026. The game log shows `Event Holiday out of Schedule will be started at Sat Dec 12`, with no errors from the script or skill file. The news post is seeded there and publishes by itself on 12 Dec. Not on the Mac yet, and not played in the client.

**News**
- **Writing a post: read [docs/NEWS-WRITING-GUIDE.md](docs/NEWS-WRITING-GUIDE.md) first.** It covers voice, lore, fact-checking, both languages and publishing.
- Posts are in `news_posts` (`site/app/Models/NewsPost.php`), edited in the admin panel under News (`site/app/Filament/Resources/NewsPosts/`). Each has English and Portuguese text in Markdown. No publish date means draft; a future date schedules it.
- Pages: `/news` and `/news/{slug}` (`NewsController`, `resources/js/pages/news/`). Shared pieces are in `resources/js/components/news.tsx`.
- Home page: the section after Happening Now shows the 3 latest posts, hidden when there are none. `FeaturedBanner` shows the latest post marked as featured until its "Until" date.
- Uploaded images are in `data/site-uploads` on each host, mounted at `storage/app/public`. They aren't in git, so back them up with the database. The hosts have separate databases and uploads, so a post made in the Mac's admin panel doesn't reach the Pi.

**Geodata**
- On the Mac: on, 224 regions in `data/geodata`.
- On the Pi: off. Tried on 31 Oct 2026 with `-Xmx6g`; rolled back after about 7 minutes because swap fell to about 300 MB free of 8 GB and Bartz was pushed out of memory (3 GB down to about 0.5 GB). Geodata had loaded, but startup had not finished. The Java heap is back to `-Xms1g -Xmx3g`.
- The files are parked on the Pi at `~/l2-kage/data/geodata-off` (and the zip is in `data/`). The earlier settings are in `.env.before-geodata`. Do not move the files back while Bartz runs on the same Pi: geodata needs about 5.5 GB for Kage, and 7.75 GB of memory can't hold that plus Bartz.
- When watching memory on the Pi, watch swap free too. ZGC's heap is shared memory, so "available" stayed high while swap ran out.

**L-Coin Store**
- **Changing it: read [docs/LCOIN-STORE.md](docs/LCOIN-STORE.md) first.** It has the steps, field meanings, the catalogue and the mistakes to avoid.
- `overrides/game/data/LimitShop.xml` decides what sells, prices and limits. The client's `LCoinShopProduct_ClassicAden-<lang>.dat` decides card names, tabs, ribbons and limit labels. `tools/lcoin-store/` checks items and can build the second from the first plus `cards.json`.
- Status: 8 products added on the Pi on 31 Oct 2026. The client rejects rewritten `.dat` files as corrupt (plain, `v211` and `v111` tested through login), so originals are back and no client has new cards. Don't retry this without patching, which this project doesn't do. Products 860-864 and others new to the client show no name and "Unlimited", but still sell with their real limits. Products the client already has cards for display correctly in game, confirmed by the owner's screenshot. Buying in game is not confirmed.

**Store (website)**
- **Changing it: read [docs/STORE.md](docs/STORE.md) first.** It was the Treasury until 1 Nov 2026.
- Categories, products and orders are in the database (`store_categories`, `products`, `orders`), edited in the admin panel under Store. `database/seeders/StoreSeeder.php` holds the 45 starting products, and a migration runs it, so a deploy fills the Store and copies past claims into `orders`.
- Pages: `/store` and `/store/{slug}` (`StoreController`, `app/Services/Store.php`, `resources/js/pages/store/`). `/treasury` still shows the page rather than redirecting, because `/store` used to redirect permanently to it and browsers may remember that.
- Admins can send a product to accounts. It waits in the Orders tab until the player chooses a character.

**Toplists**

| Site | Status |
|---|---|
| l2list | The owner chose to submit it themselves; submission not confirmed. Free listing requires the button on the home page. A moderator could reject the footer position, because their own code uses a fixed corner. If so, move it back to a corner |
| Others in `LISTING-KIT.md` | Not started. Each needs the owner to create an account or get past a check first |

## Apply a change

- **Website:** rebuild the `site` image and run `docker compose up -d --no-deps --build site` on the Mac. For the Pi, run `./build-pi.sh`, `rsync -a dist/ junior@server.local:l2-kage/` (never `--delete`), then load the archive and recreate `login game site` with `docker compose up -d --no-deps`. The website reads rates dynamically, but changing `Rates.ini` also requires recreating `game` and refreshing the item database through the site image.
- **Game config or scripts:** for a standalone override, copy the file to the same path under `~/l2-kage/` on the Pi, then `docker compose up -d --no-deps game` to apply it. For a full bundle, use `build-pi.sh` and recreate `login game site` as documented in `README-KAGE.md`. A game recreation disconnects players, so check who is online first and tell the owner.
- **L-Coin Store or other client data:** follow `docs/LCOIN-STORE.md`. A server restart alone doesn't change what the client shows, and the client's files can't be changed.
- **Java scripts:** compile-check first. A failed script can stop features such as website deliveries. Copy the file into the game container, then run `javac -proc:none -nowarn -d /tmp/x -cp /opt/l2j/libs/GameServer.jar <file>`.
- **Verify rates:** compare the same `grep -E '^(RateXp|RateSp|RatePartyXp|RatePartySp|DeathDropChanceMultiplier|SpoilDropChanceMultiplier|DropAmountMultiplierByItemId)' /opt/l2j/game/config/Rates.ini` output in both `game` and `site` (`/l2j/config/Rates.ini`), then run `curl -fsSL https://l2-kage.itjunior.dev/about | grep -o '"rates"[^}]*}' | head -1`. Do not call a deployment complete until the public payload matches.

## Rules for AI agents

- This repository is public. Never commit IP addresses, usernames, `.env` values or other access details.
- Commit or push only when the owner asks. The default branch is `master`.
- The repository uses a cone-mode sparse checkout (`L2J_Mobius_Essence_10.1_SamuraiCrow`, `docker`, `overrides`, `site`). Use `git add --sparse`. After switching branches, confirm those folders are still there.
- `~/l2-server` on the Pi is a different server (Bartz). Do not read or change its data.
- All user-facing website text needs English and Portuguese: `t(en, pt)` in React, `__()` with `lang/pt_BR.json` in PHP.
- Cloudflare caches assets for 7 days. When replacing a file, use a new name or bump `?v=N`, and do not request the new URL until the new page is deployed.
- Do not create third-party accounts or solve CAPTCHAs on toplists. The owner does those. Only tick form options that are true of the server.
- Keep `LISTING-KIT.md` in line with the live rates, and update this file when the state above changes.
- The game client runs on the owner's desktop, and they may be playing. Don't start or close it, and don't take screenshots of it: ask them to open it and share a screenshot. Before changing its files, check that `L2` isn't running.
- Client `.dat` files belong to NCSOFT. Never commit or distribute them, and don't patch the client's signed binaries.
- Never install an untested client file on the owner's only working client. Test on a separate copy, with their agreement, and treat your reasoning as unproven until the game has opened it through login: the login screen appears even with a rejected file.
- Before selling or recommending an item, run `lcoin_store.py show <id>`. A name in the XML, or an item with no handler, doesn't tell you what the item does.
- Another session may be working on the same hosts. Before a restart, read the recent game log and `git status`.
