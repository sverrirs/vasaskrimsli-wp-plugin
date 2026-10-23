<?php
/**
 * Storage for orðsifjar entries.
 *
 * Each entry is a post of type vs_ordsifja whose post_content holds the fields as JSON.
 * Keeping every field in post_content means WordPress revisions record and restore the whole
 * entry. Categories (flokkar) are a normal taxonomy so they can be managed in wp-admin.
 * Deleting moves an entry to the trash; nothing here deletes permanently.
 */

namespace Vasaskrimsli\Ordsifjar;

final class Store {

	const POST_TYPE   = 'vs_ordsifja';
	const TAXONOMY    = 'vs_ordsifja_flokkur';
	const OPT_KYNSLOD = 'vs_ordsifjar_kynslodir';
	const CACHE       = 'vs_ordsifjar_cache';
	const CAPABILITY  = 'edit_pages';

	/** Fields stored in post_content, in order. */
	const FIELDS = array( 'numer', 'kynslod', 'nafn', 'uppruni', 'thyding', 'enska', 'athugasemdir' );

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => 'Orðsifjar',
					'singular_name' => 'Orðsifjafærsla',
				),
				'public'              => false,
				'show_ui'             => true, // Needed for the trash and revision screens.
				'show_in_menu'        => false, // The grid in Admin replaces the list screen.
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => 'Flokkar',
					'singular_name' => 'Flokkur',
					'menu_name'     => 'Flokkar',
					'add_new_item'  => 'Bæta við flokki',
					'search_items'  => 'Leita í flokkum',
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				'hierarchical'      => false,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => self::CAPABILITY,
					'edit_terms'   => self::CAPABILITY,
					'delete_terms' => self::CAPABILITY,
					'assign_terms' => self::CAPABILITY,
				),
			)
		);
		add_action( 'save_post_' . self::POST_TYPE, array( self::class, 'flush' ) );
		add_action( 'trashed_post', array( self::class, 'flush' ) );
		add_action( 'untrashed_post', array( self::class, 'flush' ) );
		add_action( 'deleted_post', array( self::class, 'flush' ) );
		foreach ( array( 'created_', 'edited_', 'delete_' ) as $hook ) {
			add_action( $hook . self::TAXONOMY, array( self::class, 'flush' ) );
		}
		add_action( 'update_option_' . self::OPT_KYNSLOD, array( self::class, 'flush' ) );
		// Show the entry's fields, not raw JSON, on the revisions screen.
		add_filter( '_wp_post_revision_field_post_content', array( self::class, 'revision_field' ), 10, 3 );
	}

	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/** @return array<int, array> All published entries in display order, keyed by id. */
	public static function all(): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		$terms = wp_get_object_terms( wp_list_pluck( $posts, 'ID' ), self::TAXONOMY, array( 'fields' => 'all_with_object_id' ) );
		$by_post = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$by_post[ $t->object_id ][] = $t->name;
			}
		}
		$entries = array();
		foreach ( $posts as $p ) {
			$entries[ $p->ID ] = self::from_post( $p, $by_post[ $p->ID ] ?? array() );
		}
		uasort( $entries, array( self::class, 'compare' ) );
		set_transient( self::CACHE, $entries, DAY_IN_SECONDS );
		return $entries;
	}

	public static function compare( array $a, array $b ): int {
		return $a['kynslod'] <=> $b['kynslod']
			?: strnatcasecmp( $a['numer'], $b['numer'] )
			?: $a['id'] <=> $b['id'];
	}

	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$names = wp_get_object_terms( $id, self::TAXONOMY, array( 'fields' => 'names' ) );
		return self::from_post( $post, is_wp_error( $names ) ? array() : $names );
	}

	public static function decode( string $content ): array {
		$data = json_decode( $content, true );
		$data = is_array( $data ) ? $data : array();
		$out  = array();
		foreach ( self::FIELDS as $f ) {
			$out[ $f ] = 'kynslod' === $f ? (int) ( $data[ $f ] ?? 1 ) : (string) ( $data[ $f ] ?? '' );
		}
		return $out;
	}

	private static function from_post( \WP_Post $post, array $flokkar ): array {
		sort( $flokkar, SORT_NATURAL | SORT_FLAG_CASE );
		return array( 'id' => $post->ID ) + self::decode( $post->post_content ) + array( 'flokkar' => array_values( $flokkar ) );
	}

	/** Cleans incoming field values. Unknown keys are dropped; missing keys are left out. */
	public static function clean( array $fields ): array {
		$out = array();
		foreach ( self::FIELDS as $f ) {
			if ( ! array_key_exists( $f, $fields ) ) {
				continue;
			}
			if ( 'kynslod' === $f ) {
				$out[ $f ] = max( 1, (int) $fields[ $f ] );
			} elseif ( 'numer' === $f ) {
				$out[ $f ] = Sanitize::text( $fields[ $f ] );
			} else {
				$out[ $f ] = Sanitize::html( $fields[ $f ] );
			}
		}
		if ( array_key_exists( 'flokkar', $fields ) ) {
			$out['flokkar'] = array_values( array_filter( array_map( array( Sanitize::class, 'text' ), (array) $fields['flokkar'] ), 'strlen' ) );
		}
		return $out;
	}

	/**
	 * Creates ($id = 0) or updates an entry. Only the given fields change.
	 *
	 * @return array|\WP_Error The saved entry.
	 */
	public static function save( array $fields, int $id = 0 ) {
		$fields  = self::clean( $fields );
		$current = $id ? self::get( $id ) : null;
		if ( $id && ! $current ) {
			return new \WP_Error( 'vs_ordsifjar_not_found', 'Færslan fannst ekki.', array( 'status' => 404 ) );
		}
		$data = array_merge( $current ? array_intersect_key( $current, array_flip( self::FIELDS ) ) : self::decode( '' ), array_intersect_key( $fields, array_flip( self::FIELDS ) ) );
		if ( '' === Csv::plain( $data['nafn'] ) ) {
			return new \WP_Error( 'vs_ordsifjar_no_name', 'Færslan verður að hafa nafn.', array( 'status' => 400 ) );
		}

		// JSON_HEX_* keeps < > & ' " out of post_content so no content filter can alter the HTML inside.
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$post = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => wp_strip_all_tags( Csv::plain( $data['nafn'] ) ),
			'post_content' => wp_slash( $json ),
		);
		if ( $id ) {
			$post['ID'] = $id;
			$result     = wp_update_post( $post, true );
		} else {
			$result = wp_insert_post( $post, true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( array_key_exists( 'flokkar', $fields ) ) {
			wp_set_object_terms( (int) $result, $fields['flokkar'], self::TAXONOMY, false );
		}
		self::flush();
		return self::get( (int) $result );
	}

	/** Moves an entry to the trash (restorable). */
	public static function trash( int $id ) {
		if ( ! self::get( $id ) ) {
			return new \WP_Error( 'vs_ordsifjar_not_found', 'Færslan fannst ekki.', array( 'status' => 404 ) );
		}
		wp_trash_post( $id );
		self::flush();
		return true;
	}

	/** Revisions of an entry, newest first, decoded into fields. */
	public static function revisions( int $id ): array {
		$out = array();
		foreach ( wp_get_post_revisions( $id, array( 'order' => 'DESC' ) ) as $rev ) {
			$author = get_userdata( (int) $rev->post_author );
			$out[]  = array(
				'id'     => $rev->ID,
				'date'   => get_date_from_gmt( $rev->post_modified_gmt, 'c' ),
				'author' => $author ? $author->display_name : '',
				'fields' => self::decode( $rev->post_content ),
			);
		}
		return $out;
	}

	public static function restore( int $id, int $revision_id ) {
		$rev = wp_get_post_revision( $revision_id );
		if ( ! $rev || (int) $rev->post_parent !== $id ) {
			return new \WP_Error( 'vs_ordsifjar_bad_revision', 'Útgáfan fannst ekki.', array( 'status' => 404 ) );
		}
		return self::save( self::decode( $rev->post_content ), $id );
	}

	public static function revision_field( $value, $field, $post ) {
		if ( ! $post || self::POST_TYPE !== get_post_type( $post->post_parent ?: $post->ID ) ) {
			return $value;
		}
		$labels = array( 'numer' => 'Númer', 'kynslod' => 'Kynslóð', 'nafn' => 'Nafn', 'uppruni' => 'Upprunaorð', 'thyding' => 'Þýðing', 'enska' => 'Enska', 'athugasemdir' => 'Athugasemdir' );
		$lines  = array();
		foreach ( self::decode( (string) $value ) as $f => $v ) {
			$lines[] = $labels[ $f ] . ': ' . ( is_int( $v ) ? $v : Csv::plain( $v ) );
		}
		return implode( "\n\n", $lines );
	}

	public static function count_all_statuses(): int {
		$counts = wp_count_posts( self::POST_TYPE );
		return (int) array_sum( (array) $counts );
	}

	/** Generation headings and notes. */
	public static function kynslodir(): array {
		$value = get_option( self::OPT_KYNSLOD, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function save_kynslodir( array $rows ): array {
		$clean = array();
		foreach ( $rows as $r ) {
			$n = (int) ( $r['kynslod'] ?? 0 );
			if ( $n < 1 ) {
				continue;
			}
			$clean[ $n ] = array(
				'kynslod'      => $n,
				'akkeri'       => sanitize_title( $r['akkeri'] ?? '' ),
				'heiti_html'   => Sanitize::html( $r['heiti_html'] ?? '' ),
				'skyring_html' => Sanitize::html( $r['skyring_html'] ?? '' ),
			);
		}
		ksort( $clean );
		update_option( self::OPT_KYNSLOD, array_values( $clean ), false );
		self::flush();
		return array_values( $clean );
	}

	/** All category names, for the editor's suggestions. */
	public static function flokkar(): array {
		$names = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'fields' => 'names' ) );
		return is_wp_error( $names ) ? array() : array_values( $names );
	}
}
