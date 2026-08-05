<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $_POST['avs_save_settings'] ) && check_admin_referer( 'avs_settings_nonce' ) ) {
	$post_types    = isset( $_POST['avs_post_types'] ) ? array_map( 'sanitize_text_field', (array) $_POST['avs_post_types'] ) : array( 'post', 'page' );
	$max_pages     = isset( $_POST['avs_max_pages'] ) ? absint( $_POST['avs_max_pages'] ) : 30;
	$exclude_urls  = isset( $_POST['avs_exclude_urls'] ) ? sanitize_textarea_field( $_POST['avs_exclude_urls'] ) : '';
	$request_delay = isset( $_POST['avs_request_delay'] ) ? absint( $_POST['avs_request_delay'] ) : 500;
	$enable_credit = isset( $_POST['avs_enable_credit_footer'] ) ? 1 : 0;
	$is_local_biz  = isset( $_POST['avs_is_local_business'] ) ? 1 : 0;

	// Enforce ceiling filter limit for free build
	$free_cap  = apply_filters( 'avs_max_pages_free', 30 );
	$max_pages = min( $max_pages, $free_cap );

	$settings = array(
		'post_types'           => $post_types,
		'max_pages'            => $max_pages,
		'exclude_urls'         => $exclude_urls,
		'request_delay'        => $request_delay,
		'enable_credit_footer' => $enable_credit,
	);

	update_option( 'avs_settings', $settings );
	update_option( 'avs_is_local_business', $is_local_biz );
	update_option( 'avs_local_business_user_set', 1 );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'ai-visibility-scanner' ) . '</p></div>';
}

$settings           = get_option( 'avs_settings', array( 'post_types' => array( 'post', 'page' ), 'max_pages' => 30, 'exclude_urls' => '', 'request_delay' => 500, 'enable_credit_footer' => 1 ) );
$all_post_types     = get_post_types( array( 'public' => true ), 'objects' );
$is_local_business  = (bool) get_option( 'avs_is_local_business', false );
$auto_suggested     = (bool) get_option( 'avs_local_business_auto_suggested', false );
?>
<div class="wrap avs-wrap">
	<h1 class="avs-heading"><?php esc_html_e( 'AI Visibility Scanner — Settings', 'ai-visibility-scanner' ); ?></h1>

	<?php if ( ! $is_local_business && $auto_suggested ) : ?>
		<div class="notice notice-info is-dismissible" style="margin-top: 15px;">
			<p>
				<strong><?php esc_html_e( 'Local Business Signals Detected:', 'ai-visibility-scanner' ); ?></strong>
				<?php esc_html_e( 'We detected a Local SEO plugin, Google Maps embed, or NAP details on your site. If this is a local business with a physical address, consider enabling the LocalBusiness Schema setting below.', 'ai-visibility-scanner' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="" class="avs-card" style="margin-top: 20px;">
		<?php wp_nonce_field( 'avs_settings_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Scan Scope (Post Types)', 'ai-visibility-scanner' ); ?></th>
				<td>
					<?php foreach ( $all_post_types as $pt ) : ?>
						<label style="margin-right: 15px;">
							<input type="checkbox" name="avs_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $settings['post_types'], true ) ); ?> />
							<?php echo esc_html( $pt->label ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Select which public content types to include during sitemap and page scanning.', 'ai-visibility-scanner' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Local Business Schema Audit', 'ai-visibility-scanner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="avs_is_local_business" value="1" <?php checked( $is_local_business ); ?> />
						<?php esc_html_e( 'Is this a local business with a physical address or service area?', 'ai-visibility-scanner' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When enabled, the scanner will audit LocalBusiness NAP (Name, Address, Phone) and opening hours schema on your Homepage, Contact, and About pages.', 'ai-visibility-scanner' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Max Pages to Audit', 'ai-visibility-scanner' ); ?></th>
				<td>
					<input type="number" name="avs_max_pages" value="<?php echo esc_attr( $settings['max_pages'] ); ?>" min="1" max="30" class="small-text" />
					<p class="description"><?php esc_html_e( 'Scans your 30 most important pages (free tier cap). Permanent, non-expiring scope limit.', 'ai-visibility-scanner' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'URL Exclude List', 'ai-visibility-scanner' ); ?></th>
				<td>
					<textarea name="avs_exclude_urls" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $settings['exclude_urls'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter exact URLs or patterns to exclude from scanning (one per line).', 'ai-visibility-scanner' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Request Crawl Delay', 'ai-visibility-scanner' ); ?></th>
				<td>
					<input type="number" name="avs_request_delay" value="<?php echo esc_attr( $settings['request_delay'] ?? 500 ); ?>" min="0" max="5000" step="50" class="small-text" /> ms
					<p class="description"><?php esc_html_e( 'Delay (in milliseconds) between page loads to prevent triggering hosting security firewalls or WAF rate limits. Set to 0 for no delay.', 'ai-visibility-scanner' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Report Credit Footer', 'ai-visibility-scanner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="avs_enable_credit_footer" value="1" <?php checked( ! empty( $settings['enable_credit_footer'] ) ); ?> />
						<?php esc_html_e( 'Show subtle "Generated with AI Visibility Scanner" credit link on PDF/Report exports.', 'ai-visibility-scanner' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="avs_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'ai-visibility-scanner' ); ?>" />
		</p>
	</form>
</div>
