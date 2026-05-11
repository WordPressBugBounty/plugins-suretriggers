<?php
/**
 * Support Genix core integration file.
 *
 * @since   1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\SupportGenix;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SupportGenix
 *
 * @package SureTriggers\Integrations\SupportGenix
 */
class SupportGenix extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'SupportGenix';

	/**
	 * Status code → label map used by Mapbd_wps_ticket::GetPropertyRawOptions.
	 *
	 * @var array<string, string>
	 */
	protected static $status_labels = [
		'N' => 'New',
		'C' => 'Closed',
		'P' => 'In-progress',
		'R' => 'Re-open',
		'A' => 'Active',
		'I' => 'Inactive',
		'D' => 'Deleted',
	];

	/**
	 * Priority code → label map.
	 *
	 * @var array<string, string>
	 */
	protected static $priority_labels = [
		'N' => 'Normal',
		'M' => 'Medium',
		'H' => 'High',
	];

	/**
	 * User type code → label map.
	 *
	 * @var array<string, string>
	 */
	protected static $user_type_labels = [
		'A' => 'Agent',
		'U' => 'User',
		'G' => 'Guest',
	];

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Support Genix', 'suretriggers' );
		$this->description = __( 'Support Genix is a WordPress helpdesk and support ticketing plugin.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/supportgenix.svg';

		parent::__construct();
	}

	/**
	 * Is plugin dependency installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'SUPPORT_GENIX_LITE_VERSION' )
			|| defined( 'SUPPORT_GENIX_VERSION' )
			|| class_exists( 'Mapbd_wps_ticket' );
	}

	/**
	 * Flatten a Mapbd_wps_ticket (or similar) object into an associative array
	 * with human readable status / priority / user type labels appended.
	 *
	 * @param object|array $ticket Ticket model or array.
	 * @return array
	 */
	public static function prepare_ticket_context( $ticket ) {
		$data = self::object_to_array( $ticket );

		if ( ! is_array( $data ) ) {
			return [];
		}

		if ( isset( $data['status'] ) && isset( self::$status_labels[ $data['status'] ] ) ) {
			$data['status_label'] = self::$status_labels[ $data['status'] ];
		}

		if ( isset( $data['priority'] ) && isset( self::$priority_labels[ $data['priority'] ] ) ) {
			$data['priority_label'] = self::$priority_labels[ $data['priority'] ];
		}

		if ( isset( $data['user_type'] ) && isset( self::$user_type_labels[ $data['user_type'] ] ) ) {
			$data['user_type_label'] = self::$user_type_labels[ $data['user_type'] ];
		}

		$ticket_user_id = isset( $data['ticket_user'] ) ? (int) $data['ticket_user'] : 0;
		if ( $ticket_user_id > 0 ) {
			$user = get_userdata( $ticket_user_id );
			if ( $user ) {
				$data['ticket_user_email'] = $user->user_email;
				$data['ticket_user_name']  = $user->display_name;
				$data['ticket_user_login'] = $user->user_login;
			}
		}

		return $data;
	}

	/**
	 * Flatten a reply object.
	 *
	 * @param object|array $reply Reply object.
	 * @return array
	 */
	public static function prepare_reply_context( $reply ) {
		$data = self::object_to_array( $reply );

		if ( ! is_array( $data ) ) {
			return [];
		}

		if ( isset( $data['replied_by_type'] ) && isset( self::$user_type_labels[ $data['replied_by_type'] ] ) ) {
			$data['replied_by_type_label'] = self::$user_type_labels[ $data['replied_by_type'] ];
		}

		if ( isset( $data['ticket_status'] ) && isset( self::$status_labels[ $data['ticket_status'] ] ) ) {
			$data['ticket_status_label'] = self::$status_labels[ $data['ticket_status'] ];
		}

		$replied_by = isset( $data['replied_by'] ) ? (int) $data['replied_by'] : 0;
		if ( $replied_by > 0 && isset( $data['replied_by_type'] ) && 'G' !== $data['replied_by_type'] ) {
			$user = get_userdata( $replied_by );
			if ( $user ) {
				$data['replied_by_email'] = $user->user_email;
				$data['replied_by_name']  = $user->display_name;
			}
		}

		return $data;
	}

	/**
	 * Resolve an agent user id for making replies.  Falls back to the first
	 * administrator if the requested user isn't available.
	 *
	 * @param int $agent_id Requested agent id.
	 * @return int
	 */
	public static function resolve_agent_id( $agent_id ) {
		$agent_id = (int) $agent_id;
		if ( $agent_id > 0 && get_userdata( $agent_id ) ) {
			return $agent_id;
		}

		$admins = get_users(
			[
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			]
		);

		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}

	/**
	 * Safely cast an object / model to array.
	 *
	 * @param object|array $value Value to cast.
	 * @return array
	 */
	protected static function object_to_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'toArray' ) ) {
				$maybe = $value->toArray();
				if ( is_array( $maybe ) ) {
					return $maybe;
				}
			}

			$vars = get_object_vars( $value );
			if ( is_array( $vars ) ) {
				return $vars;
			}
		}

		return [];
	}

}

IntegrationsController::register( SupportGenix::class );
