<?php
/**
 * Horizontal news carousel (home + archive).
 *
 * @package Magdi_Hilal_Adco
 *
 * @var array $args
 */

$args     = is_array($args ?? null) ? $args : [];
$count    = isset($args['count']) ? (int) $args['count'] : 8;
$show_all = array_key_exists('show_all', $args) ? (bool) $args['show_all'] : true;
$heading  = array_key_exists('heading', $args) ? (bool) $args['heading'] : true;
$uid      = isset($args['uid']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $args['uid']) : 'mhaNews';
$posts    = mha_get_news_posts($count);
$fallback = [];

if (!$posts) {
    foreach (mha_curated_news() as $item) {
        $fallback[] = $item;
        if (count($fallback) >= 6) {
            break;
        }
    }
}

$label_id = $uid . 'Label';
?>
<section class="mha-section mha-news" aria-labelledby="<?php echo esc_attr($label_id); ?>">
    <div class="container">
        <div class="mha-news-toolbar">
            <div class="mha-section-head mb-0">
                <?php if ($heading) : ?>
                    <h2 id="<?php echo esc_attr($label_id); ?>">أحدث الأخبار</h2>
                    <p>محاسبة، ضرائب، ومنظومات إلكترونية مصرية — مع سياق اقتصادي للمحاسب.</p>
                <?php else : ?>
                    <h2 id="<?php echo esc_attr($label_id); ?>" class="sr-only">الأخبار</h2>
                <?php endif; ?>
            </div>
            <div class="mha-news-navs">
                <button type="button" class="mha-news-btn mha-news-prev" data-news-dir="prev" aria-controls="<?php echo esc_attr($uid); ?>Track" aria-label="السابق">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15 6l-1.4 1.4L17.2 11H3v2h14.2l-3.6 3.6L15 18l6-6z"/></svg>
                </button>
                <button type="button" class="mha-news-btn mha-news-next" data-news-dir="next" aria-controls="<?php echo esc_attr($uid); ?>Track" aria-label="التالي">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9 6l1.4 1.4L6.8 11H21v2H6.8l3.6 3.6L9 18l-6-6z"/></svg>
                </button>
            </div>
        </div>

        <div class="mha-news-viewport">
            <div
                id="<?php echo esc_attr($uid); ?>Track"
                class="mha-news-track"
                role="list"
                tabindex="0"
                aria-roledescription="carousel"
                aria-labelledby="<?php echo esc_attr($label_id); ?>"
            >
                <?php if ($posts) : ?>
                    <?php foreach ($posts as $item) : ?>
                        <?php mha_render_news_card($item); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php foreach ($fallback as $item) : ?>
                        <article class="mha-news-slide" role="listitem">
                            <div class="mha-news-card">
                                <div class="mha-news-source-row">
                                    <img class="mha-news-favicon" src="<?php echo esc_url(mha_img('logo-mark.png')); ?>" alt="" width="18" height="18">
                                    <span class="mha-news-outlet"><?php echo esc_html($item['outlet'] ?? 'M.H CORP'); ?></span>
                                </div>
                                <div class="mha-news-main">
                                    <div class="mha-news-copy">
                                        <h3><?php echo esc_html($item['title']); ?></h3>
                                        <p><?php echo esc_html($item['excerpt']); ?></p>
                                        <div class="mha-news-foot">
                                            <time><?php echo esc_html($item['source_date']); ?></time>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($show_all) : ?>
            <div class="text-center mha-news-all">
                <a class="mha-btn mha-btn-outline" href="<?php echo esc_url(mha_news_url()); ?>">عرض كل الأخبار</a>
            </div>
        <?php endif; ?>
    </div>
</section>
