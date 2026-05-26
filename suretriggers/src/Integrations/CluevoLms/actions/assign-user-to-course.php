<?php
/**
 * AssignUserToCourse.
 * php version 5.6
 *
 * @category AssignUserToCourse
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\CluevoLms\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\CluevoLms\CluevoLms;
use SureTriggers\Traits\SingletonLoader;

/**
 * AssignUserToCourse
 *
 * @category AssignUserToCourse
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class AssignUserToCourse extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'CluevoLms';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'cluevo_assign_user_to_course';

	use SingletonLoader;

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Assign User to Course', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id         WordPress user ID.
	 * @param int   $automation_id   Automation ID.
	 * @param array $fields          Action field definitions.
	 * @param array $selected_options Selected options.
	 *
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! function_exists( 'cluevo_add_user_perms_to_item' ) ||
			! function_exists( 'cluevo_is_lms_user' ) ||
			! function_exists( 'cluevo_make_lms_user' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'CLUEVO LMS functions are not available.', 'suretriggers' ),
			];
		}

		if ( empty( $user_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'User not found.', 'suretriggers' ),
			];
		}

		$item_id = isset( $selected_options['cluevo_item_id'] ) ? (int) $selected_options['cluevo_item_id'] : 0;

		if ( empty( $item_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'No CLUEVO course/item selected.', 'suretriggers' ),
			];
		}

		if ( ! cluevo_is_lms_user( $user_id ) ) {
			$made = cluevo_make_lms_user( $user_id );
			if ( false === $made ) {
				return [
					'status'  => 'error',
					'message' => __( 'Could not create CLUEVO LMS user record.', 'suretriggers' ),
				];
			}
		}

		// Level 1 = learner read access. Uses ON DUPLICATE KEY UPDATE so safe to call repeatedly.
		cluevo_add_user_perms_to_item( $item_id, $user_id, 1 );

		return array_merge(
			CluevoLms::get_user_context( $user_id ),
			CluevoLms::get_item_context( $item_id ),
			[ 'access_granted' => true ]
		);
	}
}

AssignUserToCourse::get_instance();
