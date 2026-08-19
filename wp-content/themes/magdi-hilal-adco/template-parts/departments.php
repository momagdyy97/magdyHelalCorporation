<?php
/**
 * Department masonry / overlay grid.
 *
 * @package Magdi_Hilal_Adco
 */
?>
<section class="mha-departments">
    <div class="container">
        <div class="mha-section-head mha-section-head-light">
            <h2>أقسام المكتب</h2>
            <p>مراجعة، ضرائب، واستشارات — يعملون معاً على ملف الشركة.</p>
        </div>
        <div class="row">
            <?php foreach (mha_departments() as $i => $dept) : ?>
                <div class="col-lg-4 mb-4">
                    <article class="mha-dept <?php echo $i === 1 ? 'mha-dept-accent' : ''; ?>" style="background-image:url('<?php echo esc_url(mha_img($dept['image'])); ?>')">
                        <div class="mha-dept-body">
                            <h3><?php echo esc_html($dept['title']); ?></h3>
                            <p><?php echo esc_html($dept['text']); ?></p>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
