<?php
/**
 * UpdateMembershipExpiration.
 * php version 5.6
 *
 * @category UpdateMembershipExpiration
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureMembers\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * UpdateMembershipExpiration
 *
 * @category UpdateMembershipExpiration
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateMembershipExpiration extends AutomateAction {

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
	public $action = 'update_membership_expiration';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Membership Expiration', 'suretriggers' ),
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
	 * @throws Exception Throws exception when validation fails.
	 *
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! $user_id ) {
			throw new Exception( esc_html__( 'User not found.', 'suretriggers' ) );
		}

		if ( ! defined( 'SUREMEMBERS_USER_META' ) || ! defined( 'SUREMEMBERS_USER_EXPIRATION' ) ) {
			throw new Exception( esc_html__( 'SureMembers plugin is not active.', 'suretriggers' ) );
		}

		$access_group_id = isset( $selected_options['st_access_group'] ) ? absint( $selected_options['st_access_group'] ) : 0;
		$expiration_date = isset( $selected_options['expiration_date'] ) ? sanitize_text_field( $selected_options['expiration_date'] ) : '';

		if ( empty( $access_group_id ) ) {
			throw new Exception( esc_html__( 'Access group is required.', 'suretriggers' ) );
		}

		if ( '' === $expiration_date ) {
			throw new Exception( esc_html__( 'Expiration date is required.', 'suretriggers' ) );
		}

		if ( false === strtotime( $expiration_date ) ) {
			throw new Exception( esc_html__( 'Invalid expiration date.', 'suretriggers' ) );
		}

		$user_groups = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( ! is_array( $user_groups ) || ! in_array( $access_group_id, array_map( 'intval', $user_groups ), true ) ) {
			throw new Exception( esc_html__( 'User does not have access to this group.', 'suretriggers' ) );
		}

		$user_expirations = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );
		if ( ! is_array( $user_expirations ) ) {
			$user_expirations = [];
		}

		$user_expirations[ $access_group_id ] = $expiration_date;
		update_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, $user_expirations );

		return [
			'user'            => WordPress::get_user_context( $user_id ),
			'group'           => WordPress::get_post_context( $access_group_id ),
			'expiration_date' => $expiration_date,
		];
	}

}

UpdateMembershipExpiration::get_instance();
