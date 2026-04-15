<?php
/**
 * Redirection404Detected.
 * php version 5.6
 *
 * @category Redirection404Detected
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Redirection\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * Redirection404Detected
 *
 * @category Redirection404Detected
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class Redirection404Detected {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'Redirection';

	/**
	 * Trigger name.
	 *
	 * @var string
	 */
	public $trigger = 'rd_404_detected';

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
	 *
	 * @return array
	 */
	public function register( $triggers ) {
		$triggers[ $this->integration ][ $this->trigger ] = [
			'label'         => __( '404 Error Detected', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'redirection_404',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 1,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * @param  array $log_data The 404 log entry data.
	 *
	 * @return void
	 */
	public function trigger_listener( $log_data ) {
		if ( ! is_array( $log_data ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$context = [];

		if ( $user_id > 0 ) {
			$context = WordPress::get_user_context( $user_id );
		}

		$context['404_url']   = isset( $log_data['url'] ) ? sanitize_text_field( $log_data['url'] ) : '';
		$context['domain']    = isset( $log_data['domain'] ) ? sanitize_text_field( $log_data['domain'] ) : '';
		$context['ip']        = isset( $log_data['ip'] ) ? sanitize_text_field( $log_data['ip'] ) : '';
		$context['agent']     = isset( $log_data['agent'] ) ? sanitize_text_field( $log_data['agent'] ) : '';
		$context['referrer']  = isset( $log_data['referrer'] ) ? sanitize_text_field( $log_data['referrer'] ) : '';
		$context['timestamp'] = isset( $log_data['created'] ) ? sanitize_text_field( $log_data['created'] ) : '';

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

Redirection404Detected::get_instance();
