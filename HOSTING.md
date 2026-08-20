# MAGDY HELAL CORP — hosting runbook

Public site URL (authoritative, no trailing slash):

**https://helal.co**

`www.helal.co` is an alias. Redirect it to the apex so the canonical WordPress URL stays `https://helal.co`. Run **Docker + nginx reverse proxy** on the VPS at `/opt/magdyHelalCorporation`. Do not commit `.env`. This repo only ships password placeholders (`CHANGE_ME`).

---

## A) VPS: Docker + nginx (primary)

Path on the server: `/opt/magdyHelalCorporation`  
Bind: `127.0.0.1:8088:80` (`docker-compose.server.yml`)  
TLS: certbot for `helal.co` and `www.helal.co`  
WordPress public URL: `WP_HOME=https://helal.co`

### 1. DNS

Point **A** records for `helal.co` and `www.helal.co` at this VPS (or CNAME `www` to `helal.co`).

### 2. Clone / pull and env

```bash
sudo mkdir -p /opt/magdyHelalCorporation
sudo chown "$USER:$USER" /opt/magdyHelalCorporation
cd /opt/magdyHelalCorporation
git pull   # or git clone … . on first install
cp -n .env.example .env
# Edit .env: replace every CHANGE_ME. Keep WP_HOME / WP_SITEURL / WP_URL as:
#   https://helal.co
```

### 3. Start WordPress (loopback only)

```bash
cd /opt/magdyHelalCorporation
docker compose -f docker-compose.server.yml up -d --build
docker compose -f docker-compose.server.yml --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
```

Apache inside the container listens on host **127.0.0.1:8088**. Canonical links, REST, and chat page buttons use **WP_HOME**.

If you change MySQL passwords after the first run, reset volumes:

```bash
docker compose -f docker-compose.server.yml down -v
```

### 4. nginx reverse proxy + HTTPS

Install nginx and certbot, then a site that forwards to loopback **8088** and sends `X-Forwarded-Proto`.

```nginx
# /etc/nginx/sites-available/helal.co
server {
    listen 80;
    listen [::]:80;
    server_name helal.co www.helal.co;

    location / {
        proxy_pass http://127.0.0.1:8088;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        client_max_body_size 64m;
    }
}
```

```bash
sudo ln -sf /etc/nginx/sites-available/helal.co /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d helal.co -d www.helal.co
```

Certbot rewrites the site to listen on 443 and keep `X-Forwarded-Proto` as `https` (or `$scheme`, which is `https` on the TLS server). `docker-compose.server.yml` sets `$_SERVER['HTTPS'] = 'on'` when that header contains `https`.

Optional — after certbot, redirect **www → apex** so browsers land on `https://helal.co` (do not reverse this; WordPress `WP_HOME` is the apex):

```nginx
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name www.helal.co;
    # ssl_certificate lines stay as certbot wrote them
    return 301 https://helal.co$request_uri;
}
```

On port 80 you can also `return 301 https://helal.co$request_uri;` for both names once the certificate exists. If an old `magdyhelal.modevops.fun` nginx site is still enabled, disable it.

### 5. After git pull (existing VPS)

```bash
cd /opt/magdyHelalCorporation
git pull
docker compose -f docker-compose.server.yml up -d --build
docker compose -f docker-compose.server.yml --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
sudo nginx -t && sudo systemctl reload nginx
```

Confirm **Settings → General** (or `wp option get home`) is `https://helal.co` with no trailing slash.

Admin email (`WP_ADMIN_EMAIL`) and the public office inbox are both `info@helal.co`.

| Public contact | |
| --- | --- |
| البريد | `info@helal.co` |
| الهاتف | `+0224051171` · `+0224051169` (`tel:+20224051171` / `tel:+20224051169`) |
| واتساب | hidden until a mobile exists |
| العنوان | مدينة نصر، القاهرة |

Set the same values in **Appearance → Customize → مكتب مجدي هلال**.

---

## B) Local Docker (laptop)

Container bind is still port **8088**. Public URL in `.env` stays `https://helal.co`.

```bash
cd /path/to/magdyHelalCorporation
cp .env.example .env
# Replace MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD, WP_ADMIN_PASSWORD.

docker compose up -d --build
docker compose --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
```

Opening `http://127.0.0.1:8088` can show PHP, but generated URLs point at the public host. That is expected.

Published image (optional):

```bash
docker compose -f docker-compose.prod.yml up -d
```

---

## Identities (fill secrets locally)

| Key | Value in repo / example |
| --- | --- |
| `MYSQL_DATABASE` | `magdi_hilal` |
| `MYSQL_USER` | `magdi` |
| `MYSQL_PASSWORD` | placeholder `CHANGE_ME` — set a real password |
| `MYSQL_ROOT_PASSWORD` | placeholder `CHANGE_ME` — set a real password |
| `WP_PORT` | `8088` (bind only) |
| `WP_HOME` / `WP_SITEURL` / `WP_URL` | `https://helal.co` |
| `WP_ADMIN_USER` | `admin` |
| `WP_ADMIN_PASSWORD` | placeholder `CHANGE_ME` — set a real password |
| `WP_ADMIN_EMAIL` | `info@helal.co` |

---

## Chat

Knowledge rows do not hardcode `localhost`. Page buttons use `home_url()`, so they follow **Settings → General** (`helal.co`). Replies still strip leftover `localhost` / `127.0.0.1` / old hosts (`magdyhelal.modevops.fun`, `magdy.modevops.fun`, `magdyhelalcorp.infinityfree.io`) and rewrite `www.helal.co` toward the apex. After a URL change, reload the site once (theme load re-seeds if the chat DB version bumped) or run:

```bash
docker compose -f docker-compose.server.yml --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
```
