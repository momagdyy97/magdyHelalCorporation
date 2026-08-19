FROM wordpress:php8.2-apache

RUN pecl install redis \
    && docker-php-ext-enable redis

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-magdi-hilal.ini
COPY wp-content/themes/magdi-hilal-adco /usr/src/wordpress/wp-content/themes/magdi-hilal-adco
COPY wp-content/mu-plugins /usr/src/wordpress/wp-content/mu-plugins
COPY docker/mha-entrypoint.sh /usr/local/bin/mha-entrypoint.sh

RUN chmod +x /usr/local/bin/mha-entrypoint.sh \
    && chown -R www-data:www-data \
      /usr/src/wordpress/wp-content/themes/magdi-hilal-adco \
      /usr/src/wordpress/wp-content/mu-plugins

ENTRYPOINT ["mha-entrypoint.sh"]
CMD ["apache2-foreground"]
