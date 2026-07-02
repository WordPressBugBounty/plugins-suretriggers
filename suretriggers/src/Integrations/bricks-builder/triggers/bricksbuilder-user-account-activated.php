<?php
/**
 * UserActivatesBricksBuilderAccount.
 * php version 5.6
 *
 * @category UserActivatesBricksBuilderAccount
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\BricksBuilder\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'UserActivatesBricksBuilderAccount' ) ) :

	/**
	 * UserActivatesBricksBuilderAccount
	 *
	 * @category UserActivatesBricksBuilderAccount
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 */
	class UserActivatesBricksBuilderAccount {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'BricksBuilder';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'bricksbuilder_user_account_activated';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_action( 'init', [ $this, 'capture_pending_activation' ], 1 );
			add_action( 'template_redirect', [ $this, 'detect_account_activation' ], 999 );
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'User Account Activated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'bricksbuilder_after_user_account_activated',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Captures a valid pending activation at init priority 1, before Bricks processes it.
		 *
		 * @return void
		 */
		public function capture_pending_activation() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET['user_id'] ) || ! isset( $_GET['activation_key'] ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = absint( $_GET['user_id'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$activation_key = sanitize_text_field( wp_unslash( $_GET['activation_key'] ) );

			if ( empty( $user_id ) || empty( $activation_key ) ) {
				return;
			}

			if ( ! get_user_by( 'id', $user_id ) ) {
				return;
			}

			// Bricks 2.1+ stores the pending token under 'bricks_activation_key' and deletes it on activation.
			$stored_key = get_user_meta( $user_id, 'bricks_activation_key', true );

			if ( ! is_string( $stored_key ) || empty( $stored_key ) ) {
				return;
			}

			if ( ! hash_equals( $stored_key, $activation_key ) ) {
				return;
			}

			set_transient( 'bricks_ottokit_pending_' . $user_id, true, 300 );
		}

		/**
		 * Confirms activation at template_redirect priority 999, after Bricks has processed it.
		 *
		 * @return void
		 */
		public function detect_account_activation() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET['user_id'] ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = absint( $_GET['user_id'] );

			if ( empty( $user_id ) || ! get_transient( 'bricks_ottokit_pending_' . $user_id ) ) {
				return;
			}

			if ( ! empty( get_user_meta( $user_id, 'bricks_activation_key', true ) ) ) {
				return;
			}

			delete_transient( 'bricks_ottokit_pending_' . $user_id );
			do_action( 'bricksbuilder_after_user_account_activated', $user_id );
		}

		/**
		 * Trigger listener
		 *
		 * @param int $user_id Activated user ID.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $user_id ) {
			if ( empty( $user_id ) ) {
				return;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger'    => $this->trigger,
					'context'    => WordPress::get_user_context( $user_id ),
					'wp_user_id' => $user_id,
				]
			);
		}
	}

	UserActivatesBricksBuilderAccount::get_instance();

endif;
