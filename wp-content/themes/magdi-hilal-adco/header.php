<?php
/**
 * Header — top info bar + RTL navigation.
 *
 * @package Magdi_Hilal_Adco
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo esc_url(mha_img('logo-mark.png')); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(mha_img('logo-mark.png')); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="mha-topbar">
    <div class="container">
        <div class="mha-topbar-inner">
            <div class="mha-topbar-contacts">
                <a href="mailto:<?php echo esc_attr(mha_public_email()); ?>" class="mha-top-link mha-top-email">
                    <?php echo mha_icon('mail'); ?>
                    <span dir="ltr"><?php echo esc_html(mha_public_email()); ?></span>
                </a>
                <span class="mha-top-link mha-top-phone">
                    <?php echo mha_icon('phone'); ?>
                    <?php echo mha_phones_inline(); ?>
                </span>
            </div>
            <div class="mha-topbar-hours">
                <?php echo mha_icon('clock'); ?>
                <span><?php echo esc_html(mha_mod('mha_hours', mha_defaults()['hours'])); ?></span>
            </div>
        </div>
    </div>
</div>

<header class="mha-header">
    <nav class="navbar navbar-expand-xl navbar-light">
        <div class="container">
            <a class="navbar-brand mha-brand mb-0" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                <img src="<?php echo esc_url(mha_img('logo.png')); ?>" alt="HELAL CORP — مكتب مجدي هلال" class="mha-brand-logo custom-logo" width="215" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mhaNav" aria-controls="mhaNav" aria-expanded="false" aria-label="القائمة">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mhaNav">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'navbar-nav mr-auto mha-nav',
                    'fallback_cb'    => 'mha_nav_fallback',
                    'walker'         => new MHA_Walker_Nav(),
                    'depth'          => 2,
                ]);
                ?>
                <form class="mha-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <label class="sr-only" for="mha-q">بحث</label>
                    <input id="mha-q" type="search" name="s" placeholder="بحث..." value="<?php echo esc_attr(get_search_query()); ?>">
                    <button type="submit" aria-label="بحث"><?php echo mha_icon('search'); ?></button>
                </form>
            </div>
        </div>
    </nav>
</header>

<main id="content" class="mha-main">
