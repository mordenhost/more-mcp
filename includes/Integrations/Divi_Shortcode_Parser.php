<?php
namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only structural parser for legacy Divi `et_pb_*` shortcodes.
 *
 * Divi does not publish a stable shortcode-to-tree API. This scanner therefore
 * owns the smallest useful contract: recognize only et_pb_* boundaries, keep
 * attributes literal, and preserve malformed regions as unsupported rather
 * than rendering or guessing at them.
 *
 * Token pairing happens before tree construction. That is load-bearing: an
 * unmatched opening tag must become one opaque leaf and must not consume every
 * valid sibling that follows it.
 */
class Divi_Shortcode_Parser {

	/**
	 * Parse content into normalized nodes and unsupported-segment diagnostics.
	 *
	 * @return array{nodes: array, unsupported_segments: array}
	 */
	public static function parse( string $content ): array {
		$tokens = self::tokenize( $content );
		$pairs  = self::pair_tokens( $tokens );
		$unsupported = [];
		$nodes = self::build_range( $content, $tokens, $pairs, 0, count( $tokens ), '', $unsupported );

		return [
			'nodes'                => $nodes,
			'unsupported_segments' => $unsupported,
		];
	}

	/** Resolve a dot-separated zero-based path in a normalized tree. */
	public static function resolve_path( array $nodes, string $path ): array {
		if ( ! self::is_valid_path( $path ) ) {
			return [ 'node' => null, 'path' => $path, 'searched_count' => 0 ];
		}

		$current = $nodes;
		$searched = 0;
		foreach ( array_map( 'intval', explode( '.', $path ) ) as $index ) {
			$searched += count( $current );
			if ( ! isset( $current[ $index ] ) || ! is_array( $current[ $index ] ) ) {
				return [ 'node' => null, 'path' => $path, 'searched_count' => $searched ];
			}
			$node = $current[ $index ];
			$current = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [];
		}

		return [ 'node' => $node, 'path' => $path, 'searched_count' => $searched ];
	}

	public static function is_valid_path( string $path ): bool {
		return $path !== '' && (bool) preg_match( '/^\d+(?:\.\d+)*$/', $path );
	}

	/** Validate caller-supplied markup as one balanced D4 shortcode subtree. */
	public static function parse_single_subtree( string $markup ): array {
		$trimmed = trim( $markup );
		if ( '' === $trimmed ) {
			return [ 'ok' => false, 'error' => 'markup is required.' ];
		}

		$parsed = self::parse( $trimmed );
		if ( count( $parsed['nodes'] ) !== 1 || ! empty( $parsed['unsupported_segments'] ) ) {
			return [ 'ok' => false, 'error' => 'D4 markup must contain exactly one balanced et_pb_* shortcode subtree.' ];
		}

		$node = $parsed['nodes'][0];
		if ( ! empty( $node['opaque'] ) || $node['raw'] !== $trimmed ) {
			return [ 'ok' => false, 'error' => 'D4 markup must contain only one complete et_pb_* shortcode subtree, with no surrounding content.' ];
		}

		return [ 'ok' => true, 'markup' => $trimmed, 'node' => $node ];
	}

	/**
	 * Convert normalized nodes into the compact nested outline returned to MCP.
	 */
	public static function outline( array $nodes, int $depth_remaining = 6 ): array {
		$out = [];
		foreach ( $nodes as $node ) {
			$entry = [
				'path'        => $node['path'],
				'type'        => $node['type'],
				'kind'        => $node['kind'],
				'module_id'   => $node['module_id'],
				'admin_label' => $node['admin_label'],
				'snippet'     => $node['snippet'],
			];
			if ( ! empty( $node['opaque'] ) ) {
				$entry['opaque'] = true;
			}
			if ( ! empty( $node['children'] ) ) {
				$entry['children'] = $depth_remaining > 0
					? self::outline( $node['children'], $depth_remaining - 1 )
					: [ [ 'truncated' => true, 'reason' => 'Depth limit reached.' ] ];
			}
			$out[] = $entry;
		}
		return $out;
	}

	/** Find structural shortcode tokens without treating quoted `[` as a tag. */
	private static function tokenize( string $content ): array {
		$tokens = [];
		$length = strlen( $content );
		$cursor = 0;

		while ( $cursor < $length ) {
			$start = strpos( $content, '[', $cursor );
			if ( false === $start ) {
				break;
			}
			$end = self::find_tag_end( $content, $start + 1 );
			if ( null === $end ) {
				break;
			}

			$raw = substr( $content, $start, $end - $start + 1 );
			$matched = false;
			if ( preg_match( '/^\[\s*\/\s*(et_pb_[a-z0-9_-]+)\s*\]$/i', $raw, $match ) ) {
				$matched = true;
				$tokens[] = [
					'type'  => 'close',
					'name'  => strtolower( $match[1] ),
					'start' => $start,
					'end'   => $end + 1,
					'raw'   => $raw,
					'attrs' => [],
				];
			} elseif ( preg_match( '/^\[\s*(et_pb_[a-z0-9_-]+)\b(.*)\]$/is', $raw, $match ) ) {
				$matched = true;
				$tail = $match[2];
				$self_closing = (bool) preg_match( '/\/\s*$/', $tail );
				if ( $self_closing ) {
					$tail = preg_replace( '/\/\s*$/', '', $tail );
				}
				$tokens[] = [
					'type'  => $self_closing ? 'self' : 'open',
					'name'  => strtolower( $match[1] ),
					'start' => $start,
					'end'   => $end + 1,
					'raw'   => $raw,
					'attrs' => self::parse_attributes( (string) $tail ),
				];
			}
			// A stray `[` in visible text can make this candidate extend through the
			// next real tag's `]`. Rescan after the stray opener so that tag survives.
			$cursor = $matched ? $end + 1 : $start + 1;
		}

		return $tokens;
	}

	/** Find `]` outside single and double quotes. */
	private static function find_tag_end( string $content, int $cursor ): ?int {
		$quote = null;
		$length = strlen( $content );
		for ( $i = $cursor; $i < $length; $i++ ) {
			$char = $content[ $i ];
			if ( null !== $quote ) {
				if ( $char === $quote && ( 0 === $i || $content[ $i - 1 ] !== '\\' ) ) {
					$quote = null;
				}
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				continue;
			}
			if ( ']' === $char ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Extract attributes literally. Supports quoted and unquoted shortcode attrs.
	 */
	private static function parse_attributes( string $input ): array {
		$attrs = [];
		preg_match_all(
			'/([a-zA-Z0-9_:-]+)\s*=\s*(?:"((?:\\.|[^"])*)"|\'((?:\\.|[^\'])*)\'|([^\s]+))/',
			$input,
			$matches,
			PREG_SET_ORDER
		);
		foreach ( $matches as $match ) {
			$value = isset( $match[2] ) && $match[2] !== ''
				? $match[2]
				: ( ( isset( $match[3] ) && $match[3] !== '' ) ? $match[3] : ( $match[4] ?? '' ) );
			$attrs[ $match[1] ] = $value;
		}
		return $attrs;
	}

	/** Pair matching open/close tokens by name, leaving malformed tags unpaired. */
	private static function pair_tokens( array $tokens ): array {
		$stacks = [];
		$pairs  = [];
		foreach ( $tokens as $index => $token ) {
			if ( 'open' === $token['type'] ) {
				$stacks[ $token['name'] ][] = $index;
				continue;
			}
			if ( 'close' !== $token['type'] || empty( $stacks[ $token['name'] ] ) ) {
				continue;
			}
			$open = array_pop( $stacks[ $token['name'] ] );
			$pairs[ $open ]  = $index;
			$pairs[ $index ] = $open;
		}
		return $pairs;
	}

	/** Build one sibling range from the pre-paired token stream. */
	private static function build_range( string $content, array $tokens, array $pairs, int $from, int $to, string $prefix, array &$unsupported ): array {
		$nodes = [];
		$i = $from;
		while ( $i < $to ) {
			$token = $tokens[ $i ];
			$path  = '' === $prefix ? (string) count( $nodes ) : $prefix . '.' . count( $nodes );

			if ( 'self' === $token['type'] ) {
				$nodes[] = self::make_node( $content, $token, null, [], $path, false );
				$i++;
				continue;
			}

			if ( 'open' === $token['type'] && isset( $pairs[ $i ] ) && $pairs[ $i ] < $to ) {
				$close_index = $pairs[ $i ];
				$children = self::build_range( $content, $tokens, $pairs, $i + 1, $close_index, $path, $unsupported );
				$nodes[]  = self::make_node( $content, $token, $tokens[ $close_index ], $children, $path, false );
				$i = $close_index + 1;
				continue;
			}

			// An unmatched opener/closer is preserved as one opaque node. Because
			// pairing was done first, this consumes only the malformed tag itself.
			$reason = 'open' === $token['type'] ? 'Unmatched opening shortcode.' : 'Unmatched closing shortcode.';
			$node = self::make_node( $content, $token, null, [], $path, true );
			$node['unsupported_reason'] = $reason;
			$nodes[] = $node;
			$unsupported[] = [ 'path' => $path, 'type' => $token['name'], 'reason' => $reason, 'raw' => $token['raw'] ];
			$i++;
		}
		return $nodes;
	}

	private static function make_node( string $content, array $open, ?array $close, array $children, string $path, bool $opaque ): array {
		$source_end = null !== $close ? $close['end'] : $open['end'];
		$inner = null !== $close ? substr( $content, $open['end'], $close['start'] - $open['end'] ) : '';
		$attrs = $open['attrs'];
		return [
			'path'        => $path,
			'type'        => $open['name'],
			'kind'        => self::kind( $open['name'] ),
			'start'       => $open['start'],
			'end'         => $source_end,
			'open_end'    => $open['end'],
			'close_start' => null !== $close ? $close['start'] : null,
			'attributes'  => $attrs,
			'module_id'   => $attrs['module_id'] ?? null,
			'admin_label' => $attrs['admin_label'] ?? null,
			'inner_text'  => $inner,
			'snippet'     => self::snippet( $inner ),
			'children'    => $children,
			'raw'         => substr( $content, $open['start'], $source_end - $open['start'] ),
			'opaque'      => $opaque,
		];
	}

	private static function kind( string $name ): string {
		$map = [
			'et_pb_section' => 'section',
			'et_pb_row'     => 'row',
			'et_pb_row_inner' => 'row',
			'et_pb_column'  => 'column',
			'et_pb_column_inner' => 'column',
		];
		return $map[ $name ] ?? 'module';
	}

	private static function snippet( string $inner ): string {
		$text = preg_replace( '/\[\/?et_pb_[^\]]*\]/i', ' ', $inner );
		$text = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) );
		if ( function_exists( 'mb_strimwidth' ) ) {
			return mb_strimwidth( $text, 0, 80, '...' );
		}
		return strlen( $text ) > 80 ? substr( $text, 0, 77 ) . '...' : $text;
	}
}
