<?php
/**
 * CancelRegistration.
 * php version 5.6
 *
 * @category CancelRegistration
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EventEspresso\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\EventEspresso\EventEspresso;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * CancelRegistration
 */
class EE_CancelRegistration extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'EventEspresso';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'ee_cancel_registration';

	use SingletonLoader;

	/**
	 * Register the action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Cancel a Registration', 'suretriggers' ),
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
	 * @return array|mixed
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		$registration_id = isset( $selected_options['registration_id'] ) ? absint( $selected_options['registration_id'] ) : 0;

		if ( empty( $registration_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Registration ID is required.', 'suretriggers' ),
			];
		}

		if ( ! class_exists( 'EEM_Registration' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Event Espresso is not active.', 'suretriggers' ),
			];
		}

		$registration = \EEM_Registration::instance()->get_one_by_ID( $registration_id );
		if ( ! is_object( $registration ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Registration not found for the provided ID.', 'suretriggers' ),
			];
		}

		if ( ! method_exists( $registration, 'set_status' ) || ! method_exists( $registration, 'save' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Unable to update registration status.', 'suretriggers' ),
			];
		}

		$current_status = method_exists( $registration, 'status_ID' ) ? (string) $registration->status_ID() : '';
		if ( 'RCN' === $current_status ) {
			return [
				'status'  => 'error',
				'message' => __( 'Registration is already cancelled.', 'suretriggers' ),
			];
		}

		$registration->set_status( 'RCN' );
		$registration->save();

		$updated_status = method_exists( $registration, 'status_ID' ) ? (string) $registration->status_ID() : '';
		if ( 'RCN' !== $updated_status ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to cancel the registration.', 'suretriggers' ),
			];
		}

		$context = EventEspresso::get_registration_context( $registration );
		if ( empty( $context ) ) {
			return [
				'status'  => 'success',
				'message' => __( 'Registration cancelled.', 'suretriggers' ),
			];
		}

		$wp_user_id = isset( $context['wp_user_id'] ) ? (int) $context['wp_user_id'] : 0;
		if ( $wp_user_id > 0 ) {
			$context = array_merge( WordPress::get_user_context( $wp_user_id ), $context );
		}

		return $context;
	}
}

EE_CancelRegistration::get_instance();
