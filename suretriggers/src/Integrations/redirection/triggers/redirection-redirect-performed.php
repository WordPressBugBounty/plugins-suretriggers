<?php
/**
 * RedirectionRedirectPerformed.
 * php version 5.6
 *
 * @category RedirectionRedirectPerformed
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\Redirection\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\Redirection\Redirection;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

/**
 * RedirectionRedirectPerformed
 *
 * @category RedirectionRedirectPerformed
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RedirectionRedirectPerformed {

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
	public $trigger = 'rd_redirect_performed';

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
			'label'         => __( 'Redirect Performed', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'redirection_visit',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 3,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * @param  object $redirect     The redirect object.
	 * @param  string $original_url The original requested URL.
	 * @param  string $target_url   The target URL being redirected to.
	 *
	 * @return void
	 */
	public function trigger_listener( $redirect, $original_url, $target_url ) {
		$user_id = get_current_user_id();
		$context = [];

		if ( $user_id > 0 ) {
			$context = WordPress::get_user_context( $user_id );
		}

		$context['original_url'] = $original_url;
		$context['target_url']   = $target_url;
		$context['redirect']     = Redirection::get_redirect_context( $redirect );

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

RedirectionRedirectPerformed::get_instance();
