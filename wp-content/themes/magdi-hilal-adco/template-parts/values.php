<?php
/**
 * Value cards: excellence, integrity, teamwork.
 *
 * @package Magdi_Hilal_Adco
 */
?>
<section class="mha-section mha-values">
    <div class="container">
        <div class="row">
            <?php foreach (mha_values() as $value) : ?>
                <div class="col-md-4 mb-4 mb-md-0">
                    <article class="mha-value-card">
                        <div class="mha-icon-ring"><?php echo mha_icon($value['icon']); ?></div>
                        <h3><?php echo esc_html($value['title']); ?></h3>
                        <p><?php echo esc_html($value['text']); ?></p>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
