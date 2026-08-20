#!/bin/sh
# Installs WordPress, Arabic, MAGDY HELAL CORP theme, Redis Cache, and demo pages.
set -eu

cd /var/www/html

WP_URL="${WP_URL:-https://magdyhelalcorp.infinityfree.io}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-changeme}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-momagdyy97@gmail.com}"

echo "Waiting for WordPress files..."
for i in $(seq 1 90); do
  if [ -f wp-config.php ]; then
    break
  fi
  sleep 2
done

if [ ! -f wp-config.php ]; then
  echo "wp-config.php not found. Is the wordpress container running?"
  exit 1
fi

if ! wp core is-installed; then
  wp core install \
    --url="${WP_URL}" \
    --title="مكتب مجدي هلال — M.H CORP" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email
fi

wp option update home "${WP_URL}" || true
wp option update siteurl "${WP_URL}" || true

wp language core install ar || true
wp site switch-language ar || true
wp rewrite structure '/%postname%/' --hard
wp option update blogname "مكتب مجدي هلال — M.H CORP"
wp option update blogdescription "magdyhelalCORP — محاسبة · ضرائب · مراجعة"
wp option update admin_email "${WP_ADMIN_EMAIL}" || true
wp option update timezone_string "Africa/Cairo"
wp option update date_format "j F Y"
wp option update WPLANG "ar"

wp theme activate magdi-hilal-adco

wp eval 'if (function_exists("mha_seed_content")) { mha_seed_content(); echo "seeded\n"; } else { echo "seed function missing\n"; }'
wp eval 'if (function_exists("mha_seed_news")) { mha_seed_news(true); echo "news seeded\n"; } else { echo "news seed missing\n"; }'
wp eval 'if (function_exists("mha_chat_install")) { mha_chat_install(true); echo "chat ready\n"; } else { echo "chat install missing\n"; }'

HELLO=$(wp post list --post_type=post --name=hello-world --format=ids 2>/dev/null || true)
[ -n "$HELLO" ] && wp post delete $HELLO --force
SAMPLE=$(wp post list --post_type=page --name=sample-page --format=ids 2>/dev/null || true)
[ -n "$SAMPLE" ] && wp post delete $SAMPLE --force

if ! wp plugin is-installed redis-cache; then
  wp plugin install redis-cache --activate
else
  wp plugin activate redis-cache || true
fi
wp redis enable || true

wp rewrite flush --hard

echo
echo "M.H CORP is ready."
echo "Site:  ${WP_URL}"
echo "Admin: ${WP_URL}/wp-admin"
echo "User:  ${WP_ADMIN_USER}"
echo "Pass:  (from WP_ADMIN_PASSWORD in .env — not printed)"
echo "Change the admin password before any public deployment."
