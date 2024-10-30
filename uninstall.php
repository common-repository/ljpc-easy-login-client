<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

global $wpdb;

$currentBlogId = get_current_blog_id();
if ( function_exists( 'switch_to_blog' ) ) {
	switch_to_blog( get_main_site_id() );
}

/**
 * Remove organisation
 */
delete_site_option( 'ljpc_easy_login_organisation_public_key' );

/**
 * Remove easy login users and transfer everything to the first known normal admin
 */
$normalAdmins    = [];
$easyLoginAdmins = [];

$users = get_users( [
	'role__in' => [ 'administrator' ],
] );
foreach ( $users as $user ) {
	$createdWithEasyLogin = get_user_meta( $user->ID, 'created_with_ljpc_easy_login', true );
	if ( empty( $createdWithEasyLogin ) ) {
		$normalAdmins[] = $user;
	} else {
		$easyLoginAdmins[] = $user;
	}
}
if ( count( $normalAdmins ) === 0 && count( $easyLoginAdmins ) > 0 ) {
	$normalAdmins[] = $easyLoginAdmins[0];
	delete_user_meta( $easyLoginAdmins[0]->ID, 'ljpc_easy_login_public_key' );
	unset( $easyLoginAdmins[0] );
	$easyLoginAdmins = array_values( $easyLoginAdmins );
}
if ( is_multisite() && ! function_exists( 'wpmu_delete_user' ) ) {
	require_once ABSPATH . '/wp-admin/includes/ms.php';
}

foreach ( $easyLoginAdmins as $easyLoginAdmin ) {
	if ( ! is_multisite() ) {
		wp_delete_user( $easyLoginAdmin->ID, $normalAdmins[0]->ID );
		continue;
	}

	$blogs = get_blogs_of_user( $easyLoginAdmin->ID );

	if ( ! empty( $blogs ) ) {
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog->userblog_id );
			remove_user_from_blog( $easyLoginAdmin->ID, $blog->userblog_id, $normalAdmins[0]->ID );

			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $easyLoginAdmin->ID ) );
			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id );
			}

			restore_current_blog();
		}
	}

	revoke_super_admin( $easyLoginAdmin->ID );
	wpmu_delete_user( $easyLoginAdmin->ID );
}

if ( function_exists( 'restore_current_blog' ) ) {
	restore_current_blog();
}
