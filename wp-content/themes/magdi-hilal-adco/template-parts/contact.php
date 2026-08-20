<?php
/**
 * RTL contact form: name, email, message.
 *
 * @package Magdi_Hilal_Adco
 */
?>
<section class="mha-section mha-contact" id="contact">
    <div class="container">
        <div class="mha-section-head">
            <h2>تواصل معنا</h2>
            <p>الاسم، البريد، والرسالة — نرد من المكتب في مدينة نصر.</p>
        </div>
        <div class="mha-contact-facts">
            <div class="mha-contact-fact">
                <?php echo mha_icon('phone'); ?>
                <strong>الهاتف</strong>
                <span class="mha-contact-phones">
                    <?php foreach (mha_office_phones() as $raw) : ?>
                        <?php echo mha_phone_anchor($raw); ?>
                    <?php endforeach; ?>
                </span>
            </div>
            <a class="mha-contact-fact" href="mailto:<?php echo esc_attr(mha_public_email()); ?>">
                <?php echo mha_icon('mail'); ?>
                <strong>البريد</strong>
                <span dir="ltr"><?php echo esc_html(mha_public_email()); ?></span>
            </a>
            <div class="mha-contact-fact">
                <?php echo mha_icon('pin'); ?>
                <strong>العنوان</strong>
                <span><?php echo esc_html(mha_mod('mha_address', mha_defaults()['address'])); ?></span>
            </div>
        </div>
        <?php mha_notice('contact'); ?>
        <form class="mha-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mha_contact">
            <?php wp_nonce_field('mha_contact', 'mha_contact_nonce'); ?>
            <div class="mha-honeypot" aria-hidden="true">
                <label>الموقع<input type="text" name="mha_company_website" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="row">
                <div class="col-lg-5">
                    <label class="sr-only" for="mha-name">الاسم كامل</label>
                    <input id="mha-name" type="text" name="mha_name" required placeholder="أدخل الاسم كامل">
                    <label class="sr-only" for="mha-email">البريد الإلكتروني</label>
                    <input id="mha-email" type="email" name="mha_email" required placeholder="أدخل البريد الإلكتروني">
                </div>
                <div class="col-lg-7">
                    <label class="sr-only" for="mha-message">محتوى الرسالة</label>
                    <textarea id="mha-message" name="mha_message" required placeholder="أدخل محتوى الرسالة" rows="6"></textarea>
                </div>
            </div>
            <button class="mha-btn mha-btn-primary" type="submit">إرسال</button>
        </form>
    </div>
</section>
