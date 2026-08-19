<?php
/**
 * 404.
 *
 * @package Magdi_Hilal_Adco
 */
get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <h1>الصفحة غير موجودة</h1>
        <p>الرابط غير صحيح أو نُقلت الصفحة. يمكنكم العودة للرئيسية أو البحث من الأعلى.</p>
        <a class="mha-btn mha-btn-primary" href="<?php echo esc_url(home_url('/')); ?>">العودة للرئيسية</a>
    </div>
</section>
<?php get_footer();
