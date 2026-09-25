<?php
/**
 * Where the widget is allowed to appear.
 *
 * @package AumChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides, for the current request, whether the widget should load.
 *
 * All of this is decided inside WordPress. Nothing here is sent anywhere: the
 * rules only choose whether the loader script is printed on this page.
 */
class AumChat_Visibility {

	/**
	 * Defaults: show it everywhere. A chat widget that hides by default would
	 * be a support ticket waiting to happen.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mode'         => 'everywhere', // everywhere | only | except.
			'urls'         => '',           // One path or URL per line, used by "only" and "except".
			'hide_roles'   => array(),      // Roles that never see it, e.g. administrator.
			'hide_cart'    => false,        // WooCommerce cart and checkout.
		);
	}

	/**
	 * Stored rules, with defaults filled in.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( AUMCHAT_RULES_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$rules = array_merge( self::defaults(), $saved );

		$rules['mode']       = in_array( $rules['mode'], array( 'everywhere', 'only', 'except' ), true ) ? $rules['mode'] : 'everywhere';
		$rules['urls']       = (string) $rules['urls'];
		$rules['hide_roles'] = is_array( $rules['hide_roles'] ) ? array_values( array_filter( array_map( 'sanitize_key', $rules['hide_roles'] ) ) ) : array();
		$rules['hide_cart']  = (bool) $rules['hide_cart'];

		return $rules;
	}

	/**
	 * Clean a submitted set of rules.
	 *
	 * @param array $raw Raw $_POST slice, already unslashed.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$rules = self::defaults();

		if ( isset( $raw['mode'] ) && in_array( $raw['mode'], array( 'everywhere', 'only', 'except' ), true ) ) {
			$rules['mode'] = $raw['mode'];
		}

		if ( isset( $raw['urls'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $raw['urls'] );
			$clean = array();
			foreach ( $lines as $line ) {
				$line = trim( sanitize_text_field( $line ) );
				if ( '' !== $line ) {
					$clean[] = $line;
				}
			}
			$rules['urls'] = implode( "\n", array_slice( $clean, 0, 200 ) );
		}

		if ( isset( $raw['hide_roles'] ) && is_array( $raw['hide_roles'] ) ) {
			$rules['hide_roles'] = array_values( array_filter( array_map( 'sanitize_key', $raw['hide_roles'] ) ) );
		}

		$rules['hide_cart'] = ! empty( $raw['hide_cart'] );

		return $rules;
	}

	/**
	 * Should the widget load on this request?
	 *
	 * @return bool
	 */
	public static function allowed() {
		$rules = self::get();

		if ( self::hidden_for_current_user( $rules ) ) {
			return false;
		}

		if ( $rules['hide_cart'] && self::is_cart_or_checkout() ) {
			return false;
		}

		if ( 'everywhere' === $rules['mode'] ) {
			return true;
		}

		$listed = self::current_url_listed( $rules['urls'] );

		return 'only' === $rules['mode'] ? $listed : ! $listed;
	}

	/**
	 * Hidden for the person looking at the page because of their role.
	 *
	 * @param array $rules Rules.
	 * @return bool
	 */
	private static function hidden_for_current_user( $rules ) {
		if ( empty( $rules['hide_roles'] ) || ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		return (bool) array_intersect( (array) $user->roles, $rules['hide_roles'] );
	}

	/**
	 * WooCommerce cart or checkout, when WooCommerce is active.
	 *
	 * @return bool
	 */
	private static function is_cart_or_checkout() {
		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		return is_cart() || is_checkout();
	}

	/**
	 * Does the current request match one of the listed paths?
	 *
	 * A line matches the page itself and everything under it, so `/shop` covers
	 * `/shop/mugs` — but not `/shopping`, which is why this compares whole path
	 * segments instead of a plain prefix. A line ending in `*` asks for the loose
	 * version on purpose (`/shop*` does match `/shopping`), and a full URL is
	 * accepted because that is what people paste.
	 *
	 * @param string $urls One per line.
	 * @return bool
	 */
	private static function current_url_listed( $urls ) {
		$path = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		}
		$path = '/' . trim( $path, '/' );

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $urls ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			/* Accept a pasted full URL by keeping only its path. */
			if ( preg_match( '#^https?://#i', $line ) ) {
				$line = (string) wp_parse_url( $line, PHP_URL_PATH );
			}

			$loose = ( '*' === substr( $line, -1 ) );
			$line  = rtrim( $line, '*' );
			$line  = '/' . trim( $line, '/' );

			if ( '/' === $line ) {
				/* The home page only, not "everything" — unless they wrote /*. */
				if ( $loose || '/' === $path ) {
					return true;
				}
				continue;
			}

			if ( $loose ) {
				if ( 0 === strpos( $path, $line ) ) {
					return true;
				}
				continue;
			}

			if ( $path === $line || 0 === strpos( $path, $line . '/' ) ) {
				return true;
			}
		}

		return false;
	}
}
