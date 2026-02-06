<?php
/**
 * UserApproved.
 * php version 5.6
 *
 * @category UserApproved
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\NewUserApprove\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;
use WP_User;

if ( ! class_exists( 'UserApproved' ) ) :

	/**
	 * UserApproved
	 *
	 * @category UserApproved
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 */
	class UserApproved {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'NewUserApprove';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'nua_user_approved';

		use SingletonLoader;

		/**
		 * Constructor
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
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
				'label'         => __( 'User Approved', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'new_user_approve_user_approved',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param WP_User $user Approved user object.
		 *
		 * @return void
		 */
		public function trigger_listener( $user ) {
			if ( empty( $user ) ) {
				return;
			}

			$user_id = $user->ID;
			$context = WordPress::get_user_context( $user_id );

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger'    => $this->trigger,
					'context'    => $context,
					'wp_user_id' => $user_id,
				]
			);
		}

	}

	UserApproved::get_instance();

endif;
