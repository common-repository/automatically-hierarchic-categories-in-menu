<?php
/*
Plugin Name: Automatically Hierarchic Categories in Menu
Description: Allows you to automatically add hierarchic categories in WordPress Navigation Menus
Plugin URI: https://atakanau.blogspot.com/2021/01/automatic-category-menu-wp-plugin.html
Version: 2.0.7
Author: Atakan Au
Author URI: https://atakanau.blogspot.com
Text Domain: automatically-hierarchic-categories-in-menu
Domain Path: /languages

License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html

Automatically Hierarchic Categories in Menu is distributed in the hope that it will be
useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
Public License for more details.
 
Automatically Hierarchic Categories in Menu published under the GNU General Public License.
https://www.gnu.org/licenses/gpl-3.0-standalone.html.

*/


// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AUTO_H_CATEGORY_MENU_PATH' ) ) {
	/**
	 * Path to the plugin directory.
	 *
	 * @since 1.0
	 */
	define( 'AUTO_H_CATEGORY_MENU_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
}
if ( ! defined( 'AUTO_H_CATEGORY_MENU_URL' ) ) {
	/**
	 * URL to the plugin directory.
	 *
	 * @since 1.0
	 */
	define( 'AUTO_H_CATEGORY_MENU_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );
}
if ( ! defined( 'AUTO_H_CATEGORY_MENU_BASENAME' ) ) {
	/**
	 * URL to the plugin base name.
	 *
	 * @since 1.0
	 */
	define( 'AUTO_H_CATEGORY_MENU_BASENAME', plugin_basename(__FILE__) );
}
if ( ! defined( 'AUTO_H_CATEGORY_MENU_RES' ) ) {
	/**
	 * Resource version for busting cache.
	 *
	 * @since 1.0
	 */
	define( 'AUTO_H_CATEGORY_MENU_RES', "2.0.7" );
}
if ( ! defined( 'AUTO_H_CATEGORY_MENU_SUPPORT_LINK' ) ) {
	/**
	 * @since 1.0
	 */
	define( 'AUTO_H_CATEGORY_MENU_SUPPORT_LINK', 'https://atakanau.blogspot.com/2021/01/automatic-category-menu-wp-plugin.html' );
}
if ( ! defined( 'AUTO_H_CATEGORY_MENU_INFO_LINK' ) ) {
	/**
	 * @since 2.0.3
	 */
	define( 'AUTO_H_CATEGORY_MENU_INFO_LINK', 'https://atakanau.wordpress.com/2023/09/26/automatically-hierarchic-categories-in-menu/' );
}
/**
 * The core plugin class
 */
require_once AUTO_H_CATEGORY_MENU_PATH . 'includes/class-auto-hierarchic-category-menu.php';

/**
 * Load the admin class if its the admin dashboard
 */
if ( is_admin() ) {
	require_once AUTO_H_CATEGORY_MENU_PATH . 'admin/class-auto-hierarchic-category-menu-admin.php';
	Auto_Hie_Category_Menu_Admin::get_instance();
} else {
	Auto_Hie_Category_Menu::get_instance();
}

/*
 *  Displays update information for a plugin. 
 */
function atakanau_ahcim_update_message($data, $response) {
	if (isset($data['upgrade_notice'])) {
		$msg = str_replace(array('<li>Warning', '<li>Info', '<p>', '</p>'), array('<li>🛑 ⚠️ Warning', '<li>ℹ️ Info', '<div>', '</div>'), $data['upgrade_notice']);
		echo '<style type="text/css">
			#automatically-hierarchic-categories-in-menu-update .update-message p:last-child{ display:none;}
			#automatically-hierarchic-categories-in-menu-update ul{ list-style:disc; margin-left:30px;}
			.wf-update-message{ padding-left:30px;}
			</style>
			<div class="update-message wf-update-message">' . wpautop($msg) . '</div>';
	}
}
add_action('in_plugin_update_message-'.AUTO_H_CATEGORY_MENU_BASENAME, 'atakanau_ahcim_update_message', 10, 2);

