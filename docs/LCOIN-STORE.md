# L-Coin Store

How the in-game store works on L2 Kage, what it sells, and how to change it.
Written 31 Oct 2026 for Samurai Crow (protocol 542). Check anything below against the code before relying on it.

> **The client's product list can't be changed on this client.** On 31 Oct 2026 it rejected every rewritten file tried through login, as corrupt. Don't install a built file on anyone's client. Details are in [File format](#file-format).

## Change the store

1. Edit `overrides/game/data/LimitShop.xml`: what is sold, the price, limits and levels.
2. Copy the client files into a folder, by language (see [Client files](#client-files)), then run:

   ```bash
   python3 tools/lcoin-store/lcoin_store.py check --client <copies>
   ```

   Run `check` until it has no `ERROR` lines. To look at an item first: `lcoin_store.py show 72605 --client <copies>`.
3. Server: copy the XML (and any item override) to the same path under `~/l2-kage/` on the Pi. Check who is online and tell the owner, because a restart disconnects players. Then run `docker compose restart game`. Expect `LimitShopData: Loaded N items.` in the log, with no `Item template null` lines.
4. The owner opens the game and checks the store.

Prefer products the client already has cards for. `check` counts how many are new to the client: those show no name and "Unlimited".

Do not start or close the game client yourself. The owner plays on that desktop, and a closed client disconnects their character.

## Who decides what

| Decided by | What |
|---|---|
| Server, `LimitShop.xml` | What is on sale, price, real limits, level check, what is delivered. The item icon comes from the item ID |
| Client, `LCoinShopProduct_ClassicAden-<lang>.dat` | Card name, tab, ribbon, main page slot, the limit label and LV line |

- The server sends only product ID, item, price and remaining limit. **The XML `category` never reaches the client.** It is only a note for people reading the file, so keep it the same as `cards.json`.
- A product missing from the client's list still sells, but its card has no name and says "Unlimited".
- A client card with no matching server product is not shown. That is why the file keeps many retail cards that are not on sale here.
- Limits are counted **per item, not per product**. Two products selling the same item share one counter. `check` warns when their limits differ.

## What each field means

`cards.json` fields, with the file's own terms in brackets:

| Field | Values |
|---|---|
| `category` (tab) | `0` Equipment, `1` Enhancements and Consumables, `2` Misc, `3` Ancient Adena Store, `4` Adena Store, `5` Events. Retail cards also use `8` to `12`; those tabs' names are not confirmed |
| `mark` (ribbon) | `none`, `event`, `sale`, `best`, `limited`, `new`. `relay` and `max` also exist; their look is not confirmed |
| `type` (main page) | `0` not on the main page, `1` top row, `2` bottom row |

The tool works out everything else from the XML. Limits in `LimitShop.xml`, on `<production>`:

| XML attribute | Shown in the client as |
|---|---|
| `accountBuyLimit` | Account limit, forever |
| `accountDailyLimit` | Account limit, one day |
| `accountWeeklyLimit` | Account limit, one week |
| `accountMonthlyLimit` | Account limit, one month |

The server has only account limits, so character limits don't exist here. The level range is `minLevel` and `maxLevel` on `<product>`.

Listed order in `cards.json` is the order in the client's file. Whether the store displays in that order is not confirmed.

## What is on sale (added 31 Oct 2026)

Prices are in L-Coin. Each new character starts with 1,000, and the weekly gift box adds 1,000.

| Product | Item | Price | Limit | Contains |
|---|---|---:|---|---|
| 860 | Samurai's Pass Kit | 800 | 1 a month | 200 Giran Seals, 5 Magic Tablets, 16,000 Spirit Ore, 1,000 Soulshot Tickets, 130 L-Coin, 300 Collection Marks |
| 861 | Rare Pass Kit | 800 | 1 a month | As above, with 50 Dye Boosters in place of the Tablets |
| 862 | Samurai's Gift Kit: Essence | 1,500 | 1 a month | 2 doll summons (11-time), 9 Time Stones, 10,000 Spirit Ore, 1,000 Soulshot Tickets, 100 Growth Rune Fragments |
| 863 | Samurai's Gift Kit: Eva | 1,500 | 1 a month | 2 doll summons (11-time), 9 Time Stones, 10,000 Spirit Ore, 500 Soulshot Tickets, 15 Dye Boosters |
| 864 | Wolf Pass - Special Update Celebration Box | 6,000 | 1 a month | 2 doll summons (11-time), rare doll choice, dye choice, skill charging stones, 10 Greater Upgrade Stones, 100,000 Spirit Ore |
| 326012801 | Transcendent Might - Spirit Rulers' Treasure | 100 | None | Lucky pack |
| 326011501 | Venir's Supply Pack | 100 | None | Lucky pack |
| 100020223 | Samurai Growth Support Box | 300 | 1 per account | Starter items and Growth Kit (Lv. 40) |

The rest of the store is unchanged in price. Products 841-855 and 100020186-100020224 now have proper cards.

**Hunt Pass and Kage Pass are not in this list.** The Hunt Pass Premium is bought in its own window (`HuntPass.ini`), and the Kage Pass on the website (`site/config/l2j.php`). See "Passes and L-Coin" in `README-KAGE.md`.

These prices are a starting point and have not been tested against real play. Watch what players buy before raising or adding to them.

Left out on purpose:
- Character Growth Support Box (72554): contains 1,000,000,000 Adena.
- Kit: Novice's Support (100955): its level-up chain hands out items the client marks "Not available".
- Aden Pass Pack (72534): contains a dated "May Pass" coin.

## Choosing items

`check` runs these on everything for sale and inside boxes. They come from real mistakes:

- **The client's name is what players see.** The server's item file can disagree: 72605 is "November Samurai Pass Kit" on the server and "Samurai's Pass Kit" in the client. Trust `show`, not the XML comment.
- **Coupons often have no handler, and that can be correct.** The client asks for a multisell when they're double-clicked, so an item used as an ingredient in `multisell/items/` works.
- **A missing handler can also be a real bug.** Doll coupons 103233 and 103502 are defined in `RelicCouponData.xml` but upstream left out the handler, so they did nothing. Fixed in `overrides/game/data/stats/items/`. An override replaces upstream's whole 100-item file, so review these two after updating upstream.
- **Items marked "Not available" in the client are retired.** Don't sell them.
- Warnings about Soul Crystals and Augmenting Stones inside lucky packs are expected. They work through the client's own windows.

## Client files

Lineage II folders by language: `system\eu`, `system\es`, `system\pl`. The Essence files are the `_ClassicAden` ones. Copy these into `<copies>/<lang>/`:

- `LCoinShopProduct_ClassicAden-<lang>.dat`
- `ItemName_ClassicAden-<lang>.dat`
- `L2GameDataName.dat`

### File format

- The originals start with `Lineage2Ver413` and are signed with NCSOFT's private key. Anyone can read them; nobody outside NCSOFT can sign new ones.
- The client's binaries have valid NCSOFT signatures, so they're unpatched and still expect that key. [L2ClientDat](https://github.com/MobiusDevelopment/L2ClientDat) saves with a different key (`v413_encdec`), which needs a patched client. Do not patch the binaries.
- **A plain file does not work.** The client reads a plain-text `.ini` with no `Lineage2Ver` header, but rejected a product list in that form: it reported corrupt files and closed about 27 seconds after starting. It worked again once the signed original was back. The format is the cause: on a separate copy, the original list with one card name changed was also rejected, and the same copy logged in normally with the original file.
- `Core.dll` is packed, so reading its code doesn't show which formats it accepts. Only a test launch can.
- **Older formats do not work either.** Going through login on a separate copy, with one card name changed each time: plain, `v211` (Blowfish) and `v111` (XOR) were all rejected as corrupt, and that copy logged in normally with the original file. `v120`, `v121`, `v212` and `v413_encdec` were not tried through login.
- **Reaching the login screen proves nothing.** All of those files, including the rejected ones, stayed open at the login screen for 40 seconds. The error comes later, so a test has to go through login.
- The one route left is patching the client's signed binaries to change the key. This project doesn't do that.
- Public documentation doesn't change this, as far as a search on 31 Oct 2026 could tell. The [L2JMobius guide](https://l2jmobius.org/forum/index.php?topic=11366.0) and its replies, read from the owner's screenshot, say to edit both sides but not how to get a saved file accepted. One reply reports a working edited store on a different client (3.5 Tales Untold) without saying how that client was prepared. Older RaGEZone threads link corrupt-file errors to an unpatched system folder.
- L2ClientDat has no Samurai Crow layout. Its `ShieldOfKingdom` layout for `lcoinshopproduct` fits exactly: all three languages parse and rebuild byte for byte, which `check` confirms each time. A warning there means a client update changed the layout.

The `.dat` files are NCSOFT's, so they stay out of this repository (see "Connect a game client" in `README-KAGE.md`). Every client, the owner's included, has the original list: everything still sells, but cards new to the client have no name and show "Unlimited".

## Undo

- Server: `~/l2-kage/data/backups/LimitShop.xml.before-store-20261031` on the Pi, or git history. Copy it back over `overrides/game/data/LimitShop.xml` and restart `game`.
- Client: close the game and run `client-mods-backup\restore-store.bat` in the client folder. It's also the fix if a client reports corrupt files after a test.

## Not confirmed yet

- Why the tabs show no text. Screenshots from before and after the server change both show this, with the original client files, so the server change didn't cause it.
- Buying and opening each new product in game.

Confirmed on 31 Oct 2026, with the original client files: the store opens with the new server list, and Transcendent Might - Spirit Rulers' Treasure and Venir's Supply Pack show their names, the 100 L-Coin price and the EVENT ribbon in the top row. Their card placement comes from the client's retail list, so `cards.json` has no effect on them.
