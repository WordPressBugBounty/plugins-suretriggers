<?php
/**
 * EventTimesSaved.
 * php version 5.6
 *
 * @category EventTimesSaved
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\EventsManager\Triggers;

use SureTriggers\Controllers\AutomationController;
use SureTriggers\Integrations\WordPress\WordPress;
use SureTriggers\Traits\SingletonLoader;

if ( ! class_exists( 'EventTimesSaved' ) ) :

	/**
	 * EventTimesSaved
	 *
	 * @category EventTimesSaved
	 * @package  SureTriggers
	 * @author   BSF <username@example.com>
	 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
	 * @link     https://www.brainstormforce.com/
	 * @since    1.0.0
	 */
	class EventTimesSaved {


		/**
		 * Integration type.
		 *
		 * @var string
		 */
		public $integration = 'EventsManager';

		/**
		 * Trigger name.
		 *
		 * @var string
		 */
		public $trigger = 'em_event_times_saved';

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
				'label'         => __( 'Event Times Updated', 'suretriggers' ),
				'action'        => $this->trigger,
				'common_action' => 'em_event_save',
				'function'      => [ $this, 'trigger_listener' ],
				'priority'      => 10,
				'accepted_args' => 2,
			];

			return $triggers;

		}


		/**
		 * Trigger listener
		 *
		 * @param bool   $result   Whether the event was saved successfully.
		 * @param object $em_event The EM_Event object.
		 *
		 * @return bool The original result to preserve the filter chain.
		 */
		public function trigger_listener( $result, $em_event ) {
			if ( ! $result ) {
				return $result;
			}

			if ( ! is_object( $em_event ) || ! property_exists( $em_event, 'event_id' ) || ! property_exists( $em_event, 'post_id' ) ) {
				return $result;
			}

			global $wpdb;

			$event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}em_events WHERE event_id = %d", $em_event->event_id ) );

			if ( empty( $event ) ) {
				return $result;
			}

			$context = (array) json_decode( (string) wp_json_encode( $event ), true );

			if ( ! empty( $event->location_id ) ) {
				$location = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}em_locations WHERE location_id = %d", $event->location_id ) );
				if ( ! empty( $location ) ) {
					$context = array_merge( $context, (array) $location );
				}
			}

			$context['post_id'] = $event->post_id;

			if ( ! empty( $event->event_owner ) ) {
				$context = array_merge(
					WordPress::get_user_context( (int) $event->event_owner ),
					$context
				);
			}

			$timeranges = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}em_timeranges WHERE timerange_group_id = %s ORDER BY timerange_start ASC", 'event_' . $em_event->event_id ) );

			if ( ! empty( $timeranges ) ) {
				$context['timeranges_count'] = count( $timeranges );
				foreach ( $timeranges as $index => $timerange ) {
					$num                                       = $index + 1;
					$context[ 'timerange_' . $num . '_start' ] = $timerange->timerange_start;
					$context[ 'timerange_' . $num . '_end' ]   = $timerange->timerange_end;
					$context[ 'timerange_' . $num . '_all_day' ] = $timerange->timerange_all_day;
				}
			}

			AutomationController::sure_trigger_handle_trigger(
				[
					'trigger' => $this->trigger,
					'context' => $context,
				]
			);

			return $result;
		}

	}

	/**
	 * Ignore false positive
	 *
	 * @psalm-suppress UndefinedMethod
	 */
	EventTimesSaved::get_instance();

endif;
