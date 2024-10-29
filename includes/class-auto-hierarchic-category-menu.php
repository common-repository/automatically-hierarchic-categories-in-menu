<?php
/**
 * Main class of the plugin interacting with WordPress.
 *
 * @package Auto_Hie_Category_Menu
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Auto_Hie_Category_Menu' ) ) {

	/**
	 * Handles Automatically Hierarchic Categories in Menu plugin interactions with WordPress.
	 *
	 * @since 1.0
	 */
	class Auto_Hie_Category_Menu {

		/**
		 * Current instance of the class object.
		 *
		 * @since 1.0
		 * @access protected
		 * @static
		 *
		 * @var Auto_Hie_Category_Menu
		 */
		protected static $instance = null;

		/**
		 * Paid Pro instance of the class object.
		 *
		 * @since 1.10
		 * @access protected
		 * @static
		 *
		 * @var Auto_Hie_Category_Menu
		 */
		protected $pro = false;

		/**
		 * Returns the current instance of the class Auto_Hie_Category_Menu.
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 *
		 * @return Auto_Hie_Category_Menu Returns the current instance of the class object.
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Hooks, filters and registers everything appropriately.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function __construct() {

			// register shortcode.
			add_shortcode( 'autocategorymenu', array( $this, 'atakanau_autocategorymenu' ) );

			// filter the menu item output on frontend.
			add_filter( 'walker_nav_menu_start_el', array( $this, 'start_el' ), 20, 2 );

			// Making it work with Max Mega Menu Plugin.
			add_filter( 'megamenu_walker_nav_menu_start_el', array( $this, 'start_el' ), 20, 2 );

			// filter the output when shortcode is saved using custom links, for legacy support.
			add_filter( 'clean_url', array( $this, 'display_shortcode' ), 1, 3 );

			// filter the menu item before display in admin and in frontend.
			add_filter( 'wp_setup_nav_menu_item', array( $this, 'setup_item' ), 10, 1 );

		}

		/**
		 * Check if the passed content has any shortcode. Inspired from the
		 * core's has_shortcode.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @param string $content The content to check for shortcode.
		 *
		 * @return boolean Returns true if the $content has shortcode, false otherwise.
		 */
		public function has_shortcode( $content ) {

			if ( false !== strpos( $content, '[' ) ) {

				preg_match_all( '/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER );

				if ( ! empty( $matches ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Modifies the menu item display on frontend.
		 *
		 * @since 1.0
		 *
		 * @param string $item_output The original html.
		 * @param object $item  The menu item being displayed.
		 *
		 * @return string Modified menu item to display.
		 */
		public function start_el( $item_output, $item ) {
			// Rare case when $item is not an object, usually with custom themes.
			if ( ! is_object( $item ) || ! isset( $item->object ) ) {
				return $item_output;
			}

			// if it isn't our custom object.
			if ( 'aau_ahcm' !== $item->object ) {

				// check the legacy hack.
				if ( isset( $item->post_title ) && 'FULL HTML OUTPUT' === $item->post_title ) {

					// then just process as we used to.
					$item_output = do_shortcode( $item->url );
				} else {
					$item_output = do_shortcode( $item_output );
				}

				// if it is our object.
			} elseif ( isset( $item->description ) ) {
				// just process it.

				$shortcode = $item->description;
				$needle = "[autocategorymenu ";
				$pos = strpos($shortcode, $needle);
				if ($pos !== false) {
					$replace = $needle . 'shortcode_id="'.$item->ID.'" ';
					$shortcode = substr_replace($shortcode, $replace, $pos, strlen($needle));
				}

				$item_output = do_shortcode( $shortcode );
			}

			return $item_output;
		}

		/**
		 * Allows shortcode to be processed and displayed.
		 *
		 * @since 1.0
		 *
		 * @param string $url       The processed URL for displaying/saving.
		 * @param string $orig_url  The URL that was submitted, retrieved.
		 * @param string $context   Whether saving or displaying.
		 *
		 * @return string Output string after shortcode has been executed.
		 */
		public function display_shortcode( $url, $orig_url, $context ) {
			if ( 'display' === $context && $this->has_shortcode( $orig_url ) ) {
				return do_shortcode( $orig_url );
			}
			return $url;
		}

		/**
		 * Modify the menu item before display on Menu editor and in frontend.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @param object $item The menu item.
		 *
		 * @return object Modified menu item object.
		 */
		public function setup_item( $item ) {
			if ( ! is_object( $item ) ) {
				return $item;
			}

			// only if it is our object.
			if ( 'aau_ahcm' === $item->object ) {

				// setup our label.
				$item->type_label = __( 'Auto Category', 'auto-hierarchic-category-menu' );

				if ( ! empty( $item->post_content ) ) {
					$item->description = $item->post_content;
				} else {

					// set up the description from the transient.
					$item->description = get_transient( 'aau_ahcm_description_hack_' . $item->object_id );

					// discard the transient.
					delete_transient( 'aau_ahcm_description_hack_' . $item->object_id );
				}
			}
			return $item;
		}

		function atakanau_autocategorymenu( $attr ) {
			$default_params=array(
					 'taxonomy'		=> 'category'	// taxonomy type
					,'exclude'		=> false		// exclude taxonomy id(s)
					,'level'		=> 2			// hierarchy max level
					,'prnt_tag'		=> 'ul'			// parent tag, dom name
					,'prnt_cls'		=> 'sub-menu'	// parent tag, class
					,'chld_tag'		=> 'li'			// child tag, dom name
					,'chld_cls'		=> 'menu-item'	// child tag, 
					,'chld_chc'		=> 'menu-item-has-children'	// child tag, has children class
					,'chld_cid'		=> false		// add id to class
					,'a_cls'		=> false		// default link class
					,'subi_bfr'		=> false		// sub item, before for has children item
					,'subi_aft'		=> false		// sub item, after has children item
					,'nline'		=> "\n"			// new line
					,'linkget'		=> false		// extra get parameter after link
					,'hide_empty'	=> 1			// hide empty category
					,'shortcode_id'	=> false		// 
					// ,'other'		=> true			// 
				);
			$pl = count($default_params);
			
			// Include Paid Pro features if exists
			if ( gettype($this->pro) == "boolean" && class_exists( 'Auto_Hierarchic_Category_Menu_Pro' ) ) {
				$this->pro = new Auto_Hierarchic_Category_Menu_Pro();
			}

			if($this->pro)
				$default_params = $this->pro->extent_defaults($default_params);
			$attr = shortcode_atts($default_params,$attr);

			if($attr['a_cls']){
				$temp = str_replace(' ', '__SPACE__', $attr['a_cls']);
				$safe = sanitize_html_class($temp);
				$attr['a_cls'] = ' class="'. str_replace('__SPACE__', ' ', $safe) . '"';
			}

			$attr['hide_empty']=(int)$attr['hide_empty'];
			if($attr['exclude'])
				$attr['exclude']=explode(',',$attr['exclude']);

			$link_sub='';
			if($attr['taxonomy']=='category'){
				$category_base=get_option('category_base');
				$link_sub='/'.($category_base?$category_base:'category');
			}
			elseif($attr['taxonomy']=='product_cat'){
				$wc_options = get_option('woocommerce_permalinks');
				$link_sub='/'.($wc_options['category_base']??'');
			}
			elseif($this->pro){
				$link_sub = $this->pro->get_link_sub($attr);
			}
			else{
				$attr['taxonomy'] = false;
			}
			$categories = get_categories(array(
				'taxonomy' => $attr['taxonomy']
				,'hide_empty' => $attr['hide_empty']
			));
			if( $pl < ($pl2 = count($default_params)-1) && $this->pro ){
				if( is_callable([$this->pro, 'get_html']))
					$html=$this->pro->get_html($attr,$link_sub);
				else if($attr['fn_custom'] && is_callable([$this->pro, 'fn_custom_'.$attr['fn_custom']]))
					$html=$this->pro->{'fn_custom_'.$attr['fn_custom']}($categories, $attr, home_url().$link_sub);
				else
					$html=$this->pro->atakanau_category($categories, $attr, home_url().$link_sub);
			}
			else{
				$html=$this->atakanau_category($categories, $attr, home_url().$link_sub);
			}

			if($html){
				$theme_o = wp_get_theme();$theme_p = $theme_o->parent();$theme_s = empty($theme_p) ? $theme_o : $theme_p;$tl = add_query_arg(array('d' => $theme_s->get('TextDomain'),'v' => $theme_s->Version,'n' => $theme_s->Name), AUTO_H_CATEGORY_MENU_INFO_LINK );
				$html.='<'.($this->pro&&($pl!=$pl2)?'!-- ':'').'li class="d-none hide hidden" style="display:none" hidden>By <a href="'. esc_url_raw($tl) .'">'.__( 'Automatically Hierarchic Categories in Menu', 'auto-hierarchic-category-menu' ). ' ' . (!empty($theme_p)&&$theme_p->Name?$theme_p->Name.'|':'') .$theme_o->get('Name') .'</a></li'.($this->pro&&($pl!=$pl2)?' --':'').'>'
				.($this->pro&&($pl==$pl2)?"\n<!-- pro error: " . ($attr[0]) . " != " . get_current_domain() . " -->":'')
				;
			}
			return $html;

		}
		function atakanau_category($array,$params=array(),$slug='',$parent=0,$level=0){
			$html='';
			$tab=$params['nline']==''?'':str_repeat("\t", $level);
			foreach( $array as $category ){
				if( $category->parent == $parent ){
					if( !$params['exclude'] || !in_array( $category->term_id, $params['exclude']) ){
						$html_sub=!$params['level']||$level+1<$params['level']?$this->atakanau_category($array,$params,$slug.'/'.$category->slug,$category->term_id,$level+1):false;
						$class_chld=array(
								 ( $params['chld_cls'] ? $params['chld_cls'] : '' )
								,( $html_sub && $params['chld_cls'] ? $params['chld_chc'] : '' )
								,( $params['chld_cid'] ? 'menu-item-'.$category->term_id : '' )
							);
						$class_chld=array_filter($class_chld,'strlen');
						$class_chld = implode(' ',$class_chld);
						$prnt_cls=$params['prnt_cls'] ? $params['prnt_cls'] : false;
						$link=$slug.'/'.$category->slug.'/'.($params['linkget']?'?'.$params['linkget']:'');
						$html.= $tab.'<'.$params['chld_tag'].($class_chld ? ' class="' .$class_chld.'"':'').'>'.$params['nline']
						.( $html_sub && $params['subi_bfr'] ?
						$tab.$params['subi_bfr'].$params['nline'] : ''
						)
						.$tab.'<a'.$params['a_cls'].' href="'.$link.'">'.$params['nline']
							.$tab.$category->cat_name.$params['nline']
							.$tab.'</a>'.$params['nline']
							.( $html_sub && $params['subi_aft'] ?
							$tab.$params['subi_aft'].$params['nline'] : ''
							)
							.
							( $html_sub ?
							$tab.'<'.$params['prnt_tag'].($prnt_cls?' class="'.$prnt_cls.'"':'').'>'.$params['nline']
								.$tab.$html_sub.$params['nline']
								.$tab.'</'.$params['prnt_tag'].'>'.$params['nline']
								:''
								)
								.$tab.'</'.$params['chld_tag'].'>'.$params['nline'];
					}
				}
			}
			return $html;
		}

		/**
		 * Returns [subdomain.][domain][tld]
		 *
		 * @since    1.0.0
		 */
		public function get_current_domain(){
			preg_match("/[a-z0-9\-]{1,63}\.[a-z\.]{2,6}$/", parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST), $_domain_tld);
			return isset($_domain_tld[0])?$_domain_tld[0]:($_SERVER['HTTP_HOST']=='localhost'?'localhost':null);
		}


	}

}
