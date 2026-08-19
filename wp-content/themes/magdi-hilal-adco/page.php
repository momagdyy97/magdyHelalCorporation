<?php
/**
 * Generic page.
 *
 * @package Magdi_Hilal_Adco
 */

get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <h1><?php the_title(); ?></h1>
    </div>
</section>
<section class="mha-section">
    <div class="container mha-prose">
        <?php
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
        ?>
    </div>
</section>
<?php
get_footer();
