<?php
/**
 * WPMovieLibrary-Details
 *
 * @package   WPMovieLibrary-Details
 * @author    Charlie MERLAND <charlie@caercam.org>
 * @license   GPL-3.0
 * @link      http://www.caercam.org/
 * @copyright 2014 Charlie MERLAND
 */

if ( ! class_exists( 'WPMovieLibrary_Trailers' ) ) :

	/**
	* Plugin class
	*
	* @package WPMovieLibrary-Details
	* @author  Charlie MERLAND <charlie@caercam.org>
	*/
	class WPMovieLibrary_Details extends WPMOLY_Details_Module {

		/**
		 * Settings for new detail
		 * 
		 * @since     1.0
		 * @var       array
		 */
		protected $detail = array();

		/**
		 * Initialize the plugin by setting localization and loading public scripts
		 * and styles.
		 *
		 * @since     1.0
		 */
		public function __construct() {

			$this->init();
		}

		/**
		 * Initializes variables
		 *
		 * @since    1.0
		 */
		public function init() {

			if ( ! wpmoly_details_requirements_met() ) {
				add_action( 'init', 'wpmoly_details_l10n' );
				add_action( 'admin_notices', 'wpmoly_details_requirements_error' );
				return false;
			}

			$this->register_hook_callbacks();

			$this->detail = array(
				'id'       => 'wpmoly-movie-barcode',
				'name'     => 'wpmoly_details[barcode]',
				'type'     => 'text',
				'title'    => 'Code-barre',
				'desc'     => 'Sélectionnez un code-barre pour ce film',
				'icon'     => 'dashicons dashicons-slides',
				'default'  => '',
				'rewrite'  => array( 'barcode' => 'codebarre' )
			);
		}

		/**
		 * Register callbacks for actions and filters
		 * 
		 * @since    1.0
		 */
		public function register_hook_callbacks() {

			add_action( 'plugins_loaded', 'wpmoly_details_l10n' );

			add_action( 'activated_plugin', __CLASS__ . '::require_wpmoly_first' );

			// Create a new detail
			add_filter( 'wpmoly_pre_filter_details', array( $this, 'create_detail' ), 10, 1 );

			// Add new detail to the available movie tags list
			add_filter( 'wpmoly_filter_movie_tags', array( $this, 'movie_tag' ), 10, 1 );

			// Add new detail to the settings panel
			add_filter( 'redux/options/wpmoly_settings/field/wpmoly-sort-details/register', array( $this, 'detail_setting' ), 10, 1 );

			// Add a formatting filter
			add_filter( 'wpmoly_format_movie_barcode', array( $this, 'format_detail' ), 10, 1 );
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                     Plugin  Activate/Deactivate
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Make sure WPMovieLibrary is active and compatible.
		 *
		 * @since    1.0
		 * 
		 * @return   boolean    Requirements met or not?
		 */
		private function wpmoly_details_requirements_error() {

			$wpml_active  = is_wpmoly_active();
			$wpml_version = ( $wpml_active && version_compare( WPML_VERSION, WPMLTR_REQUIRED_WPML_VERSION, '>=' ) );

			if ( ! $wpml_active || ! $wpml_version )
				return false;

			return true;
		}

		/**
		 * Fired when the plugin is activated.
		 *
		 * @since    1.0
		 *
		 * @param    boolean    $network_wide    True if WPMU superadmin uses
		 *                                       "Network Activate" action, false if
		 *                                       WPMU is disabled or plugin is
		 *                                       activated on an individual blog.
		 */
		public function activate( $network_wide ) {

			global $wpdb;

			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				if ( $network_wide ) {
					$blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog );
						$this->single_activate( $network_wide );
					}

					restore_current_blog();
				} else {
					$this->single_activate( $network_wide );
				}
			} else {
				$this->single_activate( $network_wide );
			}

		}

		/**
		 * Fired when the plugin is deactivated.
		 * 
		 * When deactivatin/uninstalling WPML, adopt different behaviors depending
		 * on user options. Movies and Taxonomies can be kept as they are,
		 * converted to WordPress standars or removed. Default is conserve on
		 * deactivation, convert on uninstall.
		 *
		 * @since    1.0
		 */
		public function deactivate() {
		}

		/**
		 * Runs activation code on a new WPMS site when it's created
		 *
		 * @since    1.0
		 *
		 * @param    int    $blog_id
		 */
		public function activate_new_site( $blog_id ) {
			switch_to_blog( $blog_id );
			$this->single_activate( true );
			restore_current_blog();
		}

		/**
		 * Prepares a single blog to use the plugin
		 *
		 * @since    1.0
		 *
		 * @param    bool    $network_wide
		 */
		protected function single_activate( $network_wide ) {

			self::require_wpmoly_first();
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                     Scripts/Styles and Utils
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Make sure the plugin is load after WPMovieLibrary and not
		 * before, which would result in errors and missing files.
		 *
		 * @since    1.0
		 */
		public static function require_wpmoly_first() {

			$this_plugin_path = plugin_dir_path( __FILE__ );
			$this_plugin      = basename( $this_plugin_path ) . '/wpmoly-details.php';
			$active_plugins   = get_option( 'active_plugins' );
			$this_plugin_key  = array_search( $this_plugin, $active_plugins );
			$wpml_plugin_key  = array_search( 'wpmovielibrary/wpmovielibrary.php', $active_plugins );

			if ( $this_plugin_key < $wpml_plugin_key ) {

				unset( $active_plugins[ $this_plugin_key ] );
				$active_plugins = array_merge(
					array_slice( $active_plugins, 0, $wpml_plugin_key ),
					array( $this_plugin ),
					array_slice( $active_plugins, $wpml_plugin_key )
				);

				update_option( 'active_plugins', $active_plugins );
			}
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                            Plugin methods
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Create a new movie detail
		 *
		 * @since    1.0
		 * 
		 * @param    array    Exisiting details
		 * 
		 * @return   array    Updated details
		 */
		public function create_detail( $details ) {

			$details = array_merge( $details, array( 'barcode' => $this->detail ) );

			return $details;
		}

		/**
		 * Add new movie detail to the available movie tags list
		 *
		 * @since    1.0
		 * 
		 * @param    array    Exisiting tags
		 * 
		 * @return   array    Updated tags
		 */
		public function movie_tag( $tags ) {

			$tags = array_merge( $tags, array( 'barcode' => $this->detail['title'] ) );

			return $tags;
		}

		/**
		 * Add new movie detail to the Settings panel
		 *
		 * @since    1.0
		 * 
		 * @param    array    Exisiting detail field
		 * 
		 * @return   array    Updated detail field
		 */
		public function detail_setting( $field ) {

			$field['options']['available'] = array_merge( $field['options']['available'], array( 'barcode' => $this->detail['title'] ) );

			return $field;
		}

		/**
		 * Apply some formatting to the new detail rendering
		 * 
		 * This method should be very similar to the ones present in the
		 * plugin utils class.
		 *
		 * @since    1.0
		 * 
		 * @param    array    $data Exisiting detail field
		 * @param    array    $format data format, raw or HTML
		 * 
		 * @return   array    Updated detail field
		 */
		public function format_detail( $data, $format = 'html' ) {

			$format = ( 'raw' == $format ? 'raw' : 'html' );

			if ( '' == $data )
				return $data;

			if ( wpmoly_o( 'details-icons' ) && 'html' == $format  ) {
				$view = 'shortcodes/detail-icon-title.php';
			} else if ( 'html' == $format ) {
				$view = 'shortcodes/detail.php';
			}

			$title = $data;
			if ( ! is_array( $data ) )
				$data = array( $data );

			$data = WPMovieLibrary::render_template( $view, array( 'detail' => 'barcode', 'data' => 'barcode', 'title' => $title ), $require = 'always' );

			return $data;
		}

	}
endif;
