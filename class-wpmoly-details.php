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
				'id'       => 'wpmoly-movie-audio',
				'name'     => 'wpmoly_details[audio]',
				'type'     => 'select',
				'title'    => __( 'Audio Format', 'wpmovielibrary-details' ),
				'desc'     => __( 'Select an audio format for this movie', 'wpmovielibrary-details' ),
				'icon'     => 'dashicons dashicons-megaphone',
				'options'  => array(
					'mono'     => __( 'Mono', 'wpmovielibrary-details' ),
					'stereo'   => __( 'Stéréo', 'wpmovielibrary-details' ),
					'pcm'      => __( 'PCM', 'wpmovielibrary-details' ),
					'dbthd'    => __( 'DOLBY DIGITAL TrueHD', 'wpmovielibrary-details' ),
					'dbs'      => __( 'DOLBY SURROUND', 'wpmovielibrary-details' ),
					'dbex'     => __( 'DOLBY DIGITAL EX', 'wpmovielibrary-details' ),
					'db10'     => __( 'DOLBY DIGITAL 1.0', 'wpmovielibrary-details' ),
					'dbt20'    => __( 'DOLBY DIGITAL 2.0', 'wpmovielibrary-details' ),
					'dbt51'    => __( 'DOLBY DIGITAL 5.1', 'wpmovielibrary-details' ),
					'dbplus'   => __( 'DOLBY DIGITAL PLUS', 'wpmovielibrary-details' ),
					'dts'      => __( 'DTS', 'wpmovielibrary-details' ),
					'dtsds'    => __( 'DTS Digital Surround', 'wpmovielibrary-details' ),
					'dtshd'    => __( 'DTS-HD', 'wpmovielibrary-details' ),
					'dtshdma'  => __( 'DTS-HD Master Audio', 'wpmovielibrary-details' ),
					'dtshdhra' => __( 'DTS-HD High Resolution Audio', 'wpmovielibrary-details' )
				),
				'default'  => 'stereo',
				'rewrite'  => array( 'audio' => __( 'audio', 'wpmovielibrary-details' ) )
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

			// Add a new status
			add_filter( 'wpmoly_filter_detail_status', array( $this, 'add_movie_status' ), 10, 1 );

			// Create a new detail
			add_filter( 'wpmoly_pre_filter_details', array( $this, 'create_detail' ), 10, 1 );

			// Add new detail to the settings panel
			add_filter( 'redux/options/wpmoly_settings/field/wpmoly-sort-details/register', array( $this, 'detail_setting' ), 10, 1 );

			// Add a formatting filter
			add_filter( 'wpmoly_format_movie_audio', array( $this, 'format_detail' ), 10, 1 );

			// Create a new Metabox tab
			add_filter( 'wpmoly_filter_metabox_panels', array( $this, 'add_metabox_panel' ), 10, 1 );
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
		 * Register and enqueue public-facing style sheet.
		 *
		 * @since    1.0
		 */
		public function enqueue_styles() {

			wp_enqueue_style( WPMLTR_SLUG . '-css', WPMLTR_URL . '/assets/css/public.css', array(), WPMLTR_VERSION );
		}

		/**
		 * Register and enqueue public-facing style sheet.
		 *
		 * @since    1.0
		 */
		public function admin_enqueue_styles() {

			wp_enqueue_style( WPMLTR_SLUG . '-admin-css', WPMLTR_URL . '/assets/css/admin.css', array(), WPMLTR_VERSION );
		}

		/**
		 * Register and enqueue public-facing style sheet.
		 *
		 * @since    1.0
		 */
		public function admin_enqueue_scripts() {

			wp_enqueue_script( WPMLTR_SLUG . 'admin-js', WPMLTR_URL . '/assets/js/admin.js', array( WPML_SLUG ), WPMLTR_VERSION, true );
		}

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
		 * Add a new movie status
		 *
		 * @since    1.0
		 * 
		 * @param    array    Exisiting statuses
		 * 
		 * @return   array    Updated statuses
		 */
		public function add_movie_status( $statuses ) {

			$new_status = array( 'rented' => __( 'Rented', 'wpmovielibrary-details' ) );

			$statuses = array_merge( $statuses, $new_status );

			return $statuses;
		}

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

			$details = array_merge( $details, array( 'audio' => $this->detail ) );

			return $details;
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

			$field['options']['available'] = array_merge( $field['options']['available'], array( 'audio' => $this->detail['title'] ) );

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
		 * @param    array    Exisiting detail field
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

			$title = '';
			if ( isset( $this->detail['options'][ $data ] ) )
				$title = $this->detail['options'][ $data ];
			$data = 'audio';
			$data = WPMovieLibrary::render_template( $view, array( 'detail' => 'audio', 'data' => $data, 'title' => $title ), $require = 'always' );

			return $data;
		}

		/**
		 * Create a new Metabox Panel
		 *
		 * @since    1.0
		 * 
		 * @param    array    Exisiting panels
		 * 
		 * @return   array    Updated panels
		 */
		public function add_metabox_panel( $panels ) {

			$new_panels = array(
				'trailer' => array(
					'title'    => __( 'Trailer', 'wpmovielibrary-details' ),
					'icon'     => 'dashicons dashicons-video-alt3',
					'callback' => array( $this, 'render_trailer_panel' )
				)
			);

			$panels = array_merge( $panels, $new_panels );

			return $panels;
		}

		/**
		 * Render Panel content
		 *
		 * @since    1.0
		 */
		public function render_trailer_panel() {

			ob_start();
?>
		<div id="wpmoly-images" class="wpmoly-images">
			<?php _e( 'My new trailer panel!', 'wpmovielibrary-details' ) ?>
		</div>

<?php
			$content = ob_get_contents();
			ob_end_clean();

			return $content;
		}

	}
endif;
