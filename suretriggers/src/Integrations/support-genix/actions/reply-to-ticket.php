<?php
/**
 * ReplyToTicketSupportGenix.
 * php version 5.6
 *
 * @category ReplyToTicketSupportGenix
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SupportGenix\Actions;

use Exception;
use Mapbd_wps_ticket;
use Mapbd_wps_ticket_reply;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\SupportGenix\SupportGenix;
use SureTriggers\Traits\SingletonLoader;

/**
 * ReplyToTicketSupportGenix
 */
class ReplyToTicketSupportGenix extends AutomateAction {

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
	public $action = 'reply_to_ticket_support_genix';

	/**
	 * Register action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Reply to Ticket', 'suretriggers' ),
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
		if ( ! class_exists( 'Mapbd_wps_ticket' ) || ! class_exists( 'Mapbd_wps_ticket_reply' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Support Genix plugin is not installed or active.', 'suretriggers' ),
			];
		}

		$ticket_id       = isset( $selected_options['ticket_id'] ) ? (int) $selected_options['ticket_id'] : 0;
		$reply_text      = isset( $selected_options['reply_text'] ) ? wp_kses_post( $selected_options['reply_text'] ) : '';
		$replied_by_type = isset( $selected_options['replied_by_type'] ) ? sanitize_text_field( $selected_options['replied_by_type'] ) : 'A';
		$replied_by      = isset( $selected_options['replied_by'] ) ? (int) $selected_options['replied_by'] : 0;
		$is_private      = isset( $selected_options['is_private'] ) && 'Y' === $selected_options['is_private'] ? 'Y' : 'N';

		if ( $ticket_id <= 0 || empty( $reply_text ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Ticket ID and reply text are required.', 'suretriggers' ),
			];
		}

		if ( ! in_array( $replied_by_type, [ 'A', 'U', 'G' ], true ) ) {
			$replied_by_type = 'A';
		}

		if ( 'A' === $replied_by_type ) {
			$replied_by = SupportGenix::resolve_agent_id( $replied_by );
			if ( $replied_by <= 0 ) {
				return [
					'status'  => 'error',
					'message' => __( 'Could not resolve an agent user to post the reply as.', 'suretriggers' ),
				];
			}
		} elseif ( 'U' === $replied_by_type ) {
			if ( $replied_by <= 0 || ! get_userdata( $replied_by ) ) {
				return [
					'status'  => 'error',
					'message' => __( 'A valid user id is required when replying as User.', 'suretriggers' ),
				];
			}
		}

		/**
		 * Reply entity returned by the Support Genix plugin.
		 *
		 * @var object|null $reply_obj
		 */
		$reply_obj = null;
		/**
		 * Ticket entity returned by the Support Genix plugin.
		 *
		 * @var object|null $ticket
		 */
		$ticket = null;

		try {
			$result = Mapbd_wps_ticket_reply::AddReply(
				$ticket_id,
				$reply_text,
				$replied_by,
				$replied_by_type,
				$is_private,
				[],
				$reply_obj,
				$ticket
			);
		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}

		if ( ! $result ) {
			return [
				'status'  => 'error',
				'message' => __( 'Support Genix rejected the reply.  The ticket may not exist or the user may not have permission to reply.', 'suretriggers' ),
			];
		}

		$reply_id = null;
		if ( ! empty( $reply_obj ) && is_object( $reply_obj ) && property_exists( $reply_obj, 'reply_id' ) ) {
			$reply_id = $reply_obj->reply_id;
		}

		return [
			'status'          => 'success',
			'ticket_id'       => $ticket_id,
			'reply_id'        => $reply_id,
			'replied_by'      => $replied_by,
			'replied_by_type' => $replied_by_type,
		];
	}
}

ReplyToTicketSupportGenix::get_instance();
