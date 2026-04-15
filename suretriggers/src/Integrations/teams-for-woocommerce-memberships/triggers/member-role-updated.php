<?php
/**
 * MemberRoleUpdated.
 * php version 5.6
 *
 * @category MemberRoleUpdated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\TeamsForWoocommerceMemberships\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\TeamsForWoocommerceMemberships\TeamsForWoocommerceMemberships;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'MemberRoleUpdated' ) ) :

	/**
	 * MemberRoleUpdated
	 *
	 * @category MemberRoleUpdated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class MemberRoleUpdated {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'TeamsForWoocommerceMemberships';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'wc_teams_member_role_updated';

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
				'label'         => __( "A team member's role is updated", 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wc_memberships_for_teams_team_member_role_changed',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 4,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $team Team object.
		 * @param int    $user_id User ID.
		 * @param string $new_role New role.
		 * @param string $old_role Old role.
		 *
		 * @return void
		 */
		public function trigger_listener( $team, $user_id, $new_role, $old_role ) {
			if ( ! is_object( $team ) || empty( $user_id ) ) {
				return;
			}

			$context             = TeamsForWoocommerceMemberships::get_team_context( $team );
			$context['new_role'] = $new_role;
			$context['old_role'] = $old_role;
			$context['user']     = WordPress::get_user_context( $user_id );

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	MemberRoleUpdated::get_instance();

endif;
