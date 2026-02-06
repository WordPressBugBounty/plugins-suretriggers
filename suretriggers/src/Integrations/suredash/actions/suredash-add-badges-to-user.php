<?php
/**
 * SureDashAddBadgesToUser.
 * php version 5.6
 *
 * @category SureDashAddBadgesToUser
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
 * SureDashAddBadgesToUser
 *
 * @category SureDashAddBadgesToUser
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SureDashAddBadgesToUser extends AutomateAction {

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
	public $action = 'suredash_add_badges_to_user';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Add Badges to User', 'suretriggers' ),
			'action'   => 'suredash_add_badges_to_user',
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

		// Get portal badge definitions.
		$portal_settings = get_option( defined( 'SUREDASHBOARD_SETTINGS' ) ? SUREDASHBOARD_SETTINGS : 'portal_admin_settings', [] );
		$all_badges      = is_array( $portal_settings ) && isset( $portal_settings['user_badges'] ) && is_array( $portal_settings['user_badges'] ) ? $portal_settings['user_badges'] : [];

		// Find badge definition.
		$badge_definition = null;
		foreach ( $all_badges as $badge ) {
			if ( is_array( $badge ) && isset( $badge['id'] ) && $badge['id'] === $badge_id ) {
				$badge_definition = $badge;
				break;
			}
		}

		if ( ! $badge_definition ) {
			return [
				'status'  => 'error',
				'message' => __( 'Badge not found with the provided ID.', 'suretriggers' ),
			];
		}

		// Get current user badges.
		$current_badges = function_exists( 'sd_get_user_meta' )
			? sd_get_user_meta( $target_user_id, 'portal_badges', true )
			: get_user_meta( $target_user_id, 'portal_badges', true );

		if ( ! is_array( $current_badges ) ) {
			$current_badges = [];
		}

		// Check if user already has this badge.
		$existing_ids = array_column( $current_badges, 'id' );
		if ( in_array( $badge_id, $existing_ids, true ) ) {
			return [
				'status'  => 'success',
				'message' => __( 'User already has this badge.', 'suretriggers' ),
				'user_id' => $target_user_id,
				'badge'   => $badge_definition,
			];
		}

		// Add badge to user.
		$current_badges[] = [
			'id'   => is_array( $badge_definition ) && isset( $badge_definition['id'] ) ? (string) $badge_definition['id'] : '',
			'name' => is_array( $badge_definition ) && isset( $badge_definition['name'] ) ? (string) $badge_definition['name'] : '',
		];

		// Update user badges.
		if ( function_exists( 'sd_update_user_meta' ) ) {
			sd_update_user_meta( $target_user_id, 'portal_badges', $current_badges );
		} else {
			update_user_meta( $target_user_id, 'portal_badges', $current_badges );
		}

		return [
			'status'       => 'success',
			'message'      => __( 'Badge added to user successfully.', 'suretriggers' ),
			'user_id'      => $target_user_id,
			'user_email'   => $user_email,
			'badge'        => $badge_definition,
			'total_badges' => count( $current_badges ),
		];
	}
}

SureDashAddBadgesToUser::get_instance();
