<?php
/**
 * UserLogin.
 * php version 5.6
 *
 * @category UserLogin
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Wordpress\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;


/**
 * UserLogin
 *
 * @category UserLogin
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class UserLogin {


	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WordPress';

	/**
	 * Trigger name.
	 *
	 * @var string
	 */
	public $trigger = 'wp_login';

	use SingletonLoader;


	/**
	 * Constructor
	 *
	 * @since  1.0.0
	 */
	public function __construct() {

		add_filter( 'sure_trigger_register_trigger', [ $this, 'register' ] );
	}

	/**
	 * Register action.
	 *
	 * @param array $triggers trigger data.
	 * @return array
	 */
	public function register( $triggers ) {

		$triggers[ $this->integration ][ $this->trigger ] = [
			'label'         => __( 'User logs in to the site', 'suretriggers' ),
			'action'        => 'wp_login',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 2,
		];

		return $triggers;

	}


	/**
	 * Trigger listener
	 *
	 * @param array  $user_login user_login.
	 * @param object $user user object.
	 *
	 * @return void
	 */
	public function trigger_listener( $user_login, $user ) {
		if ( ! is_object( $user ) || ! property_exists( $user, 'ID' ) ) {
			return;
		}

		if ( $this->is_duplicate_login( (int) $user->ID ) ) {
			return;
		}

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => WordPress::get_user_context( $user->ID ),
			]
		);

	}

	/**
	 * Check whether this login was already reported a moment ago.
	 *
	 * Some setups fire `wp_login` more than once for a single login (for example
	 * login widgets or user-sync plugins that log the user in again). Without a
	 * guard each of those fires would start the automation again.
	 *
	 * The same user is only reported once per window. The window defaults to
	 * 5 seconds and can be changed, or switched off by returning 0, with the
	 * `suretriggers_user_login_dedupe_window` filter.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when the login should be skipped.
	 */
	private function is_duplicate_login( $user_id ) {
		/**
		 * Filters the number of seconds in which a repeated login of the same user is ignored.
		 *
		 * @param int $window  Seconds. Return 0 to disable the check.
		 * @param int $user_id User ID.
		 */
		$window = (int) apply_filters( 'suretriggers_user_login_dedupe_window', 5, $user_id );

		if ( $window <= 0 || $user_id <= 0 ) {
			return false;
		}

		$key = 'suretriggers_wp_login_' . $user_id;

		if ( false !== get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, time(), $window );

		return false;
	}

}


UserLogin::get_instance();
