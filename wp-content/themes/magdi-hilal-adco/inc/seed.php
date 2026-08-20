<?php
/**
 * One-time demo content if WP-CLI setup did not run.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

function mha_maybe_seed()
{
    if ((int) get_option('mha_brand_version') < 6) {
        mha_apply_branding(true);
    }

    if (!is_admin() && php_sapi_name() !== 'cli') {
        return;
    }

    if (!get_option('mha_content_seeded')) {
        mha_seed_content();
        return;
    }
}
add_action('init', 'mha_maybe_seed', 30);
add_action('after_switch_theme', 'mha_seed_content');

function mha_seed_content()
{
    if (!get_option('mha_content_seeded')) {
        mha_create_demo_content();
        update_option('mha_content_seeded', 1);
        flush_rewrite_rules(false);
    }

    mha_apply_branding(true);

    if (function_exists('mha_seed_news')) {
        mha_seed_news(true);
    }
    if (function_exists('mha_chat_install')) {
        mha_chat_install(true);
    }

    return true;
}

function mha_create_demo_content()
{
    $pages = [
        'about'    => ['من نحن', 'page-templates/about.php'],
        'services' => ['خدماتنا', 'page-templates/services.php'],
        'team'     => ['فريق العمل', 'page-templates/team.php'],
        'clients'  => ['عملاؤنا', 'page-templates/clients.php'],
        'projects' => ['مشاريعنا', 'page-templates/projects.php'],
        'contact'  => ['تواصل معنا', 'page-templates/contact.php'],
        'news'     => ['الأخبار', ''],
    ];

    $ids = [];
    foreach ($pages as $slug => $meta) {
        $existing = get_page_by_path($slug);
        if ($existing) {
            $ids[$slug] = $existing->ID;
            continue;
        }
        $page = [
            'post_title'   => $meta[0],
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ];
        $id = wp_insert_post($page);
        if ($id && $meta[1]) {
            update_post_meta($id, '_wp_page_template', $meta[1]);
        }
        $ids[$slug] = $id;
    }

    $home = get_page_by_path('home');
    if (!$home) {
        $home_id = wp_insert_post([
            'post_title'  => 'الرئيسية',
            'post_name'   => 'home',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ]);
    } else {
        $home_id = $home->ID;
    }

    update_option('show_on_front', 'page');
    update_option('page_on_front', $home_id);
    if (!empty($ids['news'])) {
        update_option('page_for_posts', $ids['news']);
    }

    if (!get_posts(['post_type' => 'post', 'numberposts' => 1, 'post_status' => 'any'])) {
        if (function_exists('mha_seed_news')) {
            mha_seed_news(true);
        }
    } elseif (function_exists('mha_seed_news') && (int) get_option('mha_news_version') < 1) {
        mha_seed_news(true);
    }

    if (function_exists('mha_chat_install')) {
        mha_chat_install(true);
    }

    if (!mha_query_posts('mha_service', 1)) {
        foreach (mha_services() as $service) {
            wp_insert_post([
                'post_title'   => $service['title'],
                'post_content' => $service['text'],
                'post_excerpt' => $service['text'],
                'post_status'  => 'publish',
                'post_type'    => 'mha_service',
                'post_name'    => $service['slug'],
            ]);
        }
    }

    if (!mha_query_posts('mha_team', 1)) {
        foreach (mha_placeholder_team() as $member) {
            wp_insert_post([
                'post_title'   => $member[0],
                'post_content' => $member[1],
                'post_excerpt' => $member[1],
                'post_status'  => 'publish',
                'post_type'    => 'mha_team',
            ]);
        }
    }

    if (!mha_query_posts('mha_client', 1)) {
        foreach (mha_placeholder_clients() as $client) {
            wp_insert_post([
                'post_title'   => $client[0],
                'post_excerpt' => $client[1],
                'post_status'  => 'publish',
                'post_type'    => 'mha_client',
            ]);
        }
    }

    if (!mha_query_posts('mha_project', 1)) {
        foreach (mha_placeholder_projects() as $project) {
            wp_insert_post([
                'post_title'   => $project[0],
                'post_content' => 'نموذج لمشروع يمكن استبداله لاحقاً بتفاصيل العمل الفعلي للمكتب.',
                'post_excerpt' => $project[1] . ' — ' . $project[2],
                'post_status'  => 'publish',
                'post_type'    => 'mha_project',
            ]);
        }
    }

    $menu_name = 'القائمة الرئيسية';
    $menu = wp_get_nav_menu_object($menu_name);
    if (!$menu) {
        $menu_id = wp_create_nav_menu($menu_name);
        $map = [
            ['الرئيسية', home_url('/')],
            ['من نحن', $ids['about'] ?? 0],
            ['خدماتنا', $ids['services'] ?? 0],
            ['فريق العمل', $ids['team'] ?? 0],
            ['عملاؤنا', $ids['clients'] ?? 0],
            ['مشاريعنا', $ids['projects'] ?? 0],
            ['الأخبار', $ids['news'] ?? 0],
            ['تواصل معنا', $ids['contact'] ?? 0],
        ];
        foreach ($map as $item) {
            $args = [
                'menu-item-title'  => $item[0],
                'menu-item-status' => 'publish',
                'menu-item-type'   => is_int($item[1]) && $item[1] ? 'post_type' : 'custom',
            ];
            if (is_int($item[1]) && $item[1]) {
                $args['menu-item-object'] = 'page';
                $args['menu-item-object-id'] = $item[1];
            } else {
                $args['menu-item-url'] = $item[1];
            }
            wp_update_nav_menu_item($menu_id, 0, $args);
        }
        $locations = get_theme_mod('nav_menu_locations', []);
        $locations['primary'] = $menu_id;
        $locations['footer'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }
}

function mha_apply_branding($force = true)
{
    unset($force);
    $d = mha_defaults();
    $mods = [
        'mha_hours'         => $d['hours'],
        'mha_phone'         => $d['phone'],
        'mha_phone_2'       => $d['phone_2'],
        'mha_phone_alt'     => $d['phone_2'],
        'mha_whatsapp'      => $d['whatsapp'],
        'mha_email'         => $d['email'],
        'mha_address'       => $d['address'],
        'mha_hero_kicker'   => $d['hero_kicker'],
        'mha_hero_title'    => $d['hero_title'],
        'mha_hero_text'     => $d['hero_text'],
        'mha_hero_cta'      => $d['hero_cta'],
        'mha_about_lead'    => $d['about_lead'],
        'mha_stat_years'    => $d['stat_years'],
        'mha_stat_clients'  => $d['stat_clients'],
        'mha_stat_team'     => $d['stat_team'],
        'mha_stat_depts'    => $d['stat_depts'],
    ];
    foreach ($mods as $key => $value) {
        set_theme_mod($key, $value);
    }

    update_option('blogname', 'مكتب مجدي هلال — M.H CORP');
    update_option('blogdescription', 'magdyhelalCORP — محاسبة · ضرائب · مراجعة');

    mha_purge_ibrahim();
    mha_ensure_custom_logo(true);
    update_option('mha_brand_version', 6);
}

function mha_strip_ibrahim_text($text)
{
    $replacements = [
        'مجدي هلال وإبراهيم هلال' => 'مجدي هلال',
        'بالشراكة مع المحاسب إبراهيم هلال، و' => 'و',
        'وإبراهيم هلال و' => 'و',
        'وإبراهيم هلال' => '',
        'إبراهيم هلال' => '',
        'Ibrahim Hilal' => '',
        'Ibrahim Helal' => '',
        'Ibrahim' => '',
    ];
    $text = str_replace(array_keys($replacements), array_values($replacements), (string) $text);
    return trim(preg_replace('/\s{2,}/u', ' ', $text));
}

function mha_purge_ibrahim()
{
    $posts = get_posts([
        'post_type'   => ['mha_team', 'post', 'page', 'mha_service', 'mha_project'],
        'post_status' => 'any',
        'numberposts' => -1,
    ]);

    foreach ($posts as $post) {
        $is_ibrahim = (mb_strpos($post->post_title, 'إبراهيم') !== false)
            || (stripos($post->post_title, 'Ibrahim') !== false);
        if ($is_ibrahim) {
            wp_delete_post($post->ID, true);
            continue;
        }

        if ($post->post_type === 'mha_team' && $post->post_title === 'مجدي هلال') {
            wp_update_post([
                'ID'           => $post->ID,
                'post_content' => 'مدير المكتب — مستشار ضريبي ومحاسب قانوني',
                'post_excerpt' => 'مدير المكتب — مستشار ضريبي ومحاسب قانوني',
            ]);
            continue;
        }

        $hay = $post->post_title . $post->post_content . $post->post_excerpt;
        if (mb_strpos($hay, 'إبراهيم') === false && stripos($hay, 'Ibrahim') === false) {
            continue;
        }

        wp_update_post([
            'ID'           => $post->ID,
            'post_title'   => mha_strip_ibrahim_text($post->post_title),
            'post_content' => mha_strip_ibrahim_text($post->post_content),
            'post_excerpt' => mha_strip_ibrahim_text($post->post_excerpt),
        ]);
    }
}

function mha_ensure_custom_logo($force = false)
{
    $src = MHA_DIR . '/assets/img/logo.png';
    $mark = MHA_DIR . '/assets/img/logo-mark.png';
    if (!is_readable($src)) {
        return;
    }

    $hash = md5_file($src);
    $current = (int) get_theme_mod('custom_logo');
    $attached = $current && get_post($current) && get_attached_file($current) && file_exists(get_attached_file($current));
    if (!$force && $attached && get_option('mha_logo_hash') === $hash) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    if ($current) {
        wp_delete_attachment($current, true);
    }

    $tmp = wp_tempnam('mha-logo.png');
    if (!$tmp || !copy($src, $tmp)) {
        return;
    }

    $file_array = [
        'name'     => 'helal-corp-logo.png',
        'tmp_name' => $tmp,
    ];
    $id = media_handle_sideload($file_array, 0, 'HELAL CORP');
    if (!is_wp_error($id)) {
        set_theme_mod('custom_logo', $id);
        update_option('mha_logo_hash', $hash);
    }

    if (is_readable($mark)) {
        $icon_id = (int) get_option('site_icon');
        if ($icon_id) {
            wp_delete_attachment($icon_id, true);
        }
        $tmp_icon = wp_tempnam('mha-mark.png');
        if ($tmp_icon && copy($mark, $tmp_icon)) {
            $icon = media_handle_sideload([
                'name'     => 'helal-corp-mark.png',
                'tmp_name' => $tmp_icon,
            ], 0, 'HELAL CORP');
            if (!is_wp_error($icon)) {
                update_option('site_icon', (int) $icon);
            }
        }
    }
}
