<?php
/**
 * Default index / blog.
 *
 * @package Magdi_Hilal_Adco
 */

get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <h1><?php echo is_home() ? 'الأخبار' : wp_get_document_title(); ?></h1>
        <p>ملاحظات مهنية من مكتب مجدي هلال حول المحاسبة والضرائب والمراجعة.</p>
    </div>
</section>
<section class="mha-section">
    <div class="container">
        <div class="row">
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : the_post(); ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <?php get_template_part('template-parts/content'); ?>
                    </div>
                <?php endwhile; ?>
                <div class="col-12"><?php the_posts_pagination(); ?></div>
            <?php else : ?>
                <?php get_template_part('template-parts/content', 'none'); ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
get_footer();
