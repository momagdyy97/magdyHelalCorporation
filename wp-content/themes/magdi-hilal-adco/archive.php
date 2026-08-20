<?php
/**
 * Archives.
 *
 * @package Magdi_Hilal_Adco
 */
get_header();
?>
<section class="mha-page-head">
    <div class="container">
        <h1><?php the_archive_title(); ?></h1>
        <?php the_archive_description(); ?>
    </div>
</section>
<?php
get_template_part('template-parts/news-carousel', null, [
    'count'    => 16,
    'show_all' => false,
    'heading'  => false,
    'uid'      => 'mhaTaxonomyNews',
]);
get_footer();
