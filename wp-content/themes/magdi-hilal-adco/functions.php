<?php
/**
 * MAGDY HELAL CORP — theme bootstrap.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MHA_VERSION', '1.3.2');
define('MHA_DIR', get_template_directory());
define('MHA_URI', get_template_directory_uri());

require_once MHA_DIR . '/inc/helpers.php';
require_once MHA_DIR . '/inc/setup.php';
require_once MHA_DIR . '/inc/enqueue.php';
require_once MHA_DIR . '/inc/cpt.php';
require_once MHA_DIR . '/inc/customizer.php';
require_once MHA_DIR . '/inc/forms.php';
require_once MHA_DIR . '/inc/seed.php';
