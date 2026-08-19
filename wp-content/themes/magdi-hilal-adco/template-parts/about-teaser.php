<?php
/**
 * About teaser.
 *
 * @package Magdi_Hilal_Adco
 */
?>
<section class="mha-section mha-about-teaser">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="mha-about-copy">
                    <p class="mha-kicker">عن المكتب</p>
                    <h2>مكتب مجدي هلال</h2>
                    <p><?php echo esc_html(mha_mod('mha_about_lead', mha_defaults()['about_lead'])); ?></p>
                    <a class="mha-btn mha-btn-primary" href="<?php echo esc_url(mha_page_url('about')); ?>">عن الشركة</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mha-about-photo">
                    <span class="mha-orbit" aria-hidden="true"></span>
                    <img src="<?php echo esc_url(mha_img('about-office.png')); ?>" alt="مكتب مجدي هلال للمحاسبة">
                </div>
            </div>
        </div>
    </div>
</section>
