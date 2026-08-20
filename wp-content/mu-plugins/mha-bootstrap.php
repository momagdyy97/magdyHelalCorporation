<?php
/**
 * Plugin Name: MAGDY HELAL CORP Bootstrap
 * Description: Optional Redis constants on Docker; seed reminder; email allow-list.
 * Author: MAGDY HELAL CORP
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Redis is optional. InfinityFree shared hosting has no Redis service.
 * Docker compose sets WP_REDIS_HOST in WORDPRESS_CONFIG_EXTRA.
 * Do not default the hostname to "redis" — that would break shared hosts.
 */
if (!defined('WP_REDIS_HOST')) {
    $mha_redis = getenv('WP_REDIS_HOST');
    if (is_string($mha_redis) && $mha_redis !== '') {
        define('WP_REDIS_HOST', $mha_redis);
    }
}
if (!defined('WP_REDIS_PORT')) {
    define('WP_REDIS_PORT', 6379);
}
if (!defined('WP_REDIS_PREFIX')) {
    define('WP_REDIS_PREFIX', 'mha:');
}

add_filter('file_mod_allowed', static function ($allowed, $context) {
    return $context === 'download' ? $allowed : $allowed;
}, 10, 2);

add_action('admin_notices', static function () {
    if (!current_user_can('manage_options') || get_option('mha_content_seeded')) {
        return;
    }
    echo '<div class="notice notice-info"><p>فعّل قالب MAGDY HELAL CORP ثم حدّث الصفحة لإنشاء الصفحات، أو شغّل <code>scripts/setup.sh</code>.</p></div>';
});

add_filter('is_email', static function ($is_email, $email) {
    if (!is_string($email)) {
        return $is_email;
    }
    $allow = [
        'momagdyy97@gmail.com',
        'magdy.hilal@co',
    ];
    foreach ($allow as $ok) {
        if (strcasecmp($email, $ok) === 0) {
            return $email;
        }
    }
    return $is_email;
}, 10, 2);
