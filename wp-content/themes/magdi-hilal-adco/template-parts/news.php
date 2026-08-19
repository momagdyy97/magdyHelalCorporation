<?php
/**
 * Latest news.
 *
 * @package Magdi_Hilal_Adco
 */
$news = get_posts(['numberposts' => 3, 'post_status' => 'publish']);
?>
<section class="mha-section mha-news">
    <div class="container">
        <div class="mha-section-head">
            <h2>أحدث الأخبار</h2>
            <p>مساحة للمقالات والآراء المهنية — يمكن تعبئتها لاحقاً من لوحة ووردبريس.</p>
        </div>
        <div class="row">
            <?php if ($news) : ?>
                <?php foreach ($news as $item) : ?>
                    <div class="col-md-4 mb-4">
                        <article class="mha-news-card">
                            <a href="<?php echo esc_url(get_permalink($item)); ?>">
                                <?php if (has_post_thumbnail($item)) : ?>
                                    <?php echo get_the_post_thumbnail($item, 'mha-card'); ?>
                                <?php else : ?>
                                    <img src="<?php echo esc_url(mha_img('hero-2.png')); ?>" alt="">
                                <?php endif; ?>
                            </a>
                            <div class="mha-news-body">
                                <time><?php echo esc_html(get_the_date('', $item)); ?></time>
                                <h3><a href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></h3>
                                <p><?php echo esc_html(wp_trim_words($item->post_excerpt ?: $item->post_content, 22)); ?></p>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <?php foreach (mha_placeholder_news() as $item) : ?>
                    <div class="col-md-4 mb-4">
                        <article class="mha-news-card">
                            <img src="<?php echo esc_url(mha_img('about-office.png')); ?>" alt="">
                            <div class="mha-news-body">
                                <time>نموذج</time>
                                <h3><?php echo esc_html($item['title']); ?></h3>
                                <p><?php echo esc_html($item['excerpt']); ?></p>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="text-center">
            <a class="mha-btn mha-btn-outline" href="<?php echo esc_url(get_permalink(get_option('page_for_posts')) ?: mha_page_url('news')); ?>">عرض كل الأخبار</a>
        </div>
    </div>
</section>
