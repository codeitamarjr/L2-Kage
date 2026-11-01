# L2J Mobius Essence on Docker

This repository provides a Docker deployment for an **L2J Mobius Essence:
Samurai Crow** server (client protocol 542), together with an optional Laravel
website.

The stack runs the following services:

- MariaDB
- L2J Mobius login server
- L2J Mobius game server
- Optional Laravel website
- Optional Cloudflare Tunnel for publishing the website without opening an
  HTTP port

The repository contains server code and functional configuration only. It does
not include a game client or copyrighted client assets. Obtain and use the
client according to its applicable license and terms.

## Requirements

For a local deployment:

- Docker Desktop or Docker Engine with Docker Compose v2
- At least 8 GB of available memory when using geodata
- The source and data files required by the selected L2J Mobius chapter

For an ARM64 deployment such as a Raspberry Pi:

- A 64-bit ARM operating system
- Docker with Buildx support
- Enough memory and swap for the selected Java heap size

## Repository layout

```text
L2jmobius/
├── L2J_Mobius_Essence_10.1_SamuraiCrow/   L2J Mobius server sources
├── Dockerfile                              Login/game server image
├── docker-compose.yml                      Complete service stack
├── docker/                                 Entrypoint and database setup
├── overrides/game/                         Custom server files
├── site/                                   Optional Laravel website
├── build-pi.sh                             ARM64 image export script
├── .env.example                            Documented configuration template
└── data/                                   Local database, logs and geodata
```

The `data/` directory contains runtime state and is intentionally excluded from
Git. The `.env` file contains passwords and deployment-specific settings and
must never be committed.

## Configuration

Create the environment file before starting the stack:

```bash
cp .env.example .env
```

Replace every `change-me` value with a strong, unique value. For example:

```bash
openssl rand -hex 16
```

Important settings:

| Variable | Description |
|---|---|
| `SERVER_NAME` | Name shown by the website |
| `PUBLIC_HOST` | Hostname or IP address sent to game clients |
| `LAN_HOST` | Optional LAN address sent instead to clients on the local network |
| `LAN_SUBNET` | Local network that receives `LAN_HOST`; defaults to `192.168.0.0/16` |
| `LOGIN_PORT` | External login-server port; defaults to `2106` |
| `GAME_PORT` | External game-server port; defaults to `7777` |
| `SITE_PORT` | Website port on the Docker host; defaults to `8082` |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | L2J game database settings |
| `DB_ROOT_PASSWORD` | MariaDB root password |
| `WEB_DB_PASSWORD`, `L2WEB_DB_PASSWORD` | Read-only game and website database passwords |
| `SITE_APP_KEY` | Laravel application key |
| `GAME_JAVA_OPTS` | Java options and heap size for the game server |
| `CHARACTER_SAVE_MINUTES` | Minutes between saves of online characters; defaults to `5` |
| `AUTO_CREATE_ACCOUNTS` | Whether a new game login creates an account |
| `WEBSITE_URL` | Canonical public URL for the website |
| `MAIL_*` | Optional SMTP settings for registration and password resets |
| `COMPOSE_PROFILES` | Set to `public` to enable the Cloudflare Tunnel |
| `CF_TUNNEL_TOKEN` | Cloudflare Tunnel token when the public profile is enabled |
| `TRUSTED_PROXIES` | Proxy networks trusted by the website |

Database passwords are used when MariaDB initializes its data directory. If
you change them after the first startup, update the existing database users
manually or remove the local database and initialize it again. Removing
`data/mariadb` permanently deletes the local game and website databases.

Generate a Laravel key after building the website image if needed:

```bash
docker compose build site
docker run --rm --entrypoint php l2jmobius-site:latest artisan key:generate --show
```

Copy the generated value into `SITE_APP_KEY` in `.env`.

## Start the stack

Build and start all standard services:

```bash
docker compose up -d --build
```

Follow the game-server logs:

```bash
docker compose logs -f game
```

The first startup creates the MariaDB schema and website database users. It
may take longer than subsequent startups. The game server is ready when it
reports that it has registered with the login server.

Useful commands:

```bash
docker compose ps
docker compose logs -f login
docker compose logs -f site
docker compose restart game
docker compose down
```

The website is available at:

```text
http://<host>:<SITE_PORT>
```

The login and game ports must be reachable by players. If the server is behind
a router, forward `LOGIN_PORT` and `GAME_PORT` to the Docker host, using the
same port numbers outside and inside. Do not expose the MariaDB port publicly.

When `PUBLIC_HOST` is a public address, players on the same local network may
be unable to reach it, because many routers do not route the public address
back into the network. Set `LAN_HOST` to the Docker host's LAN address: the
login server then sends that address to clients in `LAN_SUBNET` and
`PUBLIC_HOST` to everyone else.

## Public website with Cloudflare Tunnel

The website can be published through Cloudflare without opening an HTTP port
on the router:

1. Create a Cloudflare Tunnel.
2. Configure its public hostname to route HTTP traffic to `http://site:80`.
3. Set the tunnel token in `.env`:

   ```dotenv
   COMPOSE_PROFILES=public
   CF_TUNNEL_TOKEN=<your-token>
   WEBSITE_URL=https://your-domain.example
   TRUSTED_PROXIES=172.16.0.0/12
   ```

4. Start or recreate the stack:

   ```bash
   docker compose up -d
   ```

Keep the tunnel token and all mail credentials private. Configure
`TRUSTED_PROXIES` to match the networks from which the proxy sends requests;
do not blindly trust arbitrary client-supplied proxy headers.

## ARM64 deployment

`build-pi.sh` builds both images for Linux ARM64 and creates a `dist/` folder
containing the images and runtime files. It does not copy `.env` or `data/`.

On an ARM64-capable build machine:

```bash
./build-pi.sh
```

Copy `dist/` to the target host using your preferred secure method. On the
target host:

```bash
cp .env.example .env
# Edit .env for the target host
docker load -i l2jmobius-arm64.tar.gz
docker compose up -d
```

Adjust `GAME_JAVA_OPTS` to match the available memory. Running without
geodata requires less memory, but disables geodata-dependent functionality.

## Connect a game client

Use a clean, legally obtained client compatible with the server protocol.
Configure its server address and login port according to the deployment:

```text
ServerAddr=<PUBLIC_HOST>
ServerPort=<LOGIN_PORT>
```

Some clients use a hosts-file entry or a client-specific configuration file
instead. Follow the documentation for the client and patch format you are
using. Do not distribute client files, private-server patches, or copyrighted
game assets from this repository.

### Custom login port on Windows

Some clients, such as the Samurai Crow client, always log in on port 2106 and
ignore `ServerPort`. When `LOGIN_PORT` is not 2106 (for example because another
server already uses 2106 on the same host), Windows can redirect the client's
port 2106 to the server's login port. The website's About page shows these
steps automatically, with the server's own address and port, whenever
`LOGIN_PORT` is not 2106.

1. Download the client files from the website into the client's `system`
   folder: `l2-kage.ini` (logs in on `127.0.0.2`) and `start-l2-kage.bat`
   (starts `L2.exe` with that ini). They are served from
   `site/public/downloads/`; edit them there and rebuild the site image.
2. Open the Command Prompt as Administrator and create the redirect once,
   replacing `<PUBLIC_HOST>` and `<LOGIN_PORT>` with the server's values:

   ```bat
   netsh interface portproxy add v4tov4 listenaddress=127.0.0.2 listenport=2106 connectaddress=<PUBLIC_HOST> connectport=<LOGIN_PORT>
   ```

   A DNS name works as `connectaddress` and keeps working when the server's IP
   changes.
3. Start the game with `start-l2-kage.bat`.

Only the login goes through the redirect: the login server hands the client
the game address and `GAME_PORT` directly, so a custom game port needs no
client change. The redirect survives a Windows restart. To list or remove it
(as Administrator):

```bat
netsh interface portproxy show v4tov4
netsh interface portproxy delete v4tov4 listenaddress=127.0.0.2 listenport=2106
```

The redirect needs the Windows "IP Helper" service, which runs by default.

### Disconnected after selecting a server

If the client logs in but shows "You have been disconnected" after a server is
selected, it cannot reach the game address handed out by the login server. The
login log lists those addresses (`Updated Gameserver ... IP's`). Check
`LAN_HOST` for local players and the `GAME_PORT` forward for remote players.

When `AUTO_CREATE_ACCOUNTS=True`, a new game login can create an account
automatically. Otherwise, create accounts through the website or the relevant
database administration process.

## Accounts and GM access

Game accounts are stored in the L2J Mobius database. GM access belongs to a
character and should be changed while that character is offline:

```bash
docker compose exec db sh -c \
  'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$L2J_DB_NAME" \
  -e "UPDATE characters SET accesslevel = 100 WHERE char_name = \"CharacterName\" AND online = 0"'
```

In game, `//admin` opens the administrator panel for an authorized character.
Grant GM access only to trusted administrators.

## Passes and L-Coin

L-Coin (item 91663) pays for both passes. Every new character starts with
1,000 L-Coin (`overrides/game/data/stats/players/initialEquipment.xml`), and
the website's weekly gift box adds 1,000.

**Hunt Pass (in game).** Essence's built-in battle pass (the same L2PASS
system the official servers run): each monster kill gives 1 point (2 in timed
hunting zones, party members included), a step takes `PointsForStep` points
(300 here; retail 2,400), each step has a free reward, and Premium adds a
second reward track, also for steps already completed. It starts over every
month on `DayOfMonth`. `overrides/game/config/HuntPass.ini` turns it on and
sets the Premium price (`PremiumItemId`, `PremiumItemCount`: 1,000 L-Coin).
Restart the game server after a change. Keep the rewards in the datapack's
`data/HuntPass.xml` as they are: the client draws the pass window from its own
files, so different server rewards would not match what players see.

**The Store (website).** `/store` sells the Kage Pass, class packages and supply
kits and gives away the weekly gift box. Everything in it is edited in the admin
panel under Store; see [docs/STORE.md](docs/STORE.md). A player pays from one of
their characters' bags. The website queues the order and the game server's
WebBridge script takes the price and mails the items in one step: from the
inventory when the character is online, from its saved bag when it is offline.
If the character doesn't have enough, nothing is taken and the player can buy
again. The Hunt Pass page there explains the in-game pass, with every number read
from the server's config.

**L-Coin Store (in game).** `overrides/game/data/LimitShop.xml` sets what is
sold, prices and limits, including monthly pass kits. The client's own product
list sets card names, tabs and ribbons, so a change usually needs both sides.
See [docs/LCOIN-STORE.md](docs/LCOIN-STORE.md).

## Updates and backups

Back up the following before updating:

- `data/mariadb/`
- `data/geodata/`
- `data/logs/`
- `.env`
- Any local files under `overrides/`

To update the server source or this deployment:

```bash
docker compose down
# Update the repository and review .env and overrides/
docker compose up -d --build
```

Do not delete `data/mariadb` unless you intentionally want to destroy the
databases. Test updates on a backup or separate environment first.

## Licensing and responsibility

The upstream L2J Mobius files retain their original license headers. The
upstream project notes that files may be covered by MIT or GPLv3 depending on
whether they have been rewritten; verify the header of each file you
redistribute or modify.

This repository does not provide legal advice. You are responsible for
complying with applicable laws, software licenses, game terms of service,
privacy rules, and hosting-provider policies in your jurisdiction.
