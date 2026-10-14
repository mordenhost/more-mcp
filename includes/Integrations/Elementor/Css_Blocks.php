<?php

namespace Mordenhost_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Css_Blocks {

	const GROUP_RULES = array( 'media', 'supports', 'container', 'layer', 'scope', 'document', '-moz-document', 'starting-style' );

	const MAX_DEPTH = 6;

	const PRELUDE_CHARS = 200;

	public static function split( string $css, int $from = 0, ?int $to = null, int $depth = 0 ): array {
		$to     = null === $to ? strlen( $css ) : min( $to, strlen( $css ) );
		$blocks = array();
		$i      = $from;
		while ( $i < $to ) {
			$c = $css[ $i ];
			if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c || "\f" === $c ) {
				++$i;
				continue;
			}
			if ( '/' === $c && $i + 1 < $to && '*' === $css[ $i + 1 ] ) {
				$end      = self::skip_comment( $css, $i, $to );
				$blocks[] = array(
					'kind'    => 'comment',
					'start'   => $i,
					'end'     => $end,
					'prelude' => self::clip( preg_replace( '/\s+/', ' ', trim( substr( $css, $i + 2, max( 0, $end - $i - 4 ) ) ) ) ),
				);
				$i        = $end;
				continue;
			}
			if ( '}' === $c ) {
				
				++$i;
				continue;
			}

			$start = $i;
			$is_at = '@' === $c;
			$stop  = null;
			$j     = $i;
			while ( $j < $to ) {
				$next = self::skip_token( $css, $j, $to );
				if ( $next !== $j ) {
					$j = $next;
					continue;
				}
				$d = $css[ $j ];
				if ( '{' === $d || '}' === $d || ( ';' === $d && $is_at ) ) {
					$stop = $d;
					break;
				}
				++$j;
			}

			$prelude = self::clip( preg_replace( '/\s+/', ' ', trim( self::strip_comments( substr( $css, $start, $j - $start ) ) ) ) );
			if ( '{' === $stop ) {
				$unterminated = false;
				$end          = self::block_end( $css, $j, $to, $unterminated );
				$block        = array(
					'kind'    => $is_at ? 'at-rule' : 'rule',
					'start'   => $start,
					'end'     => $end,
					'prelude' => $prelude,
				);
				if ( $unterminated ) {
					$block['unterminated'] = true;
				}
				if ( $is_at && $depth < self::MAX_DEPTH && in_array( self::at_rule_name( $css, $start, $j ), self::GROUP_RULES, true ) ) {
					$block['children'] = self::split( $css, $j + 1, $unterminated ? $end : $end - 1, $depth + 1 );
				}
				$blocks[] = $block;
				$i        = $end;
			} elseif ( ';' === $stop ) {
				$blocks[] = array(
					'kind'    => 'statement',
					'start'   => $start,
					'end'     => $j + 1,
					'prelude' => $prelude,
				);
				$i        = $j + 1;
			} else {

				$blocks[] = array(
					'kind'    => 'text',
					'start'   => $start,
					'end'     => $j,
					'prelude' => $prelude,
				);
				$i        = max( $j, $start + 1 );
			}
		}
		return $blocks;
	}

	public static function search( string $css, string $term, int $budget, int $max_entries ): array {
		$blocks = self::split( $css );
		$state  = array(
			'css'       => $css,
			'term'      => $term,
			'budget'    => $budget,
			'used'      => 0,
			'omitted'   => 0,
			'entries'   => 0,
			'max'       => $max_entries,
			'ascii'     => strlen( $css ) === mb_strlen( $css, 'UTF-8' ),
		);
		$matched = 0;
		$matches = array();
		foreach ( $blocks as $block ) {
			if ( ! self::block_contains( $css, $block, $term ) ) {
				continue;
			}
			++$matched;
			if ( $state['entries'] >= $state['max'] ) {
				continue;
			}
			$matches[] = self::entry( $block, $state );
		}
		return array(
			'blocks_total'   => count( $blocks ),
			'matched'        => $matched,
			'listed'         => count( $matches ),
			'returned_chars' => $state['used'],
			'omitted'        => $state['omitted'],
			'matches'        => $matches,
		);
	}

	private static function entry( array $block, array &$state ): array {
		++$state['entries'];
		$css   = $state['css'];
		$entry = array(
			'offset'  => self::chars( $css, $block['start'], $state['ascii'] ),
			'length'  => self::chars( $css, $block['end'], $state['ascii'] ) - self::chars( $css, $block['start'], $state['ascii'] ),
			'kind'    => $block['kind'],
			'prelude' => $block['prelude'],
		);
		if ( ! empty( $block['unterminated'] ) ) {
			$entry['unterminated'] = true;
		}

		if ( ! empty( $block['children'] ) && false === stripos( $block['prelude'], $state['term'] ) ) {
			$nested    = array();
			$truncated = false;
			foreach ( $block['children'] as $child ) {
				if ( ! self::block_contains( $css, $child, $state['term'] ) ) {
					continue;
				}
				if ( $state['entries'] >= $state['max'] ) {
					$truncated = true;
					break;
				}
				$nested[] = self::entry( $child, $state );
			}

			if ( ! empty( $nested ) ) {
				$entry['nested'] = $nested;
				if ( $truncated ) {
					$entry['nested_truncated'] = true;
				}
				return $entry;
			}
		}

		if ( $state['used'] + $entry['length'] <= $state['budget'] ) {
			$entry['css']   = substr( $css, $block['start'], $block['end'] - $block['start'] );
			$state['used'] += $entry['length'];
		} else {
			$entry['css_omitted'] = true;
			++$state['omitted'];
		}
		return $entry;
	}

	private static function block_contains( string $css, array $block, string $term ): bool {
		return false !== stripos( substr( $css, $block['start'], $block['end'] - $block['start'] ), $term );
	}

	private static function chars( string $css, int $byte, bool $ascii ): int {
		return $ascii ? $byte : mb_strlen( substr( $css, 0, $byte ), 'UTF-8' );
	}

	private static function skip_token( string $css, int $i, int $to ): int {
		$c = $css[ $i ];
		if ( '/' === $c && $i + 1 < $to && '*' === $css[ $i + 1 ] ) {
			return self::skip_comment( $css, $i, $to );
		}
		if ( '"' === $c || "'" === $c ) {
			return self::skip_string( $css, $i, $to );
		}
		if ( '\\' === $c ) {
			return min( $to, $i + 2 );
		}
		if ( ( 'u' === $c || 'U' === $c ) && $i + 3 < $to && 0 === strncasecmp( substr( $css, $i, 4 ), 'url(', 4 )
			&& ( 0 === $i || ! preg_match( '/[\w-]/', $css[ $i - 1 ] ) ) ) {

			$k = $i + 4;
			while ( $k < $to && ( ' ' === $css[ $k ] || "\t" === $css[ $k ] || "\n" === $css[ $k ] || "\r" === $css[ $k ] ) ) {
				++$k;
			}
			if ( $k < $to && ( '"' === $css[ $k ] || "'" === $css[ $k ] ) ) {
				return $i;
			}
			while ( $k < $to && ')' !== $css[ $k ] ) {
				$k += ( '\\' === $css[ $k ] ) ? 2 : 1;
			}
			return min( $to, $k + 1 );
		}
		return $i;
	}

	private static function skip_comment( string $css, int $i, int $to ): int {
		$close = strpos( $css, '*/', $i + 2 );
		return ( false === $close || $close + 2 > $to ) ? $to : $close + 2;
	}

	private static function skip_string( string $css, int $i, int $to ): int {
		$quote = $css[ $i ];
		$k     = $i + 1;
		while ( $k < $to ) {
			$d = $css[ $k ];
			if ( '\\' === $d ) {
				$k += 2;
				continue;
			}
			if ( $d === $quote ) {
				return $k + 1;
			}
			if ( "\n" === $d ) {
				return $k;
			}
			++$k;
		}
		return $to;
	}

	private static function block_end( string $css, int $open, int $to, bool &$unterminated ): int {
		$depth = 0;
		$k     = $open;
		while ( $k < $to ) {
			$next = self::skip_token( $css, $k, $to );
			if ( $next !== $k ) {
				$k = $next;
				continue;
			}
			$d = $css[ $k ];
			if ( '{' === $d ) {
				++$depth;
			} elseif ( '}' === $d ) {
				--$depth;
				if ( 0 === $depth ) {
					return $k + 1;
				}
			}
			++$k;
		}
		$unterminated = true;
		return $to;
	}

	private static function at_rule_name( string $css, int $at, int $limit ): string {
		preg_match( '/@([\w-]+)/A', substr( $css, $at, min( 64, $limit - $at ) ), $m );
		return isset( $m[1] ) ? strtolower( $m[1] ) : '';
	}

	private static function strip_comments( string $text ): string {
		return (string) preg_replace( '#/\*.*?(\*/|$)#s', ' ', $text );
	}

	private static function clip( ?string $text ): string {
		$text = (string) $text;
		if ( mb_strlen( $text, 'UTF-8' ) > self::PRELUDE_CHARS ) {
			$text = mb_substr( $text, 0, self::PRELUDE_CHARS - 1, 'UTF-8' ) . '…';
		}
		return $text;
	}
}
