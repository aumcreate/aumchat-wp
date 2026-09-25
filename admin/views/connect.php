<?php
/**
 * The screen before a site is connected.
 *
 * @package AumChat
 *
 * @var string $connect_url Where the Connect button goes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aml-card aumchat-connect">
	<p class="aumchat-connect-lead"><?php esc_html_e( 'Connect this site to your AumChat workspace.', 'aumchat' ); ?></p>
	<p class="aumchat-connect-sub"><?php esc_html_e( 'The chat widget then appears on your pages, and you answer visitors from the workspace.', 'aumchat' ); ?></p>
	<p>
		<a class="aml-btn" href="<?php echo esc_url( $connect_url ); ?>">
			<span class="dashicons dashicons-admin-links"></span>
			<?php esc_html_e( 'Connect to AumChat', 'aumchat' ); ?>
		</a>
	</p>
	<p class="aml-field-hint">
		<?php
		printf(
			/* translators: %s: link to the AumChat sign-up page. */
			esc_html__( 'You will need an AumChat account. Creating one is free: %s', 'aumchat' ),
			'<a href="' . esc_url( AUMCHAT_SERVICE . '/login' ) . '" target="_blank" rel="noopener">chat.aumcreate.com</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inline.
		);
		?>
	</p>
</div>

<details class="aml-card aumchat-manual">
	<summary><?php esc_html_e( 'Rather not leave WordPress? Paste the site key instead', 'aumchat' ); ?></summary>
	<p class="aml-field-hint" style="margin-top:12px">
		<?php esc_html_e( 'In the workspace the key is on Site settings, next to the installation snippet.', 'aumchat' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="aumchat_save" />
		<?php wp_nonce_field( 'aumchat_save' ); ?>
		<div class="aml-field">
			<label class="aml-label" for="aumchat_site_key"><?php esc_html_e( 'Site key', 'aumchat' ); ?></label>
			<input name="aumchat_site_key" id="aumchat_site_key" type="text" class="regular-text" value="" autocomplete="off" spellcheck="false" />
		</div>
		<div class="aml-actions-bar">
			<button type="submit" class="aml-btn"><?php esc_html_e( 'Save site key', 'aumchat' ); ?></button>
		</div>
	</form>
</details>
