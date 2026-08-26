<?php

namespace More_MCP\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta {

	public static function read( string $level, int $id, string $context = '' ): array {
		$plugin = Detector::primary();
		if ( 'none' === $plugin ) {
			return array();
		}
		$spec = Fields::spec( $plugin, $level );
		if ( null === $spec ) {
			return array();
		}

		$out = array();
		foreach ( Fields::supported( $plugin, $level ) as $field ) {
			$out[ $field ] = self::read_field( $plugin, $level, $id, $context, $field );
		}
		return $out;
	}

	public static function write( string $level, int $id, string $context, array $fields ): array {
		$plugin = Detector::primary();
		if ( 'none' === $plugin ) {
			throw new \Exception( 'No supported SEO plugin is active. Supported: Yoast SEO, Rank Math, All in One SEO, SEOPress, Slim SEO, The SEO Framework.' );
		}
		$spec = Fields::spec( $plugin, $level );
		if ( null === $spec ) {
			throw new \Exception( sprintf(
				'%s does not have %s-level SEO support in More MCP. Plugins that do: %s.',
				Detector::label( $plugin ),
				$level,
				self::label_list( Fields::plugins_with_support( $level ) )
			) );
		}

		
		foreach ( array_keys( $fields ) as $field ) {
			self::assert_supported( $plugin, $level, $field );
		}

		$written = array();
		foreach ( $fields as $field => $value ) {
			self::write_field( $plugin, $level, $id, $context, $field, $value );
			
			$written[ $field ] = self::read_field( $plugin, $level, $id, $context, $field );
		}
		return $written;
	}

	private static function assert_supported( string $plugin, string $level, string $field ): void {
		if ( Fields::supports( $plugin, $level, $field ) ) {
			return;
		}
		if ( ! in_array( $field, Fields::LOGICAL, true ) ) {
			throw new \Exception( sprintf(
				'Unknown SEO field "%s". Supported fields: %s.',
				$field,
				implode( ', ', Fields::LOGICAL )
			) );
		}

		
		$elsewhere = array();
		foreach ( Fields::plugins_with_support( $level ) as $other ) {
			if ( $other !== $plugin && Fields::supports( $other, $level, $field ) ) {
				$elsewhere[] = $other;
			}
		}
		$suffix = empty( $elsewhere )
			? 'No supported plugin exposes it at this level.'
			: sprintf( 'Plugins that do: %s.', self::label_list( $elsewhere ) );
		throw new \Exception( sprintf(
			'%s does not store "%s" at %s level, so More MCP will not write it. Writing it to a plausible-looking key would store cleanly and change nothing on the rendered page. %s',
			Detector::label( $plugin ),
			$field,
			$level,
			$suffix
		) );
	}

	private static function label_list( array $slugs ): string {
		return implode( ', ', array_map( array( Detector::class, 'label' ), $slugs ) );
	}

	
	private static function read_field( string $plugin, string $level, int $id, string $context, string $field ) {
		$spec = Fields::spec( $plugin, $level );
		$map  = $spec[ $field ];

		if ( 'noindex' === $field ) {
			return self::read_noindex( $spec, $map, $level, $id, $context );
		}

		switch ( $spec['strategy'] ) {
			case 'post_meta':
				return (string) get_post_meta( $id, $map, true );

			case 'term_meta':
				return (string) get_term_meta( $id, $map, true );

			case 'meta_array':
				$bag = get_post_meta( $id, $spec['key'], true );
				$bag = is_array( $bag ) ? $bag : array();
				return isset( $bag[ $map ] ) ? (string) $bag[ $map ] : '';

			case 'yoast_term_option':
				$bag = self::yoast_term_bag( $context, $id );
				return isset( $bag[ $map ] ) ? (string) $bag[ $map ] : '';

			case 'aioseo_table':
				$row = self::aioseo_row( $spec, $id );
				return ( $row && isset( $row[ $map ] ) && null !== $row[ $map ] ) ? (string) $row[ $map ] : '';
		}
		return '';
	}

	private static function read_noindex( array $spec, array $map, string $level, int $id, string $context ): bool {
		switch ( $map['strategy'] ) {
			case 'flag_string':
				$stored = 'term' === $level
					? (string) get_term_meta( $id, $map['key'], true )
					: (string) get_post_meta( $id, $map['key'], true );
				return $stored === (string) $map['on'];

			case 'robots_array':
				$stored = 'term' === $level
					? get_term_meta( $id, $map['key'], true )
					: get_post_meta( $id, $map['key'], true );
				return is_array( $stored ) && in_array( 'noindex', $stored, true );

			case 'array_flag':
				$bag = get_post_meta( $id, $spec['key'], true );
				$bag = is_array( $bag ) ? $bag : array();
				return isset( $bag[ $map['field'] ] ) && (string) $bag[ $map['field'] ] === (string) $map['on'];

			case 'array_tristate':
				$bag = get_post_meta( $id, $spec['key'], true );
				$bag = is_array( $bag ) ? $bag : array();
				return isset( $bag[ $map['field'] ] ) && (int) $bag[ $map['field'] ] === (int) $map['on'];

			case 'yoast_term_noindex':
				$bag = self::yoast_term_bag( $context, $id );
				return isset( $bag[ $map['field'] ] ) && $bag[ $map['field'] ] === $map['on'];

			case 'aioseo_robots':
				$row = self::aioseo_row( $spec, $id );
				return $row && ! empty( $row[ $map['column'] ] );
		}
		return false;
	}

	
	private static function write_field( string $plugin, string $level, int $id, string $context, string $field, $value ): void {
		$spec = Fields::spec( $plugin, $level );
		$map  = $spec[ $field ];

		if ( 'noindex' === $field ) {
			self::write_noindex( $spec, $map, $level, $id, $context, (bool) $value );
			return;
		}

		
		$value = ( 'canonical' === $field )
			? esc_url_raw( (string) $value )
			: sanitize_text_field( (string) $value );

		switch ( $spec['strategy'] ) {
			case 'post_meta':
				update_post_meta( $id, $map, $value );
				return;

			case 'term_meta':
				update_term_meta( $id, $map, $value );
				return;

			case 'meta_array':

				$bag         = get_post_meta( $id, $spec['key'], true );
				$bag         = is_array( $bag ) ? $bag : array();
				$bag[ $map ] = $value;
				update_post_meta( $id, $spec['key'], $bag );
				return;

			case 'yoast_term_option':
				self::yoast_term_write( $context, $id, array( $map => $value ) );
				return;

			case 'aioseo_table':
				self::aioseo_write( $spec, $id, array( $map => $value ), $context );
				return;
		}
	}

	private static function write_noindex( array $spec, array $map, string $level, int $id, string $context, bool $noindex ): void {
		switch ( $map['strategy'] ) {
			case 'flag_string':
				$stored = $noindex ? (string) $map['on'] : (string) $map['off'];
				if ( 'term' === $level ) {
					update_term_meta( $id, $map['key'], $stored );
				} else {
					update_post_meta( $id, $map['key'], $stored );
				}
				return;

			case 'robots_array':

				$current = 'term' === $level
					? get_term_meta( $id, $map['key'], true )
					: get_post_meta( $id, $map['key'], true );
				$current = is_array( $current ) ? $current : array();
				$current = array_values( array_filter(
					$current,
					static fn( $r ) => '' !== $r && 'noindex' !== $r && 'index' !== $r
				) );
				$current[] = $noindex ? 'noindex' : 'index';
				$current   = array_values( array_unique( $current ) );
				if ( 'term' === $level ) {
					update_term_meta( $id, $map['key'], $current );
				} else {
					update_post_meta( $id, $map['key'], $current );
				}
				return;

			case 'array_flag':
				$bag                  = get_post_meta( $id, $spec['key'], true );
				$bag                  = is_array( $bag ) ? $bag : array();
				$bag[ $map['field'] ] = $noindex ? $map['on'] : $map['off'];
				update_post_meta( $id, $spec['key'], $bag );
				return;

			case 'array_tristate':
				$bag                  = get_post_meta( $id, $spec['key'], true );
				$bag                  = is_array( $bag ) ? $bag : array();
				$bag[ $map['field'] ] = $noindex ? (int) $map['on'] : (int) $map['off'];
				update_post_meta( $id, $spec['key'], $bag );
				return;

			case 'yoast_term_noindex':
				self::yoast_term_write( $context, $id, array(
					$map['field'] => $noindex ? $map['on'] : $map['off'],
				) );
				return;

			case 'aioseo_robots':

				self::aioseo_write( $spec, $id, array(
					$map['column']    => $noindex ? 1 : 0,
					'robots_default'  => 0,
				), $context );
				return;
		}
	}

	
	private static function yoast_term_bag( string $taxonomy, int $term_id ): array {
		if ( '' === $taxonomy ) {
			return array();
		}
		$all = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! is_array( $all ) || ! isset( $all[ $taxonomy ] ) || ! is_array( $all[ $taxonomy ] ) ) {
			return array();
		}
		$tax = $all[ $taxonomy ];
		if ( isset( $tax[ $term_id ] ) && is_array( $tax[ $term_id ] ) ) {
			return $tax[ $term_id ];
		}
		$as_string = (string) $term_id;
		if ( isset( $tax[ $as_string ] ) && is_array( $tax[ $as_string ] ) ) {
			return $tax[ $as_string ];
		}
		return array();
	}

	private static function yoast_term_write( string $taxonomy, int $term_id, array $fields ): void {
		if ( '' === $taxonomy ) {
			throw new \Exception( 'taxonomy is required to write Yoast term SEO data: Yoast keys wpseo_taxonomy_meta by taxonomy, so a term ID alone does not identify a row.' );
		}
		$all = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		if ( ! isset( $all[ $taxonomy ] ) || ! is_array( $all[ $taxonomy ] ) ) {
			$all[ $taxonomy ] = array();
		}

		
		$key = $term_id;
		if ( ! isset( $all[ $taxonomy ][ $key ] ) && isset( $all[ $taxonomy ][ (string) $term_id ] ) ) {
			$key = (string) $term_id;
		}
		$existing = isset( $all[ $taxonomy ][ $key ] ) && is_array( $all[ $taxonomy ][ $key ] )
			? $all[ $taxonomy ][ $key ]
			: array();

		$all[ $taxonomy ][ $key ] = array_merge( $existing, $fields );

		$result = update_option( 'wpseo_taxonomy_meta', $all );
		if ( false === $result ) {

			$verify = self::yoast_term_bag( $taxonomy, $term_id );
			foreach ( $fields as $field => $value ) {
				if ( ! isset( $verify[ $field ] ) || $verify[ $field ] !== $value ) {
					throw new \Exception( 'Failed to write wpseo_taxonomy_meta. The option may be filtered or the write may have been blocked.' );
				}
			}
		}
	}

	
	private static function aioseo_row( array $spec, int $id ): ?array {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return null;
		}
		$table = $wpdb->prefix . $spec['table'];
		$row   = $wpdb->get_row(
			
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$spec['id_column']} = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	private static function aioseo_write( array $spec, int $id, array $columns, string $context ): void {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			throw new \Exception( 'Database unavailable.' );
		}
		$table = $wpdb->prefix . $spec['table'];

		

		$like   = $wpdb->esc_like( $table );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		if ( $exists !== $table ) {
			throw new \Exception( sprintf(
				'All in One SEO is active but its %s table does not exist. AIOSEO 4.x stores SEO data in custom tables; open any post in the WordPress editor once to let AIOSEO create them, then retry.',
				$spec['table']
			) );
		}

		$row = self::aioseo_row( $spec, $id );
		if ( null === $row ) {
			$insert = array_merge( array( $spec['id_column'] => $id ), $columns );
			
			if ( 'aioseo_terms' === $spec['table'] && '' !== $context ) {
				$insert['taxonomy'] = $context;
			}
			$ok = $wpdb->insert( $table, $insert );
		} else {
			$ok = $wpdb->update( $table, $columns, array( $spec['id_column'] => $id ) );
		}

		if ( false === $ok ) {
			throw new \Exception( sprintf(
				'Failed to write the All in One SEO %s row: %s',
				$spec['table'],
				$wpdb->last_error ? $wpdb->last_error : 'no error reported'
			) );
		}
	}

	public static function raw( string $level, int $id, string $context = '' ): array {
		$plugin = Detector::primary();
		if ( 'none' === $plugin ) {
			return array();
		}
		$spec = Fields::spec( $plugin, $level );
		if ( null === $spec ) {
			return array();
		}

		switch ( $spec['strategy'] ) {
			case 'meta_array':
				$bag = get_post_meta( $id, $spec['key'], true );
				return is_array( $bag ) ? $bag : array();

			case 'yoast_term_option':
				return self::yoast_term_bag( $context, $id );

			case 'aioseo_table':
				$row = self::aioseo_row( $spec, $id );
				return null === $row ? array() : $row;

			case 'post_meta':
			case 'term_meta':
				$out = array();
				foreach ( Fields::supported( $plugin, $level ) as $field ) {
					$map = $spec[ $field ];
					$key = is_array( $map ) ? ( $map['key'] ?? null ) : $map;
					if ( null === $key ) {
						continue;
					}
					$out[ $key ] = 'term' === $level
						? get_term_meta( $id, $key, true )
						: get_post_meta( $id, $key, true );
				}
				return $out;
		}
		return array();
	}
}
