<?php
/**
 * Latest news — home.
 *
 * @package Magdi_Hilal_Adco
 */

get_template_part('template-parts/news-carousel', null, [
    'count'    => 10,
    'show_all' => true,
    'heading'  => true,
    'uid'      => 'mhaHomeNews',
]);
