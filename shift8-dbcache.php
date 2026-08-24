<?php
/**
 * Plugin Name: Shift8 DB Cache
 * Description: Analysis-first database cache rules for WordPress with laser-focused query targeting.
 * Version: 0.1.0
 * Author: Shift8 Web
 * Author URI: https://shift8web.ca
 * Text Domain: shift8-dbcache
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SHIFT8_DBCACHE_VERSION', '0.1.0' );
define( 'SHIFT8_DBCACHE_FILE', __FILE__ );
define( 'SHIFT8_DBCACHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHIFT8_DBCACHE_URL', plugin_dir_url( __FILE__ ) );
define( 'SHIFT8_DBCACHE_BASENAME', plugin_basename( __FILE__ ) );

require_once SHIFT8_DBCACHE_PATH . 'includes/class-plugin.php';

Shift8_DBCache_Plugin::get_instance();