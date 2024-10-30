<?php

namespace LJPc\EasyLogin;

use Exception;
use Firebase\JWT\JWT;

class Token {
	public static function decode( string $token, \Firebase\JWT\Key $publicKey = null ): array {
		if ( $publicKey === null ) {
			$publicKey = Key::getPublicKey();
		}
		try {
			$decoded = JWT::decode( $token, $publicKey );

			return (array) $decoded;
		} catch ( Exception $e ) {
			return [];
		}
	}

	public static function getData( string $token ): array {
		list( $header, $payload, $signature ) = explode( ".", $token );

		return json_decode( base64_decode( $payload ), true );
	}
}
