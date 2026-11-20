<?php
/**
 * Password Reset Protection.
 *
 * Protects the lost password form against spam bots:
 *
 * - Proof of work: the lost password form on wp-login.php contains a signed challenge
 *   that the browser has to solve with JavaScript before the form can be submitted
 *   (the same idea as ALTCHA). No third-party service or cookies are involved.
 * - Per-account throttle: limits how many password reset emails one account can
 *   receive within a time window, regardless of where the request comes from
 *   (also covers other forms that call retrieve_password(), e.g. WooCommerce).
 *   Requests from users who can edit the account (admins) are not throttled.
 *
 * Settings can be changed with the `bojaco_password_reset_protection_settings` filter.
 *
 * @package BojacoMUPlugin
 */

/**
 * Get the protection settings.
 *
 * @return array{max_number: int, ttl: int, max_emails: int, window: int}
 */
function bojaco_password_reset_protection_settings() {
	$settings = apply_filters(
		'bojaco_password_reset_protection_settings',
		array(
			'max_number' => 1600000,                // Upper bound of the proof-of-work search space (difficulty). ~3 s on average on a desktop.
			'ttl'        => 30 * MINUTE_IN_SECONDS, // How long a challenge is valid.
			'max_emails' => 3,                      // Reset emails allowed per account within the window.
			'window'     => HOUR_IN_SECONDS,        // Window in which reset emails are counted.
		)
	);

	return array(
		'max_number' => max( 1000, (int) $settings['max_number'] ),
		'ttl'        => max( MINUTE_IN_SECONDS, (int) $settings['ttl'] ),
		'max_emails' => max( 1, (int) $settings['max_emails'] ),
		'window'     => max( 1, (int) $settings['window'] ),
	);
}

/**
 * Sign the challenge parameters.
 *
 * @param string $salt      Random salt.
 * @param int    $expires   Expiration timestamp.
 * @param string $challenge Challenge hash.
 * @return string
 */
function bojaco_password_reset_protection_sign( $salt, $expires, $challenge ) {
	return hash_hmac( 'sha256', $salt . '|' . $expires . '|' . $challenge, wp_salt( 'nonce' ) );
}

/**
 * Create a new proof-of-work challenge.
 *
 * The challenge is sha256( salt . number ), where number is a random secret between
 * 0 and max_number. The browser finds the number by brute force.
 *
 * @return array{salt: string, expires: int, challenge: string, signature: string, max_number: int}
 */
function bojaco_password_reset_protection_challenge() {
	$settings  = bojaco_password_reset_protection_settings();
	$salt      = bin2hex( random_bytes( 12 ) );
	$expires   = time() + $settings['ttl'];
	$challenge = hash( 'sha256', $salt . random_int( 0, $settings['max_number'] ) );

	return array(
		'salt'       => $salt,
		'expires'    => $expires,
		'challenge'  => $challenge,
		'signature'  => bojaco_password_reset_protection_sign( $salt, $expires, $challenge ),
		'max_number' => $settings['max_number'],
	);
}

/**
 * Verify the submitted proof-of-work solution.
 *
 * @return true|string True on success, error code on failure.
 */
function bojaco_password_reset_protection_verify() {
	$fields = array();

	foreach ( array( 'salt', 'expires', 'challenge', 'signature', 'number' ) as $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The signed challenge acts as the form token.
		$fields[ $field ] = isset( $_POST[ 'bojaco_pow_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'bojaco_pow_' . $field ] ) ) : '';
	}

	if (
		! ctype_xdigit( $fields['salt'] ) ||
		! ctype_digit( $fields['expires'] ) ||
		! ctype_xdigit( $fields['challenge'] ) ||
		! ctype_xdigit( $fields['signature'] ) ||
		! ctype_digit( $fields['number'] )
	) {
		return 'missing';
	}

	$expected = bojaco_password_reset_protection_sign( $fields['salt'], (int) $fields['expires'], $fields['challenge'] );
	if ( ! hash_equals( $expected, $fields['signature'] ) ) {
		return 'invalid';
	}

	if ( (int) $fields['expires'] < time() ) {
		return 'expired';
	}

	if ( ! hash_equals( $fields['challenge'], hash( 'sha256', $fields['salt'] . $fields['number'] ) ) ) {
		return 'invalid';
	}

	// Each solution can only be used once.
	$used_key = 'bojaco_pr_pow_' . md5( $fields['salt'] );
	if ( get_transient( $used_key ) ) {
		return 'invalid';
	}
	set_transient( $used_key, 1, max( 1, (int) $fields['expires'] - time() ) );

	return true;
}

/**
 * Get the transient key for the reset email throttle of a user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function bojaco_password_reset_protection_throttle_key( $user_id ) {
	return 'bojaco_pr_throttle_' . (int) $user_id;
}

/**
 * Get the timestamps of reset emails sent to a user within the current window.
 *
 * @param int $user_id User ID.
 * @return int[]
 */
function bojaco_password_reset_protection_sent( $user_id ) {
	$settings = bojaco_password_reset_protection_settings();
	$sent     = get_transient( bojaco_password_reset_protection_throttle_key( $user_id ) );
	$since    = time() - $settings['window'];

	if ( ! is_array( $sent ) ) {
		return array();
	}

	return array_values(
		array_filter(
			array_map( 'intval', $sent ),
			function ( $time ) use ( $since ) {
				return $time > $since;
			}
		)
	);
}

/**
 * Add the proof-of-work fields and solver script to the lost password form.
 */
add_action(
	'lostpassword_form',
	function () {
		$challenge = bojaco_password_reset_protection_challenge();

		foreach ( array( 'salt', 'expires', 'challenge', 'signature' ) as $field ) {
			printf(
				'<input type="hidden" name="bojaco_pow_%1$s" value="%2$s" />',
				esc_attr( $field ),
				esc_attr( $challenge[ $field ] )
			);
		}
		printf(
			'<input type="hidden" name="bojaco_pow_number" value="" data-max="%1$d" data-label="%2$s" />',
			(int) $challenge['max_number'],
			esc_attr__( 'Verifying…' )
		);
		echo '<noscript><p>' . esc_html__( 'JavaScript is required to reset your password.' ) . '</p></noscript>';

		// Make the pending state visible even if a theme restyles the login buttons.
		echo '<style>.bojaco-pow-pending,.bojaco-pow-pending:hover,.bojaco-pow-pending:focus{opacity:.5!important;cursor:wait!important;}</style>';

		$script = <<<'JS'
(function () {
	var input = document.querySelector('input[name="bojaco_pow_number"]');
	if (!input) { return; }
	var form = input.form;
	var salt = form.querySelector('input[name="bojaco_pow_salt"]').value;
	var challenge = form.querySelector('input[name="bojaco_pow_challenge"]').value;
	var max = parseInt(input.getAttribute('data-max'), 10);
	var submit = form.querySelector('[type="submit"]');
	var isInput = submit && submit.tagName === 'INPUT';
	var label = submit ? (isInput ? submit.value : submit.textContent) : '';
	function setLabel(text) { if (isInput) { submit.value = text; } else { submit.textContent = text; } }
	if (submit) {
		submit.disabled = true;
		submit.classList.add('disabled', 'bojaco-pow-pending');
		submit.setAttribute('aria-busy', 'true');
		setLabel(input.getAttribute('data-label') || label);
	}
	function done() {
		if (!submit) { return; }
		submit.disabled = false;
		submit.classList.remove('disabled', 'bojaco-pow-pending');
		submit.removeAttribute('aria-busy');
		setLabel(label);
	}

	var K = [], H = [];
	(function () {
		var n = 2, c = 0;
		function frac(x) { return ((x - Math.floor(x)) * 4294967296) | 0; }
		while (c < 64) {
			var prime = true;
			for (var d = 2; d * d <= n; d++) { if (n % d === 0) { prime = false; break; } }
			if (prime) { if (c < 8) { H[c] = frac(Math.sqrt(n)); } K[c] = frac(Math.cbrt(n)); c++; }
			n++;
		}
	})();

	function rotr(x, n) { return (x >>> n) | (x << (32 - n)); }

	function sha256(msg) {
		var bytes = [], i, t;
		for (i = 0; i < msg.length; i++) { bytes.push(msg.charCodeAt(i) & 255); }
		var bits = msg.length * 8;
		bytes.push(0x80);
		while (bytes.length % 64 !== 56) { bytes.push(0); }
		bytes.push(0, 0, 0, 0, (bits >>> 24) & 255, (bits >>> 16) & 255, (bits >>> 8) & 255, bits & 255);

		var h = H.slice(), w = new Array(64);
		for (var o = 0; o < bytes.length; o += 64) {
			for (t = 0; t < 16; t++) {
				w[t] = (bytes[o + 4 * t] << 24) | (bytes[o + 4 * t + 1] << 16) | (bytes[o + 4 * t + 2] << 8) | bytes[o + 4 * t + 3];
			}
			for (t = 16; t < 64; t++) {
				var s0 = rotr(w[t - 15], 7) ^ rotr(w[t - 15], 18) ^ (w[t - 15] >>> 3);
				var s1 = rotr(w[t - 2], 17) ^ rotr(w[t - 2], 19) ^ (w[t - 2] >>> 10);
				w[t] = (w[t - 16] + s0 + w[t - 7] + s1) | 0;
			}
			var a = h[0], b = h[1], c = h[2], d = h[3], e = h[4], f = h[5], g = h[6], hh = h[7];
			for (t = 0; t < 64; t++) {
				var t1 = (hh + (rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25)) + ((e & f) ^ (~e & g)) + K[t] + w[t]) | 0;
				var t2 = ((rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
				hh = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
			}
			h[0] = (h[0] + a) | 0; h[1] = (h[1] + b) | 0; h[2] = (h[2] + c) | 0; h[3] = (h[3] + d) | 0;
			h[4] = (h[4] + e) | 0; h[5] = (h[5] + f) | 0; h[6] = (h[6] + g) | 0; h[7] = (h[7] + hh) | 0;
		}

		var hex = '';
		for (i = 0; i < 8; i++) { hex += ('00000000' + (h[i] >>> 0).toString(16)).slice(-8); }
		return hex;
	}

	var number = 0;
	function solve() {
		var end = Math.min(number + 5000, max);
		for (; number <= end; number++) {
			if (sha256(salt + number) === challenge) {
				input.value = String(number);
				done();
				return;
			}
		}
		if (number <= max) { setTimeout(solve, 0); }
	}
	solve();
})();
JS;

		wp_print_inline_script_tag( $script );
	}
);

/**
 * Validate the proof of work and the per-account throttle before a reset email is sent.
 */
add_action(
	'lostpassword_post',
	function ( $errors, $user_data = false ) {
		global $pagenow;

		if ( 'wp-login.php' === $pagenow ) {
			$result = bojaco_password_reset_protection_verify();

			if ( 'expired' === $result ) {
				$errors->add( 'bojaco_pow_expired', __( '<strong>Error:</strong> The form has expired. Please try again.' ) );
				return;
			}

			if ( true !== $result ) {
				$errors->add( 'bojaco_pow_failed', __( '<strong>Error:</strong> Verification failed. Please make sure JavaScript is enabled and try again.' ) );
				return;
			}
		}

		if ( ! $user_data instanceof WP_User || current_user_can( 'edit_user', $user_data->ID ) ) {
			return;
		}

		$settings = bojaco_password_reset_protection_settings();
		if ( count( bojaco_password_reset_protection_sent( $user_data->ID ) ) >= $settings['max_emails'] ) {
			$errors->add( 'bojaco_too_many_resets', __( '<strong>Error:</strong> Too many password reset requests for this account. Please check your email or try again later.' ) );
		}
	},
	10,
	2
);

/**
 * Record each password reset email sent through retrieve_password().
 */
add_filter(
	'retrieve_password_notification_email',
	function ( $email, $key, $user_login, $user_data ) {
		if ( $user_data instanceof WP_User ) {
			$settings = bojaco_password_reset_protection_settings();
			$sent     = bojaco_password_reset_protection_sent( $user_data->ID );
			$sent[]   = time();

			set_transient( bojaco_password_reset_protection_throttle_key( $user_data->ID ), $sent, $settings['window'] );
		}

		return $email;
	},
	10,
	4
);
