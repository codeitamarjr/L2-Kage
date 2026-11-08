# Updating L2J Mobius

How to take in L2J Mobius' fixes without losing L2 Kage's customisations, and how to deploy them.

## Why this is safe

This repository is a git fork of L2J Mobius. The `upstream` remote is `https://gitlab.com/MobiusDevelopment/L2J_Mobius.git`, and the first upstream commit we took is part of our history. An update is a normal `git merge upstream/master`.

Our changes live outside the upstream sources:

| What | Where | What an update does to it |
|---|---|---|
| Configs, scripts, html, xml | `overrides/game/` | Nothing. The entrypoint copies it over the server every start |
| Container settings (DB, ports, IPs, save interval) | `docker/entrypoint.sh` | Nothing. Written into the configs every start |
| Passwords and host settings | `.env` on each host | Nothing. Not in git and not in `dist/` |
| Database, logs, geodata, uploads | `data/` on each host | Nothing. Not in git and not in `dist/` |
| Website | `site/` | Nothing |

So the merge itself cannot overwrite a customisation. The risks are elsewhere:

1. **An override hides an upstream fix.** If upstream fixes a file we override (for example `SquashEvent.java` or `stats/skills/48500-48599.xml`), our copy still wins, and the fix is lost until we redo it in our copy.
2. **Database changes are not applied.** `docker/db-init/` only runs on an empty `data/mariadb`. A new table or column from upstream has to be applied by hand. Never re-run a whole upstream `.sql` file on a live database: each one starts with `DROP TABLE`.
3. **Our Java overrides stop compiling.** `WebBridge`, `PremiumWelcome` and the two event scripts compile against `GameServer.jar` at startup. If upstream changes a class they use, the script fails to load, and website deliveries stop with it.
4. **New config keys.** A key upstream adds to a file we override in full (such as `Rates.ini`) is missing from our copy, so it uses its code default. That is normally the same as upstream's default, but read the new key.

## 1. Check what the update brings

```bash
tools/upstream-check.sh
```

It fetches upstream and changes nothing else. It lists the new Essence commits, then flags each of the risks above: overridden files upstream changed, database scripts, config files, events, login server code, classes our Java imports, and whether the merge has conflicts.

Read the commit list. Mobius commits fix behaviour players will notice, so mention the notable ones in a news post if they matter.

## 2. Merge on a branch

Commit or stash local work first, so the merge is the only change on the branch.

```bash
git switch -c upstream-YYYY-MM-DD
git merge upstream/master
git sparse-checkout list   # still L2J_Mobius_Essence_10.1_SamuraiCrow, docker, docs, overrides, site, tools
```

The merge also brings other chapters' history, but the sparse checkout keeps them off disk. Then deal with each flag from step 1:

- **Overridden file changed:** run the `git diff` the script prints and apply the same change to our file in `overrides/`.
- **Database script changed:** write the matching `ALTER TABLE` or `CREATE TABLE IF NOT EXISTS` and keep it for the deploy.
- **Config changed:** if we override that file, decide whether to copy the new key into our copy.

## 3. Build and test on the Mac

The Mac is the staging copy. Build a new image without touching the running one:

```bash
docker build -t l2jmobius-essence:candidate .
```

Compile our Java overrides against it:

```bash
docker run --rm -v "$PWD/overrides/game:/o:ro" --entrypoint sh l2jmobius-essence:candidate -c 'mkdir -p /tmp/x && javac -proc:none -nowarn -d /tmp/x -cp /opt/l2j/libs/GameServer.jar -sourcepath /opt/l2j/game/data/scripts $(find /o -name "*.java") && echo COMPILE OK'
```

If that passes, run it on the Mac:

```bash
docker tag l2jmobius-essence:latest l2jmobius-essence:previous
docker tag l2jmobius-essence:candidate l2jmobius-essence:latest
docker compose up -d --no-deps login game
docker compose logs --tail=300 game | grep -iE 'error|exception|failed|WebBridge|PremiumWelcome|Squash|Holiday'
```

Wait for the game server to register with the login server, then log in with the client and check something from each override: a website delivery from the Store, the premium welcome, the active event NPC.

## 4. Deploy to the Pi

Before starting, read the recent game log and `git status`, and check who is online. Recreating `game` disconnects players, so tell the owner the time.

On the Pi, back up the database and keep the current image for rollback:

```bash
cd ~/l2-kage
docker compose exec db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --databases "$L2J_DB_NAME" l2web' | gzip > data/backup-$(date +%F).sql.gz
docker tag l2jmobius-essence:latest l2jmobius-essence:previous
```

Check that the backup is not empty (`ls -lh data/backup-*.sql.gz`).

On the Mac, build and copy the bundle:

```bash
./build-pi.sh
rsync -a dist/ junior@server.local:l2-kage/
```

Never use `--delete`: it would remove the Pi's `.env` and `data/`.

On the Pi, apply any database changes from step 2, then load and recreate:

```bash
docker load -i l2jmobius-arm64.tar.gz
docker compose up -d --no-deps login game site
docker compose logs --tail=300 game | grep -iE 'error|exception|failed'
```

If the update touched no login server code (step 1 says so), `login` can be left out. Recreating `site` is only needed if the website changed too.

Then verify the rates as described in `AGENTS.md`, and check that a website delivery reaches a character.

## Rolling back

On either host:

```bash
docker tag l2jmobius-essence:previous l2jmobius-essence:latest
docker compose up -d --no-deps login game
```

If database changes were applied and need undoing, restore the backup taken in step 4. That loses everything players did since the backup, so only do it with the owner's agreement.

In git, the merge stays on its branch until it is proven. Merge the branch into `master` after the Pi runs it without errors.

## Keeping this easy

- Never edit `L2J_Mobius_Essence_10.1_SamuraiCrow/`. Every change goes in `overrides/`, and that keeps merges conflict-free.
- Update often. Two weeks of Mobius commits is easy to read; three months is not.
- When adding an override, prefer the smallest file that does the job, so fewer upstream fixes get hidden.
