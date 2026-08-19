<?php
/**
 * Styles and scripts — Bootstrap 4 + theme assets.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

function mha_enqueue()
{
    wp_enqueue_style(
        'mha-cairo',
        'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'bootstrap-4',
        'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css',
        [],
        '4.6.2'
    );

    wp_enqueue_style(
        'mha-theme',
        MHA_URI . '/assets/css/theme.css',
        ['bootstrap-4', 'mha-cairo'],
        MHA_VERSION
    );

    wp_enqueue_script('jquery');

    wp_enqueue_script(
        'popper',
        'https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js',
        ['jquery'],
        '1.16.1',
        true
    );

    wp_enqueue_script(
        'bootstrap-4',
        'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js',
        ['jquery', 'popper'],
        '4.6.2',
        true
    );

    wp_enqueue_script(
        'mha-theme',
        MHA_URI . '/assets/js/theme.js',
        ['jquery', 'bootstrap-4'],
        MHA_VERSION,
        true
    );

    wp_localize_script('mha-theme', 'mhaTheme', [
        'home' => home_url('/'),
    ]);
}
add_action('wp_enqueue_scripts', 'mha_enqueue');

function mha_resource_hints($urls, $relation)
{
    if ($relation === 'preconnect') {
        $urls[] = ['href' => 'https://fonts.googleapis.com', 'crossorigin' => true];
        $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => true];
        $urls[] = 'https://cdn.jsdelivr.net';
    }
    return $urls;
}
add_filter('wp_resource_hints', 'mha_resource_hints', 10, 2);
