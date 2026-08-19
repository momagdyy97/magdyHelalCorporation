<?php
/**
 * Custom post types: services, team, clients, projects, inquiries, subscribers.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

function mha_register_cpts()
{
    $public = [
        'public'             => true,
        'show_in_rest'       => true,
        'has_archive'        => true,
        'rewrite'            => true,
        'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
        'menu_icon'          => 'dashicons-portfolio',
    ];

    register_post_type('mha_service', array_merge($public, [
        'label'     => 'الخدمات',
        'menu_icon' => 'dashicons-clipboard',
        'rewrite'   => ['slug' => 'service'],
        'has_archive' => 'services-archive',
    ]));

    register_post_type('mha_team', array_merge($public, [
        'label'     => 'فريق العمل',
        'menu_icon' => 'dashicons-groups',
        'rewrite'   => ['slug' => 'member'],
        'supports'  => ['title', 'editor', 'thumbnail'],
        'has_archive' => false,
    ]));

    register_post_type('mha_client', array_merge($public, [
        'label'     => 'العملاء',
        'menu_icon' => 'dashicons-building',
        'rewrite'   => ['slug' => 'client'],
        'supports'  => ['title', 'thumbnail', 'excerpt'],
        'has_archive' => false,
    ]));

    register_post_type('mha_project', array_merge($public, [
        'label'     => 'المشاريع',
        'menu_icon' => 'dashicons-analytics',
        'rewrite'   => ['slug' => 'project'],
    ]));

    register_post_type('mha_message', [
        'label'               => 'رسائل التواصل',
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'supports'            => ['title', 'editor'],
        'menu_icon'           => 'dashicons-email-alt',
        'capability_type'     => 'post',
    ]);

    register_post_type('mha_subscriber', [
        'label'               => 'النشرة البريدية',
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'supports'            => ['title'],
        'menu_icon'           => 'dashicons-email',
        'capability_type'     => 'post',
    ]);
}
add_action('init', 'mha_register_cpts');

function mha_query_posts($type, $n = 8)
{
    return get_posts([
        'post_type'      => $type,
        'posts_per_page' => $n,
        'post_status'    => 'publish',
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ]);
}
