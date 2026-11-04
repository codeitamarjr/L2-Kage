# Writing News from Aden

How to write posts for the L2 Kage news section (`/news`, the home page and its banner). Written for AI agents and people.

**The goal:** a new player finishes the post wanting to log in tonight. A veteran finishes it feeling that Aden is alive.

## Voice

Write like a herald in Giran's square: bold, warm and a little theatrical, and always clear about what to do.

| Do | Don't |
|---|---|
| Challenge the reader: "Do you dare break it open?" | Corporate tone: "We are pleased to announce..." |
| Open inside the world, with a scene or a threat | Open with the feature: "This update adds..." |
| Have fun: a cheeky cat, a boastful boss, a dark joke | Mock players, or joke about real-world tragedies |
| Use short, vivid sentences | Stack adjectives: "epic, amazing, legendary, incredible" |
| Explain terms the first time: "Nectar, the pumpkins' favourite meal" | Assume the reader knows the game |
| Speak to one hero, as "you" | Address "users" or "players of the server" |

Keep the excitement in the story and the rules plain. A reader must never have to decode a metaphor to learn what to do.

## Lore

Tie each post to the world, using what the site's own history page tells: `site/resources/js/components/lore/lore-en.tsx` (read it first).

Useful threads:
- **The gods:** Einhasad and Gran Kain, and Shilen's betrayal.
- **The fallen:** the Giants, who dared to create life.
- **The threats:** Antharas waking beneath the earth, Baium, Zaken and Queen Ant.
- **The current chapter:** the Kamael Samurai and the Samurai Crow.
- **The places:** Giran, Aden, Goddard, Dragon Valley and the rest of the continent.

Don't invent new gods, history or characters and present them as canon. Small in-world colour is welcome, such as a rumour or a herald's boast. Link a relevant chapter when there is one, like `[the fall of the Giants](/lore#giants)`.

## Accuracy comes first

Every fact must be true of this server today. Check it in the files; never guess or copy from official game sites.

| Fact | Where to check |
|---|---|
| Rates | `overrides/game/config/Rates.ini`, then the upstream file in `dist/game/config` |
| Event dates, NPCs and places | `overrides/game/data/scripts/events/<Event>/`, then upstream `dist/game/data/scripts/events/<Event>/` |
| Prizes and trade costs | The event's script, `overrides/game/data/multisell/`, then upstream `dist/game/data/multisell/` |
| Store products, passes, packages and gift box | Store in the admin panel (Products and Gift Box Rotation) |

- Never promise a reward, date or feature you haven't confirmed. If unsure, leave it out and tell the owner.
- Don't create false urgency. "Until 14 November" is fine if true; "last chance" is only for the real last days.
- Don't call anything "tested" or "balanced" unless it was.
- L2 Kage is a free fan server, not affiliated with NCSOFT. Never suggest otherwise.

## Structure

1. **Title** (up to 120 characters): a hook, not a label. "The Pumpkins Are Hungry" beats "Halloween Event Update".
2. **Summary** (up to 220 characters): the hook, plus what's in it for the reader. It appears on cards and the banner, so it must stand alone.
3. **Scene:** 2 or 3 sentences that pull the reader in.
4. **What to do:** a `## How to play` heading with numbered steps. Bold each step's action.
5. **What you get, and until when:** real rewards and real dates.
6. **The challenge:** one last line that dares the reader.

Keep most posts under 200 words. Use Markdown: `##` headings, lists, `**bold**` and links. Raw HTML is removed.

## New players

Assume this may be the reader's first Lineage II post.

- Say where to start: a town or NPC, and the minimum level if there is one.
- When a post suits beginners, say so.
- Link to what they need: `/register` to create an account, `/about` for how to connect, `/items` for item details and `/store` for rewards.
- End event posts for newcomers with an invitation, like "No hero yet? [Create one](/register) and be in Aden tonight."

## Choosing fields

| Field | Guidance |
|---|---|
| Category | `event`, `quest`, `update` or `announcement` |
| Slug | Short English words with hyphens: `halloween-pumpkin-harvest` |
| Image | Optional, up to 2 MB, wide. Only use images the owner supplied or is allowed to use |
| Publish at | In UTC. Empty keeps a draft; a future date schedules it |
| Banner | Only for one big event at a time. Set "Until" to the last moment of the event |
| Banner look | `gold` by default; `halloween` adds a jack-o'-lantern; `winter` adds a snowflake. A new look needs a CSS theme in `site/resources/css/app.css` |

## English and Portuguese

Every post needs both languages. Write the Portuguese in Brazilian Portuguese as a native writer would; don't translate word for word, and make sure the jokes work. Keep names as they appear in the game (Puss the Cat, Antharas, Kage Pass).

## Publishing

- **Publish only when the owner asks.** Otherwise, save as a draft or show them the text.
- **In the admin panel:** News > New Post.
- **From code:** copy `site/database/seeders/HalloweenNewsSeeder.php` and run it inside the site container on each host with `php artisan db:seed --class=YourSeeder --force`. Use the slug as the key so running it again updates the post.

## Checklist

- [ ] Every fact checked against the server's files
- [ ] Title hooks, and the summary stands alone
- [ ] Opens inside the world, and ties to the lore
- [ ] Steps are clear enough for a first-time player
- [ ] Dates are real, and nothing is overpromised
- [ ] Both languages are complete and sound natural
- [ ] Ends with a challenge or an invitation

## Example

> **Title:** The Pumpkins Are Hungry
>
> **Summary:** Strange pumpkins are sprouting across Aden, and something inside them wants out. Feed them, break them and claim what they hide, until 14 November.
>
> The fields of Aden smell of rot and honey this autumn. Gardener Fordson's magical seeds have grown strange, and his cat, Puss, is looking for someone brave or foolish enough to tend them. Are you?
>
> ## How to play
>
> 1. **Hunt** monsters anywhere in Aden for Nectar, the pumpkins' favourite meal.
> 2. **Find Puss the Cat** in town and trade Nectar for a seed.
> 3. **Plant it** and feed it Nectar until it changes.
> 4. **Break it open** before it vanishes.
>
> The largest pumpkins laugh at ordinary weapons. Only a chrono instrument can crack them, and they can hold Halloween Kits.
>
> So, hero: will you feed the harvest, or will the harvest feed on you?
