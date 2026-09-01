<?php
/**
 * TeamMemberAddedToAccessGroup.
 * php version 5.6
 *
 * @category TeamMemberAddedToAccessGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureMembers\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'TeamMemberAddedToAccessGroup' ) ) :

	/**
	 * TeamMemberAddedToAccessGroup
	 *
	 * Fires when the owner of a SureMembers corporate/team account adds a new
	 * user to that account as a team member, granting them the account's
	 * access group.
	 *
	 * @category TeamMemberAddedToAccessGroup
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class TeamMemberAddedToAccessGroup {


		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'SureMembers';


		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'suremember_team_member_added_to_access_group';

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
				'label'         => __( 'Team Member Added to Access Group', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'suremembers_corporate_member_added',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 3,
			];

			return $triggers;

		}

		/**
		 * Trigger listener.
		 *
		 * Fires from `SureMembers\Modules\Corporate_Accounts\Member_Service::add_member()`
		 * once the team member's access grant and membership record are saved.
		 *
		 * @param int    $account_id Corporate account ID.
		 * @param int    $user_id    Team member's user ID.
		 * @param string $via        How the member was added: manual|invite_link|csv_import.
		 * @since 1.0.0
		 *
		 * @return array|void
		 */
		public function trigger_listener( $account_id, $user_id, $via ) {
			if ( empty( $user_id ) || empty( $account_id ) ) {
				return;
			}

			$access_group_id = 0;
			$owner_id        = 0;

			if ( class_exists( 'SureMembers\Modules\Corporate_Accounts\Corporate_Account_Repo' ) && is_numeric( $account_id ) ) {
				$account = \SureMembers\Modules\Corporate_Accounts\Corporate_Account_Repo::find( (int) $account_id );

				if ( is_array( $account ) ) {
					$access_group_id = isset( $account['access_group_id'] ) && is_numeric( $account['access_group_id'] ) ? absint( $account['access_group_id'] ) : 0;
					$owner_id        = isset( $account['user_id'] ) && is_numeric( $account['user_id'] ) ? absint( $account['user_id'] ) : 0;
				}
			}

			$context               = WordPress::get_user_context( $user_id );
			$context['account_id'] = $account_id;
			$context['added_via']  = $via;
			$context['group_id']   = $access_group_id;
			$context['group']      = $access_group_id ? WordPress::get_post_context( $access_group_id ) : [];
			unset( $context['group']['ID'] );

			if ( $owner_id ) {
				$context['team_owner'] = WordPress::get_user_context( $owner_id );
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'user_id' => $user_id,
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
	TeamMemberAddedToAccessGroup::get_instance();

endif;
