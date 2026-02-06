<?php
/**
 * UpdateAffiliateGroup.
 * php version 5.6
 *
 * @category UpdateAffiliateGroup
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
 * UpdateAffiliateGroup
 *
 * @category UpdateAffiliateGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateAffiliateGroup extends AutomateAction {

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
	public $action = 'fluentaffiliate_update_affiliate_group';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Update Affiliate Group (PRO)', 'suretriggers' ),
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

		// Check if FluentAffiliate AffiliateGroup model exists.
		if ( ! class_exists( 'FluentAffiliate\App\Models\AffiliateGroup' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentAffiliate Pro plugin is not installed or activated.', 'suretriggers' ),
			];
		}

		// Extract selected options.
		$group_id   = isset( $selected_options['group_id'] ) ? absint( $selected_options['group_id'] ) : 0;
		$group_name = isset( $selected_options['group_name'] ) ? sanitize_text_field( $selected_options['group_name'] ) : '';
		$rate_type  = isset( $selected_options['rate_type'] ) ? sanitize_text_field( $selected_options['rate_type'] ) : '';
		$rate       = isset( $selected_options['rate'] ) ? sanitize_text_field( $selected_options['rate'] ) : '';
		$status     = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : '';
		$notes      = isset( $selected_options['notes'] ) ? sanitize_text_field( $selected_options['notes'] ) : '';

		// Validate required fields.
		if ( empty( $group_id ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Group ID is required.', 'suretriggers' ),
			];
		}

		// Get the affiliate group.
		$group = \FluentAffiliate\App\Models\AffiliateGroup::find( $group_id );

		if ( ! $group ) {
			return [
				'status'  => 'error',
				'message' => __( 'Affiliate group not found.', 'suretriggers' ),
			];
		}

		// Validate rate type if provided.
		if ( ! empty( $rate_type ) ) {
			$allowed_rate_types = [ 'flat', 'percentage', 'default' ];
			if ( ! in_array( $rate_type, $allowed_rate_types, true ) ) {
				return [
					'status'  => 'error',
					'message' => sprintf( __( 'Invalid rate type. Allowed values: %s', 'suretriggers' ), implode( ', ', $allowed_rate_types ) ),
				];
			}
		}

		// Validate status if provided.
		if ( ! empty( $status ) ) {
			$allowed_statuses = [ 'active', 'inactive' ];
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				return [
					'status'  => 'error',
					'message' => sprintf( __( 'Invalid status. Allowed values: %s', 'suretriggers' ), implode( ', ', $allowed_statuses ) ),
				];
			}
		}

		// Update affiliate group.
		try {
			// Update group name if provided.
			if ( ! empty( $group_name ) ) {
				$group->meta_key = $group_name;
			}

			// Get current value array.
			$value = $group->value;

			// Update rate type if provided.
			if ( ! empty( $rate_type ) ) {
				$value['rate_type'] = $rate_type;
			}

			// Update rate if provided.
			if ( ! empty( $rate ) || '0' === $rate ) {
				$value['rate'] = (int) $rate;
			}

			// Update status if provided.
			if ( ! empty( $status ) ) {
				$value['status'] = $status;
			}

			// Update notes if provided.
			if ( isset( $selected_options['notes'] ) ) {
				$value['notes'] = $notes;
			}

			// Save updated value.
			$group->value = $value;
			$group->save();

			// Prepare response data.
			$context = [
				'group_id'     => $group->id,
				'group_name'   => $group->meta_key,
				'rate_type'    => isset( $group->value['rate_type'] ) ? $group->value['rate_type'] : '',
				'rate'         => isset( $group->value['rate'] ) ? $group->value['rate'] : 0,
				'group_status' => isset( $group->value['status'] ) ? $group->value['status'] : '',
				'notes'        => isset( $group->value['notes'] ) ? $group->value['notes'] : '',
				'updated_at'   => isset( $group->updated_at ) ? $group->updated_at : '',
				'status'       => 'success',
			];

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error updating affiliate group: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

UpdateAffiliateGroup::get_instance();
