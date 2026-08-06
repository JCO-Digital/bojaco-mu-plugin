<?php
/**
 * Staging Force Login.
 *
 * Redirects to the login screen always if the user is not logged in and the environment is staging.
 *
 * @package BojacoMUPlugin
 */

add_action(
	'template_redirect',
	function () {
		if ( function_exists( 'wp_get_environment_type' ) && 'staging' === wp_get_environment_type() ) {
			if ( ! is_user_logged_in() ) {
				$login_url   = wp_login_url();
				$current_url = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );

				// Clean up URLs to prevent minor differences from causing redirect loops.
				$login_path   = wp_parse_url( $login_url, PHP_URL_PATH );
				$current_path = wp_parse_url( $current_url, PHP_URL_PATH );

				if ( $login_path !== $current_path ) {
					nocache_headers();
					wp_safe_redirect( wp_login_url( $current_url ) );
					exit;
				}
			}
		}
	}
);
