<?php
/**
 * UnsubscribeUserFromEmailNotifications.
 * php version 5.6
 *
 * @category UnsubscribeUserFromEmailNotifications
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\BuddyBoss\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * UnsubscribeUserFromEmailNotifications
 *
 * Disables all BuddyBoss email notifications for a user by setting the
 * master 'enable_notification' user meta switch to 'no'. This is the
 * same meta key checked by BuddyBoss's bb_is_notification_enabled() before
 * dispatching any notification email, so setting it once blocks all of them.
 *
 * Intended use-case: SMTP bounce webhook → OttoKit automation → this action,
 * so that users whose email addresses hard-bounce are silently opted out rather
 * than continuing to accumulate bounce events.
 *
 * @category UnsubscribeUserFromEmailNotifications
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UnsubscribeUserFromEmailNotifications extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'BuddyBoss';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'bb_unsubscribe_user_from_email_notifications';

	use SingletonLoader;

	/**
	 * Register the action.
	 *
	 * @param array $actions Registered actions.
	 *
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Unsubscribe User from Email Notifications', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * Looks up the WordPress user by email address, then sets the BuddyBoss
	 * master email-notification opt-out flag on their profile.
	 *
	 * @param int   $user_id         Triggering user ID (may differ from target).
	 * @param int   $automation_id   Automation ID.
	 * @param array $fields          Field definitions.
	 * @param array $selected_options Values chosen/mapped by the automation author.
	 *
	 * @return array Context on success, error array on failure.
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! function_exists( 'bp_update_user_meta' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Required BuddyBoss function bp_update_user_meta not found.', 'suretriggers' ),
			];
		}

		$user_email = isset( $selected_options['wp_user_email'] ) ? $selected_options['wp_user_email'] : '';

		if ( empty( $user_email ) || ! is_email( $user_email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'A valid user email address is required.', 'suretriggers' ),
			];
		}

		$user = get_user_by( 'email', $user_email );

		if ( ! $user ) {
			return [
				'status'  => 'error',
				'message' => __( 'No user found with the provided email address.', 'suretriggers' ),
			];
		}

		// Setting 'enable_notification' to 'no' acts as the master off-switch that
		// BuddyBoss's bb_is_notification_enabled() checks before sending any email
		// notification. All per-type preferences are preserved so the user can
		// opt back in via their profile without losing their earlier settings.
		bp_update_user_meta( $user->ID, 'enable_notification', 'no' );

		$context                        = WordPress::get_user_context( $user->ID );
		$context['email_notifications'] = 'disabled';

		return $context;
	}
}

UnsubscribeUserFromEmailNotifications::get_instance();
