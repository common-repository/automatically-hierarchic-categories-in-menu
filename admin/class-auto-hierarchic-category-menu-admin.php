<?php
/**
 * Handles admin side interactions of the plugin with WordPress.
 *
 * @package Auto_Hie_Category_Menu
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Auto_Hie_Category_Menu_Admin' ) && class_exists( 'Auto_Hie_Category_Menu' ) ) {

	/**
	 * Handles admin side interactions of Automatically Hierarchic Categories in Menu plugin with WordPress.
	 *
	 * @since 1.0
	 */
	class Auto_Hie_Category_Menu_Admin extends Auto_Hie_Category_Menu {

		/**
		 * Current instance of the class object.
		 *
		 * @since 1.0
		 * @access protected
		 * @static
		 *
		 * @var Auto_Hie_Category_Menu_Admin
		 */
		protected static $instance = null;

		/**
		 * Paid Pro value.
		 *
		 * @since 2.0.1
		 * @access protected
		 * @static
		 *
		 * @var Auto_Hie_Category_Menu_Admin
		 */
		protected $pro = false;

		/**
		 * Admin side hooks, filters and registers everything appropriately.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function __construct() {

			// Calling parent class' constructor.
			parent::__construct();

			// Setup the meta box.
			add_action( 'admin_init', array( $this, 'setup_meta_box' ) );

			// Enqueue custom JS.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

			// Add an ajax hack to save the html content.
			add_action( 'wp_ajax_aau_ahcm_description_hack', array( $this, 'description_hack' ) );

			// Hook to allow saving of shortcode in custom link metabox for legacy support.
			add_action( 'wp_loaded', array( $this, 'security_check' ) );

			// Hijack the ajax_add_menu_item function in order to save Shortcode menu item properly.
			add_action( 'wp_ajax_add-menu-item', array( $this, 'ajax_add_menu_item' ), 0 );

			// Include Paid Pro features if exists
			if ( class_exists( 'Auto_Hierarchic_Category_Menu_Pro' ) ) {
				$this->pro = true;
			}
		}

		/**
		 * Returns the current instance of the class Auto_Hie_Category_Menu_Admin.
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 *
		 * @return Auto_Hie_Category_Menu_Admin Returns the current instance of the
		 *                                  class object.
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Register our custom meta box.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @return void
		 */
		public function setup_meta_box() {
			add_meta_box( 'add-shortcode-section', __( 'Auto Category Shortcode', 'auto-hierarchic-category-menu' ), array( $this, 'meta_box' ), 'nav-menus', 'side', 'default' );
		}

		/**
		 * Enqueue our custom JS.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @param string $hook The current screen.
		 *
		 * @return void
		 */
		public function enqueue( $hook ) {

			// Don't enqueue if it isn't the menu editor.
			if ( 'nav-menus.php' !== $hook ) {
				return;
			}

			wp_enqueue_script( 'aau-ahcm-admin', AUTO_H_CATEGORY_MENU_URL . 'admin/js/auto-hierarchic-category-menu.min.js', array( 'nav-menu' ), AUTO_H_CATEGORY_MENU_RES, true );
		}

		/**
		 * An AJAX based workaround to save descriptions without using the
		 * custom object type.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @return void
		 */
		public function description_hack() {
			// Verify the nonce.
			$nonce = filter_input( INPUT_POST, 'description-nonce', FILTER_SANITIZE_STRING );
			if ( ! wp_verify_nonce( $nonce, 'aau-ahcm-description-nonce' ) ) {
				wp_die();
			}

			// Get the menu item. We need this unfiltered, so using FILTER_UNSAFE_RAW.
			// phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter
			$item = filter_input( INPUT_POST, 'menu-item', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );

			// Save the description in a transient. This is what we'll use in setup_item().
			set_transient( 'aau_ahcm_description_hack_' . $item['menu-item-object-id'], $item['menu-item-description'] );

			// Increment the object id, so it can be used by JS.
			$object_id = $this->new_object_id( $item['menu-item-object-id'] );

			echo esc_js( $object_id );

			wp_die();
		}

		/**
		 * Allows shortcodes into the custom link URL field.
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		public function security_check() {
			if ( current_user_can( 'activate_plugins' ) ) {
				// Conditionally adding the function for database context for.
				add_filter( 'clean_url', array( $this, 'save_shortcode' ), 99, 3 );
			add_filter('plugin_row_meta', array($this, 'plugin_meta_links'), 10, 2);
			}
		}

		/**
		 * Ajax handler for add menu item request.
		 *
		 * This method is hijacked from WordPress default ajax_add_menu_item
		 * so need to be updated accordingly.
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		public function ajax_add_menu_item() {

			check_ajax_referer( 'add-menu_item', 'menu-settings-column-nonce' );

			if ( ! current_user_can( 'edit_theme_options' ) ) {
				wp_die( -1 );
			}

			require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

			// For performance reasons, we omit some object properties from the checklist.
			// The following is a hacky way to restore them when adding non-custom items.
			$menu_items_data = array();
			// Get the menu item. We need this unfiltered, so using FILTER_UNSAFE_RAW.
			// phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter
			$menu_item = filter_input( INPUT_POST, 'menu-item', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
			foreach ( $menu_item as $menu_item_data ) {
				if (
				! empty( $menu_item_data['menu-item-type'] ) &&
				'custom' !== $menu_item_data['menu-item-type'] &&
				'aau_ahcm' !== $menu_item_data['menu-item-type'] &&
				! empty( $menu_item_data['menu-item-object-id'] )
				) {
					switch ( $menu_item_data['menu-item-type'] ) {
						case 'post_type':
							$_object = get_post( $menu_item_data['menu-item-object-id'] );
							break;

						case 'taxonomy':
							$_object = get_term( $menu_item_data['menu-item-object-id'], $menu_item_data['menu-item-object'] );
							break;
					}

					$_menu_items = array_map( 'wp_setup_nav_menu_item', array( $_object ) );
					$_menu_item  = reset( $_menu_items );

					// Restore the missing menu item properties.
					$menu_item_data['menu-item-description'] = $_menu_item->description;
				}

				$menu_items_data[] = $menu_item_data;
			}

			$item_ids = wp_save_nav_menu_items( 0, $menu_items_data );
			if ( is_wp_error( $item_ids ) ) {
				wp_die( 0 );
			}

			$menu_items = array();

			foreach ( (array) $item_ids as $menu_item_id ) {
				$menu_obj = get_post( $menu_item_id );
				if ( ! empty( $menu_obj->ID ) ) {
					$menu_obj        = wp_setup_nav_menu_item( $menu_obj );
					$menu_obj->label = $menu_obj->title; // don't show "(pending)" in ajax-added items.
					$menu_items[]    = $menu_obj;
				}
			}

			$menu = filter_input( INPUT_POST, 'menu', FILTER_SANITIZE_NUMBER_INT );
			/** This filter is documented in wp-admin/includes/nav-menu.php */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$walker_class_name = apply_filters( 'wp_edit_nav_menu_walker', 'Walker_Nav_Menu_Edit', $menu );

			if ( ! class_exists( $walker_class_name ) ) {
				wp_die( 0 );
			}

			if ( ! empty( $menu_items ) ) {
				$args = array(
					'after'       => '',
					'before'      => '',
					'link_after'  => '',
					'link_before' => '',
					'walker'      => new $walker_class_name(),
				);
				echo walk_nav_menu_tree( $menu_items, 0, (object) $args );
			}
			wp_die();
		}

		/**
		 * Method to allow saving of shortcodes in custom_link URL.
		 *
		 * @since 1.0
		 *
		 * @param string $url The processed URL for displaying/saving.
		 * @param string $orig_url The URL that was submitted, retreived.
		 * @param string $context Whether saving or displaying.
		 *
		 * @return string String containing the shortcode.
		 */
		public function save_shortcode( $url, $orig_url, $context ) {

			if ( 'db' === $context && $this->has_shortcode( $orig_url ) ) {
				return $orig_url;
			}
			return $url;
		}

		public function plugin_meta_links($links, $file){
			if ( $file == AUTO_H_CATEGORY_MENU_BASENAME ) {
				$support_link = '<a target="_blank" href="'.AUTO_H_CATEGORY_MENU_SUPPORT_LINK.'?utm_content=textlink&utm_medium=link&utm_source='.preg_replace('/^(https?:\/\/)/', '', get_site_url()).'&utm_campaign=wpadminplugins#comments">' . __(translate('Support')) . '</a>';
				$rate_link = '<a target="_blank" href="https://wordpress.org/support/plugin/automatically-hierarchic-categories-in-menu/reviews/?filter=5#new-post">' . __(translate('Rate','auto-hierarchic-category-menu')).' ★★★★★' . '</a>';
				$links[] = $support_link;
				$links[] = $rate_link;
			}
			return $links;
		}

		/**
		 * Gets a new object ID, given the current one
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @param int $last_object_id The current/last object id.
		 *
		 * @return int Returns new object ID.
		 */
		public function new_object_id( $last_object_id ) {

			// make sure it's an integer.
			$object_id = (int) $last_object_id;

			// increment it.
			$object_id ++;

			// if object_id was 0 to start off with, make it 1.
			$object_id = ( $object_id < 1 ) ? 1 : $object_id;

			// save into the options table.
			update_option( 'aau_ahcm_last_object_id', $object_id );

			return $object_id;
		}

		/**
		 * Display our custom meta box.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @global int $_nav_menu_placeholder        A placeholder index for the menu item.
		 * @global int|string $nav_menu_selected_id  (id, name or slug) of the currently-selected menu.
		 *
		 * @return void
		 */
		public function meta_box() {
			global $_nav_menu_placeholder, $nav_menu_selected_id;

			$nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;

			$last_object_id = get_option( 'aau_ahcm_last_object_id', 0 );
			$object_id      = $this->new_object_id( $last_object_id );
			?>
			<div class="aau-ahcm-div" id="aau-ahcm-div">
				<input type="hidden" class="menu-item-db-id" name="menu-item[<?php echo esc_attr( $nav_menu_placeholder ); ?>][menu-item-db-id]" value="0" />
				<input type="hidden" class="menu-item-object-id" name="menu-item[<?php echo esc_attr( $nav_menu_placeholder ); ?>][menu-item-object-id]" value="<?php echo esc_attr( $object_id ); ?>" />
				<input type="hidden" class="menu-item-object" name="menu-item[<?php echo esc_attr( $nav_menu_placeholder ); ?>][menu-item-object]" value="aau_ahcm" />
				<input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr( $nav_menu_placeholder ); ?>][menu-item-type]" value="aau_ahcm" />
				<input type="hidden" id="aau-ahcm-description-nonce" value="<?php echo esc_attr( wp_create_nonce( 'aau-ahcm-description-nonce' ) ); ?>" />
				<p id="menu-item-title-wrap">
					<label for="aau-ahcm-title"><?php esc_html_e( 'Title', 'auto-hierarchic-category-menu' ); ?></label>
					<input id="aau-ahcm-title" name="menu-item[<?php echo esc_attr( $nav_menu_placeholder ); ?>][menu-item-title]" type="text" class="regular-text menu-item-textbox" title="<?php esc_attr_e( 'Title', 'auto-hierarchic-category-menu' ); ?>" style="width:100%" />
				</p>

				<label for="aau-ahcm-shortcode" class="category-tabs wp-tab-bar"><?php esc_html_e( 'Shortcode', 'auto-hierarchic-category-menu' ); ?> <a href="<?php echo AUTO_H_CATEGORY_MENU_SUPPORT_LINK; ?>?utm_content=helplink&utm_medium=link&utm_source=<?php echo preg_replace('/^(https?:\/\/)/', '', get_site_url()) ?>&utm_campaign=wpadminmenus"><small title="<?php _e( 'Read more on blog', 'auto-hierarchic-category-menu' ); ?>" class="dashicons dashicons-editor-help"></small></a></label>
				<p id="menu-item-shortcode-wrap">
					<textarea style="width:100%;" rows="9" id="aau-ahcm-shortcode" name="menu-item[<?php echo esc_attr( $nav_menu_placeholder ); ?>][menu-item-description]" class="code menu-item-textbox" title="<?php esc_attr_e( 'Shortcode here!', 'auto-hierarchic-category-menu' ); ?>"></textarea>
				</p>

				<p class="field-description description description-wide add-edit-menu-action">
					<span class="description">
						• <?php esc_html_e( 'Type your shortcode with parameters, e.g.', 'auto-hierarchic-category-menu' ); ?>
						<br>
						<code>[autocategorymenu hide_empty="0"]</code>
						<br>
<?php $taxonomies = get_taxonomies(); 
if ( ! empty($taxonomies) ) : sort($taxonomies) ?>
						• <?php esc_html_e( 'List of taxonomy registered in database: ', 'auto-hierarchic-category-menu' ); ?>
	<div id="taxonomy-list" class="taxonomylistdiv">
	<ul id="taxonomy-list-tabs" class="add-menu-item-tabs">
		<li>
			<a class="nav-tab-link" data-type="tabs-panel-taxonomy-list-show" href="#tabs-panel-taxonomy-list-show"><?php _e('Show');?></a>
		</li>
		<li class="tabs">
			<a class="nav-tab-link" data-type="tabs-panel-taxonomy-list-hide" href="#tabs-panel-taxonomy-list-hide"><?php _e('Hide');?></a>
		</li>
	</ul><!-- .taxonomy-list -->
	<div id="tabs-panel-taxonomy-list-show" class="tabs-panel tabs-panel-inactive tabs-panel-taxonomy-list-show" role="region" aria-label="<?php _e('Show');?>" tabindex="0">
		<small>
		<p>
		<?php
		foreach($taxonomies as $taxonomy){?><?php
			echo esc_attr($taxonomy) .($taxonomy=='category'?' — '.__('Default Data'):($taxonomy=='product_cat'?'':' — '.'Pro') );
			?><br/><?php
		}
		?>
		</p>
		</small>
	</div><!-- /.tabs-panel -->
	<div id="tabs-panel-taxonomy-list-hide" class="tabs-panel-active tabs-panel-taxonomy-list-hide" role="region" aria-label="<?php _e('Hide');?>" tabindex="0">
	</div><!-- /.tabs-panel -->
</div>
<?php endif; ?>
						
						<a href="<?php echo AUTO_H_CATEGORY_MENU_SUPPORT_LINK; ?>?utm_content=textlink&utm_medium=link&utm_source=<?php echo preg_replace('/^(https?:\/\/)/', '', get_site_url()) ?>&utm_campaign=wpadminmenus"><?php _e( 'Read more on blog', 'auto-hierarchic-category-menu' ); ?></a>
<?php if ( !$this->pro ) $this->get_box_qrcode(); ?>
					</span>
				</p>

				<p class="button-controls" style="display:block;">
					<span class="add-to-menu">
						<input type="submit" <?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?> class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'auto-hierarchic-category-menu' ); ?>" name="add-aau-ahcm-menu-item" id="submit-aau-ahcm" />
						<span class="spinner"></span>
					</span>
				</p>
				
<?php if ( $this->pro ): ?>
<?php  $this->get_box_qrcode() ?>
<?php endif; ?>

			</div>
			<?php
		}
		public function get_box_qrcode() {?><br/><br/><div class="attention alignleft  comment-ays">
<!-- If you like the plugin, please make a donation, no cracking! -->
<pre style="line-height: 1.1;"><code style="background-color: initial;">Scan via <a href="https://accounts.binance.com/en/register?ref=319392384">Binance App</a> for donation: 
 ▄▄▄▄▄▄▄   ▄ ▄   ▄    ▄ ▄▄ ▄▄▄▄▄▄▄
 █ ▄▄▄ █ ▀ ▀ ▄█▄▀▄▀▄█▀  ▄  █ ▄▄▄ █
 █ ███ █ ▀█▀ ▀  █▀   █▄█ ▄ █ ███ █
 █▄▄▄▄▄█ █▀▄▀█▀▄ █▀█ █ █ ▄ █▄▄▄▄▄█
 ▄▄▄▄▄ ▄▄▄█▀█  █▄▄  ▀ █▄▀ ▄ ▄ ▄ ▄ 
 ▀▀▀▀▀ ▄ █ █ ▀▄ █ ▄▀ █▀▄█▄█ ███▀▄ 
 ▄▀ █ █▄  ▄▄ █▄█▄▀▄█▀▄█▀▀▀▄█▄▄ ██▀
 █ ▄   ▄▀▄█ ▄▄▄▄▄▀▄▀▀▄▀▄▄▄▄▀█▄█▀▄ 
 █▄ █▄█▄▀▀▄ █  ▄▄▀▄█▀ ▄▄▀  ▀▄▄▄▀█▀
  ▄██ █▄█ ▀▄ ▀▄ ██▄█ █▀▄█▄█ ▄█▄▀ ▄
 █▀  ▄ ▄██▄█ █▀▀▄▄▄▄▀   ▀ ▄██   ▄▀
 █  ▄▀█▄▄▀▄ ▄▄  █▄▄▀ ▄▀█▀█▄▀▀▄ ▀██
 █ █▀ █▄█▄▀▄█   ▄▄ █▀▄▀ ▀▄▄█▄▄█ ▀▀
 ▄▄▄▄▄▄▄ █▀▄ ▀█▄▄ ▄▄ █▀ ▄█ ▄ █ ▀▄ 
 █ ▄▄▄ █ ▄ ▀ █▀▄▄▀ ▄▀ ████▄▄▄██  █
 █ ███ █ █ █▄▄  ▀▄▄ ▀▄▀▀ ▄▄▄█ ▀▀▀ 
 █▄▄▄▄▄█ █  █  ▀▄  ▀▀▄▄▀██▀▄ ▀▄██▀</code></pre></div>
<?php
		}

	}

}

