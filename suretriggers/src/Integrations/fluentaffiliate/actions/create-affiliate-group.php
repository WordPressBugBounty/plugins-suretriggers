<?php
/**
 * CreateAffiliateGroup.
 * php version 5.6
 *
 * @category CreateAffiliateGroup
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
 * CreateAffiliateGroup
 *
 * @category CreateAffiliateGroup
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateAffiliateGroup extends AutomateAction {

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
	public $action = 'fluentaffiliate_create_affiliate_group';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Affiliate Group (PRO)', 'suretriggers' ),
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
		$group_name = isset( $selected_options['group_name'] ) ? sanitize_text_field( $selected_options['group_name'] ) : '';
		$rate_type  = ! empty( $selected_options['rate_type'] ) ? sanitize_text_field( $selected_options['rate_type'] ) : 'default';
		$rate       = isset( $selected_options['rate'] ) ? sanitize_text_field( $selected_options['rate'] ) : '';
		$status     = ! empty( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : 'active';
		$notes      = isset( $selected_options['notes'] ) ? sanitize_text_field( $selected_options['notes'] ) : '';
		// Validate required fields.
		if ( empty( $group_name ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Group name is required.', 'suretriggers' ),
			];
		}

		// Validate rate type.
		$allowed_rate_types = [ 'flat', 'percentage', 'default' ];
		if ( ! in_array( $rate_type, $allowed_rate_types, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid rate type. Allowed values: %s', 'suretriggers' ), implode( ', ', $allowed_rate_types ) ),
			];
		}

		// Validate status.
		$allowed_statuses = [ 'active', 'inactive' ];
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Invalid status. Allowed values: %s', 'suretriggers' ), implode( ', ', $allowed_statuses ) ),
			];
		}

		// Prepare group data.
		$group_data = [
			'meta_key' => $group_name,
			'value'    => [
				'rate_type' => $rate_type,
				'rate'      => ! empty( $rate ) ? (int) $rate : 0,
				'status'    => $status,
				'notes'     => $notes,
			],
		];

		// Create affiliate group.
		try {
			// Fire before create hook.
			do_action( 'fluent_affiliate/before_create_affiliate_group', $group_data ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			$affiliate_group = \FluentAffiliate\App\Models\AffiliateGroup::create( $group_data );

			if ( ! $affiliate_group ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to create affiliate group.', 'suretriggers' ),
				];
			}

			// Prepare response data.
			$context = [
				'group_id'     => isset( $affiliate_group->id ) ? $affiliate_group->id : '',
				'group_name'   => isset( $affiliate_group->meta_key ) ? $affiliate_group->meta_key : '',
				'rate_type'    => isset( $affiliate_group->value['rate_type'] ) ? $affiliate_group->value['rate_type'] : '',
				'rate'         => isset( $affiliate_group->value['rate'] ) ? $affiliate_group->value['rate'] : 0,
				'group_status' => isset( $affiliate_group->value['status'] ) ? $affiliate_group->value['status'] : '',
				'notes'        => isset( $affiliate_group->value['notes'] ) ? $affiliate_group->value['notes'] : '',
				'created_at'   => isset( $affiliate_group->created_at ) ? $affiliate_group->created_at : '',
				'status'       => 'success',
			];

			return $context;

		} catch ( \Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error creating affiliate group: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

CreateAffiliateGroup::get_instance();
