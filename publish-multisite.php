<?php
/**
 * Plugin Name: Publish Duplicate Post to Multisite
 * Plugin URI:  duplicate-publish-multisite
 * Description: Publish duplicated post to multisite based on category.
 * Version:     1.2
 * Author:      Closemarketing
 * Author URI:  https://close.marketing
 * Text Domain: duplicate-publish-multisite
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package     WordPress
 * @author      Closemarketing
 * @copyright   2021 Closemarketing
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:      pubmult
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'PUBLISHMU_VERSION', '1.2' );

add_action( 'plugins_loaded', 'pubmult_plugin_init' );
/**
 * Load localization files
 *
 * @return void
 */
function pubmult_plugin_init() {
	load_plugin_textdomain( 'duplicate-publish-multisite', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Include files.
require_once plugin_dir_path( __FILE__ ) . '/includes/class-pubmult-settings.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-admin-publishmu.php';
