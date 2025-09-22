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
 * Version:     1.1.0
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
	$output_type = 'csv';
	if ( defined( 'CARES_USAGE_LOG_USE_ELASTICSEARCH' ) && CARES_USAGE_LOG_USE_ELASTICSEARCH ) {
		$output_type = 'elastic';
	}

	// If writing to a CSV, but we can't, bail.
	if ( 'csv' === $output_type && ( ! defined( 'CARES_USAGE_LOG' ) || ! is_writable( CARES_USAGE_LOG ) ) ) {
		return;
	}

	// If we don't want to track anonymous traffic, and this visit is anonymous, bail.
	if ( defined( 'CARES_USAGE_LOG_IGNORE_ANON' ) && CARES_USAGE_LOG_IGNORE_ANON && ! is_user_logged_in() ) {
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

	$start_time = $GLOBALS['timestart'] ?? microtime( true );

	// Gather the data for the entry.
	$log_entry = array(
		// User's IP
		'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? false,
		// Time now
		'@timestamp' => date( 'c' ),
		// Site requested
		'host' => $_SERVER['HTTP_HOST'] ?? false,
		// The rest of the request
		'url' => $_SERVER['REQUEST_URI'] ?? false,
		// URL parameters
		'post_action' => $_POST['action'] ?? false,
		// Page generation time, in seconds
		'page_gen_milliseconds' => (float) number_format( microtime( true ) - $start_time, 4, ".", "" ),
		// Memory usage, in KB
		'page_gen_memory_kb' => (int) number_format( $memory_usage / 1024, 0, ".", "" ),
		// Query count
		'page_gen_queries' => count( $queries ),
		// Query time, in seconds
		'page_gen_query_time_seconds' => (float) number_format( $query_time, 4, ".", "" ),
	);

	if ( 'csv' === $output_type ) {
		// Write the entry.
		$fp = fopen( CARES_USAGE_LOG, 'a' );
		if ( $fp ) {
			fputcsv( $fp, array_values( $log_entry ) );
			fclose( $fp );
		}
	} else {
		// Send the entry to ElasticSearch
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded_data = wp_json_encode( $log_entry );
		} else {
			$encoded_data = json_encode( $log_entry );
		}

		$request_args = array(
			'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
			'body'    => $encoded_data,
			'method'  => 'POST',
			'timeout' => 15,
		);

		$request = wp_remote_request( 'http://localhost:9200/cares_shared_requests/_doc', $request_args );

		$request_response_code = (int) wp_remote_retrieve_response_code( $request );
		$is_valid_res = ( $request_response_code >= 200 && $request_response_code <= 299 );
		if ( false === $request || is_wp_error( $request ) || ! $is_valid_res ) {
			error_log( 'ElasticSearch POST failed: ' . $encoded_data );
		}
	}
}
