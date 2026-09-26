<?php
/**
 * Plugin Name:       AumChat
 * Description:       Puts the AumChat widget on your site. Connect once; everything else is configured in your AumChat workspace.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            AumCreate
 * Author URI:        https://aumcreate.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aumchat
 *
 * @package AumChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AUMCHAT_VERSION', '1.0.0' );
define( 'AUMCHAT_FILE', __FILE__ );
define( 'AUMCHAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUMCHAT_OPTION', 'aumchat_settings' );
define( 'AUMCHAT_RULES_OPTION', 'aumchat_rules' );

/**
 * The service this plugin is a client for.
 *
 * Hard-coded, https only, and never taken from user input: the widget script is
 * loaded from here into every visitor's browser, so it must not be settable.
 */
/*
 * The AumChat service. Sites never need to change this; it exists as a constant so that
 * development against a local server does not require editing the plugin (define it in wp-config.php).
 */
if ( ! defined( 'AUMCHAT_SERVICE' ) ) {
	define( 'AUMCHAT_SERVICE', 'https://chat.aumcreate.com' );
}

require_once AUMCHAT_DIR . 'includes/class-aumchat-visibility.php';
require_once AUMCHAT_DIR . 'includes/class-aumchat-widget.php';
require_once AUMCHAT_DIR . 'includes/class-aumchat-service.php';
require_once AUMCHAT_DIR . 'includes/class-aumchat-catalog.php';

if ( is_admin() ) {
	require_once AUMCHAT_DIR . 'includes/class-aumchat-admin.php';
}

/**
 * Saves some settings while keeping the rest.
 *
 * Writing the whole option would quietly drop anything the caller did not list — which is how the
 * sync token disappeared the first time somebody pressed "Check again".
 *
 * It lives here, not in the admin class, because the daily sync runs through WP-Cron, where nothing
 * from wp-admin is loaded: calling an admin-only class from there would be a fatal error on exactly
 * the path nobody watches.
 *
 * @param array $changes Keys to change.
 * @return void
 */
function aumchat_save_settings( $changes ) {
	$saved = get_option( AUMCHAT_OPTION, array() );
	update_option( AUMCHAT_OPTION, array_merge( is_array( $saved ) ? $saved : array(), $changes ) );
}

/**
 * Stored settings.
 *
 * site_key    string  The public key of the AumChat site. It is printed in the page
 *                     source on purpose; it is an identifier, not a secret.
 * site_name   string  Remembered from the last check, so the settings page can say
 *                     which site is connected without calling the service on every load.
 * site_domain string  Same, used to warn when it stops matching this WordPress site.
 *
 * @return array{site_key:string,site_name:string,site_domain:string,push_token:string,push_at:int,push_count:int}
 */
function aumchat_get_settings() {
	$saved = get_option( AUMCHAT_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return array(
		'site_key'    => isset( $saved['site_key'] ) ? (string) $saved['site_key'] : '',
		'site_name'   => isset( $saved['site_name'] ) ? (string) $saved['site_name'] : '',
		'site_domain' => isset( $saved['site_domain'] ) ? (string) $saved['site_domain'] : '',
		'push_token'  => isset( $saved['push_token'] ) ? (string) $saved['push_token'] : '',
		'push_at'     => isset( $saved['push_at'] ) ? (int) $saved['push_at'] : 0,
		'push_count'  => isset( $saved['push_count'] ) ? (int) $saved['push_count'] : 0,
	);
}

/**
 * A site key is 6-40 letters and digits. Anything else never reaches the database
 * or the page, so a bad paste cannot become markup.
 *
 * @param string $key Raw input.
 * @return string Clean key, or an empty string.
 */
function aumchat_clean_key( $key ) {
	$key = trim( (string) $key );

	return preg_match( '/^[A-Za-z0-9]{6,40}$/', $key ) ? $key : '';
}

/**
 * The host of this WordPress site, in the shape AumChat stores domains in:
 * lower case, no scheme, no path, no leading "www.".
 *
 * @return string
 */
function aumchat_this_domain() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$port = wp_parse_url( home_url(), PHP_URL_PORT );
	if ( ! is_string( $host ) || '' === $host ) {
		return '';
	}

	$host = strtolower( $host );
	$host = preg_replace( '/^www\./', '', $host );

	/* Keep the port only when there is one, so local development matches too. */
	return $port ? $host . ':' . (int) $port : $host;
}

register_activation_hook( __FILE__, array( 'AumChat_Catalog', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AumChat_Catalog', 'deactivate' ) );

new AumChat_Widget();
new AumChat_Catalog();
