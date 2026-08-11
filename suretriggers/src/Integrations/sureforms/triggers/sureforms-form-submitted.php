<?php
/**
 * SureFormsFormSubmitted.
 * php version 5.6
 *
 * @category SureFormsFormSubmitted
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\SureForms\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'SureFormsFormSubmitted' ) ) :

	/**
	 * SureFormsFormSubmitted
	 *
	 * @category SureFormsFormSubmitted
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 *
	 * @psalm-suppress UndefinedTrait
	 */
	class SureFormsFormSubmitted {


		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'SureForms';


		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'sureforms_form_submitted';

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
		 * Register action.
		 *
		 * @param array $triggers trigger data.
		 * @return array
		 */
		public function register( $triggers ) {

			$triggers[ $this->integration ][ $this->trigger ] = [
				'label'         => __( 'Form Submitted', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'srfm_form_submit',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 1,
			];
			return $triggers;

		}

		/**
		 * Trigger listener
		 *
		 * @param array $response Response Data.
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function trigger_listener( $response ) {

			if ( empty( $response ) ) {
				return;
			}

			// If entry_id exists, fetch PDF links from the entry extras.
			if ( ! empty( $response['entry_id'] ) && class_exists( '\SRFM\Inc\Database\Tables\Entries' ) ) {
				$entry = \SRFM\Inc\Database\Tables\Entries::get( intval( $response['entry_id'] ) );
				if ( ! empty( $entry['extras']['pdf_links'] ) && is_array( $entry['extras']['pdf_links'] ) ) {
					$response['pdf_links'] = $entry['extras']['pdf_links'];
				}
			}

			if ( ! empty( $response['message'] ) && is_string( $response['message'] ) ) {
				$trimmed_message = $this->trim_large_message( $response['message'] );
				if ( null === $trimmed_message ) {
					unset( $response['message'] );
				} else {
					$response['message'] = $trimmed_message;
				}
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger'    => $this->trigger,
					'wp_user_id' => ap_get_current_user_id(),
					'context'    => $response,
				]
			);
		}

		/**
		 * Trim inline base64 media from the confirmation message before it is sent to the SaaS,
		 * as authors pasting images directly into the SureForms success message inflate it enough
		 * to fail SaaS-side processing. Falls back to dropping the whole field if it is still oversized.
		 *
		 * @param string $message Confirmation message markup.
		 * @since 1.0.0
		 *
		 * @return string|null Trimmed message, or null if it should be dropped entirely.
		 */
		public function trim_large_message( $message ) {
			$message = (string) preg_replace(
				'/src=(["\'])(?:data:)?[a-zA-Z0-9\/+.-]+;base64,[A-Za-z0-9+\/=]+\1/i',
				'src=$1$1',
				$message
			);

			/**
			 * Filters the maximum allowed size (in bytes) of the SureForms confirmation message
			 * sent as trigger context. If the message still exceeds this after stripping inline
			 * base64 media, it is dropped entirely rather than sent.
			 *
			 * @param int $max_size Maximum size in bytes. Default 30720 (30KB).
			 */
			$max_size = apply_filters( 'sure_trigger_sureforms_max_message_size', 30720 );

			if ( strlen( $message ) > $max_size ) {
				return null;
			}

			return $message;
		}
	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	SureFormsFormSubmitted::get_instance();

endif;
