<?php
/**
 * SureDashRemoveBadgesFromUser.
 * php version 5.6
 *
 * @category SureDashRemoveBadgesFromUser
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureDash\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * SureDashRemoveBadgesFromUser
 *
 * @category SureDashRemoveBadgesFromUser
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashRemoveBadgesFromUser extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SureDash';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'suredash_remove_badges_from_user';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Remove Badges from User', 'suretriggers' ),
			'action'   => 'suredash_remove_badges_from_user',
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
	 * @return array|bool
	 * @throws Exception Error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'SUREDASHBOARD_VER' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'SureDash plugin is not active or properly configured.', 'suretriggers' ),
			];
		}

		$user_email = ! empty( $selected_options['user_email'] ) ? sanitize_email( $selected_options['user_email'] ) : '';
		$badge_id   = ! empty( $selected_options['badge_id'] ) ? sanitize_text_field( $selected_options['badge_id'] ) : '';

		if ( empty( $user_email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'User email is required.', 'suretriggers' ),
			];
		}

		if ( empty( $badge_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Badge is required.', 'suretriggers' ),
			];
		}

		$user = get_user_by( 'email', $user_email );

		if ( ! $user ) {
			return [
				'status'  => 'error',
				'message' => __( 'User not found with the provided email.', 'suretriggers' ),
			];
		}

		$target_user_id = $user->ID;

		// Get current user badges.
		$current_badges = function_exists( 'sd_get_user_meta' )
			? sd_get_user_meta( $target_user_id, 'portal_badges', true )
			: get_user_meta( $target_user_id, 'portal_badges', true );

		if ( ! is_array( $current_badges ) || empty( $current_badges ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'User does not have any badges to remove.', 'suretriggers' ),
			];
		}

		// Check if user has this badge.
		$existing_ids = array_column( $current_badges, 'id' );
		if ( ! in_array( $badge_id, $existing_ids, true ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'User does not have the specified badge.', 'suretriggers' ),
			];
		}

		// Find badge details before removing.
		$removed_badge = null;
		foreach ( $current_badges as $badge ) {
			if ( isset( $badge['id'] ) && $badge['id'] === $badge_id ) {
				$removed_badge = $badge;
				break;
			}
		}

		// Filter out the badge.
		$updated_badges = array_values(
			array_filter(
				$current_badges,
				function ( $badge ) use ( $badge_id ) {
					return ! isset( $badge['id'] ) || $badge['id'] !== $badge_id;
				}
			)
		);

		// Update user badges.
		if ( function_exists( 'sd_update_user_meta' ) ) {
			sd_update_user_meta( $target_user_id, 'portal_badges', $updated_badges );
		} else {
			update_user_meta( $target_user_id, 'portal_badges', $updated_badges );
		}

		return [
			'status'        => 'success',
			'message'       => __( 'Badge removed from user successfully.', 'suretriggers' ),
			'user_id'       => $target_user_id,
			'user_email'    => $user_email,
			'removed_badge' => $removed_badge,
			'total_badges'  => count( $updated_badges ),
		];
	}
}

SureDashRemoveBadgesFromUser::get_instance();
