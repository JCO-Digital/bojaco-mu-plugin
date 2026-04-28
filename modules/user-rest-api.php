<?php
/**
 * This disables the users rest API endpoint for not logged in users.
 */

/**
 * Restrict user endpoints from REST API for non-logged in users.
 *
 * @param mixed           $result  Response to replace the requested version with. Can be anything a normal endpoint can return, or null to continue dispatching the request.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request used to generate the response.
 *
 * @return mixed WP_Error if access is denied, otherwise the original result.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		// Check if the request is for the users endpoints.
		if ( strpos( $request->get_route(), '/wp/v2/users' ) === 0 ) {
			// If the user is not logged in, return a forbidden error.
			if ( ! is_user_logged_in() ) {
				return new WP_Error(
					'rest_forbidden',
					__( 'You must be logged in to access user data.' ),
					array( 'status' => 401 )
				);
			}
		}

		return $result;
	},
	10,
	3
);
