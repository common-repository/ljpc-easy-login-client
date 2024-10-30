<?php
/**
 * Plugin Name:         LJPc Easy Login Client
 * Plugin URI:          https://www.easy-login.nl/
 * Description:         Deze plugin maakt het mogelijk om vertrouwde gebruikers in te laten loggen via Easy Login
 * Version:             1.0.2
 * Author:              LJPc solutions
 * Author URI:          https://www.ljpc.nl
 * Requires PHP:        7.0
 * Requires at least:   5.0
 * License:             GPL-v3
 */

use LJPc\EasyLogin\EasyLogin;

require __DIR__ . '/vendor/autoload.php';

function initializeEasyLogin() {
	$easyLogin = EasyLogin::instance();
	if ( isset( $_GET['easy_login_token'] ) ) {
		$easyLogin->handleExternalCall( $_GET['easy_login_token'] );
	} elseif ( isset( $_GET['easy_login_internal_token'] ) ) {
		$easyLogin->handleInternalCall( $_GET['easy_login_internal_token'] );
	}
}

add_action( 'init', 'initializeEasyLogin' );
add_action( 'admin_init', 'initializeEasyLogin' );
