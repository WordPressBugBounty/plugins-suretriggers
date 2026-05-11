<?php
/**
 * EventOn integrations file
 *
 * @since 1.0.0
 * @package SureTrigger
 */

namespace SureTriggers\Integrations\EventOn;

use SureTriggers\Controllers\IntegrationsController;
use SureTriggers\Integrations\Integrations;
use SureTriggers\Traits\SingletonLoader;

/**
 * Class EventOn
 *
 * @package SureTriggers\Integrations\EventOn
 */
class EventOn extends Integrations {

	use SingletonLoader;

	/**
	 * ID
	 *
	 * @var string
	 */
	protected $id = 'EventOn';

	/**
	 * SureTrigger constructor.
	 */
	public function __construct() {
		$this->name        = __( 'EventOn', 'suretriggers' );
		$this->description = __( 'Event calendar plugin for WordPress.', 'suretriggers' );
		$this->icon_url    = SURE_TRIGGERS_URL . 'assets/icons/eventon.svg';

		parent::__construct();
	}

	/**
	 * Get event context data.
	 *
	 * @param int $event_id Event Post ID.
	 *
	 * @return array
	 */
	public static function get_event_context( $event_id ) {
		$event_post = get_post( $event_id );
		if ( ! $event_post || 'ajde_events' !== $event_post->post_type ) {
			return [];
		}

		$context = [
			'event_id'          => $event_id,
			'event_title'       => $event_post->post_title,
			'event_description' => $event_post->post_content,
			'event_status'      => $event_post->post_status,
			'event_url'         => get_permalink( $event_id ),
			'featured_image'    => get_the_post_thumbnail_url( $event_id, 'full' ),
		];

		// Event subtitle.
		$subtitle                  = get_post_meta( $event_id, 'evcal_subtitle', true );
		$context['event_subtitle'] = is_string( $subtitle ) ? $subtitle : '';

		// Unix start/end timestamps.
		$unix_start = get_post_meta( $event_id, 'evcal_srow', true );
		$unix_end   = get_post_meta( $event_id, 'evcal_erow', true );

		$context['event_start_unix'] = is_numeric( $unix_start ) ? (int) $unix_start : 0;
		$context['event_end_unix']   = is_numeric( $unix_end ) ? (int) $unix_end : 0;

		if ( is_numeric( $unix_start ) && (int) $unix_start > 0 ) {
			$context['event_start_date'] = gmdate( 'Y-m-d', (int) $unix_start );
			$context['event_start_time'] = gmdate( 'H:i:s', (int) $unix_start );
		} else {
			$context['event_start_date'] = '';
			$context['event_start_time'] = '';
		}

		if ( is_numeric( $unix_end ) && (int) $unix_end > 0 ) {
			$context['event_end_date'] = gmdate( 'Y-m-d', (int) $unix_end );
			$context['event_end_time'] = gmdate( 'H:i:s', (int) $unix_end );
		} else {
			$context['event_end_date'] = '';
			$context['event_end_time'] = '';
		}

		// All day flag.
		$all_day                  = get_post_meta( $event_id, 'evcal_allday', true );
		$context['event_all_day'] = ( 'yes' === $all_day ) ? 'yes' : 'no';

		// Featured / internal status.
		$featured                  = get_post_meta( $event_id, '_featured', true );
		$context['event_featured'] = ( 'yes' === $featured ) ? 'yes' : 'no';

		$internal_status                  = get_post_meta( $event_id, '_status', true );
		$context['event_internal_status'] = is_string( $internal_status ) ? $internal_status : '';

		// Timezone.
		$timezone                  = get_post_meta( $event_id, 'evo_event_timezone', true );
		$context['event_timezone'] = is_string( $timezone ) ? $timezone : '';

		// External / learn more links.
		$exlink                  = get_post_meta( $event_id, 'evcal_exlink', true );
		$context['event_exlink'] = is_string( $exlink ) ? $exlink : '';

		$lmlink                  = get_post_meta( $event_id, 'evcal_lmlink', true );
		$context['event_lmlink'] = is_string( $lmlink ) ? $lmlink : '';

		// Event color.
		$color                  = get_post_meta( $event_id, 'evcal_event_color', true );
		$context['event_color'] = is_string( $color ) ? $color : '';

		// Virtual event.
		$virtual_url                  = get_post_meta( $event_id, '_vir_url', true );
		$context['event_virtual_url'] = is_string( $virtual_url ) ? $virtual_url : '';

		// Location taxonomy.
		$location_terms = wp_get_post_terms( $event_id, 'event_location' );
		if ( is_array( $location_terms ) && ! empty( $location_terms ) ) {
			$first                    = $location_terms[0];
			$context['location_id']   = $first->term_id;
			$context['location_name'] = $first->name;
			$context['location_slug'] = $first->slug;
		}

		// Organizer taxonomy.
		$organizer_terms = wp_get_post_terms( $event_id, 'event_organizer' );
		if ( is_array( $organizer_terms ) && ! empty( $organizer_terms ) ) {
			$first                     = $organizer_terms[0];
			$context['organizer_id']   = $first->term_id;
			$context['organizer_name'] = $first->name;
			$context['organizer_slug'] = $first->slug;
		}

		// Event type taxonomy (primary).
		if ( taxonomy_exists( 'event_type' ) ) {
			$type_terms = wp_get_post_terms( $event_id, 'event_type' );
			if ( is_array( $type_terms ) && ! empty( $type_terms ) ) {
				$type_names = [];
				foreach ( $type_terms as $term ) {
					$type_names[] = $term->name;
				}
				$context['event_type_names'] = implode( ', ', $type_names );
			}
		}

		$author_id                  = (int) $event_post->post_author;
		$context['event_author_id'] = $author_id;

		return $context;
	}

	/**
	 * Is Plugin depended on plugin is installed or not.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		return class_exists( 'EventON' );
	}
}

IntegrationsController::register( EventOn::class );
