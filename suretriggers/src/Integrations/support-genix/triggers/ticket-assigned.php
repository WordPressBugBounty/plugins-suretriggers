<?php
/**
 * TicketAssignedSupportGenix.
 * php version 5.6
 *
 * @category TicketAssignedSupportGenix
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

if ( ! class_exists( 'TicketAssignedSupportGenix' ) ) :

	/**
	 * TicketAssignedSupportGenix
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class TicketAssignedSupportGenix {

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
		public $trigger = 'ticket_assigned_support_genix';

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
				'label'         => __( 'Ticket Assigned to Agent', 'suretriggers' ),
				'action'        => 'ticket_assigned_support_genix',
				'common_action' => 'apbd-wps/action/ticket-assigned',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];

			return $triggers;
		}

		/**
		 * Trigger listener.
		 *
		 * @param object $ticket ticket object.
		 *
		 * @return void
		 */
		public function trigger_listener( $ticket ) {
			if ( empty( $ticket ) ) {
				return;
			}

			$context = [
				'ticket' => SupportGenix::prepare_ticket_context( $ticket ),
			];

			$assigned_to = isset( $context['ticket']['assigned_on'] ) ? (int) $context['ticket']['assigned_on'] : 0;
			if ( $assigned_to > 0 ) {
				$assignee = get_userdata( $assigned_to );
				if ( $assignee ) {
					$context['assigned_to_email'] = $assignee->user_email;
					$context['assigned_to_name']  = $assignee->display_name;
				}
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
	TicketAssignedSupportGenix::get_instance();

endif;
