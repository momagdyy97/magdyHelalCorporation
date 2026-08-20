<?php
/**
 * Post card.
 *
 * @package Magdi_Hilal_Adco
 */
if (function_exists('mha_render_news_card')) {
    mha_render_news_card(get_post());
    return;
}
?>
<article <?php post_class('mha-news-card'); ?>>
    <a href="<?php the_permalink(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('mha-news-thumb'); ?>
        <?php else : ?>
            <img src="<?php echo esc_url(mha_img('logo-mark.png')); ?>" alt="">
        <?php endif; ?>
    </a>
    <div class="mha-news-body">
        <time><?php echo esc_html(get_the_date()); ?></time>
        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <p><?php echo esc_html(get_the_excerpt()); ?></p>
        <a class="mha-news-more" href="<?php the_permalink(); ?>">اقرأ المزيد</a>
    </div>
</article>
