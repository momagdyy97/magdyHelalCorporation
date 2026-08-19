<?php
/**
 * Single post.
 *
 * @package Magdi_Hilal_Adco
 */

get_header();
?>
<article class="mha-article">
    <?php while (have_posts()) : the_post(); ?>
        <header class="mha-page-head">
            <div class="container">
                <p class="mha-kicker"><?php echo esc_html(get_the_date()); ?></p>
                <h1><?php the_title(); ?></h1>
            </div>
        </header>
        <div class="mha-section">
            <div class="container mha-prose">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="mha-article-hero"><?php the_post_thumbnail('mha-hero'); ?></div>
                <?php endif; ?>
                <?php the_content(); ?>
            </div>
        </div>
    <?php endwhile; ?>
</article>
<?php
get_footer();
