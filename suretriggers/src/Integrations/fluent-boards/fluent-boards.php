<?php
/**
 * FluentBoards core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\FluentBoards;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\FluentBoards
 */
class FluentBoards extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'FluentBoards';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'FluentBoards', 'suretriggers' );
		$this->description = __( 'FluentBoards is the Ultimate Scheduling Solution for WordPress. Harness the power of unlimited appointments, bookings, webinars, events, sales calls, etc., and save time with scheduling automation.', 'suretriggers' );
		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'FLUENT_BOARDS' );
	}

	/**
	 * Resolve a WP user id to act as, preferring in order: the automation's
	 * user_id, a selected option to prefer (e.g. "Created By"), then the
	 * site's first administrator.
	 *
	 * FluentBoards' own API/permission layer (PermissionManager) gates board
	 * reads/writes on get_current_user_id(). A webhook-triggered automation
	 * run has no logged-in WP user of its own, so those checks silently fail
	 * unless a fallback identity is resolved and applied — see
	 * self::maybe_set_acting_user().
	 *
	 * @param int|string $user_id      Automation's configured user id.
	 * @param int|string $preferred_id Selected option to prefer, if any (e.g. "Created By").
	 * @return int
	 */
	public static function resolve_acting_user_id( $user_id, $preferred_id = '' ) {
		if ( ! empty( $user_id ) ) {
			return absint( $user_id );
		}

		if ( ! empty( $preferred_id ) ) {
			return absint( $preferred_id );
		}

		$admins = get_users(
			[
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
				'fields'  => 'ID',
			]
		);

		return ! empty( $admins ) ? absint( $admins[0] ) : 0;
	}

	/**
	 * Set the current user to act as, only when no user is already resolved
	 * for this request. A dashboard "Test" run is a cookie-authenticated
	 * REST request and already has a valid current user; forcing another id
	 * there would overwrite a working session instead of fixing a missing one.
	 *
	 * @param int|string $user_id      Automation's configured user id.
	 * @param int|string $preferred_id Selected option to prefer, if any (e.g. "Created By").
	 * @return void
	 */
	public static function maybe_set_acting_user( $user_id, $preferred_id = '' ) {
		if ( get_current_user_id() ) {
			return;
		}

		$acting_user_id = self::resolve_acting_user_id( $user_id, $preferred_id );
		if ( ! empty( $acting_user_id ) ) {
			wp_set_current_user( $acting_user_id );
		}
	}

}

IntegrationsController::register( FluentBoards::class );
