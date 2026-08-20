<?php
/**
 * Theme supports, menus, and image sizes.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

function mha_setup()
{
    load_theme_textdomain('magdi-hilal-adco', MHA_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 420,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('customize-selective-refresh-widgets');

    register_nav_menus([
        'primary' => __('القائمة الرئيسية', 'magdi-hilal-adco'),
        'footer'  => __('قائمة التذييل', 'magdi-hilal-adco'),
    ]);

    add_image_size('mha-card', 800, 560, true);
    add_image_size('mha-hero', 1600, 900, true);
    add_image_size('mha-news-thumb', 240, 240, true);

    add_filter('locale', static function ($locale) {
        return is_admin() ? $locale : 'ar';
    });
}
add_action('after_setup_theme', 'mha_setup');

function mha_widgets()
{
    register_sidebar([
        'name'          => __('الشريط الجانبي', 'magdi-hilal-adco'),
        'id'            => 'sidebar-1',
        'before_widget' => '<section class="mha-widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="mha-widget-title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'mha_widgets');

function mha_excerpt_length()
{
    return 28;
}
add_filter('excerpt_length', 'mha_excerpt_length');

function mha_excerpt_more()
{
    return '…';
}
add_filter('excerpt_more', 'mha_excerpt_more');

function mha_body_classes($classes)
{
    $classes[] = 'mha-site';
    if (is_rtl()) {
        $classes[] = 'rtl';
    }
    return $classes;
}
add_filter('body_class', 'mha_body_classes');

class MHA_Walker_Nav extends Walker_Nav_Menu
{
    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        $output .= '<div class="dropdown-menu"><ul class="list-unstyled mb-0">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        $output .= '</ul></div>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes, true);
        $active = in_array('current-menu-item', $classes, true) || in_array('current-menu-parent', $classes, true);

        if ($depth === 0) {
            $li = 'nav-item' . ($has_children ? ' dropdown' : '') . ($active ? ' active' : '');
            $link = 'nav-link' . ($has_children ? ' dropdown-toggle' : '');
            $extra = $has_children ? ' data-toggle="dropdown" aria-haspopup="true"' : '';
            $output .= '<li class="' . esc_attr($li) . '">';
            $output .= '<a class="' . esc_attr($link) . '" href="' . esc_url($item->url) . '"' . $extra . '>' . esc_html($item->title) . '</a>';
        } else {
            $output .= '<li><a class="dropdown-item" href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
        }
    }
}
