<?php
/**
 * REST API used by the admin grid. Everything requires the edit_pages capability.
 *
 * Base: /wp-json/vasaskrimsli/v1/ordsifjar
 */

namespace Vasaskrimsli\Ordsifjar;

final class Rest {

	const NS   = 'vasaskrimsli/v1';
	const BASE = '/ordsifjar';

	public static function register(): void {
		$auth = static function () {
			return current_user_can( Store::CAPABILITY );
		};

		register_rest_route( self::NS, self::BASE, array(
			array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => array( self::class, 'list' ) ),
			array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => array( self::class, 'create' ) ),
		) );
		register_rest_route( self::NS, self::BASE . '/(?P<id>\d+)', array(
			array( 'methods' => 'POST, PUT, PATCH', 'permission_callback' => $auth, 'callback' => array( self::class, 'update' ) ),
			array( 'methods' => 'DELETE', 'permission_callback' => $auth, 'callback' => array( self::class, 'trash' ) ),
		) );
		register_rest_route( self::NS, self::BASE . '/(?P<id>\d+)/saga', array(
			'methods' => 'GET', 'permission_callback' => $auth, 'callback' => array( self::class, 'revisions' ),
		) );
		register_rest_route( self::NS, self::BASE . '/(?P<id>\d+)/saga/(?P<rid>\d+)', array(
			'methods' => 'POST', 'permission_callback' => $auth, 'callback' => array( self::class, 'restore' ),
		) );
		register_rest_route( self::NS, self::BASE . '/kynslodir', array(
			'methods' => 'POST', 'permission_callback' => $auth, 'callback' => array( self::class, 'save_kynslodir' ),
		) );
		register_rest_route( self::NS, self::BASE . '/innflutningur', array(
			'methods' => 'POST', 'permission_callback' => $auth, 'callback' => array( self::class, 'import_preview' ),
		) );
		register_rest_route( self::NS, self::BASE . '/innflutningur/(?P<token>[a-z0-9]+)', array(
			'methods' => 'POST', 'permission_callback' => $auth, 'callback' => array( self::class, 'import_apply' ),
		) );
	}

	public static function list(): \WP_REST_Response {
		return new \WP_REST_Response( array(
			'entries'   => array_values( Store::all() ),
			'kynslodir' => Store::kynslodir(),
			'flokkar'   => Store::flokkar(),
			'afrit'     => Import::backups(),
		) );
	}

	public static function create( \WP_REST_Request $req ) {
		return self::respond( Store::save( (array) $req->get_json_params() ) );
	}

	public static function update( \WP_REST_Request $req ) {
		return self::respond( Store::save( (array) $req->get_json_params(), (int) $req['id'] ) );
	}

	public static function trash( \WP_REST_Request $req ) {
		return self::respond( Store::trash( (int) $req['id'] ) );
	}

	public static function revisions( \WP_REST_Request $req ) {
		return self::respond( Store::revisions( (int) $req['id'] ) );
	}

	public static function restore( \WP_REST_Request $req ) {
		return self::respond( Store::restore( (int) $req['id'], (int) $req['rid'] ) );
	}

	public static function save_kynslodir( \WP_REST_Request $req ) {
		return self::respond( Store::save_kynslodir( (array) ( $req->get_json_params()['kynslodir'] ?? array() ) ) );
	}

	public static function import_preview( \WP_REST_Request $req ) {
		$files = $req->get_file_params();
		if ( empty( $files['skra']['tmp_name'] ) || ! is_uploaded_file( $files['skra']['tmp_name'] ) ) {
			return new \WP_Error( 'vs_ordsifjar_no_file', 'Engin skrá barst.', array( 'status' => 400 ) );
		}
		if ( $files['skra']['size'] > 10 * MB_IN_BYTES ) {
			return new \WP_Error( 'vs_ordsifjar_too_big', 'Skráin er stærri en 10 MB.', array( 'status' => 400 ) );
		}
		return self::respond( Import::preview( (string) file_get_contents( $files['skra']['tmp_name'] ) ) );
	}

	public static function import_apply( \WP_REST_Request $req ) {
		return self::respond( Import::apply( (string) $req['token'] ) );
	}

	private static function respond( $result ) {
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result );
	}
}
