<?php
/**
 * CreateCoupon.
 * php version 5.6
 *
 * @category CreateCoupon
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\AdvancedCoupons\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * CreateCoupon
 *
 * @category CreateCoupon
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateCoupon extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'AdvancedCoupons';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'acfw_create_coupon';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Coupon', 'suretriggers' ),
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
	 * @return void|array|bool
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		$coupon_code               = ! empty( $selected_options['coupon_code'] ) ? sanitize_text_field( $selected_options['coupon_code'] ) : $this->generate_coupon_code();
		$description               = isset( $selected_options['description'] ) ? sanitize_textarea_field( $selected_options['description'] ) : '';
		$discount_type             = isset( $selected_options['discount_type'] ) ? sanitize_text_field( $selected_options['discount_type'] ) : 'percent';
		$coupon_amount             = isset( $selected_options['coupon_amount'] ) ? floatval( $selected_options['coupon_amount'] ) : 0;
		$is_free_shipping          = ! empty( $selected_options['is_free_shipping'] ) ? 'yes' : 'no';
		$start_date                = isset( $selected_options['start_date'] ) ? sanitize_text_field( $selected_options['start_date'] ) : '';
		$expiry_date               = isset( $selected_options['expiry_date'] ) ? sanitize_text_field( $selected_options['expiry_date'] ) : '';
		$min_spend                 = isset( $selected_options['min_spend'] ) ? floatval( $selected_options['min_spend'] ) : '';
		$max_spend                 = isset( $selected_options['max_spend'] ) ? floatval( $selected_options['max_spend'] ) : '';
		$is_individual             = ! empty( $selected_options['is_individual'] ) ? 'yes' : 'no';
		$exclude_sale_items        = ! empty( $selected_options['exclude_sale_items'] ) ? 'yes' : 'no';
		$allowed_emails            = ! empty( $selected_options['allowed_emails'] ) ? array_filter( array_map( 'sanitize_email', explode( ',', $selected_options['allowed_emails'] ) ) ) : '';
		$usage_limit_per_coupon    = isset( $selected_options['usage_limit_per_coupon'] ) ? absint( $selected_options['usage_limit_per_coupon'] ) : '';
		$limit_items               = isset( $selected_options['limit_items'] ) ? absint( $selected_options['limit_items'] ) : '';
		$usage_limit_per_user      = isset( $selected_options['usage_limit_per_user'] ) ? absint( $selected_options['usage_limit_per_user'] ) : '';
		$product_cat_ids           = [];
		$product_cat_names         = [];
		$product_cat_exclude_ids   = [];
		$product_cat_exclude_names = [];
		$product_ids               = [];
		$product_names             = [];
		$exclude_product_ids       = [];
		$exclude_product_names     = [];

		if ( isset( $selected_options['product_cat'] ) && is_array( $selected_options['product_cat'] ) ) {
			foreach ( $selected_options['product_cat'] as $product_cat ) {
				$product_cat_ids[]   = $product_cat['value'];
				$product_cat_names[] = $product_cat['label'];
			}
		}

		if ( isset( $selected_options['exclude_product_cat'] ) && is_array( $selected_options['exclude_product_cat'] ) ) {
			foreach ( $selected_options['exclude_product_cat'] as $exclude_product_cat ) {
				$product_cat_exclude_ids[]   = $exclude_product_cat['value'];
				$product_cat_exclude_names[] = $exclude_product_cat['label'];
			}
		}

		if ( isset( $selected_options['product'] ) && is_array( $selected_options['product'] ) ) {
			foreach ( $selected_options['product'] as $product ) {
				$product_ids[]   = $product['value'];
				$product_names[] = $product['label'];
			}
		}

		if ( isset( $selected_options['exclude_product'] ) && is_array( $selected_options['exclude_product'] ) ) {
			foreach ( $selected_options['exclude_product'] as $exclude_product ) {
				$exclude_product_ids[]   = $exclude_product['value'];
				$exclude_product_names[] = $exclude_product['label'];
			}
		}

		$products         = ! empty( $product_ids ) ? implode( ',', array_map( 'intval', $product_ids ) ) : '';
		$exclude_products = ! empty( $exclude_product_ids ) ? implode( ',', array_map( 'intval', $exclude_product_ids ) ) : '';

		// Check if coupon code already exists using WooCommerce function.
		if ( function_exists( 'wc_get_coupon_id_by_code' ) ) {
			$existing_coupon_id = wc_get_coupon_id_by_code( $coupon_code );
			if ( $existing_coupon_id ) {
				return [
					'status'  => 'error',
					'message' => __( 'A coupon with this code already exists.', 'suretriggers' ),
				];
			}
		}

		$args = [
			'post_title'   => $coupon_code,
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => $user_id,
			'post_type'    => 'shop_coupon',
			'post_excerpt' => $description,
		];

		$coupon_id = wp_insert_post( $args, true );

		if ( is_wp_error( $coupon_id ) ) {
			return [
				'status'  => 'error',
				'message' => $coupon_id->get_error_message(),
			];
		}

		if ( ! $coupon_id ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to create coupon.', 'suretriggers' ),
			];
		}

		$coupon_meta = [
			'discount_type'              => $discount_type,
			'coupon_amount'              => $coupon_amount,
			'free_shipping'              => $is_free_shipping,
			'expiry_date'                => $expiry_date,
			'date_expires'               => ! empty( $expiry_date ) ? strtotime( $expiry_date ) : '',
			'minimum_amount'             => $min_spend,
			'maximum_amount'             => $max_spend,
			'individual_use'             => $is_individual,
			'exclude_sale_items'         => $exclude_sale_items,
			'product_ids'                => $products,
			'exclude_product_ids'        => $exclude_products,
			'product_categories'         => $product_cat_ids,
			'exclude_product_categories' => $product_cat_exclude_ids,
			'customer_email'             => $allowed_emails,
			'usage_limit'                => $usage_limit_per_coupon,
			'limit_usage_to_x_items'     => $limit_items,
			'usage_limit_per_user'       => $usage_limit_per_user,
		];

		foreach ( $coupon_meta as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		// Advanced Coupons specific features.
		$enable_coupon_url = isset( $selected_options['enable_coupon_url'] ) && $selected_options['enable_coupon_url'] ? 'yes' : 'no';
		$coupon_url_code   = isset( $selected_options['coupon_url_code'] ) ? sanitize_title( $selected_options['coupon_url_code'] ) : '';
		$success_message   = isset( $selected_options['success_message'] ) ? sanitize_textarea_field( $selected_options['success_message'] ) : '';
		$redirect_url      = isset( $selected_options['redirect_url'] ) ? esc_url_raw( $selected_options['redirect_url'] ) : '';

		$acfw_meta = [
			'_acfw_enable_coupon_url'  => $enable_coupon_url,
			'_acfw_coupon_url_code'    => $coupon_url_code,
			'_acfw_success_message'    => $success_message,
			'_acfw_after_redirect_url' => $redirect_url,
			'_acfw_schedule_start'     => $start_date,
			'_acfw_schedule_end'       => $expiry_date,
		];

		foreach ( $acfw_meta as $key => $value ) {
			if ( '' !== $value ) {
				update_post_meta( $coupon_id, $key, $value );
			}
		}

		// Prepare return data.
		$return_data = [
			'coupon_id'                             => $coupon_id,
			'coupon_code'                           => $coupon_code,
			'coupon_description'                    => $description,
			'discount_type'                         => $discount_type,
			'coupon_amount'                         => $coupon_amount,
			'free_shipping'                         => $is_free_shipping,
			'start_date'                            => $start_date,
			'expiry_date'                           => $expiry_date,
			'minimum_amount'                        => $min_spend,
			'maximum_amount'                        => $max_spend,
			'individual_use'                        => $is_individual,
			'exclude_sale_items'                    => $exclude_sale_items,
			'product_ids'                           => $products,
			'product_ids_list'                      => implode( ',', $product_ids ),
			'product_names'                         => $product_names,
			'product_names_list'                    => implode( ',', $product_names ),
			'exclude_product_ids'                   => $exclude_products,
			'exclude_product_ids_list'              => implode( ',', $exclude_product_ids ),
			'exclude_product_names'                 => $exclude_product_names,
			'exclude_product_names_list'            => implode( ',', $exclude_product_names ),
			'product_categories'                    => $product_cat_ids,
			'product_categories_list'               => implode( ',', $product_cat_ids ),
			'product_categories_names'              => $product_cat_names,
			'product_categories_names_list'         => implode( ',', $product_cat_names ),
			'exclude_product_categories'            => $product_cat_exclude_ids,
			'exclude_product_categories_list'       => implode( ',', $product_cat_exclude_ids ),
			'exclude_product_categories_names'      => $product_cat_exclude_names,
			'exclude_product_categories_names_list' => implode( ',', $product_cat_exclude_names ),
			'customer_email'                        => is_array( $allowed_emails ) ? implode( ',', $allowed_emails ) : $allowed_emails,
			'usage_limit'                           => $usage_limit_per_coupon,
			'limit_usage_to_x_items'                => $limit_items,
			'usage_limit_per_user'                  => $usage_limit_per_user,
			'enable_coupon_url'                     => $enable_coupon_url,
			'coupon_url_code'                       => $coupon_url_code,
			'success_message'                       => $success_message,
			'redirect_url'                          => $redirect_url,
		];

		return array_merge(
			$return_data,
			WordPress::get_user_context( $user_id )
		);
	}

	/**
	 * Generate random coupon code.
	 *
	 * @return string
	 */
	private function generate_coupon_code() {
		return substr( str_shuffle( 'ABCDEFGHJKMNPQRSTUVWXYZ23456789' ), 0, 8 );
	}
}

CreateCoupon::get_instance();
