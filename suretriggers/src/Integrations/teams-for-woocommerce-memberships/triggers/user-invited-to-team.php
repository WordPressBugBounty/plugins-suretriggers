<?php
/**
 * UserInvitedToTeam.
 * php version 5.6
 *
 * @category UserInvitedToTeam
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

if ( ! class_exists( 'UserInvitedToTeam' ) ) :

	/**
	 * UserInvitedToTeam
	 *
	 * @category UserInvitedToTeam
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class UserInvitedToTeam {

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
		public $trigger = 'wc_teams_user_invited';

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
				'label'         => __( 'A user is invited to a team', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wc_memberships_for_teams_invitation_created',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $invitation Invitation object.
		 *
		 * @return void
		 */
		public function trigger_listener( $invitation ) {
			if ( ! is_object( $invitation ) ) {
				return;
			}

			if ( ! function_exists( 'wc_memberships_for_teams_get_team' ) ) {
				return;
			}

			$context = [];

			if ( method_exists( $invitation, 'get_team_id' ) ) {
				$team_id = $invitation->get_team_id();
				$context = TeamsForWoocommerceMemberships::get_team_context( $team_id );
			}

			if ( method_exists( $invitation, 'get_id' ) ) {
				$context['invitation_id'] = $invitation->get_id();
			}

			if ( method_exists( $invitation, 'get_email' ) ) {
				$context['invitation_email'] = $invitation->get_email();
			}

			if ( method_exists( $invitation, 'get_role' ) ) {
				$context['invitation_role'] = $invitation->get_role();
			}

			if ( method_exists( $invitation, 'get_sender_id' ) ) {
				$sender_id = $invitation->get_sender_id();
				if ( $sender_id ) {
					$context['sender'] = WordPress::get_user_context( $sender_id );
				}
			}

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
	UserInvitedToTeam::get_instance();

endif;
