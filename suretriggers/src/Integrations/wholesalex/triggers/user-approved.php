<?php
/**
 * UserApproved
 *
 * @package  SureTriggers
 * @category Integration
 * @author   BSF
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.1.38
 */

namespace SureTriggers\Integrations\WholesaleX\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WholesaleX\WholesaleX;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'UserApproved' ) ) :

	/**
	 * UserApproved
	 *
	 * @category UserApproved
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.1.38
	 */
	class UserApproved {

		use SingletonLoader;

		/**
		 * Integration name.
		 *
		 * @var string
		 */
		public $integration = 'WholesaleX';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wholesalex_user_approved';

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
		}

		/**
		 * Register trigger.
		 *
		 * WholesaleX registers users with a pending status; a user only gets
		 * their role-based discount rules once WholesaleX marks them active
		 * (either via admin approval or auto-approval), so this trigger fires
		 * on that approval moment rather than on raw registration — that way
		 * anything synced out (e.g. to Brevo/MailerPress) already carries the
		 * user's final wholesale role.
		 *
		 * @param array $triggers Registered triggers.
		 * @return array Modified triggers.
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'New User Approved', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wholesalex_set_status_active',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param int    $user_id  Approved user ID.
		 * @param string $password Plaintext password (only set on auto-approval at
		 *                         registration; deliberately not forwarded into the
		 *                         automation context).
		 * @return void
		 */
		public function trigger_listener( $user_id, $password = '' ) {
			unset( $password );

			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				return;
			}

			$context = WholesaleX::get_user_context( $user_id );
			if ( empty( $context ) ) {
				return;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	UserApproved::get_instance();

endif;
