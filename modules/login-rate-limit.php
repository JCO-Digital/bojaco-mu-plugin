<?php
/**
 * Login Rate Limit.
 *
 * Locks out an IP address from logging in (wp-login.php, XML-RPC and anything else
 * that goes through wp_authenticate()) after too many failed attempts.
 *
 * Settings can be changed with the `bojaco_login_rate_limit_settings` filter, and the
 * client IP resolution with the `bojaco_login_rate_limit_ip` filter (e.g. to trust a
 * proxy header such as X-Forwarded-For behind a known load balancer).
 *
 * @package BojacoMUPlugin
 */

/**
 * Get the rate limit settings.
 *
 * @return array{max_attempts: int, window: int, lockout: int}
 */
function bojaco_login_rate_limit_settings() {
	$settings = apply_filters(
		'bojaco_login_rate_limit_settings',
		array(
			'max_attempts' => 5,                    // Failed attempts allowed within the window.
			'window'       => 15 * MINUTE_IN_SECONDS, // Window in which failed attempts are counted.
			'lockout'      => 15 * MINUTE_IN_SECONDS, // How long the IP is locked out.
		)
	);

	return array(
		'max_attempts' => max( 1, (int) $settings['max_attempts'] ),
		'window'       => max( 1, (int) $settings['window'] ),
		'lockout'      => max( 1, (int) $settings['lockout'] ),
	);
}

/**
 * Get the transient key for the current client IP.
 *
 * Only REMOTE_ADDR is used by default, since proxy headers can be spoofed by the client.
 *
 * @return string
 */
function bojaco_login_rate_limit_key() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only hashed, never output.
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	$ip = (string) apply_filters( 'bojaco_login_rate_limit_ip', $ip );

	return 'bojaco_login_rl_' . md5( $ip );
}

/**
 * Get the current rate limit state for the client IP.
 *
 * @return array{count: int, first: int, locked_until: int}
 */
function bojaco_login_rate_limit_state() {
	$state = get_transient( bojaco_login_rate_limit_key() );

	if ( ! is_array( $state ) ) {
		$state = array();
	}

	return array(
		'count'        => isset( $state['count'] ) ? (int) $state['count'] : 0,
		'first'        => isset( $state['first'] ) ? (int) $state['first'] : 0,
		'locked_until' => isset( $state['locked_until'] ) ? (int) $state['locked_until'] : 0,
	);
}

/**
 * Block authentication while the client IP is locked out.
 *
 * Runs after core authentication so it also rejects correct credentials during a
 * lockout, which prevents the lockout from being used as a password oracle.
 */
add_filter(
	'authenticate',
	function ( $user ) {
		$state = bojaco_login_rate_limit_state();

		if ( $state['locked_until'] > time() ) {
			$minutes = (int) ceil( ( $state['locked_until'] - time() ) / MINUTE_IN_SECONDS );

			return new WP_Error(
				'bojaco_too_many_attempts',
				sprintf(
					/* translators: %d: number of minutes. */
					_n(
						'<strong>Error:</strong> Too many failed login attempts. Please try again in %d minute.',
						'<strong>Error:</strong> Too many failed login attempts. Please try again in %d minutes.',
						$minutes
					),
					$minutes
				)
			);
		}

		return $user;
	},
	99
);

/**
 * Count failed login attempts and lock out the IP when the limit is reached.
 */
add_action(
	'wp_login_failed',
	function ( $username, $error = null ) {
		// Attempts made during a lockout don't extend it.
		if ( $error instanceof WP_Error && $error->get_error_code() === 'bojaco_too_many_attempts' ) {
			return;
		}

		$settings = bojaco_login_rate_limit_settings();
		$state    = bojaco_login_rate_limit_state();
		$now      = time();

		if ( $state['locked_until'] > $now ) {
			return;
		}

		// Start a new window if the previous one has expired.
		if ( $state['first'] + $settings['window'] < $now ) {
			$state = array(
				'count'        => 0,
				'first'        => $now,
				'locked_until' => 0,
			);
		}

		++$state['count'];

		if ( $state['count'] >= $settings['max_attempts'] ) {
			$state['locked_until'] = $now + $settings['lockout'];
			$expiration            = $settings['lockout'];

			do_action( 'bojaco_login_rate_limit_lockout', $username, $state );
		} else {
			$expiration = $state['first'] + $settings['window'] - $now;
		}

		set_transient( bojaco_login_rate_limit_key(), $state, max( 1, $expiration ) );
	},
	10,
	2
);

/**
 * Reset the counter after a successful login.
 */
add_action(
	'wp_login',
	function () {
		delete_transient( bojaco_login_rate_limit_key() );
	}
);
