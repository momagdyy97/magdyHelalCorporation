<?php
/**
 * Stats counters.
 *
 * @package Magdi_Hilal_Adco
 */
$stats = [
    [mha_mod('mha_stat_years', mha_defaults()['stat_years']), 'سنوات خبرة مهنية'],
    [mha_mod('mha_stat_clients', mha_defaults()['stat_clients']), 'عميل وشركة'],
    [mha_mod('mha_stat_team', mha_defaults()['stat_team']), 'محاسباً في الفريق'],
    [mha_mod('mha_stat_depts', mha_defaults()['stat_depts']), 'أقسام متخصصة'],
];
?>
<section class="mha-stats">
    <div class="container">
        <div class="row">
            <?php foreach ($stats as $stat) : ?>
                <div class="col-6 col-md-3">
                    <div class="mha-stat">
                        <strong class="mha-count" data-count="<?php echo esc_attr($stat[0]); ?>">0</strong>
                        <span><?php echo esc_html($stat[1]); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
