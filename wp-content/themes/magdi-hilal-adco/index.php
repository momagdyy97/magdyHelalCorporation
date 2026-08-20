<?php
/**
 * Default index / blog (صفحة الأخبار).
 *
 * @package Magdi_Hilal_Adco
 */

get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <h1><?php echo is_home() ? 'الأخبار' : wp_get_document_title(); ?></h1>
        <p>ملاحظات مهنية من مكتب مجدي هلال حول المحاسبة والضرائب والمراجعة والاقتصاد المصري.</p>
    </div>
</section>
<?php
get_template_part('template-parts/news-carousel', null, [
    'count'    => 16,
    'show_all' => false,
    'heading'  => false,
    'uid'      => 'mhaArchiveNews',
]);
get_footer();
