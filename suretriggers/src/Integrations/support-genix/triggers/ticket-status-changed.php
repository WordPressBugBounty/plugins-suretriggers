<?php
/**
 * TicketStatusChangedSupportGenix.
 * php version 5.6
 *
 * @category TicketStatusChangedSupportGenix
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SupportGenix\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\SupportGenix\SupportGenix;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'TicketStatusChangedSupportGenix' ) ) :

	/**
	 * TicketStatusChangedSupportGenix
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class TicketStatusChangedSupportGenix {

		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'SupportGenix';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'ticket_status_changed_support_genix';

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
		 * Register trigger.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {
			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Ticket Status Changed', 'suretriggers' ),
				'action'        => 'ticket_status_changed_support_genix',
				'common_action' => 'apbd-wps/action/ticket-status-change',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param object $ticket ticket object.
		 * @param int    $changed_by user id that triggered the change.
		 *
		 * @return void
		 */
		public function trigger_listener( $ticket, $changed_by = 0 ) {
			if ( empty( $ticket ) ) {
				return;
			}

			$context = [
				'ticket'     => SupportGenix::prepare_ticket_context( $ticket ),
				'changed_by' => (int) $changed_by,
			];

			$changed_by_user = $changed_by ? get_userdata( (int) $changed_by ) : null;
			if ( $changed_by_user ) {
				$context['changed_by_email'] = $changed_by_user->user_email;
				$context['changed_by_name']  = $changed_by_user->display_name;
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	TicketStatusChangedSupportGenix::get_instance();

endif;
