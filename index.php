<?php
/**
 * Plugin Name: Bojaco MU Plugin
 * Plugin URI:  https://github.com/JCO-Digital/bojaco-mu-plugin
 * Description: A custom MU plugin
 * Version:     1.1.1
 * Author:      J&Co Digital
 * Author URI:  https://jco.fi
 * License:     GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		$disabled_modules = apply_filters( 'bojaco_mu_plugin_disabled_modules', array() );

		if ( ! in_array( 'user-rest-api', $disabled_modules, true ) ) {
			require_once 'modules/user-rest-api.php';
		}

		if ( ! in_array( 'staging-force-login', $disabled_modules, true ) ) {
			require_once 'modules/staging-force-login.php';
		}
	}
);
