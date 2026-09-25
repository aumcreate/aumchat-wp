<?php
/**
 * The status line.
 *
 * This is the reason the plugin talks to the service at all: a site key that is
 * correct but saved for another domain shows nothing on the site and reports no
 * error anywhere, so it has to be said out loud, in the one place someone looks.
 *
 * @package AumChat
 *
 * @var array|WP_Error|null $status Result of the check.
 * @var string              $domain This site's domain.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_wp_error( $status ) ) : ?>
	<p class="aumchat-status is-bad">
		<span class="dashicons dashicons-warning"></span>
		<span><?php echo esc_html( $status->get_error_message() ); ?></span>
	</p>
	<?php
	return;
endif;

if ( ! is_array( $status ) ) {
	return;
}

if ( false === $status['domain_matches'] ) :
	?>
	<p class="aumchat-status is-bad">
		<span class="dashicons dashicons-warning"></span>
		<span>
			<strong><?php esc_html_e( 'The widget will not appear yet.', 'aumchat' ); ?></strong><br />
			<?php
			printf(
				/* translators: 1: domain saved in AumChat, 2: this WordPress site's domain. */
				esc_html__( 'This site key belongs to %1$s, and AumChat only serves the widget on the domain saved with it. This WordPress site is %2$s. Change the domain in the workspace, or connect the site that matches.', 'aumchat' ),
				'<code>' . esc_html( $status['domain'] ) . '</code>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inline.
				'<code>' . esc_html( $domain ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inline.
			);
			?>
		</span>
	</p>
	<?php
	return;
endif;

if ( $status['widget_seen_at'] ) :
	?>
	<p class="aumchat-status is-ok">
		<span class="dashicons dashicons-yes-alt"></span>
		<span>
			<strong><?php esc_html_e( 'Live.', 'aumchat' ); ?></strong>
			<?php
			printf(
				/* translators: %s: human readable time difference, for example "5 minutes". */
				esc_html__( 'AumChat last saw the widget on this site %s ago.', 'aumchat' ),
				esc_html( human_time_diff( strtotime( $status['widget_seen_at'] ), time() ) )
			);
			?>
		</span>
	</p>
	<?php
	return;
endif;
?>
<p class="aumchat-status is-wait">
	<span class="dashicons dashicons-clock"></span>
	<span><?php esc_html_e( 'Connected. Open your site in a browser once and the widget will appear.', 'aumchat' ); ?></span>
</p>
