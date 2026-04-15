<?php
/**
 * RedirectionRedirectCreated.
 * php version 5.6
 *
 * @category RedirectionRedirectCreated
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
 * RedirectionRedirectCreated
 *
 * @category RedirectionRedirectCreated
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class RedirectionRedirectCreated {

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
	public $trigger = 'rd_redirect_created';

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
			'label'         => __( 'Redirect Rule Created', 'suretriggers' ),
			'action'        => $this->trigger,
			'common_action' => 'redirection_redirect_updated',
			'function'      => [ $this, 'trigger_listener' ],
			'priority'      => 10,
			'accepted_args' => 2,
		];

		return $triggers;
	}

	/**
	 * Trigger listener.
	 *
	 * On creation, the first parameter is the insert ID (int).
	 * On update, the first parameter is the old Red_Item object.
	 * We only fire for creation (when first arg is int).
	 *
	 * @param  int|object $id_or_old_redirect Insert ID on creation, old redirect object on update.
	 * @param  object     $redirect           The new redirect object.
	 *
	 * @return void
	 */
	public function trigger_listener( $id_or_old_redirect, $redirect ) {
		if ( ! is_int( $id_or_old_redirect ) ) {
			return;
		}

		if ( ! is_object( $redirect ) || ! method_exists( $redirect, 'to_json' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$context = [];

		if ( $user_id > 0 ) {
			$context = WordPress::get_user_context( $user_id );
		}

		$context['redirect'] = Redirection::get_redirect_context( $redirect );

		AutomationController::sure_trigger_handle_trigger(
			[
				'trigger' => $this->trigger,
				'context' => $context,
			]
		);
	}
}

RedirectionRedirectCreated::get_instance();
