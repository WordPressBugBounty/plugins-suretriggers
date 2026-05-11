<?php
/**
 * UpdateCustomer.
 * php version 5.6
 *
 * @category UpdateCustomer
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\LatePoint\Actions;

use OsCustomerModel;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use Exception;

/**
 * UpdateCustomer
 *
 * @category UpdateCustomer
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateCustomer extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'LatePoint';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'lp_update_customer';

	use SingletonLoader;

	/**
	 * Register action.
	 *
	 * @param array $actions action data.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Customer', 'suretriggers' ),
			'action'   => 'lp_update_customer',
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
	 * @throws Exception Exception.
	 *
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( 'OsCustomerModel' ) ) {
			return [
				'status'  => 'error',
				'message' => 'LatePoint plugin not installed.',
			];
		}

		$customer_id = isset( $selected_options['customer_id'] ) ? $selected_options['customer_id'] : '';
		if ( empty( $customer_id ) ) {
			return [
				'status'  => 'error',
				'message' => 'Customer ID is required.',
			];
		}

		$customer = new OsCustomerModel( $customer_id );
		if ( ! isset( $customer->id ) || empty( $customer->id ) ) {
			return [
				'status'  => 'error',
				'message' => 'Customer not found.',
			];
		}

		$old_customer_data = $customer->get_data_vars();

		$customer_params = [
			'first_name'  => isset( $selected_options['first_name'] ) && '' !== $selected_options['first_name'] ? $selected_options['first_name'] : $customer->first_name,
			'last_name'   => isset( $selected_options['last_name'] ) && '' !== $selected_options['last_name'] ? $selected_options['last_name'] : $customer->last_name,
			'email'       => isset( $selected_options['email'] ) && '' !== $selected_options['email'] ? $selected_options['email'] : $customer->email,
			'phone'       => isset( $selected_options['phone'] ) && '' !== $selected_options['phone'] ? $selected_options['phone'] : $customer->phone,
			'notes'       => isset( $selected_options['notes'] ) && '' !== $selected_options['notes'] ? $selected_options['notes'] : $customer->notes,
			'admin_notes' => isset( $selected_options['admin_notes'] ) && '' !== $selected_options['admin_notes'] ? $selected_options['admin_notes'] : $customer->admin_notes,
		];

		$customer_custom_fields = [];
		if ( ! empty( $selected_options['customer_fields'] ) ) {
			foreach ( $selected_options['customer_fields'] as $field ) {
				if ( is_array( $field ) && ! empty( $field ) ) {
					foreach ( $field as $key => $value ) {
						if ( false === strpos( $key, 'field_column' ) && '' !== $value ) {
							$customer_custom_fields[ $key ] = $value;
						}
					}
				}
			}
		}
		$customer_params['custom_fields'] = $customer_custom_fields;
		$customer->set_data( $customer_params );

		if ( $customer->save() ) {
			do_action( 'latepoint_customer_updated', $customer, $old_customer_data );
			$return_data = $customer->get_data_vars();
			unset( $return_data['password'] );
			return $return_data;
		} else {
			$errors    = $customer->get_error_messages();
			$error_msg = isset( $errors[0] ) ? $errors[0] : 'Customer could not be updated.';
			return [
				'status'  => 'error',
				'message' => $error_msg,
			];
		}
	}

}

UpdateCustomer::get_instance();
