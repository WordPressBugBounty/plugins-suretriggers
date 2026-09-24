<?php
/**
 * SendEmailByCampaign.
 * php version 5.6
 *
 * @category SendEmailByCampaign
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\FluentCRM\Actions;

use DateTime;
use Exception;
use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * SendEmailByCampaign
 *
 * @category SendEmailByCampaign
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class SendEmailByCampaign extends AutomateAction {


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
	public $action = 'fluentcrm_send_email_by_campaign';

	use SingletonLoader;

	/**
	 * Register a action.
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public function register( $actions ) {

		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Send Email By Campaign', 'suretriggers' ),
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
	 *
	 * @return array|void|mixed
	 *
	 * @throws Exception Exception.
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {

		$username = $selected_options['api_username'];
		$password = $selected_options['api_password'];

		$selected_list = $selected_options['list_id'];
		$selected_tag  = $selected_options['tag_id'];

		$tags_lists[] = [
			'list' => $selected_list,
			'tag'  => $selected_tag,
		];

		if ( is_array( $selected_list ) && is_array( $selected_tag ) ) {
			$max_count  = max( count( $selected_list ), count( $selected_tag ) );
			$tags_lists = [];
			for ( $i = 0; $i < $max_count; $i++ ) {
				$tags_lists[] = [
					'list' => $selected_list[ $i ]['value'] ? $selected_list[ $i ]['value'] : '',
					'tag'  => $selected_tag[ $i ]['value'] ? $selected_tag[ $i ]['value'] : '',
				];
			}
		}

		$header_data = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
		];

		// Check if selected list not exists then return error.
		if ( 'all' != $selected_list ) {
			$args = [
				'headers'   => $header_data,
				'sslverify' => false,
			];
			if ( is_array( $selected_list ) ) {
				$all_lists_request         = wp_remote_get( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/lists/', $args );
				$all_lists_response_body   = wp_remote_retrieve_body( $all_lists_request );
				$all_list_response_context = json_decode( $all_lists_response_body, true );
				if ( is_array( $all_list_response_context ) && ! empty( $all_list_response_context['lists'] ) ) {
					$list_ids       = array_map(
						function ( $list ) {
							return $list['id'];
						},
						$all_list_response_context['lists']
					);
					$filtered_lists = array_filter(
						$selected_list,
						function ( $list ) {
							return 'all' !== $list['value'];
						}
					);
					$ids            = array_column( $filtered_lists, 'value' );
					if ( ! empty( array_diff( $ids, $list_ids ) ) ) {
						return [
							'status'  => 'error',
							'message' => __( "Selected List doesn't exists!!", 'suretriggers' ), 
							
						];
					}
				}
			} else {
				$list_request          = wp_remote_get( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/lists/' . $selected_list, $args );
				$list_response_body    = wp_remote_retrieve_body( $list_request );
				$list_response_context = json_decode( $list_response_body, true );
				if ( '' == $list_response_context ) {
					return [
						'status'  => 'error',
						'message' => __( "Selected List doesn't exists!!", 'suretriggers' ), 
						
					];
				}
			}
		}

		// Check if selected tag not exists then return error.
		if ( 'all' != $selected_tag ) {
			$args = [
				'headers'   => $header_data,
				'sslverify' => false,
			];
			if ( is_array( $selected_tag ) ) {
				$all_tags_request         = wp_remote_get( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/tags/', $args );
				$all_tags_response_body   = wp_remote_retrieve_body( $all_tags_request );
				$all_tag_response_context = json_decode( $all_tags_response_body, true );
				if ( is_array( $all_tag_response_context ) && ! empty( $all_tag_response_context['tags']['data'] ) ) {
					$tag_ids       = array_map(
						function ( $tag ) {
							return $tag['id'];
						},
						$all_tag_response_context['tags']['data']
					);
					$filtered_tags = array_filter(
						$selected_tag,
						function ( $tag ) {
							return 'all' !== $tag['value'];
						}
					);
					$ids           = array_column( $filtered_tags, 'value' );
					if ( ! empty( array_diff( $ids, $tag_ids ) ) ) {
						return [
							'status'  => 'error',
							'message' => __( "Selected Tag doesn't exists!!", 'suretriggers' ), 
							
						];
					}
				}
			} else {
				$tags_response = wp_remote_get( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/tags/' . $selected_tag, $args );
				$tags_body     = wp_remote_retrieve_body( $tags_response );
				$tags_context  = json_decode( $tags_body, true );
				if ( is_array( $tags_context ) && '' == $tags_context['tag'] ) {
					return [
						'status'  => 'error',
						'message' => __( "Selected Tag doesn't exists!!", 'suretriggers' ), 
						
					];
				}
			}
		}
		// Check if selected campaign not exists then create new campaign.
		$args             = [
			'headers'   => $header_data,
			'sslverify' => false,
		];
		$request          = wp_remote_get( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/campaigns/' . $selected_options['campaign'], $args );
		$response_code    = wp_remote_retrieve_response_code( $request );
		$response_body    = wp_remote_retrieve_body( $request );
		$response_context = json_decode( $response_body, true );
		if ( ! empty( $response_context ) && is_array( $response_context ) && isset( $response_context['campaign'] ) ) {
			$response_context = $response_context['campaign'];
		}
		// Track whether this is a pre-existing campaign, so we know below whether it's
		// safe to reset its recipients/status or whether that would resend a live campaign.
		$campaign_existed = ( 404 !== $response_code );
		// Campaign not exists, so create new one.
		if ( 404 === $response_code ) {
			$args = [
				'headers'   => $header_data,
				'sslverify' => false,
				'body'      => wp_json_encode( [ 'title' => $selected_options['campaign'] ] ),
			];
			/**
			 *
			 * Ignore line
			 *
			 * @phpstan-ignore-next-line
			 */
			$new_campaign_request       = wp_remote_post( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/campaigns', $args );
			$response_code              = wp_remote_retrieve_response_code( $new_campaign_request );
			$new_campaign_response_body = wp_remote_retrieve_body( $new_campaign_request );
			$response_context           = json_decode( $new_campaign_response_body, true );
			if ( 200 !== $response_code ) {
				return $response_context;
			}
		} elseif ( 200 !== $response_code ) {
			return $response_context;
		}
		// Refuse to touch a pre-existing campaign that's already past draft (sent/processing/
		// scheduled) — FluentCRM's draft-recipients endpoint would otherwise silently reset it
		// back to draft and wipe its sent-email history, causing a duplicate send on re-run.
		if ( $campaign_existed && is_array( $response_context ) && isset( $response_context['status'] ) && 'draft' !== $response_context['status'] ) {
			return [
				'status'  => 'error',
				'message' => __( 'This campaign is no longer in draft status (it may have already been sent or scheduled) and cannot be modified.', 'suretriggers' ),
			];
		}
		// Prepare email body.
		$args = array_merge(
			$args,
			[
				'method' => 'PUT',
			]
		);
		if ( is_array( $response_context ) ) {
			// Use campaign values if email body, subject, from name, and from email are not provided.
			// Remove backslash escaping from email body to prevent image URLs
			// from being wrapped with %5C%22 (URL-encoded \") in the campaign.
			$email_body = isset( $selected_options['email_body'] ) && ! empty( $selected_options['email_body'] ) ?
				wp_unslash( $selected_options['email_body'] ) : ( isset( $response_context['email_body'] ) ? $response_context['email_body'] : '' );

			$email_subject = isset( $selected_options['email_subject'] ) && ! empty( $selected_options['email_subject'] ) ? 
				$selected_options['email_subject'] : ( isset( $response_context['email_subject'] ) ? $response_context['email_subject'] : '' );
			
			$from_name = isset( $selected_options['from_name'] ) && ! empty( $selected_options['from_name'] ) ? 
				$selected_options['from_name'] : ( isset( $response_context['settings']['mailer_settings']['from_name'] ) ? 
				$response_context['settings']['mailer_settings']['from_name'] : '' );
			
			$from_email = isset( $selected_options['from_email'] ) && ! empty( $selected_options['from_email'] ) ? 
				$selected_options['from_email'] : ( isset( $response_context['settings']['mailer_settings']['from_email'] ) ? 
				$response_context['settings']['mailer_settings']['from_email'] : '' );
			
			$reply_to_name = isset( $selected_options['reply_to_name'] ) && ! empty( $selected_options['reply_to_name'] ) ? 
				$selected_options['reply_to_name'] : ( isset( $response_context['settings']['mailer_settings']['reply_to_name'] ) ? 
				$response_context['settings']['mailer_settings']['reply_to_name'] : '' );
			
			$reply_to_email = isset( $selected_options['reply_to_email'] ) && ! empty( $selected_options['reply_to_email'] ) ? 
				$selected_options['reply_to_email'] : ( isset( $response_context['settings']['mailer_settings']['reply_to_email'] ) ? 
				$response_context['settings']['mailer_settings']['reply_to_email'] : '' );
			
			$args['body'] = wp_json_encode(
				[
					'title'         => $response_context['title'],
					'email_body'    => $email_body,
					'email_subject' => $email_subject,
					'settings'      => [
						'mailer_settings'     => [
							'from_name'      => $from_name,
							'is_custom'      => 'yes',
							'from_email'     => $from_email,
							'reply_to_name'  => $reply_to_name,
							'reply_to_email' => $reply_to_email,
						],
						'subscribers'         => $tags_lists,
						'excludedSubscribers' => null,
						'sending_filter'      => 'list_tag',
						'dynamic_segment'     => [
							'id'   => '',
							'slug' => '',
						],
						'advanced_filters'    => [
							[],
						],
					],
				]
			);
		}
		/**
		 *
		 * Ignore line
		 *
		 * @phpstan-ignore-next-line
		 */
		$settings_request       = wp_remote_request( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/campaigns/' . $response_context['id'], $args );
		$settings_response_code = wp_remote_retrieve_response_code( $settings_request );
		$settings_response_body = wp_remote_retrieve_body( $settings_request );
		$settings_context       = json_decode( $settings_response_body, true );
		if ( 200 !== $settings_response_code ) {
			return $settings_context;
		}
		if ( ! empty( $args['body'] ) ) {
			$args = [
				'headers'   => $header_data,
				'sslverify' => false,
				'body'      => $args['body'],
			];
		}
		$campaign_id = '';
		if ( is_array( $settings_context ) && isset( $settings_context['campaign'] ) && is_array( $settings_context['campaign'] ) && isset( $settings_context['campaign']['id'] ) ) {
			$raw_campaign_id = $settings_context['campaign']['id'];
			if ( is_string( $raw_campaign_id ) || is_numeric( $raw_campaign_id ) ) {
				$campaign_id = (string) $raw_campaign_id;
			}
		}
		$contact_body_data = [
			'subscribers'    => $tags_lists,
			'sending_filter' => 'list_tag',
		];
		$contact_body      = wp_json_encode( $contact_body_data );
		if ( $contact_body ) {
			$check_estimated_contacts = wp_remote_post(
				$selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/campaigns/estimated-contacts',
				[
					'headers'   => $header_data,
					'sslverify' => false,
					'body'      => $contact_body,
				]
			);
			$contacts                 = wp_remote_retrieve_body( $check_estimated_contacts );
			$contacts_context         = json_decode( $contacts, true );
			if ( is_array( $contacts_context ) && 0 == $contacts_context['count'] ) {
				return [
					'status'  => 'error',
					'message' => __( 'No contacts found based on your selection!!', 'suretriggers' ),

				];
			}
			// Persist the recipients selection on the campaign so FluentCRM's schedule
			// endpoint has a recipients_count to check; without this call the campaign
			// remains in Draft status and scheduling is rejected as "no recipients".
			$draft_recipients_request = wp_remote_post(
				$selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/campaigns/' . $campaign_id . '/draft-recipients',
				[
					'headers'   => $header_data,
					'sslverify' => false,
					'body'      => $contact_body,
				]
			);
			$draft_recipients_code    = wp_remote_retrieve_response_code( $draft_recipients_request );
			$draft_recipients_body    = wp_remote_retrieve_body( $draft_recipients_request );
			$draft_recipients_context = json_decode( $draft_recipients_body, true );
			if ( 200 !== $draft_recipients_code ) {
				return [
					'status'  => 'error',
					'message' => is_array( $draft_recipients_context ) && isset( $draft_recipients_context['message'] ) ? $draft_recipients_context['message'] : __( 'Failed to set campaign recipients.', 'suretriggers' ),
				];
			}
		}
		/**
		 *
		 * Ignore line
		 *
		 * @phpstan-ignore-next-line
		 */
		$final_request       = wp_remote_post( $selected_options['wordpress_url'] . '/wp-json/fluent-crm/v2/campaigns/' . $campaign_id . '/schedule', $args );
		$final_response_code = wp_remote_retrieve_response_code( $final_request );
		$final_response_body = wp_remote_retrieve_body( $final_request );
		$final_context       = json_decode( $final_response_body, true );
		if ( is_wp_error( $final_request ) ) {
			// Keep the original WP_Error errors shape intact (for anything already reading it)
			// and additionally flag 'status' so failures are no longer reported as a success.
			$error_result           = $final_request->errors;
			$error_result['status'] = 'error';
			return $error_result;
		}
		if ( 200 !== $final_response_code ) {
			// Preserve whatever body FluentCRM returned (e.g. its own 'message') and just
			// flag it as an error instead of replacing the payload.
			$error_result = is_array( $final_context ) ? $final_context : [];
			if ( ! isset( $error_result['message'] ) ) {
				$error_result['message'] = __( 'Failed to send campaign.', 'suretriggers' );
			}
			$error_result['status'] = 'error';
			return $error_result;
		}
		// Preserve the original success payload (campaign, message, current_timestamp, …) as-is
		// and only add 'status'/'campaign_id' on top, so existing field mappings keep working.
		$success_result           = is_array( $final_context ) ? $final_context : [];
		$success_result['status'] = 'success';
		if ( ! isset( $success_result['campaign_id'] ) ) {
			$success_result['campaign_id'] = $campaign_id;
		}
		return $success_result;
	}

}

SendEmailByCampaign::get_instance();
