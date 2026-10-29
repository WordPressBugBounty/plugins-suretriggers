<?php
/**
 * FindLead.
 * php version 5.6
 *
 * @category FindLead
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentPlayer\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Integrations\FluentPlayer\FluentPlayer;
use SureTriggers\Traits\SingletonLoader;

/**
 * FindLead
 *
 * @category FindLead
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class FindLead extends AutomateAction {

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'FluentPlayer';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'fluent_player_find_lead';

	use SingletonLoader;

	/**
	 * Register an action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Find Lead by Email', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];

		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int                 $user_id user_id.
	 * @param int                 $automation_id automation_id.
	 * @param array               $fields fields.
	 * @param array<string,mixed> $selected_options selectedOptions.
	 *
	 * @return array<string,mixed>
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! class_exists( '\FluentPlayer\App\Models\EmailCollection' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Fluent Player is not active.', 'suretriggers' ),
			];
		}

		$email = isset( $selected_options['email'] ) && is_string( $selected_options['email'] ) ? sanitize_email( $selected_options['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'A valid email address is required.', 'suretriggers' ),
			];
		}

		$lead = \FluentPlayer\App\Models\EmailCollection::findByEmail( $email );
		if ( ! $lead ) {
			return [
				'status'  => 'error',
				'message' => __( 'No lead found for this email address.', 'suretriggers' ),
			];
		}

		$context = FluentPlayer::to_array( $lead );
		if ( ! empty( $context['media_id'] ) && is_numeric( $context['media_id'] ) ) {
			$context = array_merge( $context, FluentPlayer::get_post_context( absint( $context['media_id'] ), 'media' ) );
		}
		if ( ! empty( $context['media_id'] ) && is_numeric( $context['media_id'] ) && isset( $context['layer_id'] ) ) {
			$context = array_merge( $context, FluentPlayer::get_layer_context( absint( $context['media_id'] ), $context['layer_id'] ) );
		}

		return $context;
	}
}

FindLead::get_instance();
