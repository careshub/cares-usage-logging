<?php
/**
 * CARES site usage logger
 *
 * @package   cares-usage-log-writer
 * @author    dcavins
 * @copyright 2018 CARES
 * @license   GPL v2 or later
 *
 * Plugin Name: CARES Usage Logging
 * Description: Basic logging of database queries, page generation time and memory usage to a shared log.
 * Version:     1.0.0
 * Plugin URI:  https://github.com/careshub/cares-usage-log-writer
 * Author:      David Cavins
 * Text Domain: cares-usage-log-writer
 * Domain Path: /languages/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
add_action( 'shutdown', 'cares_usage_log_record_entry' );
function cares_usage_log_record_entry() {
	if ( ! defined( 'CARES_USAGE_LOG' ) || ! is_writable( CARES_USAGE_LOG ) ) {
		return;
	}

	// Prepare query count and query time.
	$queries = (array) $GLOBALS['wpdb']->queries;
	$query_time = 0;
	foreach ( $queries as $query ) {
		$query_time += $query[1];
	}

	// Prepare memory usage.
	if ( function_exists( 'memory_get_peak_usage' ) ) {
		$memory_usage = memory_get_peak_usage();
	} elseif ( function_exists( 'memory_get_usage' ) ) {
		$memory_usage = memory_get_usage();
	} else {
		$memory_usage = 0;
	}

	// Gather the data for the entry.
	$log_entry = array(
		// User's IP
		$_SERVER['REMOTE_ADDR'],
		// Time now
		date( 'c' ),
		// Site requested
		$_SERVER['HTTP_HOST'],
		// The rest of the request
		$_SERVER['REQUEST_URI'],
		// Page generation time, in seconds
		number_format( microtime( true ) - $GLOBALS['timestart'], 4 ),
		// Memory usage, in KB
		number_format( $memory_usage / 1024 ),
		// Query count
		count( $queries ),
		// Query time, in seconds
		number_format( $query_time, 4 ),
	);

	// Write the entry.
	$fp = fopen( CARES_USAGE_LOG, 'a' );
	if ( $fp ) {
		fputcsv( $fp, $log_entry );
		fclose( $fp );
	}
}
