# MAGDY HELAL CORP — hosting runbook

Public site URL (authoritative):

**https://magdyhelalcorp.infinityfree.io**

`*.infinityfree.io` is **InfinityFree shared hosting**, not Docker. Pick **A** (most likely) or **B**.

Do not commit `.env`. Passwords were blank in the project notes — fill them from the InfinityFree panel or your own secrets. This repo only ships placeholders (`changeme`).

---

## A) Deploy ON InfinityFree (this domain)

InfinityFree runs PHP + MySQL on their servers. **Do not** upload Docker, Redis, or `docker-compose*.yml`. Redis is optional; the theme must not fatal if Redis is absent.

### 1. Install WordPress

In the InfinityFree control panel:

1. Open **Softaculous** (or the WordPress installer) and install WordPress on `magdyhelalcorp.infinityfree.io`.
2. Or upload a full WordPress tree via File Manager / FTP into `htdocs`.

Use **PHP 8.x** if the panel offers it (theme requires PHP 8.0+).

### 2. Upload this project’s theme and mu-plugins

From this repo, upload:

- `wp-content/themes/magdi-hilal-adco/` → `wp-content/themes/magdi-hilal-adco/`
- `wp-content/mu-plugins/` → `wp-content/mu-plugins/`

You do **not** need Redis, `Dockerfile`, or compose files on this host.

### 3. MySQL names from the panel (not from `.env.example`)

InfinityFree MySQL names are **prefixed** (for example `sql_user_xxx` / `sqlxxx.infinityfree.com`). The local Docker names `magdi` / `magdi_hilal` apply **only** if you created those exact names in the panel — usually you did not.

Open **MySQL Databases** in the panel and paste the real values into `wp-config.php`:

```php
define('DB_NAME', 'paste_from_infinityfree_panel');
define('DB_USER', 'paste_from_infinityfree_panel');
define('DB_PASSWORD', 'paste_from_infinityfree_panel'); // we cannot invent this
define('DB_HOST', 'paste_from_infinityfree_panel');     // often sqlNNN.infinityfree.com, not localhost
$table_prefix = 'mha_'; // keep this if you want the same prefix as Docker; otherwise leave the installer default
```

You still need the MySQL password from the InfinityFree panel. Nobody can invent it for you.

### 4. Site URL

In **wp-admin → Settings → General**:

- WordPress Address (URL) = `https://magdyhelalcorp.infinityfree.io`
- Site Address (URL) = `https://magdyhelalcorp.infinityfree.io`

Or in `wp-config.php`:

```php
define('WP_HOME', 'https://magdyhelalcorp.infinityfree.io');
define('WP_SITEURL', 'https://magdyhelalcorp.infinityfree.io');
```

### 5. Activate the theme

**Appearance → Themes → MAGDY HELAL CORP**.

Chat tables (`mha_chat_sessions`, `mha_chat_messages`, `mha_chat_knowledge`) are created on theme load via `mha_chat_install`. No extra SQL import is required.

Admin email default in this project: `momagdyy97@gmail.com`  
**Appearance → Customize → مكتب مجدي هلال** for phone, address, and public email.

### 6. Permalinks

**Settings → Permalinks → Post name**, then Save.

---

## B) Docker on a VPS / laptop, InfinityFree URL only in config

This path uses Docker. It does **not** put the site on InfinityFree’s shared servers.

### Local Docker (port 8088, public URL in env)

```bash
cd /path/to/magdyHelalCorporation
cp .env.example .env
# Edit .env: replace MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD, WP_ADMIN_PASSWORD.
# Leave WP_HOME / WP_SITEURL / WP_URL as:
#   https://magdyhelalcorp.infinityfree.io

docker compose up -d --build
docker compose --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
```

- Container bind: host port **8088** → WordPress `:80`
- Canonical links, REST, and chat buttons use **WP_HOME** (`https://magdyhelalcorp.infinityfree.io`)
- Opening `http://localhost:8088` can show PHP, but generated URLs point at the InfinityFree host. For a working local-only preview, temporarily set `WP_HOME` / `WP_SITEURL` / `WP_URL` to `http://localhost:8088`, run setup again, then switch them back before deploy.

If you change MySQL passwords after the first run, reset volumes:

```bash
docker compose down -v
```

### VPS Docker (loopback only)

```bash
cp .env.example .env
# fill passwords; keep WP_HOME=https://magdyhelalcorp.infinityfree.io only if DNS for that
# host actually points at this machine (InfinityFree subdomains usually cannot).

docker compose -f docker-compose.server.yml up -d --build
docker compose -f docker-compose.server.yml --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
```

Bind: `127.0.0.1:8088:80` (put nginx/Caddy in front).

**DNS honesty:** putting `WP_HOME=https://magdyhelalcorp.infinityfree.io` in `.env` does **not** make that hostname resolve to your VPS. InfinityFree `*.infinityfree.io` subdomains are served by InfinityFree. They typically **cannot** be pointed at a VPS. Use path **A** for this domain, or use a custom domain whose DNS you control for path **B**.

Published image (optional):

```bash
docker compose -f docker-compose.prod.yml up -d
```

---

## Identities (fill secrets locally)

| Key | Value in repo / example |
| --- | --- |
| `MYSQL_DATABASE` | `magdi_hilal` (Docker only; InfinityFree uses panel names) |
| `MYSQL_USER` | `magdi` (Docker only) |
| `MYSQL_PASSWORD` | placeholder `changeme` — set a real password |
| `MYSQL_ROOT_PASSWORD` | placeholder `changeme_root` — set a real password |
| `WP_PORT` | `8088` |
| `WP_HOME` / `WP_SITEURL` / `WP_URL` | `https://magdyhelalcorp.infinityfree.io` |
| `WP_ADMIN_USER` | `admin` |
| `WP_ADMIN_PASSWORD` | placeholder `changeme` — set a real password |
| `WP_ADMIN_EMAIL` | `momagdyy97@gmail.com` |

---

## Chat

Knowledge rows do not hardcode `localhost`. Page buttons use `home_url()`, so they follow **Settings → General**. After a URL change, reload the site once (theme load re-seeds if the chat DB version bumped) or run:

```bash
docker compose --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
```
