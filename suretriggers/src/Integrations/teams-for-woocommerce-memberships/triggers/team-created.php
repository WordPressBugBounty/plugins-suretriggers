<?php
/**
 * TeamCreated.
 * php version 5.6
 *
 * @category TeamCreated
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

if ( ! class_exists( 'TeamCreated' ) ) :

	/**
	 * TeamCreated
	 *
	 * @category TeamCreated
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class TeamCreated {

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
		public $trigger = 'wc_teams_team_created';

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
				'label'         => __( 'A team is created', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'wc_memberships_for_teams_team_created',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener
		 *
		 * @param object $team Team object.
		 * @param bool   $updating Whether this is an update.
		 *
		 * @return void
		 */
		public function trigger_listener( $team, $updating ) {
			if ( $updating ) {
				return;
			}

			if ( ! is_object( $team ) ) {
				return;
			}

			$context = TeamsForWoocommerceMemberships::get_team_context( $team );

			if ( method_exists( $team, 'get_owner_id' ) ) {
				$owner_id = $team->get_owner_id();
				if ( $owner_id ) {
					$context['owner'] = WordPress::get_user_context( $owner_id );
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
	TeamCreated::get_instance();

endif;
