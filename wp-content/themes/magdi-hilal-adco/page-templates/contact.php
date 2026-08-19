<?php
/**
 * Template Name: تواصل معنا
 *
 * @package Magdi_Hilal_Adco
 */
get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <h1>تواصل معنا</h1>
        <p><?php echo esc_html(mha_mod('mha_address', mha_defaults()['address'])); ?></p>
    </div>
</section>
<?php get_template_part('template-parts/contact'); ?>
<?php get_footer();
