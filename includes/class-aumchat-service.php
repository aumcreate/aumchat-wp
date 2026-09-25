<?php
/**
 * The one call this plugin makes to AumChat.
 *
 * @package AumChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks a site key against the service.
 *
 * This is the only request the plugin itself makes, it happens only in the admin
 * when someone connects or presses "Check again", and it sends only the site key
 * and this site's domain. Visitors are never involved in it.
 */
class AumChat_Service {

	/**
	 * Cache key for the last result.
	 */
	const TRANSIENT = 'aumchat_status';

	/**
	 * Ask the service about a key.
	 *
	 * @param string $key    Site key.
	 * @param string $domain This WordPress site's domain.
	 * @return array|WP_Error {
	 *     @type string      $name            Site name in AumChat.
	 *     @type string      $domain          Domain saved in AumChat.
	 *     @type bool|null   $domain_matches  Whether it matches this site.
	 *     @type string|null $widget_seen_at  When the widget last loaded, ISO 8601.
	 * }
	 */
	public static function check( $key, $domain ) {
		$url = add_query_arg(
			array(
				'key'    => rawurlencode( $key ),
				'domain' => rawurlencode( $domain ),
			),
			AUMCHAT_SERVICE . '/api/plugin/site'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'user-agent' => 'AumChat WordPress plugin/' . AUMCHAT_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 404 === $code ) {
			return new WP_Error( 'aumchat_unknown_key', __( 'AumChat does not know this site key. Copy it again from your workspace.', 'aumchat' ) );
		}

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['ok'] ) ) {
			return new WP_Error(
				'aumchat_unexpected',
				sprintf(
					/* translators: %d: HTTP status code returned by the AumChat service. */
					__( 'AumChat answered with an unexpected response (HTTP %d). Try again in a moment.', 'aumchat' ),
					(int) $code
				)
			);
		}

		return array(
			'name'           => isset( $body['name'] ) ? (string) $body['name'] : '',
			'domain'         => isset( $body['domain'] ) ? (string) $body['domain'] : '',
			'domain_matches' => isset( $body['domainMatches'] ) ? (bool) $body['domainMatches'] : null,
			'widget_seen_at' => isset( $body['widgetSeenAt'] ) && $body['widgetSeenAt'] ? (string) $body['widgetSeenAt'] : null,
		);
	}

	/**
	 * Same as check(), cached for fifteen minutes so opening the settings page
	 * repeatedly does not hit the service every time.
	 *
	 * @param string $key    Site key.
	 * @param string $domain This site's domain.
	 * @param bool   $fresh  Skip the cache.
	 * @return array|WP_Error
	 */
	public static function status( $key, $domain, $fresh = false ) {
		$cache_key = self::TRANSIENT . '_' . md5( $key . '|' . $domain );

		if ( ! $fresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = self::check( $key, $domain );
		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
		}

		return $result;
	}
}
