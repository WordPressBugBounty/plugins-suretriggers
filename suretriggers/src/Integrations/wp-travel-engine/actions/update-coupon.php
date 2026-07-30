<?php
/**
 * UpdateCoupon.
 * php version 5.6
 *
 * @category UpdateCoupon
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
 * UpdateCoupon
 *
 * @category UpdateCoupon
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UpdateCoupon extends AutomateAction {

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
	public $action = 'wte_update_coupon';

	/**
	 * Register the action.
	 *
	 * @param array $actions List of registered actions.
	 * @return array Modified list of actions.
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'       => __( 'Update a Coupon', 'suretriggers' ),
			'description' => __( 'Update an existing coupon for WP Travel Engine', 'suretriggers' ),
			'action'      => $this->action,
			'function'    => [ $this, '_action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener that updates a coupon post and meta.
	 *
	 * @param int   $user_id          User ID.
	 * @param int   $automation_id    Automation ID.
	 * @param array $fields           Trigger fields.
	 * @param array $selected_options Selected options for coupon.
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

		$post_update = [];

		if ( ! empty( $selected_options['coupon_title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $selected_options['coupon_title'] );
		}

		if ( ! empty( $selected_options['status'] ) ) {
			$post_update['post_status'] = sanitize_text_field( $selected_options['status'] );
		}

		if ( ! empty( $post_update ) ) {
			$post_update['ID'] = $coupon_id;
			$result            = wp_update_post( $post_update, true );

			if ( is_wp_error( $result ) ) {
				return [
					'status'  => 'error',
					'message' => $result->get_error_message(),
				];
			}
		}

		if ( ! empty( $selected_options['coupon_code'] ) ) {
			update_post_meta( $coupon_id, 'wp_travel_engine_coupon_code', sanitize_text_field( $selected_options['coupon_code'] ) );
		}

		$coupon_metas_raw = get_post_meta( $coupon_id, 'wp_travel_engine_coupon_metas', true );
		$coupon_metas     = is_array( $coupon_metas_raw ) ? $coupon_metas_raw : [];

		if ( ! isset( $coupon_metas['general'] ) || ! is_array( $coupon_metas['general'] ) ) {
			$coupon_metas['general'] = [];
		}

		if ( ! isset( $coupon_metas['restriction'] ) || ! is_array( $coupon_metas['restriction'] ) ) {
			$coupon_metas['restriction'] = [];
		}

		if ( ! empty( $selected_options['discount_type'] ) ) {
			$coupon_metas['general']['coupon_type'] = sanitize_text_field( $selected_options['discount_type'] );
		}

		if ( isset( $selected_options['discount_value'] ) && '' !== $selected_options['discount_value'] ) {
			$coupon_metas['general']['coupon_value'] = floatval( $selected_options['discount_value'] );
		}

		if ( ! empty( $selected_options['start_date'] ) ) {
			$coupon_metas['general']['coupon_start_date'] = sanitize_text_field( $selected_options['start_date'] );
		}

		if ( ! empty( $selected_options['expiry_date'] ) ) {
			$coupon_metas['general']['coupon_expiry_date'] = sanitize_text_field( $selected_options['expiry_date'] );
		}

		if ( isset( $selected_options['usage_limit'] ) && '' !== $selected_options['usage_limit'] ) {
			$coupon_metas['restriction']['coupon_limit_number'] = absint( $selected_options['usage_limit'] );
		}

		if ( ! empty( $selected_options['trip_ids'] ) ) {
			if ( is_array( $selected_options['trip_ids'] ) ) {
				if ( isset( $selected_options['trip_ids'][0]['value'] ) ) {
					$trip_ids = array_map(
						function( $item ) {
							return absint( $item['value'] );
						},
						$selected_options['trip_ids']
					);
				} else {
					$trip_ids = array_map( 'absint', $selected_options['trip_ids'] );
				}
			} else {
				$trip_ids = array_map( 'absint', array_map( 'trim', explode( ',', $selected_options['trip_ids'] ) ) );
			}
			$coupon_metas['restriction']['restricted_trips'] = $trip_ids;
		}

		update_post_meta( $coupon_id, 'wp_travel_engine_coupon_metas', $coupon_metas );

		$updated_coupon   = get_post( $coupon_id );
		$coupon_code_meta = get_post_meta( $coupon_id, 'wp_travel_engine_coupon_code', true );
		$general          = $coupon_metas['general'];
		$restriction      = $coupon_metas['restriction'];

		return [
			'status' => 'success',
			'data'   => [
				'coupon_id'      => $coupon_id,
				'coupon_code'    => is_string( $coupon_code_meta ) ? $coupon_code_meta : '',
				'coupon_title'   => $updated_coupon instanceof \WP_Post ? html_entity_decode( $updated_coupon->post_title ) : '',
				'discount_type'  => isset( $general['coupon_type'] ) ? $general['coupon_type'] : '',
				'discount_value' => isset( $general['coupon_value'] ) ? $general['coupon_value'] : '',
				'start_date'     => isset( $general['coupon_start_date'] ) ? $general['coupon_start_date'] : '',
				'expiry_date'    => isset( $general['coupon_expiry_date'] ) ? $general['coupon_expiry_date'] : '',
				'usage_limit'    => isset( $restriction['coupon_limit_number'] ) ? $restriction['coupon_limit_number'] : '',
				'trip_ids'       => isset( $restriction['restricted_trips'] ) ? $restriction['restricted_trips'] : [],
				'status'         => $updated_coupon instanceof \WP_Post ? $updated_coupon->post_status : '',
			],
		];
	}
}

UpdateCoupon::get_instance();
