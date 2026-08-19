<?php
/**
 * Plugin Name: MAGDY HELAL CORP Bootstrap
 * Description: Local defaults — Redis, UTF-8, and seed reminders.
 * Author: MAGDY HELAL CORP
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_REDIS_HOST')) {
    define('WP_REDIS_HOST', 'redis');
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
    if (is_string($email) && strcasecmp($email, 'magdy.hilal@co') === 0) {
        return $email;
    }
    return $is_email;
}, 10, 2);
