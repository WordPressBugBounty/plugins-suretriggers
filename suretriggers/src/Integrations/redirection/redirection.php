<?php
/**
 * Redirection core integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\Redirection;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class SureTrigger
 *
 * @package SureTriggers\Integrations\Redirection
 */
class Redirection extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'Redirection';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'Redirection', 'suretriggers' );
		$this->description = __( 'Redirection is a WordPress plugin to manage 301 redirections, keep track of 404 errors, and generally tidy up any loose ends your site may have.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/redirection.svg';

		parent::__construct();
	}

	/**
	 * Is Plugin depended plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return defined( 'REDIRECTION_FILE' );
	}

	/**
	 * Get the default WordPress module group ID from the Redirection plugin.
	 *
	 * @return int|false
	 */
	public static function get_default_group_id() {
		if ( ! class_exists( 'Red_Group' ) ) {
			return false;
		}

		$groups = \Red_Group::get_all_for_module( 1 );
		if ( ! empty( $groups ) && isset( $groups[0]['id'] ) ) {
			return (int) $groups[0]['id'];
		}

		return false;
	}

	/**
	 * Get redirect context array from a Red_Item object.
	 *
	 * @param object $redirect Redirect object.
	 * @return array
	 */
	public static function get_redirect_context( $redirect ) {
		if ( ! method_exists( $redirect, 'to_json' ) ) {
			return [];
		}

		$json = $redirect->to_json();

		return [
			'redirect_id' => isset( $json['id'] ) ? $json['id'] : 0,
			'source_url'  => isset( $json['url'] ) ? $json['url'] : '',
			'match_type'  => isset( $json['match_type'] ) ? $json['match_type'] : '',
			'action_type' => isset( $json['action_type'] ) ? $json['action_type'] : '',
			'action_code' => isset( $json['action_code'] ) ? $json['action_code'] : 0,
			'action_data' => isset( $json['action_data'] ) ? $json['action_data'] : [],
			'title'       => isset( $json['title'] ) ? $json['title'] : '',
			'hits'        => isset( $json['hits'] ) ? $json['hits'] : 0,
			'regex'       => isset( $json['regex'] ) ? $json['regex'] : false,
			'group_id'    => isset( $json['group_id'] ) ? $json['group_id'] : 0,
			'position'    => isset( $json['position'] ) ? $json['position'] : 0,
			'last_access' => isset( $json['last_access'] ) ? $json['last_access'] : '',
			'enabled'     => isset( $json['enabled'] ) ? $json['enabled'] : false,
		];
	}
}

IntegrationsController::register( Redirection::class );
