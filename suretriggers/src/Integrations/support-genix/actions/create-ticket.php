<?php
/**
 * CreateTicketSupportGenix.
 * php version 5.6
 *
 * @category CreateTicketSupportGenix
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SupportGenix\Actions;

use Exception;
use Mapbd_wps_ticket;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * CreateTicketSupportGenix
 */
class CreateTicketSupportGenix extends AutomateAction {

	use SingletonLoader;

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'SupportGenix';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'create_ticket_support_genix';

	/**
	 * Register action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Ticket', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id User ID.
	 * @param int   $automation_id Automation ID.
	 * @param array $fields Fields.
	 * @param array $selected_options Selected options.
	 *
	 * @return array
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( 'Mapbd_wps_ticket' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Support Genix plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$title       = isset( $selected_options['ticket_title'] ) ? sanitize_text_field( $selected_options['ticket_title'] ) : '';
		$body        = isset( $selected_options['ticket_body'] ) ? wp_kses_post( $selected_options['ticket_body'] ) : '';
		$ticket_user = isset( $selected_options['ticket_user'] ) ? (int) $selected_options['ticket_user'] : 0;
		$category_id = isset( $selected_options['cat_id'] ) ? (int) $selected_options['cat_id'] : 0;
		$priority    = isset( $selected_options['priority'] ) ? sanitize_text_field( $selected_options['priority'] ) : 'N';
		$is_public   = isset( $selected_options['is_public'] ) && 'Y' === $selected_options['is_public'] ? 'Y' : 'N';
		$mailbox_id  = isset( $selected_options['mailbox_id'] ) ? (int) $selected_options['mailbox_id'] : 0;

		if ( empty( $title ) || empty( $body ) || $ticket_user <= 0 ) {
			return [
				'status'  => 'error',
				'message' => __( 'Ticket title, body, and ticket user id are required.', 'suretriggers' ),
			];
		}

		if ( ! get_userdata( $ticket_user ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %d: WP user id */
					__( 'User with ID %d was not found.', 'suretriggers' ),
					$ticket_user
				),
			];
		}

		$priority = in_array( $priority, [ 'N', 'M', 'H' ], true ) ? $priority : 'N';

		try {
			$ticket = new Mapbd_wps_ticket();
			$ticket->title( $title );
			$ticket->ticket_body( $body );
			$ticket->ticket_user( $ticket_user );
			if ( $category_id > 0 ) {
				$ticket->cat_id( $category_id );
			}
			$ticket->status( 'N' );
			$ticket->priority( $priority );
			$ticket->is_public( $is_public );
			$ticket->opened_time( gmdate( 'Y-m-d H:i:s' ) );
			$ticket->opened_by( $ticket_user );
			$ticket->opened_by_type( 'U' );
			if ( $mailbox_id > 0 ) {
				$ticket->mailbox_id( $mailbox_id );
			}

			$created = Mapbd_wps_ticket::create_ticket( $ticket, [], false, true );
		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}

		if ( ! $created ) {
			return [
				'status'  => 'error',
				'message' => __( 'Support Genix rejected the ticket.  Check required fields and custom field validation.', 'suretriggers' ),
			];
		}

		return [
			'status'          => 'success',
			'ticket_id'       => isset( $ticket->id ) ? $ticket->id : null,
			'ticket_track_id' => isset( $ticket->ticket_track_id ) ? $ticket->ticket_track_id : null,
			'title'           => $title,
			'ticket_user'     => $ticket_user,
		];
	}
}

CreateTicketSupportGenix::get_instance();
