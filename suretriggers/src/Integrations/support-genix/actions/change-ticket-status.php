<?php
/**
 * ChangeTicketStatusSupportGenix.
 * php version 5.6
 *
 * @category ChangeTicketStatusSupportGenix
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
 * ChangeTicketStatusSupportGenix
 */
class ChangeTicketStatusSupportGenix extends AutomateAction {

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
	public $action = 'change_ticket_status_support_genix';

	/**
	 * Allowed status codes.
	 *
	 * @var array
	 */
	protected $allowed_statuses = [ 'N', 'C', 'P', 'R' ];

	/**
	 * Register action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Change Ticket Status', 'suretriggers' ),
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
		global $wpdb;

		if ( ! class_exists( 'Mapbd_wps_ticket' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Support Genix plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$ticket_id  = isset( $selected_options['ticket_id'] ) ? (int) $selected_options['ticket_id'] : 0;
		$new_status = isset( $selected_options['status'] ) ? sanitize_text_field( $selected_options['status'] ) : '';

		if ( $ticket_id <= 0 || empty( $new_status ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Ticket ID and target status are required.', 'suretriggers' ),
			];
		}

		if ( ! in_array( $new_status, $this->allowed_statuses, true ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Unsupported status. Allowed: N (New), C (Closed), P (In-progress), R (Re-open).', 'suretriggers' ),
			];
		}

		try {
			$ticket = Mapbd_wps_ticket::FindBy( 'id', $ticket_id );
		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}

		if ( empty( $ticket ) ) {
			return [
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %d: ticket id */
					__( 'Ticket with ID %d was not found.', 'suretriggers' ),
					$ticket_id
				),
			];
		}

		try {
			$update = new Mapbd_wps_ticket();
			$update->status( $new_status );
			$update->last_status_update_time( gmdate( 'Y-m-d H:i:s' ) );
			if ( 'R' === $new_status ) {
				$update->re_open_time( gmdate( 'Y-m-d H:i:s' ) );
				$update->re_open_by( get_current_user_id() );
				$update->re_open_by_type( 'A' );
			}
			$update->SetWhereUpdate( 'id', $ticket_id );
			$update->UnsetAllExcepts( 'status,last_status_update_time,re_open_time,re_open_by,re_open_by_type' );
			$updated = $update->Update();
		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}

		if ( ! $updated ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to update ticket status.', 'suretriggers' ),
			];
		}

		$updated_ticket = Mapbd_wps_ticket::FindBy( 'id', $ticket_id );
		if ( $updated_ticket ) {
			// Third-party hook names defined by the Support Genix plugin; cannot be renamed.
			//phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			do_action( 'apbd-wps/action/ticket-status-change', $updated_ticket, get_current_user_id() );
			//phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			do_action( 'apbd-wps/action/ticket-property-update', $updated_ticket, 'status' );
		}

		return [
			'status'     => 'success',
			'ticket_id'  => $ticket_id,
			'new_status' => $new_status,
		];
	}
}

ChangeTicketStatusSupportGenix::get_instance();
