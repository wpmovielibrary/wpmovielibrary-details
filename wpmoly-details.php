<?php
/**
 * WPMovieLibrary-Details
 *
 * Boilerplate plugin to demonstrate details extension possibilities
 *
 * @package   WPMovieLibrary-Details
 * @author    Charlie MERLAND <charlie@caercam.org>
 * @license   GPL-3.0
 * @link      http://www.caercam.org/
 * @copyright 2014 CaerCam.org
 *
 * @wordpress-plugin
 * Plugin Name: WPMovieLibrary-Details
 * Plugin URI:  https://github.com/wpmovielibrary/wpmovielibrary-details
 * Description: Boilerplate plugin to demonstrate details extension possibilities
 * Version:     1.0
 * Author:      Charlie MERLAND
 * Author URI:  http://www.caercam.org/
 * License:     GPL-3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * GitHub Plugin URI: https://github.com/wpmovielibrary/wpmovielibrary-details
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WPMOLY_DETAILS_NAME',                   'WPMovieLibrary-Details' );
define( 'WPMOLY_DETAILS_VERSION',                '1.0' );
define( 'WPMOLY_DETAILS_SLUG',                   'wpmoly-details' );
define( 'WPMOLY_DETAILS_URL',                    plugins_url( basename( __DIR__ ) ) );
define( 'WPMOLY_DETAILS_PATH',                   plugin_dir_path( __FILE__ ) );
define( 'WPMOLY_DETAILS_REQUIRED_PHP_VERSION',   '5.4' );
define( 'WPMOLY_DETAILS_REQUIRED_WP_VERSION',    '4.0' );


/**
 * Determine whether WPMOLY is active or not.
 *
 * @since    1.0
 *
 * @return   boolean
 */
if ( ! function_exists( 'is_wpmoly_active' ) ) :
	function is_wpmoly_active() {

		return defined( 'WPMOLY_VERSION' );
	}
endif;

/**
 * Checks if the system requirements are met
 * 
 * @since    1.0
 * 
 * @return   bool    True if system requirements are met, false if not
 */
function wpmoly_details_requirements_met() {

	global $wp_version;

	if ( version_compare( PHP_VERSION, WPMOLY_DETAILS_REQUIRED_PHP_VERSION, '<' ) )
		return false;

	if ( version_compare( $wp_version, WPMOLY_DETAILS_REQUIRED_WP_VERSION, '<' ) )
		return false;

	return true;
}

/**
 * Prints an error that the system requirements weren't met.
 * 
 * @since    1.0
 */
function wpmoly_details_requirements_error() {
	global $wp_version;

	require_once WPMOLY_DETAILS_PATH . '/views/requirements-error.php';
}

/**
 * Prints an error that the system requirements weren't met.
 * 
 * @since    1.0
 */
function wpmoly_details_l10n() {

	$domain = 'wpmovielibrary-details';
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	load_textdomain( $domain, WPMOLY_DETAILS_PATH . 'languages/' . $domain . '-' . $locale . '.mo' );
	load_plugin_textdomain( $domain, FALSE, basename( __DIR__ ) . '/languages/' );
}

/*
 * Check requirements and load main class
 * The main program needs to be in a separate file that only gets loaded if the
 * plugin requirements are met. Otherwise older PHP installations could crash
 * when trying to parse it.
 */
if ( wpmoly_details_requirements_met() ) {

	require_once( WPMOLY_DETAILS_PATH . 'includes/class-module.php' );
	require_once( WPMOLY_DETAILS_PATH . 'class-wpmoly-details.php' );

	if ( class_exists( 'WPMovieLibrary_Details' ) ) {
		$GLOBALS['wpmoly_details'] = new WPMovieLibrary_Details();
		register_activation_hook(   __FILE__, array( $GLOBALS['wpmoly_details'], 'activate' ) );
		register_deactivation_hook( __FILE__, array( $GLOBALS['wpmoly_details'], 'deactivate' ) );
	}
}
else {
	wpmoly_details_requirements_error();
}
