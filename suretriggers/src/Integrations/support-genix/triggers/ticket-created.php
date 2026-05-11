<?php
/**
 * TicketCreatedSupportGenix.
 * php version 5.6
 *
 * @category TicketCreatedSupportGenix
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

if ( ! class_exists( 'TicketCreatedSupportGenix' ) ) :

	/**
	 * TicketCreatedSupportGenix
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class TicketCreatedSupportGenix {

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
		public $trigger = 'ticket_created_support_genix';

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
				'label'         => __( 'Ticket Created', 'suretriggers' ),
				'action'        => 'ticket_created_support_genix',
				'common_action' => 'apbd-wps/action/ticket-created',
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
		 * @param mixed  $custom_fields custom fields.
		 *
		 * @return void
		 */
		public function trigger_listener( $ticket, $custom_fields = null ) {
			if ( empty( $ticket ) ) {
				return;
			}

			$context = [
				'ticket'        => SupportGenix::prepare_ticket_context( $ticket ),
				'custom_fields' => $custom_fields,
			];

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
	TicketCreatedSupportGenix::get_instance();

endif;
