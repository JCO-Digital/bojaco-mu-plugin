<?php
/**
 * Disable Password Reset.
 *
 * Disables password resets on non-production sites (environment type staging,
 * development or local), so that a copied database can't be used to send reset
 * emails to real users or to take over their accounts.
 *
 * The `BOJACO_DISABLE_PASSWORD_RESET` constant overrides the environment check:
 * `true` disables password resets on any site, `false` keeps them enabled.
 *
 * @package BojacoMUPlugin
 */

if ( defined( 'BOJACO_DISABLE_PASSWORD_RESET' ) ) {
	$bojaco_disable_password_reset = (bool) BOJACO_DISABLE_PASSWORD_RESET;
} else {
	$bojaco_disable_password_reset = function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'staging', 'development', 'local' ), true );
}

if ( $bojaco_disable_password_reset ) {
	/**
	 * Block password reset key generation. This covers the lost password form, the
	 * "Send password reset" action in the admin and new user notification emails.
	 */
	add_filter( 'allow_password_reset', '__return_false' );

	/**
	 * Hide the "Lost your password?" link on the login screen.
	 */
	add_filter( 'lost_password_html_link', '__return_empty_string' );

	/**
	 * Block the password reset screens on wp-login.php, so that reset links created
	 * before the module was enabled (or copied from production) can't be used either.
	 */
	foreach ( array( 'lostpassword', 'retrievepassword', 'resetpass', 'rp' ) as $bojaco_action ) {
		add_action(
			'login_form_' . $bojaco_action,
			function () {
				wp_safe_redirect( wp_login_url() );
				exit;
			}
		);
	}
	unset( $bojaco_action );
}
