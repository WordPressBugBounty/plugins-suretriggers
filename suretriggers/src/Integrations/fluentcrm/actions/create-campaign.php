<?php
/**
 * CreateCampaign.
 * php version 5.6
 *
 * @category CreateCampaign
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentCRM\Actions;

use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;
use FluentCrm\App\Models\Campaign;


/**
 * CreateCampaign
 *
 * @category CreateCampaign
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class CreateCampaign extends AutomateAction {


	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'FluentCRM';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'fluentcrm_create_campaign';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Create Campaign', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, 'action_listener' ],
		];
		return $actions;
	}

	/**
	 * Action listener.
	 *
	 * @param int   $user_id user_id.
	 * @param int   $automation_id automation_id.
	 * @param array $fields fields.
	 * @param array $selected_options selectedOptions.
	 * @return array
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! function_exists( 'FluentCrmApi' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentCRM is not active.', 'suretriggers' ),
			];
		}

		if ( ! class_exists( 'FluentCrm\App\Models\Campaign' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'FluentCRM Campaign model not available.', 'suretriggers' ),
			];
		}

		$campaign_name = isset( $selected_options['campaign_name'] ) ? sanitize_text_field( $selected_options['campaign_name'] ) : '';

		if ( empty( $campaign_name ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'Campaign name is required.', 'suretriggers' ),
			];
		}

		// Optional email fields.
		$email_subject    = isset( $selected_options['email_subject'] ) ? sanitize_text_field( $selected_options['email_subject'] ) : '';
		$email_pre_header = isset( $selected_options['email_pre_header'] ) ? sanitize_text_field( $selected_options['email_pre_header'] ) : '';
		$html_content     = isset( $selected_options['html_content'] ) ? wp_kses_post( $selected_options['html_content'] ) : '';

		// Recipient lists/tags — paired by index, matching the send-email actions' convention.
		$selected_list = isset( $selected_options['recipient_lists'] ) ? $selected_options['recipient_lists'] : 'all';
		$selected_tag  = isset( $selected_options['recipient_tags'] ) ? $selected_options['recipient_tags'] : 'all';

		$subscribers[] = [
			'list' => is_numeric( $selected_list ) ? (string) $selected_list : $selected_list,
			'tag'  => is_numeric( $selected_tag ) ? (string) $selected_tag : $selected_tag,
		];

		if ( is_array( $selected_list ) && is_array( $selected_tag ) ) {
			$max_count   = max( count( $selected_list ), count( $selected_tag ) );
			$subscribers = [];
			for ( $i = 0; $i < $max_count; $i++ ) {
				$raw_list      = isset( $selected_list[ $i ]['value'] ) ? $selected_list[ $i ]['value'] : '';
				$raw_tag       = isset( $selected_tag[ $i ]['value'] ) ? $selected_tag[ $i ]['value'] : '';
				$subscribers[] = [
					'list' => is_numeric( $raw_list ) ? (string) $raw_list : $raw_list,
					'tag'  => is_numeric( $raw_tag ) ? (string) $raw_tag : $raw_tag,
				];
			}
		}

		// Excluded lists/tags — same pairing convention.
		$excluded_list = isset( $selected_options['excluded_lists'] ) ? $selected_options['excluded_lists'] : '';
		$excluded_tag  = isset( $selected_options['excluded_tags'] ) ? $selected_options['excluded_tags'] : '';

		$excluded_subscribers = null;
		if ( ! empty( $excluded_list ) || ! empty( $excluded_tag ) ) {
			$excluded_subscribers[] = [
				'list' => is_numeric( $excluded_list ) ? (string) $excluded_list : $excluded_list,
				'tag'  => is_numeric( $excluded_tag ) ? (string) $excluded_tag : $excluded_tag,
			];

			if ( is_array( $excluded_list ) && is_array( $excluded_tag ) ) {
				$max_count            = max( count( $excluded_list ), count( $excluded_tag ) );
				$excluded_subscribers = [];
				for ( $i = 0; $i < $max_count; $i++ ) {
					$raw_list               = isset( $excluded_list[ $i ]['value'] ) ? $excluded_list[ $i ]['value'] : '';
					$raw_tag                = isset( $excluded_tag[ $i ]['value'] ) ? $excluded_tag[ $i ]['value'] : '';
					$excluded_subscribers[] = [
						'list' => is_numeric( $raw_list ) ? (string) $raw_list : $raw_list,
						'tag'  => is_numeric( $raw_tag ) ? (string) $raw_tag : $raw_tag,
					];
				}
			}
		}

		// Build base campaign data — no settings here so boot() applies all FluentCRM defaults.
		$campaign_data = [
			'title' => $campaign_name,
		];

		if ( ! empty( $email_subject ) ) {
			$campaign_data['email_subject'] = $email_subject;
		}

		if ( ! empty( $email_pre_header ) ) {
			$campaign_data['email_pre_header'] = $email_pre_header;
		}

		if ( ! empty( $html_content ) ) {
			$campaign_data['email_body'] = $html_content;
		}

		try {
			$campaign = Campaign::create( $campaign_data );

			if ( ! $campaign ) {
				return [
					'status'  => 'error',
					'message' => __( 'Failed to create campaign.', 'suretriggers' ),
				];
			}

			// Merge subscriber settings into boot() defaults (mailer_settings, template_config etc.)
			// using the same wp_parse_args pattern as FluentCRM's own draftRecipients().
			$subscriber_settings = [
				'subscribers'         => $subscribers,
				'excludedSubscribers' => $excluded_subscribers,
				'sending_filter'      => 'list_tag',
			];
			$campaign->settings  = wp_parse_args( $subscriber_settings, is_array( $campaign->settings ) ? $campaign->settings : [] );
			$campaign->save();

			return [
				'status'          => 'success',
				'message'         => sprintf( __( 'Campaign "%s" created successfully.', 'suretriggers' ), $campaign_name ),
				'campaign_id'     => $campaign->id,
				'campaign_title'  => $campaign->title,
				'campaign_slug'   => $campaign->slug,
				'campaign_status' => $campaign->status,
				'campaign_type'   => isset( $campaign->type ) ? $campaign->type : 'campaign',
				'created_at'      => $campaign->created_at,
			];

		} catch ( Exception $e ) {
			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Error creating campaign: %s', 'suretriggers' ), $e->getMessage() ),
			];
		}
	}

}

CreateCampaign::get_instance();
