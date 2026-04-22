<?php
/**
 * Plugin Name: Bojaco MU Plugin
 * Plugin URI:  https://github.com/JCO-Digital/bojaco-mu-plugin
 * Description: A custom MU plugin
 * Version:     1.0.0
 * Author:      J&Co Digital
 * Author URI:  https://jco.fi
 * License:     GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BOJACO_MU_PLUGIN_DISABLED_MODULES = apply_filters( 'bojaco_mu_plugin_disabled_modules', [] );

if ( ! in_array( 'user-rest-api', BOJACO_MU_PLUGIN_DISABLED_MODULES, true ) ) {
	require_once 'modules/user-rest-api.php';
}
