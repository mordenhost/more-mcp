<?php

namespace Mordenhost_MCP\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Callback_Url {

	public static function with_params( string $url, array $params ): string {
		$add = array();
		foreach ( $params as $key => $value ) {
			if ( null !== $value && '' !== (string) $value ) {
				$add[ (string) $key ] = (string) $value;
			}
		}
		if ( ! $add ) {
			return $url;
		}

		$fragment = '';
		$hash     = strpos( $url, '#' );
		if ( false !== $hash ) {
			$fragment = substr( $url, $hash );
			$url      = substr( $url, 0, $hash );
		}

		$query = '';
		$mark  = strpos( $url, '?' );
		if ( false !== $mark ) {
			$query = substr( $url, $mark + 1 );
			$url   = substr( $url, 0, $mark );
		}

		$kept = array();
		foreach ( '' === $query ? array() : explode( '&', $query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$name = rawurldecode( explode( '=', $pair, 2 )[0] );
			if ( ! array_key_exists( $name, $add ) ) {
				$kept[] = $pair;
			}
		}

		$kept[] = http_build_query( $add, '', '&', PHP_QUERY_RFC3986 );
		return $url . '?' . implode( '&', $kept ) . $fragment;
	}
}
