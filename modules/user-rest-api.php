<?php
/**
 * This disables the users rest API endpoint for not logged in users.
 */

/**
 * Remove user endpoints from REST API for non-logged in users.
 *
 * @param array $endpoints The REST API endpoints.
 *
 * @return array The modified REST API endpoints.
 */
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( is_user_logged_in() || ! is_array( $endpoints ) ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	},
	10,
	1
);
