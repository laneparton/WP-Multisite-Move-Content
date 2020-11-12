<?php
/**
 * The plugin bootstrap file
 *
 * @link              http://devinvinson.com
 * @since             1.0.0
 * @package           Multisite_Move_Content
 *
 * Plugin Name:       Multisite Move Content
 * Version:           1.0.0
 * Author:            Lane Parton
 * Author URI:        https://laneparton.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       multisite_move-Content
 * Domain Path:       /languages
 */

// Include Composer autoloader
require __DIR__ . '/vendor/autoload.php';

use WPMultisiteMoveContent\Bulk_Actions;

$bulkActions = new Bulk_Actions();

if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}