<?php
/**
 * GetAppointmentDetails.
 * php version 5.6
 *
 * @category GetAppointmentDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Bookly\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * GetAppointmentDetails
 *
 * @category GetAppointmentDetails
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class GetAppointmentDetails extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Bookly';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'bookly_get_appointment_details';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Get Appointment Details', 'suretriggers' ),
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
	 * @param array $selected_options selected_options.
	 *
	 * @return array|void
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		if ( ! class_exists( '\Bookly\Lib\Plugin' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Bookly is not installed or activated.', 'suretriggers' ),
			];
		}

		$appointment_id          = isset( $selected_options['customer_appointment_id'] ) ? intval( $selected_options['customer_appointment_id'] ) : 0;
		$customer_email          = isset( $selected_options['customer_email'] ) ? sanitize_email( $selected_options['customer_email'] ) : '';
		$include_custom_fields   = isset( $selected_options['include_custom_fields'] ) ? (bool) $selected_options['include_custom_fields'] : true;
		$include_customer        = isset( $selected_options['include_customer'] ) ? (bool) $selected_options['include_customer'] : true;
		$include_service_details = isset( $selected_options['include_service_details'] ) ? (bool) $selected_options['include_service_details'] : true;
		$include_staff_details   = isset( $selected_options['include_staff_details'] ) ? (bool) $selected_options['include_staff_details'] : true;

		if ( empty( $appointment_id ) && empty( $customer_email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Customer Appointment ID or Customer Email is required.', 'suretriggers' ),
			];
		}

		global $wpdb;

		if ( $appointment_id ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT ca.*, a.staff_id, a.service_id, a.start_date, a.end_date, a.location_id, a.internal_note
					FROM {$wpdb->prefix}bookly_customer_appointments ca
					LEFT JOIN {$wpdb->prefix}bookly_appointments a ON a.id = ca.appointment_id
					WHERE ca.id = %d",
					$appointment_id
				),
				ARRAY_A
			);
		} else {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT ca.*, a.staff_id, a.service_id, a.start_date, a.end_date, a.location_id, a.internal_note
					FROM {$wpdb->prefix}bookly_customer_appointments ca
					LEFT JOIN {$wpdb->prefix}bookly_appointments a ON a.id = ca.appointment_id
					INNER JOIN {$wpdb->prefix}bookly_customers c ON c.id = ca.customer_id
					WHERE c.email = %s
					ORDER BY ca.id DESC
					LIMIT 1",
					$customer_email
				),
				ARRAY_A
			);
		}

		if ( ! $row || ! is_array( $row ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Appointment not found.', 'suretriggers' ),
			];
		}

		$response = [ 'appointment' => $row ];

		if ( $include_custom_fields && ! empty( $row['custom_fields'] ) ) {
			$decoded = json_decode( (string) $row['custom_fields'], true );
			$fields  = [];
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $field ) {
					if ( is_array( $field ) && isset( $field['label'] ) ) {
						$fields[ $field['label'] ] = isset( $field['value'] ) ? $field['value'] : '';
					}
				}
			}
			$response['custom_fields'] = $fields;
		}

		if ( $include_customer && ! empty( $row['customer_id'] ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$response['customer'] = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, wp_user_id, full_name, first_name, last_name, email, phone, country, state, postcode, city, street
					FROM {$wpdb->prefix}bookly_customers WHERE id = %d",
					(int) $row['customer_id']
				),
				ARRAY_A
			);
		}

		if ( $include_service_details && ! empty( $row['service_id'] ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$response['service'] = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, category_id, title, price, duration, info FROM {$wpdb->prefix}bookly_services WHERE id = %d",
					(int) $row['service_id']
				),
				ARRAY_A
			);
		}

		if ( $include_staff_details && ! empty( $row['staff_id'] ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$response['staff'] = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, wp_user_id, full_name, email, phone, info FROM {$wpdb->prefix}bookly_staff WHERE id = %d",
					(int) $row['staff_id']
				),
				ARRAY_A
			);
		}

		$response['success'] = true;
		$response['message'] = __( 'Appointment details retrieved successfully.', 'suretriggers' );

		return $response;
	}
}

GetAppointmentDetails::get_instance();
