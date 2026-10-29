<?php
/**
 * ExportBookings.
 * php version 5.6
 *
 * @category ExportBookings
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */

namespace SureTriggers\Integrations\WPTravelEngine\Actions;

use SureTriggers\Integrations\AutomateAction;
use SureTriggers\Traits\SingletonLoader;

/**
 * ExportBookings
 *
 * @category ExportBookings
 * @package  SureTriggers
 * @author   BSF <username@example.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://www.brainstormforce.com/
 * @since    1.0.0
 */
class ExportBookings extends AutomateAction {

	use SingletonLoader;

	/**
	 * Integration type.
	 *
	 * @var string
	 */
	public $integration = 'WPTravelEngine';

	/**
	 * Action name.
	 *
	 * @var string
	 */
	public $action = 'wte_export_bookings';

	/**
	 * Maximum number of bookings exported in one run.
	 *
	 * @var int
	 */
	const MAX_BOOKINGS = 5000;

	/**
	 * Bookings fetched per query while collecting rows.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 500;

	/**
	 * Maximum number of bookings examined in one run, so a filter that matches
	 * little or nothing cannot scan an entire large site.
	 *
	 * @var int
	 */
	const MAX_SCAN = 20000;

	/**
	 * Columns every export starts with, so an export with no bookings still has a header.
	 *
	 * @var array<int,string>
	 */
	const BASE_COLUMNS = [
		'Booking ID',
		'Booking Status',
		'Trip Name',
		'Total Cost',
		'Total Paid',
		'Payment Gateway',
		'No. of Travellers',
		'Booking Date',
		'Trip Date',
	];

	/**
	 * Cron hook that deletes a generated export.
	 *
	 * @var string
	 */
	const DELETE_HOOK = 'suretriggers_wte_delete_export';

	/**
	 * Register the action.
	 *
	 * @param array $actions Actions array.
	 * @return array
	 */
	public function register( $actions ) {
		$actions[ $this->integration ][ $this->action ] = [
			'label'    => __( 'Export Bookings', 'suretriggers' ),
			'action'   => $this->action,
			'function' => [ $this, '_action_listener' ],
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
	 * @return array
	 */
	public function _action_listener( $user_id, $automation_id, $fields, $selected_options ) {
		if ( ! defined( 'WP_TRAVEL_ENGINE_VERSION' ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'WP Travel Engine plugin is not active.', 'suretriggers' ),
			];
		}

		// Large exports on busy sites can exceed the default limits.
		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); //phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Long-running export.
		}

		$start_date = ! empty( $selected_options['start_date'] ) ? sanitize_text_field( $selected_options['start_date'] ) : '';
		$end_date   = ! empty( $selected_options['end_date'] ) ? sanitize_text_field( $selected_options['end_date'] ) : '';
		$status     = ! empty( $selected_options['booking_status'] ) ? sanitize_text_field( $selected_options['booking_status'] ) : 'all';
		$trip_id    = ! empty( $selected_options['trip_id'] ) ? absint( $selected_options['trip_id'] ) : 0;

		foreach ( [ $start_date, $end_date ] as $date ) {
			if ( '' !== $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return [
					'status'  => 'error',
					'message' => __( 'Dates must be in YYYY-MM-DD format.', 'suretriggers' ),
				];
			}
		}

		$args = [
			'post_type'      => 'booking',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => self::BATCH_SIZE, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Batched export.
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		if ( '' !== $start_date || '' !== $end_date ) {
			$date_query = [ 'inclusive' => true ];
			if ( '' !== $start_date ) {
				$date_query['after'] = $start_date;
			}
			if ( '' !== $end_date ) {
				$date_query['before'] = $end_date . ' 23:59:59';
			}
			$args['date_query'] = [ $date_query ];
		}

		if ( 'all' !== $status && '' !== $status ) {
			$args['meta_query'] = [
				[
					'key'   => 'wp_travel_engine_booking_status',
					'value' => $status,
				],
			];
		}

		// Page through bookings so the trip filter is applied before the cap.
		$rows      = [];
		$page      = 1;
		$scanned   = 0;
		$truncated = false;
		do {
			$args['paged'] = $page;
			$booking_ids   = [];
			foreach ( ( new \WP_Query( $args ) )->posts as $post ) {
				$booking_ids[] = $post instanceof \WP_Post ? $post->ID : (int) $post;
			}
			$fetched  = count( $booking_ids );
			$scanned += $fetched;
			if ( $fetched ) {
				// One query per batch instead of one per booking.
				update_meta_cache( 'post', $booking_ids );
			}
			foreach ( $booking_ids as $booking_id ) {
				$row = $this->build_row( $booking_id, $trip_id );
				if ( null !== $row ) {
					$rows[] = $row;
					if ( count( $rows ) >= self::MAX_BOOKINGS ) {
						break 2;
					}
				}
			}
			if ( self::BATCH_SIZE === $fetched && $scanned >= self::MAX_SCAN ) {
				$truncated = true;
				break;
			}
			$page++;
		} while ( self::BATCH_SIZE === $fetched );

		$file = $this->write_csv( $rows );
		if ( is_wp_error( $file ) ) {
			return [
				'status'  => 'error',
				'message' => $file->get_error_message(),
			];
		}

		// Booking rows contain personal data, so only the file details are returned, not the rows.
		$response = [
			'status'         => 'success',
			'message'        => __( 'Bookings exported successfully.', 'suretriggers' ),
			'file_url'       => $file['url'],
			'file_name'      => $file['name'],
			'total_exported' => count( $rows ),
			'truncated'      => $truncated || count( $rows ) >= self::MAX_BOOKINGS,
		];
		if ( ! $file['scheduled'] ) {
			$response['warning'] = __( 'Automatic deletion of the export could not be scheduled. The file will be removed on the next export.', 'suretriggers' );
		}
		return $response;
	}

	/**
	 * Build one export row for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $trip_id    Trip filter (0 for all).
	 * @return array<string,string>|null Null when the booking is filtered out.
	 */
	private function build_row( $booking_id, $trip_id ) {
		$cart_info = $this->to_array( get_post_meta( $booking_id, 'cart_info', true ) );
		$items     = isset( $cart_info['items'] ) ? $this->to_array( $cart_info['items'] ) : [];

		// With a trip filter only the matching cart items describe this row; otherwise all of them do.
		$relevant = [];
		foreach ( $items as $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}
			if ( $trip_id && ( ! isset( $cart_item['trip_id'] ) || absint( $cart_item['trip_id'] ) !== $trip_id ) ) {
				continue;
			}
			$relevant[] = $cart_item;
		}
		if ( $trip_id && empty( $relevant ) ) {
			return null;
		}

		$trip_names = [];
		$trip_dates = [];
		$travellers = 0;
		foreach ( $relevant as $item ) {
			$item_trip_id = ( isset( $item['trip_id'] ) && is_numeric( $item['trip_id'] ) ) ? absint( $item['trip_id'] ) : 0;
			$trip_name    = $item_trip_id ? get_the_title( $item_trip_id ) : '';
			if ( '' !== $trip_name && ! in_array( $trip_name, $trip_names, true ) ) {
				$trip_names[] = $trip_name;
			}
			if ( isset( $item['datetime'] ) ) {
				$trip_dates[] = $this->to_text( $item['datetime'] );
			}
			if ( isset( $item['pax'] ) && is_array( $item['pax'] ) ) {
				foreach ( $item['pax'] as $count ) {
					$travellers += is_numeric( $count ) ? (int) $count : 0;
				}
			}
		}

		$gateway = get_post_meta( $booking_id, 'wp_travel_engine_booking_payment_gateway', true );

		// Same fallback as WP Travel Engine: bookings without a status meta use the post status.
		$booking_status = $this->to_text( get_post_meta( $booking_id, 'wp_travel_engine_booking_status', true ) );
		if ( '' === $booking_status ) {
			$booking_status = (string) get_post_status( $booking_id );
		}

		// Current carts keep the total under "totals", older ones at the top level.
		$totals     = isset( $cart_info['totals'] ) ? $this->to_array( $cart_info['totals'] ) : [];
		$total_cost = isset( $totals['total'] ) ? $this->to_text( $totals['total'] ) : ( isset( $cart_info['total'] ) ? $this->to_text( $cart_info['total'] ) : '' );

		$row = [
			'Booking ID'        => (string) $booking_id,
			'Booking Status'    => $booking_status,
			'Trip Name'         => implode( ', ', $trip_names ),
			'Total Cost'        => $total_cost,
			'Total Paid'        => $this->get_total_paid( $booking_id ),
			'Payment Gateway'   => 'N/A' === $gateway ? '' : $this->to_text( $gateway ),
			'No. of Travellers' => (string) $travellers,
			'Booking Date'      => (string) get_post_time( 'Y-m-d', false, $booking_id ),
			'Trip Date'         => implode( ', ', array_filter( $trip_dates ) ),
		];

		foreach ( $this->flatten_section( 'Billing', get_post_meta( $booking_id, 'wptravelengine_billing_details', true ) ) as $key => $value ) {
			$row[ $key ] = $value;
		}

		$travelers = $this->to_array( get_post_meta( $booking_id, 'wptravelengine_travelers_details', true ) );
		$index     = 1;
		foreach ( $travelers as $traveler ) {
			foreach ( $this->flatten_section( 'Traveler ' . $index, $traveler ) as $key => $value ) {
				$row[ $key ] = $value;
			}
			$index++;
		}

		$index = 1;
		foreach ( $this->get_emergency_contacts( $booking_id ) as $contact ) {
			foreach ( $this->flatten_section( 'Emergency Contact ' . $index, $contact ) as $key => $value ) {
				$row[ $key ] = $value;
			}
			$index++;
		}

		$row['Additional Notes'] = $this->to_text( get_post_meta( $booking_id, 'wptravelengine_additional_note', true ) );

		return $row;
	}

	/**
	 * Emergency contacts as a list, mirroring WP Travel Engine's own handling:
	 * a single contact is stored flat, several as a list, and old bookings in a legacy setting.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<int,mixed>
	 */
	private function get_emergency_contacts( $booking_id ) {
		$contacts = $this->to_array( get_post_meta( $booking_id, 'wptravelengine_emergency_details', true ) );
		if ( empty( $contacts ) ) {
			$legacy   = $this->to_array( get_post_meta( $booking_id, 'wp_travel_engine_placeorder_setting', true ) );
			$place    = isset( $legacy['place_order'] ) ? $this->to_array( $legacy['place_order'] ) : [];
			$contacts = isset( $place['relation'] ) ? $this->to_array( $place['relation'] ) : [];
		}
		if ( empty( $contacts ) ) {
			return [];
		}
		return isset( $contacts[0] ) ? array_values( $contacts ) : [ $contacts ];
	}

	/**
	 * Total paid for a booking. Uses WP Travel Engine's own calculation (sum of payments)
	 * when available, falling back to the stored meta.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	private function get_total_paid( $booking_id ) {
		$class = 'WPTravelEngine\Core\Models\Post\Booking';
		if ( class_exists( $class ) ) {
			try {
				$booking  = new $class( $booking_id );
				$callback = [ $booking, 'get_total_paid_amount' ];
				if ( is_callable( $callback ) ) {
					return $this->to_text( $callback() );
				}
			} catch ( \Exception $e ) {
				// Fall through to the stored meta.
				unset( $e );
			}
		}
		return $this->to_text( get_post_meta( $booking_id, 'paid_amount', true ) );
	}

	/**
	 * Flatten an associative section into "Prefix - Field label" => text pairs.
	 *
	 * @param string $prefix Column prefix.
	 * @param mixed  $data   Section data (array or serialized array).
	 * @return array<string,string>
	 */
	private function flatten_section( $prefix, $data ) {
		$data = $this->to_array( $data );
		$out  = [];
		foreach ( $data as $field => $value ) {
			$label                           = ucwords( str_replace( '_', ' ', (string) $field ) );
			$out[ $prefix . ' - ' . $label ] = $this->to_text( $value );
		}
		return $out;
	}

	/**
	 * Normalise a meta value (array or serialized array) to an array.
	 *
	 * @param mixed $value Value.
	 * @return array<mixed>
	 */
	private function to_array( $value ) {
		if ( is_string( $value ) ) {
			$value = maybe_unserialize( $value );
		}
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Convert any meta value to a plain string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function to_text( $value ) {
		if ( is_array( $value ) ) {
			$parts = [];
			foreach ( $value as $item ) {
				$parts[] = $this->to_text( $item );
			}
			return implode( ', ', $parts );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Write rows to a CSV file in the uploads directory.
	 *
	 * @param array<int,array<string,string>> $rows Rows.
	 * @return array{url:string,name:string,scheduled:bool}|\WP_Error
	 */
	private function write_csv( $rows ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'wte_export_upload_dir', __( 'Unable to access the uploads directory.', 'suretriggers' ) );
		}

		$dir = trailingslashit( $upload['basedir'] ) . 'suretriggers-exports';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'wte_export_mkdir', __( 'Unable to create the export directory.', 'suretriggers' ) );
		}
		// Block directory listing.
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		}

		// Stop CDNs and browsers from caching the export where the server supports it.
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_headers.c>\nHeader set Cache-Control \"private, no-store\"\n</IfModule>\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		}

		// Remove exports older than an hour so files don't accumulate.
		$old_files = glob( $dir . '/wte-bookings-*.csv' );
		if ( is_array( $old_files ) ) {
			foreach ( $old_files as $old_file ) {
				if ( filemtime( $old_file ) < time() - HOUR_IN_SECONDS ) {
					wp_delete_file( $old_file );
				}
			}
		}

		$header = self::BASE_COLUMNS;
		foreach ( $rows as $row ) {
			foreach ( array_keys( $row ) as $column ) {
				if ( ! in_array( $column, $header, true ) ) {
					$header[] = $column;
				}
			}
		}
		// Keep the notes column last regardless of discovery order.
		$notes_pos = array_search( 'Additional Notes', $header, true );
		if ( false !== $notes_pos ) {
			unset( $header[ $notes_pos ] );
			$header[] = 'Additional Notes';
			$header   = array_values( $header );
		}

		$name = 'wte-bookings-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 12, false ) . '.csv';
		$path = $dir . '/' . $name;

		$handle = fopen( $path, 'w' ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new \WP_Error( 'wte_export_write', __( 'Unable to write the export file.', 'suretriggers' ) );
		}

		if ( ! empty( $header ) ) {
			fputcsv( $handle, array_map( [ $this, 'escape_csv_cell' ], $header ), ',', '"', '\\' ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			foreach ( $rows as $row ) {
				$line = [];
				foreach ( $header as $column ) {
					$line[] = $this->escape_csv_cell( isset( $row[ $column ] ) ? $row[ $column ] : '' );
				}
				fputcsv( $handle, $line, ',', '"', '\\' ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			}
		}
		fclose( $handle ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// Delete this export shortly after it is created, whether or not another export ever runs.
		$scheduled = false !== wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::DELETE_HOOK, [ $name ] );

		return [
			'url'       => trailingslashit( $upload['baseurl'] ) . 'suretriggers-exports/' . $name,
			'name'      => $name,
			'scheduled' => $scheduled,
		];
	}

	/**
	 * Delete a generated export file (cron callback).
	 *
	 * @param string $name Export file name.
	 * @return void
	 */
	public static function delete_export( $name ) {
		$name = basename( (string) $name );
		if ( 0 !== strpos( $name, 'wte-bookings-' ) || '.csv' !== substr( $name, -4 ) ) {
			return;
		}
		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['basedir'] ) . 'suretriggers-exports/' . $name;
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Neutralise spreadsheet formula injection in user-supplied cells.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private function escape_csv_cell( $value ) {
		if ( '' === $value || is_numeric( $value ) ) {
			return $value;
		}
		if ( in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $value;
		}
		return $value;
	}
}

// Registered whenever the integration loads (WP Travel Engine active); deletion is skipped if that plugin is deactivated first.
add_action( ExportBookings::DELETE_HOOK, [ ExportBookings::class, 'delete_export' ] );
ExportBookings::get_instance();
