<?php
/**
 * Image-overlay service tiles.
 *
 * @package Magdi_Hilal_Adco
 */
$items = mha_query_posts('mha_service', 4);
?>
<section class="mha-section" id="services">
    <div class="container">
        <div class="mha-section-head">
            <h2>خدماتنا</h2>
            <p>محاسبة، ضرائب، مراجعة، واستشارات — بنفس الفريق الذي يعرف ملف الشركة.</p>
        </div>
        <div class="row">
            <?php if ($items) : ?>
                <?php foreach ($items as $i => $item) : ?>
                    <?php
                    $fallback = mha_services()[$i] ?? mha_services()[0];
                    $image = get_the_post_thumbnail_url($item, 'mha-card') ?: mha_img($fallback['image']);
                    ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <a class="mha-service-tile" href="<?php echo esc_url(get_permalink($item)); ?>" style="background-image:url('<?php echo esc_url($image); ?>')">
                            <span class="mha-service-shade"></span>
                            <span class="mha-service-icon"><?php echo mha_icon($fallback['icon']); ?></span>
                            <h3><?php echo esc_html(get_the_title($item)); ?></h3>
                            <p><?php echo esc_html(wp_trim_words($item->post_excerpt ?: $item->post_content, 16)); ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <?php foreach (mha_services() as $service) : ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <a class="mha-service-tile" href="<?php echo esc_url(mha_page_url('services')); ?>" style="background-image:url('<?php echo esc_url(mha_img($service['image'])); ?>')">
                            <span class="mha-service-shade"></span>
                            <span class="mha-service-icon"><?php echo mha_icon($service['icon']); ?></span>
                            <h3><?php echo esc_html($service['title']); ?></h3>
                            <p><?php echo esc_html($service['text']); ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
