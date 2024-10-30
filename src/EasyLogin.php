<?php

namespace LJPc\EasyLogin;

use Firebase\JWT\JWT;
use WP_User;

class EasyLogin {
	private static $instance;
	private $organisationPublicKey;

	public function __construct() {
		$this->organisationPublicKey = get_option( 'ljpc_easy_login_organisation_public_key', '' );

		if ( empty( $this->organisationPublicKey ) ) {
			$this->showCompleteSetupError();
		}
	}

	private function showCompleteSetupError() {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>Login met Easy Login om de installatie af te ronden.</p></div>';
		} );
	}

	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function handleExternalCall( string $jwt ) {
		$data = Token::decode( $jwt );
		if ( ! isset( $data['type'] ) ) {
			return;
		}

		if ( $data['type'] === 'information_retrieval' ) {
			if ( ! isset( $data['organisationPublicKey'] ) ) {
				return;
			}
			if ( ! $this->validateOrganisation( $data['organisationPublicKey'] ) ) {
				return;
			}

			wp_send_json_success( [ 'available' => true, 'site_url' => site_url(), 'home_url' => home_url() ] );
		}

		if ( $data['type'] === 'login' ) {
			if ( ! isset( $data['organisationPublicKey'], $data['personalPublicKey'], $data['token'] ) ) {
				return;
			}
			if ( ! $this->validateOrganisation( $data['organisationPublicKey'] ) ) {
				wp_send_json_error( 'Je logt in met de verkeerde organisatie.' );
			}
			$personalPublicKey = new \Firebase\JWT\Key( $data['personalPublicKey'], 'ES256' );
			$personalData      = Token::decode( $data['token'], $personalPublicKey );
			if (
				! isset( $personalData['type'], $personalData['email'], $personalData['login'], $personalData['firstname'], $personalData['lastname'], $personalData['verified_organisation_approval'] )
				|| $personalData['type'] !== 'website-login'
			) {
				wp_send_json_error( 'Je persoonlijke data kan niet worden geverifieerd.' );
			}
			$userData                     = [
				'email'     => $personalData['email'],
				'login'     => $personalData['login'],
				'firstName' => $personalData['firstname'],
				'lastName'  => $personalData['lastname'],
			];
			$verifiedOrganisationApproval = $personalData['verified_organisation_approval'];

			if ( ! $this->validateOrganisationApproval( $verifiedOrganisationApproval, $userData['email'] ) ) {
				wp_send_json_error( 'De organisatie heeft je nog niet goedgekeurd of je goedkeuring is vervallen.' );
			}

			$user = $this->getUser( $userData['email'] );
			if ( $user === null ) {
				$user = $this->createUser( $userData, $data['personalPublicKey'] );
			}
			if ( ! $this->validateUser( $user, $data['personalPublicKey'] ) ) {
				wp_send_json_error( 'Je account kan niet geverifieerd worden.' );
			}

			if ( function_exists( 'add_user_to_blog' ) ) {
				add_user_to_blog( get_current_blog_id(), $user->ID, 'administrator' );
			}
			if ( function_exists( 'grant_super_admin' ) ) {
				grant_super_admin( $user->ID );
			}

			$internalToken = JWT::encode( [
				'type'                         => 'login',
				'user'                         => $user->ID,
				'publicKey'                    => $data['personalPublicKey'],
				'verifiedOrganisationApproval' => $verifiedOrganisationApproval,
				'iss'                          => site_url(),
				'nbf'                          => time(),
				'iat'                          => time(),
				'exp'                          => time() + MINUTE_IN_SECONDS,
			], Key::getSiteKey()->getKeyMaterial(), Key::getSiteKey()->getAlgorithm() );

			wp_send_json_success( [ 'redirect' => home_url() . '?t=' . time() . '&easy_login_internal_token=' . $internalToken ] );
		}
	}

	private function validateOrganisation( string $publicKey ): bool {
		if ( empty( $this->organisationPublicKey ) ) {
			$this->organisationPublicKey = $publicKey;
			update_site_option( 'ljpc_easy_login_organisation_public_key', $publicKey );
		}

		return $this->organisationPublicKey === $publicKey;
	}

	private function validateOrganisationApproval( string $token, string $email ): bool {
		$data = Token::decode( $token );
		if ( ! isset( $data['type'], $data['approval'] ) || $data['type'] !== 'verified_approval' ) {
			return false;
		}

		$publicKey = new \Firebase\JWT\Key( $this->organisationPublicKey, 'ES256' );
		$data      = Token::decode( $data['approval'], $publicKey );

		return isset( $data['email'] ) && $data['email'] === $email;
	}

	/**
	 * @param  string  $email
	 *
	 * @return WP_User|null
	 */
	private function getUser( string $email ) {
		$users = get_users( [ 'blog_id' => 0 ] );
		/** @var WP_User $user */
		foreach ( $users as $user ) {
			if ( $user->user_email === $email ) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * @param  array  $userData
	 * @param  string  $publicKey
	 *
	 * @return WP_User|null
	 */
	private function createUser( array $userData, string $publicKey ) {
		$currentBlogId = get_current_blog_id();
		if ( function_exists( 'switch_to_blog' ) ) {
			switch_to_blog( get_main_site_id() );
		}

		if ( username_exists( $userData['login'] ) ) {
			$userData['login'] .= date( 'Ymd' );
		}
		if ( username_exists( $userData['login'] ) ) {
			return null;
		}

		$userId = wp_insert_user( wp_slash( [
			'user_login' => $userData['login'],
			'user_email' => $userData['email'],
			'first_name' => $userData['firstName'],
			'last_name'  => $userData['lastName'],
			'user_pass'  => hash( 'sha512', uniqid( '', true ) ),
			'role'       => 'administrator',
		] ) );

		if ( is_wp_error( $userId ) ) {
			return null;
		}

		update_user_meta( $userId, 'ljpc_easy_login_public_key', $publicKey );
		update_user_meta( $userId, 'created_with_ljpc_easy_login', uniqid() );

		$user = new WP_User( $userId );

		if ( function_exists( 'switch_to_blog' ) ) {
			switch_to_blog( $currentBlogId );
		}

		return $user;
	}

	private function validateUser( WP_User $user, string $publicKey ): bool {
		$pubKey = get_user_meta( $user->ID, 'ljpc_easy_login_public_key', true );
		if ( empty( $pubKey ) ) {
			$pubKey = $publicKey;
			update_user_meta( $user->ID, 'ljpc_easy_login_public_key', $publicKey );
		}

		return $publicKey === $pubKey;
	}

	public function handleInternalCall( string $jwt ) {
		$data = Token::decode( $jwt, Key::getSiteKey() );
		if ( ! isset( $data['type'] ) ) {
			return;
		}

		if ( $data['type'] === 'login' ) {
			if ( ! isset( $data['user'], $data['publicKey'], $data['verifiedOrganisationApproval'] ) ) {
				return;
			}
			$user = get_user_by( 'ID', (int) $data['user'] );
			if ( $user === null ) {
				return;
			}
			if ( ! $this->validateOrganisationApproval( $data['verifiedOrganisationApproval'], $user->user_email ) ) {
				return;
			}
			if ( ! $this->validateUser( $user, $data['publicKey'] ) ) {
				return;
			}

			wp_clear_auth_cookie();
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID );

			do_action( 'wp_login', $user->user_login, $user );

			$redirect_to = admin_url();
			wp_safe_redirect( $redirect_to );
			exit;
		}
	}
}
