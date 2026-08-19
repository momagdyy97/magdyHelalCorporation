<?php
/**
 * Customizer: contact details, stats, hero copy.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

function mha_customize_register($wp_customize)
{
    $wp_customize->add_panel('mha_panel', [
        'title'    => 'مكتب مجدي هلال',
        'priority' => 30,
    ]);

    $wp_customize->add_section('mha_contact', [
        'title' => 'بيانات التواصل',
        'panel' => 'mha_panel',
    ]);

    $fields = [
        'mha_hours'     => ['ساعات العمل', mha_defaults()['hours']],
        'mha_phone'     => ['الهاتف (+201000354045)', mha_defaults()['phone']],
        'mha_phone_alt' => ['هاتف إضافي', mha_defaults()['phone_alt']],
        'mha_whatsapp'  => ['واتساب (دولي بدون +)', mha_defaults()['whatsapp']],
        'mha_email'     => ['البريد', mha_defaults()['email']],
        'mha_address'   => ['العنوان', mha_defaults()['address']],
    ];

    foreach ($fields as $id => $meta) {
        $wp_customize->add_setting($id, ['default' => $meta[1], 'sanitize_callback' => 'sanitize_text_field']);
        $wp_customize->add_control($id, [
            'label'   => $meta[0],
            'section' => 'mha_contact',
            'type'    => 'text',
        ]);
    }

    $wp_customize->add_section('mha_hero', [
        'title' => 'الشريحة الرئيسية',
        'panel' => 'mha_panel',
    ]);

    $hero = [
        'mha_hero_kicker' => ['سطر صغير', mha_defaults()['hero_kicker']],
        'mha_hero_title'  => ['العنوان', mha_defaults()['hero_title']],
        'mha_hero_text'   => ['النص', mha_defaults()['hero_text']],
        'mha_hero_cta'    => ['زر الدعوة', mha_defaults()['hero_cta']],
    ];
    foreach ($hero as $id => $meta) {
        $cb = $id === 'mha_hero_text' ? 'sanitize_textarea_field' : 'sanitize_text_field';
        $wp_customize->add_setting($id, ['default' => $meta[1], 'sanitize_callback' => $cb]);
        $wp_customize->add_control($id, [
            'label'   => $meta[0],
            'section' => 'mha_hero',
            'type'    => $id === 'mha_hero_text' ? 'textarea' : 'text',
        ]);
    }

    $wp_customize->add_section('mha_stats', [
        'title' => 'الأرقام',
        'panel' => 'mha_panel',
    ]);

    foreach (['mha_stat_years' => 'سنوات الخبرة', 'mha_stat_clients' => 'العملاء', 'mha_stat_team' => 'أعضاء الفريق', 'mha_stat_depts' => 'الأقسام'] as $id => $label) {
        $key = str_replace('mha_', '', $id);
        $wp_customize->add_setting($id, ['default' => mha_defaults()[$key] ?? '', 'sanitize_callback' => 'sanitize_text_field']);
        $wp_customize->add_control($id, ['label' => $label, 'section' => 'mha_stats', 'type' => 'text']);
    }

    $wp_customize->add_section('mha_about', [
        'title' => 'نبذة المكتب',
        'panel' => 'mha_panel',
    ]);
    $wp_customize->add_setting('mha_about_lead', [
        'default'           => mha_defaults()['about_lead'],
        'sanitize_callback' => 'sanitize_textarea_field',
    ]);
    $wp_customize->add_control('mha_about_lead', [
        'label'   => 'النص التعريفي',
        'section' => 'mha_about',
        'type'    => 'textarea',
    ]);
}
add_action('customize_register', 'mha_customize_register');
