<?php
/**
 * ListCoupons.
 * php version 5.6
 *
 * @category ListCoupons
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
 * ListCoupons.
 *
 * @category ListCoupons
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class ListCoupons extends AutomateAction {

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
	public $action = 'wte_list_coupons';

	/**
	 * Register the action.
	 *
	 * @param array $actions List of registered actions.
	 * @return array Modified list of actions.
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'       => __( 'List Coupons', 'suretriggers' ),
			'description' => __( 'Get a list of coupons with optional filtering', 'suretriggers' ),
			'action'      => $this->action,
			'function'    => [ $this, '_action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener that returns list of coupons.
	 *
	 * @param int   $user_id          User ID.
	 * @param int   $automation_id    Automation ID.
	 * @param array $fields           Trigger fields.
	 * @param array $selected_options Selected options for coupon filtering.
	 * @return array Result with status and data or error.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! function_exists( 'wp_travel_engine_get_settings' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'WP Travel Engine plugin is not active.', 'suretriggers' ),
			];
		}

		$limit = ! empty( $selected_options['limit'] ) ? intval( $selected_options['limit'] ) : 20;

		$args = [
			'post_type'      => 'wte-coupon',
			'post_status'    => isset( $selected_options['status'] ) ? $selected_options['status'] : 'publish',
			'posts_per_page' => $limit,
		];

		$coupons_query = new \WP_Query( $args );
		$coupons       = [];

		if ( $coupons_query->have_posts() ) {
			while ( $coupons_query->have_posts() ) {
				$coupons_query->the_post();
				$coupon_id = get_the_ID();

				if ( ! $coupon_id ) {
					continue;
				}

				$coupon_code_meta = get_post_meta( $coupon_id, 'wp_travel_engine_coupon_code', true );
				$coupon_metas_raw = get_post_meta( $coupon_id, 'wp_travel_engine_coupon_metas', true );
				$coupon_metas     = is_array( $coupon_metas_raw ) ? $coupon_metas_raw : [];

				$general     = isset( $coupon_metas['general'] ) && is_array( $coupon_metas['general'] ) ? $coupon_metas['general'] : [];
				$restriction = isset( $coupon_metas['restriction'] ) && is_array( $coupon_metas['restriction'] ) ? $coupon_metas['restriction'] : [];

				$coupons[] = [
					'coupon_id'      => $coupon_id,
					'coupon_code'    => is_string( $coupon_code_meta ) ? $coupon_code_meta : '',
					'coupon_title'   => get_the_title(),
					'discount_type'  => isset( $general['coupon_type'] ) ? $general['coupon_type'] : '',
					'discount_value' => isset( $general['coupon_value'] ) ? $general['coupon_value'] : '',
					'start_date'     => isset( $general['coupon_start_date'] ) ? $general['coupon_start_date'] : '',
					'expiry_date'    => isset( $general['coupon_expiry_date'] ) ? $general['coupon_expiry_date'] : '',
					'usage_limit'    => isset( $restriction['coupon_limit_number'] ) ? $restriction['coupon_limit_number'] : '',
					'trip_ids'       => isset( $restriction['restricted_trips'] ) ? $restriction['restricted_trips'] : [],
					'status'         => get_post_status( $coupon_id ),
					'date_created'   => get_the_date( 'Y-m-d H:i:s' ),
				];
			}
			wp_reset_postdata();
		}

		return [
			'status' => 'success',
			'data'   => [
				'coupons' => $coupons,
				'count'   => count( $coupons ),
			],
		];
	}
}

ListCoupons::get_instance();
