<?php
/**
 * Contact form and newsletter handlers.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

function mha_handle_contact()
{
    if (!isset($_POST['mha_contact_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mha_contact_nonce'])), 'mha_contact')) {
        wp_safe_redirect(add_query_arg('contact', 'invalid', wp_get_referer() ?: home_url('/')));
        exit;
    }

    if (!empty($_POST['mha_company_website'])) {
        wp_safe_redirect(add_query_arg('contact', 'ok', wp_get_referer() ?: home_url('/')));
        exit;
    }

    $name    = sanitize_text_field(wp_unslash($_POST['mha_name'] ?? ''));
    $email   = sanitize_email(wp_unslash($_POST['mha_email'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['mha_message'] ?? ''));

    if ($name === '' || $email === '' || $message === '' || !is_email($email)) {
        wp_safe_redirect(add_query_arg('contact', 'error', wp_get_referer() ?: home_url('/')));
        exit;
    }

    $post_id = wp_insert_post([
        'post_type'    => 'mha_message',
        'post_status'  => 'private',
        'post_title'   => $name . ' — ' . $email,
        'post_content' => $message,
    ]);

    if ($post_id && !is_wp_error($post_id)) {
        update_post_meta($post_id, 'mha_email', $email);
        $to = mha_public_email();
        wp_mail($to, 'رسالة جديدة من موقع مكتب مجدي هلال — HELAL CORP: ' . $name, $message . "\n\n" . $email);
    }

    wp_safe_redirect(add_query_arg('contact', 'ok', wp_get_referer() ?: home_url('/')));
    exit;
}
add_action('admin_post_nopriv_mha_contact', 'mha_handle_contact');
add_action('admin_post_mha_contact', 'mha_handle_contact');

function mha_handle_newsletter()
{
    if (!isset($_POST['mha_news_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mha_news_nonce'])), 'mha_newsletter')) {
        wp_safe_redirect(add_query_arg('newsletter', 'invalid', wp_get_referer() ?: home_url('/')));
        exit;
    }

    $email = sanitize_email(wp_unslash($_POST['mha_newsletter_email'] ?? ''));
    if (!is_email($email)) {
        wp_safe_redirect(add_query_arg('newsletter', 'error', wp_get_referer() ?: home_url('/')));
        exit;
    }

    $existing = get_page_by_title($email, OBJECT, 'mha_subscriber');
    if (!$existing) {
        wp_insert_post([
            'post_type'   => 'mha_subscriber',
            'post_status' => 'private',
            'post_title'  => $email,
        ]);
    }

    wp_safe_redirect(add_query_arg('newsletter', 'ok', wp_get_referer() ?: home_url('/')));
    exit;
}
add_action('admin_post_nopriv_mha_newsletter', 'mha_handle_newsletter');
add_action('admin_post_mha_newsletter', 'mha_handle_newsletter');

function mha_notice($query_key)
{
    if (empty($_GET[$query_key])) {
        return;
    }
    $map = [
        'ok'      => ['تم الإرسال بنجاح. سنتواصل معكم قريباً.', 'ok'],
        'error'   => ['يرجى التأكد من الاسم والبريد ومحتوى الرسالة.', 'err'],
        'invalid' => ['انتهت صلاحية النموذج. حدّث الصفحة ثم أعد المحاولة.', 'err'],
    ];
    $status = sanitize_key(wp_unslash($_GET[$query_key]));
    if (!isset($map[$status])) {
        return;
    }
    printf(
        '<div class="mha-alert mha-alert-%s" role="status">%s</div>',
        esc_attr($map[$status][1]),
        esc_html($map[$status][0])
    );
}
