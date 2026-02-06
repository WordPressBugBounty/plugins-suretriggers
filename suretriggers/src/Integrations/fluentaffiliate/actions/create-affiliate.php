<?php
/**
 * CreateAffiliate.
 * php version 5.6
 *
 * @category CreateAffiliate
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentAffiliate\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * CreateAffiliate
 *
 * @category CreateAffiliate
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateAffiliate extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'FluentAffiliate';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'fluentaffiliate_create_affiliate';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Affiliate', 'suretriggers' ),
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
	 * @return array|void
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		// Check if FluentAffiliate Affiliate model exists.
		if ( ! class_exists( 'FluentAffiliate\App\Models\Affiliate' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentAffiliate plugin is not installed or activated.', 'suretriggers' ),
			];
		}

		// Extract selected options.
		$email           = isset( $selected_options['email'] ) ? sanitize_email( $selected_options['email'] ) : '';
		$first_name      = isset( $selected_options['first_name'] ) ? sanitize_text_field( $selected_options['first_name'] ) : '';
		$last_name       = isset( $selected_options['last_name'] ) ? sanitize_text_field( $selected_options['last_name'] ) : '';
		$username        = isset( $selected_options['username'] ) ? sanitize_user( $selected_options['username'] ) : '';
		$password        = isset( $selected_options['password'] ) ? $selected_options['password'] : '';
		$status          = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : 'active';
		$payment_email   = isset( $selected_options['payment_email'] ) ? sanitize_email( $selected_options['payment_email'] ) : $email;
		$rate_type       = isset( $selected_options['rate_type'] ) ? sanitize_text_field( $selected_options['rate_type'] ) : 'percentage';
		$commission_rate = isset( $selected_options['commission_rate'] ) ? sanitize_text_field( $selected_options['commission_rate'] ) : '';
		$notes           = isset( $selected_options['notes'] ) ? sanitize_textarea_field( $selected_options['notes'] ) : '';

		// Validate required fields.
		if ( empty( $email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Email is required to create an affiliate.', 'suretriggers' ),
			];
		}

		// Check if user exists by email.
		$wp_user = get_user_by( 'email', $email );

		// If user doesn't exist, create new WordPress user.
		if ( false === $wp_user ) {
			// Username is required for new user creation.
			if ( empty( $username ) ) {
				$username = sanitize_user( current( explode( '@', $email ) ), true );
			}

			// Password is required for new user creation.
			if ( empty( $password ) ) {
				$password = wp_generate_password( 12, true );
			}

			// Check if username already exists.
			if ( username_exists( $username ) ) {
				return [
					'status'  => 'error',
					'message' => sprintf( __( 'Username "%s" already exists. Please provide a different username.', 'suretriggers' ), $username ),
				];
			}

			// Create new WordPress user.
			$user_data = [
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			];

			$new_user_id = wp_insert_user( $user_data );

			if ( is_wp_error( $new_user_id ) ) {
				return [
					'status'  => 'error',
					'message' => sprintf( __( 'Failed to create user: %s', 'suretriggers' ), $new_user_id->get_error_message() ),
				];
			}

			$wp_user = get_user_by( 'ID', $new_user_id );
		}

		// Ensure we have a valid user object.
		if ( false === $wp_user ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to retrieve user information.', 'suretriggers' ),
			];
		}

		// Check if user is already an affiliate.
		$existing_affiliate = \FluentAffiliate\App\Models\Affiliate::where( 'user_id', $wp_user->ID )->first();

		if ( $existing_affiliate ) {
			return [
				'status'  => 'error',
				'message' => __( 'User is already an affiliate.', 'suretriggers' ),
			];
		}

		// Prepare affiliate data.
		$affiliate_data = [
			'user_id'       => $wp_user->ID,
			'status'        => $status,
			'payment_email' => $payment_email,
			'rate_type'     => $rate_type,
			'note'          => $notes,
		];

		// Add commission rate if provided.
		if ( ! empty( $commission_rate ) ) {
			$affiliate_data['rate'] = $commission_rate;
		}

		// Create affiliate.
		try {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::create( $affiliate_data );

			if ( ! $affiliate ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to create affiliate.', 'suretriggers' ),
				];
			}

			// Update note and rate separately as they may not be in fillable array.
			$update_data = [];
			if ( ! empty( $notes ) ) {
				$update_data['note'] = $notes;
			}
			if ( ! empty( $commission_rate ) ) {
				$update_data['rate'] = $commission_rate;
			}

			if ( ! empty( $update_data ) ) {
				$affiliate->update( $update_data );
				// Refresh the affiliate object to get updated values.
				$affiliate = $affiliate->fresh();
			}

			// Prepare response data.
			$context = [
				'affiliate_id'     => $affiliate->id,
				'user_id'          => $affiliate->user_id,
				'email'            => $email,
				'first_name'       => $first_name,
				'last_name'        => $last_name,
				'affiliate_status' => $affiliate->status,
				'payment_email'    => $affiliate->payment_email,
				'rate_type'        => $affiliate->rate_type,
				'commission_rate'  => ! empty( $affiliate->rate ) ? $affiliate->rate : $commission_rate,
				'notes'            => ! empty( $affiliate->note ) ? $affiliate->note : $notes,
				'created_at'       => $affiliate->created_at,
				'status'           => 'success',
			];

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error creating affiliate: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

CreateAffiliate::get_instance();
