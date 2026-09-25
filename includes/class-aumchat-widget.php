<?php
/**
 * Puts the widget on the front end.
 *
 * @package AumChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One job: print the AumChat loader on public pages, once, asynchronously.
 */
class AumChat_Widget {

	/**
	 * Handle used for the enqueued script.
	 */
	const HANDLE = 'aumchat-loader';

	/**
	 * Hook into the front end only.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_site_attribute' ), 10, 2 );
	}

	/**
	 * Enqueue the loader when a site is connected.
	 *
	 * Not loaded in the admin, in the block editor, in feeds, or on AMP pages:
	 * a chat widget there is at best useless and at worst breaks the screen.
	 */
	public function enqueue() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return;
		}

		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			return;
		}

		$settings = aumchat_get_settings();
		if ( '' === $settings['site_key'] ) {
			return;
		}

		/* Where the site owner said it may appear (Settings > AumChat). */
		if ( ! AumChat_Visibility::allowed() ) {
			return;
		}

		/**
		 * Filters whether the widget is printed on the current request.
		 *
		 * Returning false hides it, which is how a theme hides the widget on
		 * checkout, on a landing page, or for signed-in members.
		 *
		 * @param bool   $show     Whether to print the widget.
		 * @param string $site_key The connected site key.
		 */
		if ( ! apply_filters( 'aumchat_show_widget', true, $settings['site_key'] ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			AUMCHAT_SERVICE . '/loader.js',
			array(),
			null, // The service versions its own file; a ?ver= query would only break its cache.
			array(
				'strategy'  => 'async',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Add the data-site attribute the loader reads.
	 *
	 * The loader uses document.currentScript to find its own key, so the
	 * attribute has to be on the tag itself.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function add_site_attribute( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		$settings = aumchat_get_settings();
		if ( '' === $settings['site_key'] ) {
			return $tag;
		}

		return str_replace(
			' src=',
			' data-site="' . esc_attr( $settings['site_key'] ) . '" src=',
			$tag
		);
	}
}
