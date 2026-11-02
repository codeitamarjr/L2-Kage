# The Store

The website's store at `/store`. Everything in it lives in the database and is edited in the admin panel under **Store**.
This is not the L-Coin Store inside the game, which is `docs/LCOIN-STORE.md`.

| Admin page | What it does |
|---|---|
| Products | Add, edit, copy, hide or delete products, and send one to accounts |
| Categories | The buttons in the Store's banner. An empty one stays hidden |
| Orders | Everything bought or sent. What was sent can be cancelled until the player chooses a character |
| Gift Box Rotation | The weeks of the Weekly Gift Box. An active one replaces the product's own contents |

## Add a product

1. Products, then New product. Choose a category, or add one first under Categories.
2. Add images: up to 8, 2 MB each. The first is the cover. With none, the category's image or the drawn picture is used.
3. Write the name and description in English and Portuguese. A blank line starts a new paragraph.
4. Set the price. Any item can be the currency. Empty means free.
5. Add the items. **Check each item's `/items` page first**: a name doesn't tell you what it does.
6. A random bonus is optional. One is rolled by weight for each one bought, which is how a chest works.
7. For one class only, open Class, choose the group and turn on **Only this class can get it**.
8. Set the limit, if any. `One per season` uses the season name: changing the name lets every account buy it again.

The form refuses what the game server would reject or that could be abused:

- More than 32 items in an order, counting the bonus as one.
- A count above 1 of an item that doesn't stack. Add it as separate entries instead.
- More than 1 in an order when anything inside doesn't stack.
- An Adena price under the items' list prices, because it could be resold to an NPC for a profit.

A product's item can also be a grade key (`enchant_weapon`, `enchant_armor`, `blessed_enchant_weapon`). The receiving character gets the scroll for their level.

## Send a product to accounts

On Products, use **Send**, choose accounts or every account (banned ones are skipped), and write a reason. The player sees the reason.

Each account gets a free order in its Orders tab and a badge on the site header, plus an email if it has an address. Mail needs a character, so nothing reaches the game until the player chooses one. Limits don't apply, and sending doesn't use up what the account can still buy. The contents are kept as they were when sent, and a random bonus is rolled when the character is chosen.

## Seeding

`database/seeders/StoreSeeder.php` holds the 6 categories and the 45 starting products. A new host gets them by itself, because the migration runs the seeder when the site starts. To add anything missing by hand:

```bash
docker compose exec site php artisan db:seed --force
```

It matches by web address, so it's safe on a live site: it never changes a product that already exists and never brings back a deleted one. A product added in the admin panel isn't in the seeder.

## Things to know

- The main page lists every product: those set to **List first**, then the packages for the player's class (Samurai for guests), then the rest. Cards appear 12 at a time as the player scrolls, and the count sits under the grid.
- The hosts have separate databases and uploads, so a product added on the Mac doesn't reach the Pi.
- Images are in `data/site-uploads`, which isn't in git. Back them up with the database.
- Deleting a product hides it and keeps its orders. The Deleted filter on Products can restore it. The Weekly Gift Box and Hunt Pass can only be unpublished.
- Gifts for a True Samurai needs the earring overrides in `overrides/game/data/stats` (`items/105400-105499.xml`, `skills/50900-50999.xml`). The datapack gives Earring of Samurai's Path no slot and an empty skill, so it can't be worn without them.
- The Hunt Pass is unlocked in the game. Its price and text come from `HuntPass.ini`, so its form is shorter and it can't be sent.
- Premium time can't be sold yet, because the game server's `add_premium` doesn't charge.
- `gift_claims`, `pass_claims`, `package_claims` and `supply_purchases` are no longer used. Their rows were copied to `orders`, and they can be dropped in a later release.
