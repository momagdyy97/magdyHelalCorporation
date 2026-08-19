<?php
/**
 * Archives.
 *
 * @package Magdi_Hilal_Adco
 */
get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <h1><?php the_archive_title(); ?></h1>
        <?php the_archive_description(); ?>
    </div>
</section>
<section class="mha-section">
    <div class="container">
        <div class="row">
            <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
                <div class="col-md-6 col-lg-4 mb-4"><?php get_template_part('template-parts/content'); ?></div>
            <?php endwhile; else : ?>
                <?php get_template_part('template-parts/content', 'none'); ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php get_footer();
