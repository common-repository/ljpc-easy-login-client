<?php

namespace LJPc\EasyLogin;

class Key {
	public static function getPublicKey(): \Firebase\JWT\Key {
		return new \Firebase\JWT\Key( file_get_contents( __DIR__ . '/res/pubkey.pem' ), 'ES256' );
	}

	public static function getSiteKey(): \Firebase\JWT\Key {
		$key = get_transient( 'ljpc_easy_login_site_key' );
		if ( empty( $key ) || $key === false ) {
			$key = hash( 'sha512', uniqid( '', true ) );
			set_transient( 'ljpc_easy_login_site_key', $key, 15 * MINUTE_IN_SECONDS );
		}

		return new \Firebase\JWT\Key( $key, 'HS256' );
	}
}
