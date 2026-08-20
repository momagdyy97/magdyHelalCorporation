<?php
/**
 * Template Name: من نحن
 *
 * @package Magdi_Hilal_Adco
 */

get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <p class="mha-kicker">M.H CORP</p>
        <h1>من نحن</h1>
        <p>مكتب محاسبة ومراجعة في مدينة نصر — مكتب مجدي هلال.</p>
    </div>
</section>
<section class="mha-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4">
                <div class="mha-prose">
                    <h2>قصة المكتب</h2>
                    <p><?php echo esc_html(mha_mod('mha_about_lead', mha_defaults()['about_lead'])); ?></p>
                    <p>المكتب يخدم شركات وليس أفراداً بالدرجة الأولى: إمساك دفاتر، أعمال ضريبية، مراجعة، واستشارات تساعد الإدارة على قراءة المركز المالي قبل أن تتحول المشكلة إلى فحص أو خسائر دفترية.</p>
                    <p>العنوان: <?php echo esc_html(mha_mod('mha_address', mha_defaults()['address'])); ?>. للتواصل: <?php echo mha_phones_inline(); ?> — <span dir="ltr"><?php echo esc_html(mha_public_email()); ?></span></p>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <img class="mha-round-img" src="<?php echo esc_url(mha_img('about-office.png')); ?>" alt="مكتب مجدي هلال">
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6 mb-4">
                <article class="mha-value-card">
                    <h3>مجدي هلال</h3>
                    <p>مدير المكتب. محاسب قانوني ومستشار ضريبي، يتابع ملفات الشركات والأعمال الضريبية والمراجعة.</p>
                </article>
            </div>
            <div class="col-md-6 mb-4">
                <article class="mha-value-card">
                    <h3>فريق المحاسبين</h3>
                    <p>يعمل في المكتب نحو 20 إلى 30 محاسباً في المحاسبة والضرائب والمراجعة، كوحدة واحدة مع مدير المكتب.</p>
                </article>
            </div>
        </div>
    </div>
</section>
<?php get_template_part('template-parts/stats'); ?>
<?php get_footer();
