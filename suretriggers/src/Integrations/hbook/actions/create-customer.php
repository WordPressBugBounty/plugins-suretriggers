<?php
/**
 * CreateCustomer.
 * php version 5.6
 *
 * @category CreateCustomer
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\HBook\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use SureTriggers\Integrations\HBook\HBook;

/**
 * CreateCustomer
 *
 * @category CreateCustomer
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateCustomer extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'HBook';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'hbook_create_customer';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Customer', 'suretriggers' ),
			'action'   => 'hbook_create_customer',
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
		if ( ! class_exists( '\HbDataBaseActions' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'HBook plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$db = new \HbDataBaseActions();

		$email = isset( $selected_options['email'] ) && is_string( $selected_options['email'] ) ? sanitize_email( $selected_options['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'A valid customer email is required.', 'suretriggers' ),
			];
		}

		$existing_id = $db->get_customer_id( $email );
		if ( $existing_id ) {
			$context                   = HBook::get_customer_context( absint( $existing_id ) );
			$context['already_exists'] = true;
			return $context;
		}

		// Only HBook's standard customer fields are accepted; never pass arbitrary input through.
		$allowed_fields = [ 'first_name', 'last_name', 'phone', 'address_1', 'address_2', 'city', 'state_province', 'zip_code', 'country' ];
		$info           = [ 'email' => $email ];
		foreach ( $allowed_fields as $field ) {
			if ( isset( $selected_options[ $field ] ) && is_scalar( $selected_options[ $field ] ) ) {
				$info[ $field ] = sanitize_text_field( (string) $selected_options[ $field ] );
			}
		}

		$customer_id = $db->create_customer( $email, $info );
		if ( ! $customer_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create the customer.', 'suretriggers' ),
			];
		}

		$context                   = HBook::get_customer_context( absint( $customer_id ) );
		$context['already_exists'] = false;
		return $context;
	}
}

CreateCustomer::get_instance();
