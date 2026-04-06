<?php
/**
 * WPAdverts core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\WPAdverts;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\WPAdverts
 */
class WPAdverts extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'WPAdverts';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'WP Adverts', 'suretriggers' );
		$this->description = __( 'WPAdverts is the lightest plugin to create a classifieds or job board on your WordPress site.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/wpadverts.svg';

		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'ADVERTS_FILE' );
	}

	/**
	 * Get advert context data.
	 *
	 * @param int $post_id Advert post ID.
	 * @return array
	 */
	public static function get_advert_context( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		$context = [
			'advert_id'      => $post->ID,
			'advert_title'   => $post->post_title,
			'advert_content' => $post->post_content,
			'advert_status'  => $post->post_status,
			'advert_url'     => get_permalink( $post->ID ),
			'advert_date'    => $post->post_date,
			'advert_author'  => $post->post_author,
		];

		$person = get_post_meta( $post_id, 'adverts_person', true );
		if ( ! empty( $person ) ) {
			$context['advert_contact_name'] = $person;
		}

		$email = get_post_meta( $post_id, 'adverts_email', true );
		if ( ! empty( $email ) ) {
			$context['advert_contact_email'] = $email;
		}

		$phone = get_post_meta( $post_id, 'adverts_phone', true );
		if ( ! empty( $phone ) ) {
			$context['advert_contact_phone'] = $phone;
		}

		$price = get_post_meta( $post_id, 'adverts_price', true );
		if ( ! empty( $price ) ) {
			$context['advert_price'] = $price;
		}

		$location = get_post_meta( $post_id, 'adverts_location', true );
		if ( ! empty( $location ) ) {
			$context['advert_location'] = $location;
		}

		$featured_image = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post->ID ), 'full' );
		if ( ! empty( $featured_image ) && is_array( $featured_image ) ) {
			$context['advert_featured_image'] = $featured_image[0];
		}

		$categories = get_the_terms( $post->ID, 'advert_category' );
		if ( ! empty( $categories ) && is_array( $categories ) ) {
			$cat_names = [];
			$cat_ids   = [];
			foreach ( $categories as $category ) {
				$cat_names[] = $category->name;
				$cat_ids[]   = $category->term_id;
			}
			$context['advert_categories']   = implode( ', ', $cat_names );
			$context['advert_category_ids'] = implode( ', ', $cat_ids );
		}

		$expiration = get_post_meta( $post_id, '_expiration_date', true );
		if ( ! empty( $expiration ) ) {
			$context['advert_expiration_date'] = is_numeric( $expiration ) ? gmdate( 'Y-m-d H:i:s', absint( $expiration ) ) : '';
		}

		return $context;
	}

	/**
	 * Get payment context data.
	 *
	 * @param \WP_Post $payment Payment post object.
	 * @return array
	 */
	public static function get_payment_context( $payment ) {
		if ( ! $payment instanceof \WP_Post ) {
			return [];
		}

		$context = [
			'payment_id'     => $payment->ID,
			'payment_title'  => $payment->post_title,
			'payment_status' => $payment->post_status,
			'payment_date'   => $payment->post_date,
		];

		$object_id = get_post_meta( $payment->ID, '_adverts_object_id', true );
		if ( ! empty( $object_id ) ) {
			$context['payment_advert_id'] = $object_id;
		}

		$paid = get_post_meta( $payment->ID, '_adverts_payment_paid', true );
		if ( '' !== $paid ) {
			$context['payment_amount_paid'] = $paid;
		}

		$total = get_post_meta( $payment->ID, '_adverts_payment_total', true );
		if ( '' !== $total ) {
			$context['payment_amount_total'] = $total;
		}

		$gateway = get_post_meta( $payment->ID, '_adverts_payment_gateway', true );
		if ( ! empty( $gateway ) ) {
			$context['payment_gateway'] = $gateway;
		}

		$payment_type = get_post_meta( $payment->ID, '_adverts_payment_type', true );
		if ( ! empty( $payment_type ) ) {
			$context['payment_type'] = $payment_type;
		}

		$buyer_name = get_post_meta( $payment->ID, 'adverts_person', true );
		if ( ! empty( $buyer_name ) ) {
			$context['payment_buyer_name'] = $buyer_name;
		}

		$buyer_email = get_post_meta( $payment->ID, 'adverts_email', true );
		if ( ! empty( $buyer_email ) ) {
			$context['payment_buyer_email'] = $buyer_email;
		}

		$user_id = get_post_meta( $payment->ID, '_adverts_user_id', true );
		if ( ! empty( $user_id ) ) {
			$context['payment_user_id'] = $user_id;
		}

		return $context;
	}
}

IntegrationsController::register( WPAdverts::class );
