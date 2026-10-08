<?php
namespace More_MCP\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ip_Matcher {

	public static function parse( string $raw ): array {
		$entries = array();
		$invalid = array();

		foreach ( preg_split( '/[\s,;]+/', trim( $raw ), -1, PREG_SPLIT_NO_EMPTY ) as $item ) {
			if ( null !== self::compile( $item ) ) {
				$entries[ $item ] = $item; 
			} else {
				$invalid[] = $item;
			}
		}

		return array(
			'entries' => array_values( $entries ),
			'invalid' => $invalid,
		);
	}

	public static function matches( string $ip, array $entries ): bool {
		$addr = self::to_binary( $ip );
		if ( null === $addr ) {
			return false;
		}

		foreach ( $entries as $entry ) {
			$net = self::compile( (string) $entry );
			if ( null === $net || strlen( $net['addr'] ) !== strlen( $addr ) ) {
				continue;
			}
			if ( self::prefix_equal( $addr, $net['addr'], $net['bits'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function client_ip( array $server, array $trusted ): string {
		$remote = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
		if ( null === self::to_binary( $remote ) ) {
			return '';
		}

		if ( empty( $trusted ) || ! self::matches( $remote, $trusted ) ) {
			return $remote;
		}

		$forwarded = isset( $server['HTTP_X_FORWARDED_FOR'] ) ? (string) $server['HTTP_X_FORWARDED_FOR'] : '';
		if ( '' === trim( $forwarded ) ) {
			return $remote;
		}

		foreach ( array_reverse( explode( ',', $forwarded ) ) as $hop ) {
			$hop = trim( $hop );
			if ( null === self::to_binary( $hop ) ) {

				return $remote;
			}
			if ( ! self::matches( $hop, $trusted ) ) {
				return $hop;
			}
		}

		return $remote;
	}

	private static function compile( string $entry ): ?array {
		$slash = strpos( $entry, '/' );
		if ( false === $slash ) {
			$addr = self::to_binary( $entry );
			return null === $addr ? null : array(
				'addr' => $addr,
				'bits' => strlen( $addr ) * 8,
			);
		}

		$addr = self::to_binary( substr( $entry, 0, $slash ) );
		$len  = substr( $entry, $slash + 1 );
		if ( null === $addr || '' === $len || ! ctype_digit( $len ) ) {
			return null;
		}

		$bits = (int) $len;
		if ( $bits > strlen( $addr ) * 8 ) {
			return null;
		}
		return array(
			'addr' => $addr,
			'bits' => $bits,
		);
	}

	private static function to_binary( string $ip ): ?string {
		$ip = trim( $ip );
		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return null;
		}

		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return null;
		}

		if ( 16 === strlen( $packed ) && 0 === strncmp( $packed, str_repeat( "\0", 10 ) . "\xff\xff", 12 ) ) {
			return substr( $packed, 12 );
		}
		return $packed;
	}

	private static function prefix_equal( string $a, string $b, int $bits ): bool {
		$bytes = intdiv( $bits, 8 );
		if ( $bytes > 0 && 0 !== strncmp( $a, $b, $bytes ) ) {
			return false;
		}

		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
		return ( ord( $a[ $bytes ] ) & $mask ) === ( ord( $b[ $bytes ] ) & $mask );
	}
}
