<?php
/**
 * SureTriggers Troubleshooting Page.
 * php version 5.6
 *
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.4
 */

$clear_cache_url = wp_nonce_url( admin_url( 'admin.php?st-reset=true' ), 'st-reset-action' );
?>
<div class="wrap st-troubleshooting-wrap">
	<div class="st-troubleshooting-header">
		<h2><?php esc_html_e( 'Troubleshooting', 'suretriggers' ); ?></h2>
		<p class="st-troubleshooting-desc">
			<?php esc_html_e( 'If you are experiencing issues with OttoKit, clearing the cache will disconnect and re-connect your site. Your automations and settings will be preserved.', 'suretriggers' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( $clear_cache_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Clear Cache & Reconnect', 'suretriggers' ); ?>
			</a>
		</p>
	</div>

	<div class="st-troubleshooting-scenarios">
		<h3><?php esc_html_e( 'When should you clear the cache?', 'suretriggers' ); ?></h3>

		<div class="st-scenario-card">
			<h4><?php esc_html_e( '1. Connection issues after site migration', 'suretriggers' ); ?></h4>
			<p>
				<?php esc_html_e( 'If you recently migrated your site to a new domain or hosting provider, the stored connection tokens may still point to the old URL. Clearing the cache re-establishes the OttoKit connection with the new site URL so triggers and actions can communicate correctly.', 'suretriggers' ); ?>
			</p>
		</div>

		<div class="st-scenario-card">
			<h4><?php esc_html_e( '2. Automations suddenly stopped firing', 'suretriggers' ); ?></h4>
			<p>
				<?php esc_html_e( 'If your triggers and automations were working previously but have suddenly stopped, the authentication token or registered events may have become stale. Clearing the cache refreshes the token, re-syncs all registered events with the OttoKit server, and restores the connection.', 'suretriggers' ); ?>
			</p>
		</div>

		<div class="st-scenario-card">
			<h4><?php esc_html_e( '3. Unexpected behavior after a plugin update', 'suretriggers' ); ?></h4>
			<p>
				<?php esc_html_e( 'After updating OttoKit (or related plugins like WooCommerce, LearnDash, etc.), cached data from the previous version may cause triggers to not appear, events to not register, or the status page to show stale information. Clearing the cache resolves these version mismatch issues.', 'suretriggers' ); ?>
			</p>
		</div>
	</div>

	<div class="st-troubleshooting-note">
		<p>
			<strong><?php esc_html_e( 'Note:', 'suretriggers' ); ?></strong>
			<?php esc_html_e( 'Clearing the cache will not delete your automations, settings, or connected integrations. It only resets the local connection data so your site can re-authenticate with the OttoKit platform.', 'suretriggers' ); ?>
		</p>
	</div>
</div>
