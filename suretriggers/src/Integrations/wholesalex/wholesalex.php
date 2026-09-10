<?php
/**
 * WholesaleX core integrations file
 *
 * @since 1.1.38
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\WholesaleX;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class WholesaleX
 *
 * @package SureTriggers\Integrations\WholesaleX
 */
class WholesaleX extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'WholesaleX';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'WholesaleX', 'suretriggers' );
		$this->description = __( 'WholesaleX is a WooCommerce wholesale solution with role-based pricing, dynamic discount rules, and a B2B/B2C registration & approval workflow.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/wholesalex.svg';

		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'WHOLESALEX_VER' ) && function_exists( 'wholesalex' );
	}

	/**
	 * Build the shared user context (core WP fields + WholesaleX role/status
	 * meta) used by every WholesaleX trigger.
	 *
	 * Registration-form fields beyond these (company name, phone, etc.) are
	 * defined dynamically per site via WholesaleX's form builder, so they are
	 * intentionally left out here rather than hardcoded against meta keys
	 * that may not exist on a given install.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed>
	 */
	public static function get_user_context( $user_id ) {
		$user_id = absint( $user_id );
		$context = [];

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $context;
		}

		$context['wp_user_id']      = $user->ID;
		$context['user_login']      = $user->user_login;
		$context['display_name']    = $user->display_name;
		$context['user_firstname']  = $user->user_firstname;
		$context['user_lastname']   = $user->user_lastname;
		$context['user_email']      = $user->user_email;
		$context['user_registered'] = $user->user_registered;

		$context['wholesalex_status'] = get_user_meta( $user_id, '__wholesalex_status', true );

		if ( function_exists( 'wholesalex' ) ) {
			$role_id              = get_user_meta( $user_id, '__wholesalex_role', true );
			$registration_role_id = get_user_meta( $user_id, '__wholesalex_registration_role', true );

			$context['wholesalex_role']              = $role_id ? wholesalex()->get_role_name_by_role_id( $role_id ) : '';
			$context['wholesalex_registration_role'] = $registration_role_id ? wholesalex()->get_role_name_by_role_id( $registration_role_id ) : '';
		} else {
			$context['wholesalex_role']              = '';
			$context['wholesalex_registration_role'] = '';
		}

		return $context;
	}
}

IntegrationsController::register( WholesaleX::class );
