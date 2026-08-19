<?php
/**
 * Hero image slider.
 *
 * @package Magdi_Hilal_Adco
 */
$slides = [
    [
        'image' => mha_img('hero-1.png'),
        'kicker' => mha_mod('mha_hero_kicker', mha_defaults()['hero_kicker']),
        'title' => mha_mod('mha_hero_title', mha_defaults()['hero_title']),
        'text' => mha_mod('mha_hero_text', mha_defaults()['hero_text']),
    ],
    [
        'image' => mha_img('hero-2.png'),
        'kicker' => 'المحاسبة والضرائب والمراجعة',
        'title' => 'شركاء القرار المالي للشركات في مدينة نصر',
        'text' => 'مجدي هلال وفريق المكتب يرافقون الدورة المحاسبية من المستند اليومي حتى التقرير والإقرار.',
    ],
];
?>
<section class="mha-hero">
    <div id="mhaHero" class="carousel slide" data-ride="carousel" data-interval="7000">
        <ol class="carousel-indicators">
            <?php foreach ($slides as $i => $slide) : ?>
                <li data-target="#mhaHero" data-slide-to="<?php echo (int) $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>"></li>
            <?php endforeach; ?>
        </ol>
        <div class="carousel-inner">
            <?php foreach ($slides as $i => $slide) : ?>
                <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                    <div class="mha-hero-slide" style="background-image:url('<?php echo esc_url($slide['image']); ?>')">
                        <div class="mha-hero-overlay">
                            <div class="container">
                                <p class="mha-hero-kicker"><?php echo esc_html($slide['kicker']); ?></p>
                                <h1><?php echo esc_html($slide['title']); ?></h1>
                                <p class="mha-hero-bar"><?php echo esc_html($slide['text']); ?></p>
                                <div class="mha-hero-actions">
                                    <a class="mha-btn mha-btn-gold" href="<?php echo esc_url(mha_page_url('contact')); ?>">
                                        <?php echo esc_html(mha_mod('mha_hero_cta', mha_defaults()['hero_cta'])); ?>
                                    </a>
                                    <a class="mha-btn mha-btn-ghost" href="<?php echo esc_url(mha_page_url('services')); ?>">خدمات المكتب</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <a class="carousel-control-prev" href="#mhaHero" role="button" data-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">السابق</span>
        </a>
        <a class="carousel-control-next" href="#mhaHero" role="button" data-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">التالي</span>
        </a>
    </div>
</section>
