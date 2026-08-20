<?php
/**
 * Footer — newsletter, links, Nasr City address, floating actions.
 *
 * @package Magdi_Hilal_Adco
 */
?>
</main>

<footer class="mha-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0 mha-footer-col">
                <div class="mha-footer-brand">
                    <img src="<?php echo esc_url(mha_img('logo-white.png')); ?>" alt="HELAL CORP" class="mha-footer-logo" width="240" height="40">
                    <p>مكتب محاسبة ومراجعة واستشارات ضريبية في مدينة نصر، بقيادة المحاسب القانوني والمستشار الضريبي مجدي هلال وفريق يضم نحو 20 إلى 30 محاسباً.</p>
                    <p class="mha-footer-meta">
                        <?php echo mha_icon('pin'); ?>
                        <?php echo esc_html(mha_mod('mha_address', mha_defaults()['address'])); ?>
                    </p>
                    <p class="mha-footer-meta">
                        <?php echo mha_icon('phone'); ?>
                        <?php echo mha_phones_inline(); ?>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 mb-4 mb-lg-0 mha-footer-col">
                <h4>تصفح الموقع</h4>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'mha-footer-links',
                    'fallback_cb'    => false,
                    'depth'          => 1,
                ]);
                ?>
            </div>
            <div class="col-lg-4 mha-footer-col">
                <h4>الخدمة البريدية</h4>
                <p>تواصل معنا ليصلك ملخص عن الأنظمة الضريبية وتحديثات المكتب.</p>
                <?php mha_notice('newsletter'); ?>
                <form class="mha-newsletter" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mha_newsletter">
                    <?php wp_nonce_field('mha_newsletter', 'mha_news_nonce'); ?>
                    <label class="sr-only" for="mha-news-email">البريد الإلكتروني</label>
                    <input id="mha-news-email" type="email" name="mha_newsletter_email" required placeholder="أدخل بريدك الإلكتروني">
                    <button type="submit" aria-label="اشترك"><?php echo mha_icon('send'); ?></button>
                </form>
            </div>
        </div>
        <div class="mha-copy">
            <span>جميع الحقوق محفوظة © <?php echo esc_html(gmdate('Y')); ?> مكتب مجدي هلال — HELAL CORP</span>
        </div>
    </div>
</footer>

<div class="mha-float">
    <?php if (mha_whatsapp_link()) : ?>
    <a class="mha-float-btn mha-float-wa" href="<?php echo esc_url(mha_whatsapp_link()); ?>" target="_blank" rel="noopener" aria-label="واتساب">
        <?php echo mha_icon('whatsapp'); ?>
    </a>
    <?php endif; ?>
    <button type="button" class="mha-float-btn mha-float-chat" id="mhaChatToggle" aria-label="مستشار HELAL CORP" aria-expanded="false" aria-controls="mhaChatPanel">
        <?php echo mha_icon('chat'); ?>
    </button>
</div>
<button type="button" class="mha-top" id="mhaTop" aria-label="العودة للأعلى"><?php echo mha_icon('arrow'); ?></button>

<?php wp_footer(); ?>
</body>
</html>
