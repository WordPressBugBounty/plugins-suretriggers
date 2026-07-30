<?php
/**
 * RetrieveCoupon.
 * php version 5.6
 *
 * @category RetrieveCoupon
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPTravelEngine\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * RetrieveCoupon
 *
 * @category RetrieveCoupon
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RetrieveCoupon extends AutomateAction {

	use SingletonLoader;

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WPTravelEngine';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'wte_retrieve_coupon';

	/**
	 * Register the action.
	 *
	 * @param array $actions List of registered actions.
	 * @return array Modified list of actions.
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'       => __( 'Retrieve a Coupon', 'suretriggers' ),
			'description' => __( 'Retrieve details of a coupon from WP Travel Engine', 'suretriggers' ),
			'action'      => $this->action,
			'function'    => [ $this, '_action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener that retrieves a coupon.
	 *
	 * @param int   $user_id          User ID.
	 * @param int   $automation_id    Automation ID.
	 * @param array $fields           Trigger fields.
	 * @param array $selected_options Selected options for coupon retrieval.
	 * @return array Result with status and data or error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		if ( ! function_exists( 'wp_travel_engine_get_settings' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'WP Travel Engine plugin is not active.', 'suretriggers' ),
			];
		}

		if ( empty( $selected_options['coupon_id'] ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Coupon ID is required.', 'suretriggers' ),
			];
		}

		$coupon_id = absint( $selected_options['coupon_id'] );
		$coupon    = get_post( $coupon_id );

		if ( ! $coupon || 'wte-coupon' !== $coupon->post_type ) {
			return [
				'status'  => 'error',
				'message' => __( 'Coupon not found.', 'suretriggers' ),
			];
		}

		$coupon_code_meta = get_post_meta( $coupon_id, 'wp_travel_engine_coupon_code', true );
		$coupon_metas_raw = get_post_meta( $coupon_id, 'wp_travel_engine_coupon_metas', true );
		$coupon_metas     = is_array( $coupon_metas_raw ) ? $coupon_metas_raw : [];

		$general     = isset( $coupon_metas['general'] ) && is_array( $coupon_metas['general'] ) ? $coupon_metas['general'] : [];
		$restriction = isset( $coupon_metas['restriction'] ) && is_array( $coupon_metas['restriction'] ) ? $coupon_metas['restriction'] : [];

		return [
			'status' => 'success',
			'data'   => [
				'coupon_id'      => $coupon_id,
				'coupon_code'    => is_string( $coupon_code_meta ) ? $coupon_code_meta : '',
				'coupon_title'   => html_entity_decode( get_the_title( $coupon_id ) ),
				'discount_type'  => isset( $general['coupon_type'] ) ? $general['coupon_type'] : '',
				'discount_value' => isset( $general['coupon_value'] ) ? $general['coupon_value'] : '',
				'start_date'     => isset( $general['coupon_start_date'] ) ? $general['coupon_start_date'] : '',
				'expiry_date'    => isset( $general['coupon_expiry_date'] ) ? $general['coupon_expiry_date'] : '',
				'usage_limit'    => isset( $restriction['coupon_limit_number'] ) ? $restriction['coupon_limit_number'] : '',
				'trip_ids'       => isset( $restriction['restricted_trips'] ) ? $restriction['restricted_trips'] : [],
				'status'         => $coupon->post_status,
			],
		];
	}
}

RetrieveCoupon::get_instance();
