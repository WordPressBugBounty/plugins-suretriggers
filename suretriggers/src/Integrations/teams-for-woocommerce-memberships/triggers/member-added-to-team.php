<?php
/**
 * MemberAddedToTeam.
 * php version 5.6
 *
 * @category MemberAddedToTeam
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

if ( ! class_exists( 'MemberAddedToTeam' ) ) :

	/**
	 * MemberAddedToTeam
	 *
	 * @category MemberAddedToTeam
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class MemberAddedToTeam {

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
		public $trigger = 'wc_teams_member_added';

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
				'label'         => __( 'A member is added to a team', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wc_memberships_for_teams_team_member_added',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $team Team object.
		 * @param int    $user_id User ID of the member added.
		 * @param string $role Member role.
		 *
		 * @return void
		 */
		public function trigger_listener( $team, $user_id, $role ) {
			if ( ! is_object( $team ) || empty( $user_id ) ) {
				return;
			}

			$context         = TeamsForWoocommerceMemberships::get_team_context( $team );
			$context['role'] = $role;
			$context['user'] = WordPress::get_user_context( $user_id );

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
	MemberAddedToTeam::get_instance();

endif;
