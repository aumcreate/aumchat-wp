<?php
/**
 * The screen once a site is connected: what it is, whether it works, where it shows.
 *
 * @package AumChat
 *
 * @var array               $settings Stored settings.
 * @var array|WP_Error|null $status   Result of the last service check.
 * @var string              $domain   This site's domain.
 * @var array               $rules    Visibility rules.
 * @var array               $roles    All roles on this site, name => label.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aumchat_name = '' !== $settings['site_name'] ? $settings['site_name'] : $settings['site_key'];
?>
<div class="aml-card">
	<h2 class="aml-card-h"><span class="dashicons dashicons-admin-site-alt3"></span><?php esc_html_e( 'Connected site', 'aumchat' ); ?></h2>

	<div class="aumchat-site">
		<span class="grow">
			<span class="aumchat-site-name"><?php echo esc_html( $aumchat_name ); ?></span>
			<?php if ( '' !== $settings['site_domain'] ) : ?>
				<br /><span class="aumchat-site-domain"><?php echo esc_html( $settings['site_domain'] ); ?></span>
			<?php endif; ?>
		</span>
		<code class="aumchat-key"><?php echo esc_html( $settings['site_key'] ); ?></code>
	</div>

	<?php require AUMCHAT_DIR . 'admin/views/status.php'; ?>

	<div class="aml-actions-bar">
		<a class="aml-btn" href="<?php echo esc_url( AUMCHAT_SERVICE . '/overview' ); ?>" target="_blank" rel="noopener">
			<span class="dashicons dashicons-external"></span>
			<?php esc_html_e( 'Open the workspace', 'aumchat' ); ?>
		</a>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aumchat_recheck" />
			<?php wp_nonce_field( 'aumchat_recheck' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Check again', 'aumchat' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aumchat_disconnect" />
			<?php wp_nonce_field( 'aumchat_disconnect' ); ?>
			<button type="submit" class="aml-btn aml-btn-quiet"><?php esc_html_e( 'Disconnect', 'aumchat' ); ?></button>
		</form>
	</div>

	<p class="aml-field-hint">
		<?php esc_html_e( 'How the widget looks, what it answers, business hours and your team are set in the workspace, not here. They follow the site, so they apply everywhere it is installed.', 'aumchat' ); ?>
	</p>
</div>

<?php
/*
 * Products. Shown only when this site actually has a catalogue: a brochure site has no products,
 * and a settings box that can never do anything is just one more thing to wonder about.
 */
$aumchat_sources = AumChat_Catalog::sources();
if ( $aumchat_sources ) :
	$aumchat_names = array_map(
		static function ( $s ) {
			return 'nexcart' === $s ? 'AumNexCart' : 'WooCommerce';
		},
		$aumchat_sources
	);
	$aumchat_sync = get_transient( 'aumchat_sync_result' );
	?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aml-card">
	<input type="hidden" name="action" value="aumchat_sync" />
	<?php wp_nonce_field( 'aumchat_sync' ); ?>

	<h2 class="aml-card-title"><?php esc_html_e( 'Products', 'aumchat' ); ?></h2>
	<p class="aml-field-hint">
		<?php
		/*
		 * Names every catalogue found, because both can be present and both are sent. Naming only
		 * one told shop owners with both that half their catalogue was staying put, while it was
		 * in fact going out — a small wrong sentence about something that already happened.
		 */
		printf(
			/* translators: %s: names of the catalogue plugins found, e.g. "WooCommerce and AumNexCart". */
			esc_html__( '%s found on this site. Sending your products lets the widget answer questions about them and show product cards.', 'aumchat' ),
			esc_html( implode( ' + ', $aumchat_names ) )
		);
		?>
	</p>

	<?php
	/*
	 * The saved token is never printed back. Only the last four characters are, and only to answer
	 * "is one saved?".
	 *
	 * Why it matters: this value can write the whole catalogue into the shop's AumChat workspace,
	 * and the field used to render it in full with type="text" — directly under a hint that calls it
	 * a secret. Shop owners screenshot this screen for support, record it, share it in meetings;
	 * the token goes with it and they do not notice, because it looks like every other settings box
	 * on the page. The site key sitting right above makes that worse: the two fields looked identical
	 * and one of them is public on purpose.
	 *
	 * Leaving the box empty keeps what is stored (handle_sync already ignores an empty submit), so
	 * the common case — press Sync again — never asks for the token twice.
	 */
	$aumchat_token      = (string) $settings['push_token'];
	$aumchat_token_tail = strlen( $aumchat_token ) > 4 ? substr( $aumchat_token, -4 ) : '';
	?>
	<label class="aml-label" for="aumchat-push-token"><?php esc_html_e( 'Sync token', 'aumchat' ); ?></label>
	<?php if ( '' !== $aumchat_token ) : ?>
		<p class="aml-field-value">
			<code><?php echo esc_html( str_repeat( "\xe2\x80\xa2", 8 ) . $aumchat_token_tail ); ?></code>
			<?php esc_html_e( 'saved', 'aumchat' ); ?>
		</p>
	<?php endif; ?>
	<input
		type="password"
		id="aumchat-push-token"
		name="push_token"
		class="aml-input"
		value=""
		autocomplete="off"
		spellcheck="false"
		placeholder="<?php echo '' !== $aumchat_token ? esc_attr__( 'Leave empty to keep the saved token', 'aumchat' ) : ''; ?>"
	/>
	<p class="aml-field-hint">
		<?php esc_html_e( 'Copy it from Products in your AumChat workspace. It is a secret — unlike the site key, it is not public.', 'aumchat' ); ?>
	</p>
	<?php if ( '' !== $aumchat_token ) : ?>
		<p class="aml-field-hint">
			<label>
				<input type="checkbox" name="forget_token" value="1" />
				<?php esc_html_e( 'Forget the saved token (stops product sync)', 'aumchat' ); ?>
			</label>
		</p>
	<?php endif; ?>

	<?php if ( is_array( $aumchat_sync ) ) : ?>
		<div class="<?php echo $aumchat_sync['ok'] ? 'aml-ok' : 'aml-warn'; ?>"><?php echo esc_html( $aumchat_sync['message'] ); ?></div>
	<?php elseif ( $settings['push_at'] > 0 ) : ?>
		<p class="aml-field-hint">
			<?php
			printf(
				/* translators: 1: number of products, 2: how long ago, e.g. "2 hours". */
				esc_html__( 'Last sync: %1$d products, %2$s ago.', 'aumchat' ),
				(int) $settings['push_count'],
				esc_html( human_time_diff( $settings['push_at'], time() ) )
			);
			?>
		</p>
	<?php endif; ?>

	<p><button type="submit" class="aml-button"><?php esc_html_e( 'Sync products now', 'aumchat' ); ?></button></p>
</form>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="aumchat_rules" />
	<?php wp_nonce_field( 'aumchat_rules' ); ?>

	<div class="aml-card">
		<h2 class="aml-card-h"><span class="dashicons dashicons-visibility"></span><?php esc_html_e( 'Where the widget appears', 'aumchat' ); ?></h2>
		<p class="aml-card-desc"><?php esc_html_e( 'By default it is on every public page. These rules are applied here in WordPress; nothing about them is sent to AumChat.', 'aumchat' ); ?></p>

		<div class="aumchat-rules">
			<?php
			$aumchat_modes = array(
				'everywhere' => array( __( 'Every page', 'aumchat' ), __( 'The usual choice.', 'aumchat' ) ),
				'except'     => array( __( 'Every page except the ones listed below', 'aumchat' ), __( 'For a checkout, a landing page, a form you do not want interrupted.', 'aumchat' ) ),
				'only'       => array( __( 'Only the pages listed below', 'aumchat' ), __( 'For trying it on one page first.', 'aumchat' ) ),
			);
			foreach ( $aumchat_modes as $aumchat_value => $aumchat_copy ) :
				?>
				<label class="aml-switch-row">
					<input type="radio" name="aumchat_rules[mode]" value="<?php echo esc_attr( $aumchat_value ); ?>" <?php checked( $rules['mode'], $aumchat_value ); ?> />
					<span class="aml-switch-label">
						<?php echo esc_html( $aumchat_copy[0] ); ?>
						<span class="aumchat-rule-note"><?php echo esc_html( $aumchat_copy[1] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>

		<div class="aml-field">
			<label class="aml-label" for="aumchat_urls"><?php esc_html_e( 'The pages', 'aumchat' ); ?></label>
			<textarea class="aumchat-urls" id="aumchat_urls" name="aumchat_rules[urls]" rows="4" spellcheck="false"><?php echo esc_textarea( $rules['urls'] ); ?></textarea>
			<p class="aml-field-hint">
				<?php esc_html_e( 'One per line. A path covers everything under it, so /shop also covers /shop/mugs. Use / for the home page alone. Pasting a full address works too.', 'aumchat' ); ?>
			</p>
		</div>
	</div>

	<div class="aml-card">
		<h2 class="aml-card-h"><span class="dashicons dashicons-groups"></span><?php esc_html_e( 'Exceptions', 'aumchat' ); ?></h2>

		<?php if ( ! empty( $roles ) ) : ?>
			<div class="aml-field">
				<span class="aml-label"><?php esc_html_e( 'Hide it from signed-in users with these roles', 'aumchat' ); ?></span>
				<div class="aumchat-roles">
					<?php foreach ( $roles as $aumchat_role => $aumchat_label ) : ?>
						<label>
							<input type="checkbox" name="aumchat_rules[hide_roles][]" value="<?php echo esc_attr( $aumchat_role ); ?>" <?php checked( in_array( $aumchat_role, $rules['hide_roles'], true ) ); ?> />
							<?php echo esc_html( $aumchat_label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="aml-field-hint"><?php esc_html_e( 'Most people tick Administrator, so their own widget stops following them around while they work.', 'aumchat' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<label class="aml-switch-row">
				<span class="aml-switch">
					<input type="checkbox" name="aumchat_rules[hide_cart]" value="1" <?php checked( $rules['hide_cart'] ); ?> />
					<span class="aml-track"></span>
				</span>
				<span class="aml-switch-label">
					<?php esc_html_e( 'Hide it on the cart and checkout', 'aumchat' ); ?>
					<span class="aumchat-rule-note"><?php esc_html_e( 'Some shops prefer nothing moving while someone is paying.', 'aumchat' ); ?></span>
				</span>
			</label>
		<?php endif; ?>

		<div class="aml-actions-bar">
			<button type="submit" class="aml-btn"><?php esc_html_e( 'Save rules', 'aumchat' ); ?></button>
		</div>
	</div>
</form>

<details class="aml-card aumchat-fold">
	<summary><span class="dashicons dashicons-editor-code"></span><?php esc_html_e( 'Hiding it from a theme or another plugin', 'aumchat' ); ?></summary>
	<div class="aumchat-fold-desc">
		<p class="aml-field-hint"><?php esc_html_e( 'When a rule is not enough, one filter decides per request:', 'aumchat' ); ?></p>
		<pre class="aumchat-key" style="display:block;padding:12px;overflow:auto"><code>add_filter( 'aumchat_show_widget', function ( $show ) {
	return is_page( 'quote' ) ? false : $show;
} );</code></pre>
	</div>
</details>
