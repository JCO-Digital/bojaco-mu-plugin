<?php
/**
 * This disables the users rest API endpoint for not logged in users.
 */

add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( isset( $endpoints['/wp/v2/users'] ) ) {
		unset( $endpoints['/wp/v2/users'] );
	}
	return $endpoints;
}, 10, 1 );
