#!/usr/bin/env bash
# Copies the theme and mu-plugins from the image into the WordPress html tree.
# Local compose sets MHA_SYNC_THEME=0 so bind-mounted source files are not overwritten.
set -euo pipefail

if [ "${MHA_SYNC_THEME:-1}" = "1" ]; then
  mkdir -p /var/www/html/wp-content/themes /var/www/html/wp-content/mu-plugins
  if [ -d /usr/src/wordpress/wp-content/themes/magdi-hilal-adco ]; then
    rm -rf /var/www/html/wp-content/themes/magdi-hilal-adco
    cp -a /usr/src/wordpress/wp-content/themes/magdi-hilal-adco /var/www/html/wp-content/themes/
  fi
  if [ -d /usr/src/wordpress/wp-content/mu-plugins ]; then
    cp -a /usr/src/wordpress/wp-content/mu-plugins/. /var/www/html/wp-content/mu-plugins/
  fi
  chown -R www-data:www-data \
    /var/www/html/wp-content/themes/magdi-hilal-adco \
    /var/www/html/wp-content/mu-plugins 2>/dev/null || true
fi

exec docker-entrypoint.sh "$@"
