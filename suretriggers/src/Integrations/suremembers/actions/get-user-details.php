<?php
/**
 * GetUserDetails.
 * php version 5.6
 *
 * @category GetUserDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureMembers\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * GetUserDetails
 *
 * @category GetUserDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetUserDetails extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureMembers';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'get_user_details';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get User Details', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 *
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! $user_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'User not found.', 'suretriggers' ),
			];
		}

		if ( ! defined( 'SUREMEMBERS_USER_META' ) || ! defined( 'SUREMEMBERS_USER_EXPIRATION' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureMembers plugin is not active.', 'suretriggers' ),
			];
		}

		$group_ids = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		$group_ids = is_array( $group_ids ) ? array_map( 'intval', $group_ids ) : [];

		$expirations = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );
		$expirations = is_array( $expirations ) ? $expirations : [];

		$access_groups = [];
		foreach ( $group_ids as $group_id ) {
			$access_groups[] = [
				'id'         => $group_id,
				'name'       => get_the_title( $group_id ),
				'expiration' => isset( $expirations[ $group_id ] ) ? $expirations[ $group_id ] : '',
			];
		}

		return [
			'user'          => WordPress::get_user_context( $user_id ),
			'access_groups' => $access_groups,
			'group_count'   => count( $access_groups ),
		];
	}

}

GetUserDetails::get_instance();
