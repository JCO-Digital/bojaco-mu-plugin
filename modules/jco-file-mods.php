<?php
/**
 * J&Co File Mods.
 *
 * Allows file modifications (plugin/theme/core installs and updates) for administrators
 * with an @jco.fi email address, even when DISALLOW_FILE_MODS is set.
 *
 * @package BojacoMUPlugin
 */

add_filter(
	'file_mod_allowed',
	function ( $allowed ) {
		if ( $allowed || ! is_user_logged_in() ) {
			return $allowed;
		}

		$user = wp_get_current_user();

		$is_admin = in_array( 'administrator', (array) $user->roles, true ) || ( is_multisite() && is_super_admin( $user->ID ) );
		if ( ! $is_admin ) {
			return $allowed;
		}

		$domain = '@jco.fi';
		$email  = strtolower( $user->user_email );

		return substr( $email, -strlen( $domain ) ) === $domain;
	}
);
