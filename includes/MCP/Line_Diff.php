<?php

namespace More_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Line_Diff {

	const MAX_REGION = 1500;

	const CONTEXT = 2;

	public static function diff( string $old, string $new ): array {
		if ( $old === $new ) {
			return array(
				'changed'   => false,
				'added'     => 0,
				'removed'   => 0,
				'truncated' => false,
				'hunks'     => array(),
			);
		}

		$a = preg_split( '/\r\n|\n|\r/', $old );
		$b = preg_split( '/\r\n|\n|\r/', $new );
		$a = false === $a ? array( $old ) : $a;
		$b = false === $b ? array( $new ) : $b;

		$prefix = 0;
		$max    = min( count( $a ), count( $b ) );
		while ( $prefix < $max && $a[ $prefix ] === $b[ $prefix ] ) {
			++$prefix;
		}
		$suffix = 0;
		while ( $suffix < $max - $prefix && $a[ count( $a ) - 1 - $suffix ] === $b[ count( $b ) - 1 - $suffix ] ) {
			++$suffix;
		}

		$mid_a = array_slice( $a, $prefix, count( $a ) - $prefix - $suffix );
		$mid_b = array_slice( $b, $prefix, count( $b ) - $prefix - $suffix );

		if ( count( $mid_a ) > self::MAX_REGION || count( $mid_b ) > self::MAX_REGION ) {
			return array(
				'changed'   => true,
				'added'     => count( $mid_b ),
				'removed'   => count( $mid_a ),
				'truncated' => true,
				'hunks'     => array(),
			);
		}

		$ops = self::edit_script( $mid_a, $mid_b );

		$added   = 0;
		$removed = 0;
		foreach ( $ops as $op ) {
			if ( '+' === $op[0] ) {
				++$added;
			} elseif ( '-' === $op[0] ) {
				++$removed;
			}
		}

		$full = array();
		for ( $i = 0; $i < $prefix; $i++ ) {
			$full[] = array( ' ', $a[ $i ] );
		}
		foreach ( $ops as $op ) {
			$full[] = $op;
		}
		for ( $i = count( $a ) - $suffix; $i < count( $a ); $i++ ) {
			$full[] = array( ' ', $a[ $i ] );
		}

		return array(
			'changed'   => true,
			'added'     => $added,
			'removed'   => $removed,
			'truncated' => false,
			'hunks'     => self::hunks( $full ),
		);
	}

	private static function edit_script( array $a, array $b ): array {
		$n = count( $a );
		$m = count( $b );
		if ( 0 === $n ) {
			return array_map(
				static function ( $line ) {
					return array( '+', $line );
				},
				$b
			);
		}
		if ( 0 === $m ) {
			return array_map(
				static function ( $line ) {
					return array( '-', $line );
				},
				$a
			);
		}

		$lcs = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m - 1; $j >= 0; $j-- ) {
				$lcs[ $i ][ $j ] = ( $a[ $i ] === $b[ $j ] )
					? $lcs[ $i + 1 ][ $j + 1 ] + 1
					: max( $lcs[ $i + 1 ][ $j ], $lcs[ $i ][ $j + 1 ] );
			}
		}

		$ops = array();
		$i   = 0;
		$j   = 0;
		while ( $i < $n && $j < $m ) {
			if ( $a[ $i ] === $b[ $j ] ) {
				$ops[] = array( ' ', $a[ $i ] );
				++$i;
				++$j;
			} elseif ( $lcs[ $i + 1 ][ $j ] >= $lcs[ $i ][ $j + 1 ] ) {
				$ops[] = array( '-', $a[ $i ] );
				++$i;
			} else {
				$ops[] = array( '+', $b[ $j ] );
				++$j;
			}
		}
		for ( ; $i < $n; $i++ ) {
			$ops[] = array( '-', $a[ $i ] );
		}
		for ( ; $j < $m; $j++ ) {
			$ops[] = array( '+', $b[ $j ] );
		}
		return $ops;
	}

	private static function hunks( array $ops ): array {
		$count   = count( $ops );
		$changed = array();
		foreach ( $ops as $index => $op ) {
			if ( ' ' !== $op[0] ) {
				$changed[] = $index;
			}
		}
		if ( ! $changed ) {
			return array();
		}

		$old_at = array();
		$new_at = array();
		$o      = 1;
		$n      = 1;
		foreach ( $ops as $index => $op ) {
			$old_at[ $index ] = $o;
			$new_at[ $index ] = $n;
			if ( '+' !== $op[0] ) {
				++$o;
			}
			if ( '-' !== $op[0] ) {
				++$n;
			}
		}

		$hunks = array();
		$start = max( 0, $changed[0] - self::CONTEXT );
		$end   = min( $count - 1, $changed[0] + self::CONTEXT );
		foreach ( array_slice( $changed, 1 ) as $index ) {
			if ( $index - self::CONTEXT <= $end + 1 ) {
				$end = min( $count - 1, $index + self::CONTEXT );
				continue;
			}
			$hunks[] = self::hunk( $ops, $start, $end, $old_at, $new_at );
			$start   = max( 0, $index - self::CONTEXT );
			$end     = min( $count - 1, $index + self::CONTEXT );
		}
		$hunks[] = self::hunk( $ops, $start, $end, $old_at, $new_at );
		return $hunks;
	}

	private static function hunk( array $ops, int $start, int $end, array $old_at, array $new_at ): array {
		$lines = array();
		for ( $i = $start; $i <= $end; $i++ ) {
			$line    = $ops[ $i ][1];
			$lines[] = $ops[ $i ][0] . ( strlen( $line ) > 300 ? substr( $line, 0, 300 ) . '…' : $line );
		}
		return array(
			'old_start' => $old_at[ $start ],
			'new_start' => $new_at[ $start ],
			'lines'     => $lines,
		);
	}
}
