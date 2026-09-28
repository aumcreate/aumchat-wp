<?php
/**
 * The settings screen: connect a site, then get out of the way.
 *
 * @package AumChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings > AumChat.
 */
class AumChat_Admin {

	/**
	 * Page slug.
	 */
	const PAGE = 'aumchat';

	/**
	 * The hook suffix of our settings screen, so the stylesheet loads there and
	 * nowhere else. Without this check a plugin's admin CSS leaks into every
	 * other screen in the dashboard.
	 *
	 * @var string
	 */
	private $screen = '';

	/**
	 * Register the screen and its handlers.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'catch_connect_return' ) );
		add_action( 'admin_post_aumchat_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_aumchat_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_post_aumchat_recheck', array( $this, 'handle_recheck' ) );
		add_action( 'admin_post_aumchat_rules', array( $this, 'handle_rules' ) );
		add_action( 'admin_post_aumchat_sync', array( $this, 'handle_sync' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AUMCHAT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add the settings page.
	 */
	public function add_page() {
		$this->screen = (string) add_options_page(
			__( 'AumChat', 'aumchat' ),
			__( 'AumChat', 'aumchat' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Our stylesheet, on our screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( '' === $this->screen || $hook !== $this->screen ) {
			return;
		}

		$rel  = 'admin/assets/admin.css';
		$path = AUMCHAT_DIR . $rel;

		wp_enqueue_style(
			'aumchat-admin',
			plugins_url( $rel, AUMCHAT_FILE ),
			array( 'dashicons' ),
			file_exists( $path ) ? (string) filemtime( $path ) : AUMCHAT_VERSION
		);
	}

	/**
	 * Save the visibility rules.
	 */
	public function handle_rules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'aumchat' ) );
		}
		check_admin_referer( 'aumchat_rules' );

		$raw = isset( $_POST['aumchat_rules'] ) && is_array( $_POST['aumchat_rules'] )
			? map_deep( wp_unslash( $_POST['aumchat_rules'] ), 'sanitize_textarea_field' )
			: array();

		update_option( AUMCHAT_RULES_OPTION, AumChat_Visibility::sanitize( $raw ) );

		wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'rules' ) ) );
		exit;
	}

	/**
	 * "Settings" next to Deactivate on the plugins list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'aumchat' ) . '</a>' );

		return $links;
	}

	/**
	 * URL of this settings page.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	private function page_url( $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE ), $args ),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Coming back from the workspace: ?aumchat_key=...&state=<nonce>.
	 *
	 * The state is a nonce this site created before sending the admin away, so a
	 * key can only arrive as the result of someone here pressing Connect.
	 */
	public function catch_connect_return() {
		if ( ! isset( $_GET['page'], $_GET['aumchat_key'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is the "state" parameter checked below.
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! wp_verify_nonce( $state, 'aumchat_connect' ) ) {
			wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'state' ) ) );
			exit;
		}

		$key = aumchat_clean_key( sanitize_text_field( wp_unslash( $_GET['aumchat_key'] ) ) );
		if ( '' === $key ) {
			wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'badkey' ) ) );
			exit;
		}

		$this->connect( $key );
	}

	/**
	 * Pasted the key by hand.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'aumchat' ) );
		}
		check_admin_referer( 'aumchat_save' );

		$raw = isset( $_POST['aumchat_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['aumchat_site_key'] ) ) : '';
		$key = aumchat_clean_key( $raw );

		if ( '' === $key ) {
			wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'badkey' ) ) );
			exit;
		}

		$this->connect( $key );
	}

	/**
	 * Verify a key with the service and store it.
	 *
	 * The key is saved either way: the widget is allowed to work even when the
	 * service cannot be reached from this server right now.
	 *
	 * @param string $key Clean site key.
	 */
	private function connect( $key ) {
		$status = AumChat_Service::check( $key, aumchat_this_domain() );

		if ( is_wp_error( $status ) ) {
			if ( 'aumchat_unknown_key' === $status->get_error_code() ) {
				wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'unknown' ) ) );
				exit;
			}

			aumchat_save_settings(
				array(
					'site_key'    => $key,
					'site_name'   => '',
					'site_domain' => '',
				)
			);
			wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'saved_unverified' ) ) );
			exit;
		}

		aumchat_save_settings(
			array(
				'site_key'    => $key,
				'site_name'   => $status['name'],
				'site_domain' => $status['domain'],
			)
		);
		delete_transient( AumChat_Service::TRANSIENT . '_' . md5( $key . '|' . aumchat_this_domain() ) );

		wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'connected' ) ) );
		exit;
	}

	/**
	 * Forget the key.
	 */
	public function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'aumchat' ) );
		}
		check_admin_referer( 'aumchat_disconnect' );

		delete_option( AUMCHAT_OPTION );
		wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => 'disconnected' ) ) );
		exit;
	}

	/**
	 * Ask the service again, ignoring the cache.
	 */

	/**
	 * "Sync products now".
	 *
	 * @return void
	 */
	public function handle_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'aumchat' ) );
		}
		check_admin_referer( 'aumchat_sync' );

		/*
		 * Empty means "keep what is stored" — the field is rendered empty on every load because the
		 * saved token is never printed back (see admin/views/connected.php). Clearing it therefore
		 * needs its own control, or a stored token could never be removed.
		 */
		$token = isset( $_POST['push_token'] ) ? sanitize_text_field( wp_unslash( $_POST['push_token'] ) ) : '';
		if ( isset( $_POST['forget_token'] ) ) {
			aumchat_save_settings( array( 'push_token' => '' ) );
		} elseif ( '' !== $token ) {
			aumchat_save_settings( array( 'push_token' => $token ) );
		}

		$result = AumChat_Catalog::push();
		set_transient( 'aumchat_sync_result', $result, 60 );

		wp_safe_redirect( $this->page_url( array( 'aumchat_notice' => $result['ok'] ? 'synced' : 'sync_failed' ) ) );
		exit;
	}

	public function handle_recheck() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'aumchat' ) );
		}
		check_admin_referer( 'aumchat_recheck' );

		$settings = aumchat_get_settings();
		if ( '' !== $settings['site_key'] ) {
			$status = AumChat_Service::status( $settings['site_key'], aumchat_this_domain(), true );
			if ( ! is_wp_error( $status ) ) {
				aumchat_save_settings(
					array(
						'site_name'   => $status['name'],
						'site_domain' => $status['domain'],
					)
				);
			}
		}

		wp_safe_redirect( $this->page_url() );
		exit;
	}

	/**
	 * Print the notice for ?aumchat_notice=.
	 */
	private function notice() {
		$code = isset( $_GET['aumchat_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['aumchat_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides which message to print.
		if ( '' === $code ) {
			return;
		}

		$messages = array(
			'connected'        => array( 'success', __( 'Connected. The widget is live on your site.', 'aumchat' ) ),
			'disconnected'     => array( 'success', __( 'Disconnected. The widget has been removed from your site.', 'aumchat' ) ),
			'saved_unverified' => array( 'warning', __( 'Site key saved, but this server could not reach AumChat to confirm it. The widget will still load for visitors.', 'aumchat' ) ),
			'badkey'           => array( 'error', __( 'That does not look like a site key. It is 6 to 40 letters and digits.', 'aumchat' ) ),
			'unknown'          => array( 'error', __( 'AumChat does not know this site key. Copy it again from your workspace.', 'aumchat' ) ),
			'state'            => array( 'error', __( 'That connection link had expired. Press Connect again.', 'aumchat' ) ),
			'rules'            => array( 'success', __( 'Saved.', 'aumchat' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}

	/**
	 * The settings screen: one card before connecting, three after.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = aumchat_get_settings();
		$connected = '' !== $settings['site_key'];
		$domain    = aumchat_this_domain();
		$rules     = AumChat_Visibility::get();
		$status    = $connected ? AumChat_Service::status( $settings['site_key'], $domain ) : null;

		$roles = array();
		if ( function_exists( 'wp_roles' ) ) {
			foreach ( wp_roles()->get_names() as $role => $label ) {
				$roles[ $role ] = translate_user_role( $label );
			}
		}

		$connect_url = add_query_arg(
			array(
				'domain' => rawurlencode( $domain ),
				'return' => rawurlencode( $this->page_url() ),
				'state'  => rawurlencode( wp_create_nonce( 'aumchat_connect' ) ),
			),
			AUMCHAT_SERVICE . '/wp-connect'
		);
		?>
		<div class="wrap aml-app">
			<div class="aml-header">
				<span class="aml-logo"><span class="dashicons dashicons-format-chat"></span></span>
				<div>
					<h1 class="aml-title">
						<?php esc_html_e( 'AumChat', 'aumchat' ); ?>
						<?php if ( $connected ) : ?>
							<span class="aml-badge"><?php esc_html_e( 'Connected', 'aumchat' ); ?></span>
						<?php endif; ?>
					</h1>
					<p class="aml-subtitle"><?php esc_html_e( 'Chat with the people on your site, answered by AI until a person takes over.', 'aumchat' ); ?></p>
				</div>
			</div>
			<?php /* WordPress drops admin notices after the first h1; this marker tells it to put them here instead, so they do not land inside the header. */ ?>
			<hr class="wp-header-end" />

			<?php $this->notice(); ?>

			<?php
			if ( $connected ) {
				require AUMCHAT_DIR . 'admin/views/connected.php';
			} else {
				require AUMCHAT_DIR . 'admin/views/connect.php';
			}
			?>
		</div>
		<?php
	}

}

new AumChat_Admin();
