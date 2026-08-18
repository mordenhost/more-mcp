<?php
/**
 * Server-side block markup validation.
 *
 * THE HARD LIMIT, stated up front because every consumer needs to understand it:
 * Gutenberg validates blocks in the BROWSER. It re-runs each block's JavaScript
 * save() function against the parsed attributes and diffs the result against the
 * stored markup; a mismatch is what produces "This block contains unexpected or
 * invalid content" and the "Attempt Block Recovery" prompt.
 *
 * PHP cannot execute save(). Therefore nothing in this file can promise that
 * the editor will accept a given piece of markup. What it CAN do is catch the
 * errors that are decidable server-side — malformed delimiters, bad attribute
 * JSON, type mismatches against a registered schema, misplaced nesting — and
 * report how far it got.
 *
 * Hence: results carry a `confidence` level, never a boolean "valid".
 */

namespace More_MCP\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Validator {

	/** Parsed cleanly; delimiters balanced; attribute JSON well-formed. */
	const CONFIDENCE_STRUCTURAL = 'structural_ok';

	/** Structural, plus every block name is registered server-side. */
	const CONFIDENCE_REGISTERED = 'registered';

	/** Registered, plus attributes type-check against the registered schema. */
	const CONFIDENCE_SCHEMA = 'schema_ok';

	/** At least one block is not in the PHP registry. NOT an error. */
	const CONFIDENCE_UNKNOWN = 'unknown_block';

	/** Markup is malformed in a way PHP can prove. */
	const CONFIDENCE_INVALID = 'invalid';

	/**
	 * Validate a chunk of block markup.
	 *
	 * @param string $markup Raw block markup (one or more blocks).
	 * @return array {
	 *     @type string $confidence  One of the CONFIDENCE_* constants.
	 *     @type bool   $parseable   Whether parse_blocks produced a usable tree.
	 *     @type int    $block_count Number of top-level blocks found.
	 *     @type array  $errors      Hard problems. Non-empty means do not write.
	 *     @type array  $warnings    Advisory only.
	 *     @type array  $unknown     Block names absent from the PHP registry.
	 *     @type string $limitation  The save()-cannot-run caveat.
	 * }
	 */
	public static function validate( string $markup ): array {
		$errors   = [];
		$warnings = [];
		$unknown  = [];

		// --- Tier 1: structural ---------------------------------------
		$blocks = Parser::parse( $markup );

		if ( empty( $blocks ) && trim( $markup ) !== '' ) {
			return self::result(
				self::CONFIDENCE_INVALID,
				false,
				0,
				[ 'Markup could not be parsed into any blocks. Check that block comment delimiters are present and balanced, e.g. <!-- wp:paragraph --><p>text</p><!-- /wp:paragraph -->.' ],
				[],
				[]
			);
		}

		$delimiter_errors = self::check_delimiters( $markup );
		if ( ! empty( $delimiter_errors ) ) {
			return self::result( self::CONFIDENCE_INVALID, false, count( $blocks ), $delimiter_errors, [], [] );
		}

		$attr_errors = [];
		self::walk(
			$blocks,
			static function ( array $block, string $path ) use ( &$attr_errors ) {
				if ( isset( $block['attrs'] ) && ! is_array( $block['attrs'] ) ) {
					$attr_errors[] = "Block at path {$path} has malformed attributes (expected a JSON object).";
				}
			}
		);
		if ( ! empty( $attr_errors ) ) {
			return self::result( self::CONFIDENCE_INVALID, true, count( $blocks ), $attr_errors, [], [] );
		}

		// --- Tier 2: registration -------------------------------------
		if ( ! Registry::is_available() ) {
			$warnings[] = 'Block type registry unavailable on this request; registration and schema checks were skipped.';
			return self::result( self::CONFIDENCE_STRUCTURAL, true, count( $blocks ), [], $warnings, [] );
		}

		self::walk(
			$blocks,
			static function ( array $block, string $path ) use ( &$unknown ) {
				$name = $block['blockName'] ?? null;
				// null blockName = freeform classic HTML between blocks. Legal.
				if ( $name === null ) {
					return;
				}
				if ( ! Registry::is_registered( $name ) ) {
					$unknown[ $name ] = $name;
				}
			}
		);

		// --- Tier 3: attribute schema ---------------------------------
		self::walk(
			$blocks,
			static function ( array $block, string $path ) use ( &$warnings ) {
				$name = $block['blockName'] ?? null;
				if ( $name === null ) {
					return;
				}
				$type = Registry::get( $name );
				if ( $type === null ) {
					return; // unknown; already tracked
				}

				$schema = $type->attributes ?? [];
				$attrs  = $block['attrs'] ?? [];
				if ( ! is_array( $schema ) || empty( $schema ) || ! is_array( $attrs ) ) {
					return;
				}

				foreach ( $attrs as $key => $value ) {
					if ( ! isset( $schema[ $key ] ) ) {
						$warnings[] = "Block {$name} at path {$path}: attribute '{$key}' is not declared in its registered schema. It may be ignored by the editor.";
						continue;
					}
					$expected = $schema[ $key ]['type'] ?? null;
					if ( $expected !== null && ! self::type_matches( $value, $expected ) ) {
						$actual     = gettype( $value );
						$warnings[] = "Block {$name} at path {$path}: attribute '{$key}' expects type {$expected} but got {$actual}.";
					}
				}

				// A dynamic block's HTML is produced by PHP at render time, so
				// saved markup should carry no inner HTML of its own.
				if ( $type->is_dynamic() && trim( (string) ( $block['innerHTML'] ?? '' ) ) !== '' && empty( $block['innerBlocks'] ) ) {
					$warnings[] = "Block {$name} at path {$path} is a dynamic block but carries inner HTML. Dynamic blocks are normally stored self-closing, e.g. <!-- wp:{$name} {\"attr\":1} /-->.";
				}
			}
		);

		// --- Nesting constraints --------------------------------------
		$warnings = array_merge( $warnings, self::check_nesting( $blocks ) );

		if ( ! empty( $unknown ) ) {
			return self::result(
				self::CONFIDENCE_UNKNOWN,
				true,
				count( $blocks ),
				[],
				$warnings,
				array_values( $unknown )
			);
		}

		$confidence = empty( $warnings ) ? self::CONFIDENCE_SCHEMA : self::CONFIDENCE_REGISTERED;

		return self::result( $confidence, true, count( $blocks ), [], $warnings, [] );
	}

	/**
	 * Verify parent / ancestor / allowedBlocks constraints across the tree.
	 *
	 * Advisory only — these are editor-insertion rules, and content can
	 * legitimately violate them after a theme or plugin change.
	 *
	 * @param array  $blocks       Block list.
	 * @param string $parent_name  Parent block name, if any.
	 * @param array  $ancestors    Ancestor block names, outermost first.
	 * @param string $prefix       Path prefix.
	 * @return array Warning strings.
	 */
	private static function check_nesting( array $blocks, string $parent_name = '', array $ancestors = [], string $prefix = '' ): array {
		$warnings = [];

		foreach ( $blocks as $i => $block ) {
			$path = $prefix === '' ? (string) $i : $prefix . '.' . $i;
			$name = $block['blockName'] ?? null;

			if ( $name !== null ) {
				$type = Registry::get( $name );
				if ( $type !== null ) {
					if ( ! empty( $type->parent ) && is_array( $type->parent ) ) {
						if ( $parent_name === '' || ! in_array( $parent_name, $type->parent, true ) ) {
							$allowed    = implode( ', ', $type->parent );
							$actual     = $parent_name === '' ? 'the top level' : $parent_name;
							$warnings[] = "Block {$name} at path {$path} declares parent [{$allowed}] but appears inside {$actual}.";
						}
					}
					if ( ! empty( $type->ancestor ) && is_array( $type->ancestor ) ) {
						if ( empty( array_intersect( $type->ancestor, $ancestors ) ) ) {
							$allowed    = implode( ', ', $type->ancestor );
							$warnings[] = "Block {$name} at path {$path} requires an ancestor from [{$allowed}], which was not found.";
						}
					}
				}

				// Does this block's parent permit it?
				if ( $parent_name !== '' ) {
					$parent_type = Registry::get( $parent_name );
					if ( $parent_type !== null && ! empty( $parent_type->allowed_blocks ) && is_array( $parent_type->allowed_blocks ) ) {
						if ( ! in_array( $name, $parent_type->allowed_blocks, true ) ) {
							$warnings[] = "Block {$parent_name} restricts children to [" . implode( ', ', $parent_type->allowed_blocks ) . "] but contains {$name} at path {$path}.";
						}
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$warnings = array_merge(
					$warnings,
					self::check_nesting(
						$block['innerBlocks'],
						$name ?? '',
						$name !== null ? array_merge( $ancestors, [ $name ] ) : $ancestors,
						$path
					)
				);
			}
		}

		return $warnings;
	}

	/**
	 * Balance-check block comment delimiters.
	 *
	 * parse_blocks() is famously forgiving — it recovers from unbalanced
	 * delimiters by reinterpreting them rather than failing. That silent
	 * recovery is exactly what produces surprising output, so count them here.
	 *
	 * @param string $markup Raw markup.
	 * @return array Error strings.
	 */
	private static function check_delimiters( string $markup ): array {
		$errors = [];

		preg_match_all( '/<!--\s+wp:([a-z0-9-]+\/[a-z0-9-]+|[a-z0-9-]+)(\s+\{.*?\})?\s+(\/)?-->/s', $markup, $opens, PREG_SET_ORDER );
		preg_match_all( '/<!--\s+\/wp:([a-z0-9-]+\/[a-z0-9-]+|[a-z0-9-]+)\s+-->/s', $markup, $closes, PREG_SET_ORDER );

		$open_names  = [];
		$close_names = [];

		foreach ( $opens as $m ) {
			// A self-closing block (trailing /) needs no closer.
			if ( isset( $m[3] ) && $m[3] === '/' ) {
				continue;
			}
			$open_names[] = $m[1];
		}
		foreach ( $closes as $m ) {
			$close_names[] = $m[1];
		}

		if ( count( $open_names ) !== count( $close_names ) ) {
			$errors[] = sprintf(
				'Unbalanced block delimiters: %d opening tag(s) requiring a closer, but %d closing tag(s).',
				count( $open_names ),
				count( $close_names )
			);
		}

		$open_counts  = array_count_values( $open_names );
		$close_counts = array_count_values( $close_names );
		foreach ( $open_counts as $name => $count ) {
			$closed = $close_counts[ $name ] ?? 0;
			if ( $closed !== $count ) {
				$errors[] = "Block '{$name}': {$count} opening tag(s) but {$closed} closing tag(s).";
			}
		}
		foreach ( $close_counts as $name => $count ) {
			if ( ! isset( $open_counts[ $name ] ) ) {
				$errors[] = "Closing tag for '{$name}' has no matching opening tag.";
			}
		}

		// Attribute JSON must parse.
		foreach ( $opens as $m ) {
			if ( ! empty( $m[2] ) ) {
				$json = trim( $m[2] );
				json_decode( $json, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$errors[] = sprintf( "Block '%s' has malformed attribute JSON: %s", $m[1], json_last_error_msg() );
				}
			}
		}

		return $errors;
	}

	/**
	 * Loose type check of an attribute value against a schema type.
	 *
	 * Deliberately permissive: numeric strings satisfy number/integer because
	 * that is how they survive a JSON round-trip through the editor.
	 *
	 * @param mixed  $value    Attribute value.
	 * @param string $expected Declared type.
	 * @return bool
	 */
	private static function type_matches( $value, string $expected ): bool {
		switch ( $expected ) {
			case 'string':
				return is_string( $value );
			case 'number':
				return is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) );
			case 'integer':
				return is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) );
			case 'boolean':
				return is_bool( $value );
			case 'array':
				return is_array( $value );
			case 'object':
				return is_array( $value ) || is_object( $value );
			case 'null':
				return $value === null;
			default:
				return true; // unknown declared type — do not guess
		}
	}

	/**
	 * Depth-first walk, invoking a callback per block with its path.
	 *
	 * @param array    $blocks   Block list.
	 * @param callable $callback fn(array $block, string $path): void
	 * @param string   $prefix   Path prefix.
	 */
	private static function walk( array $blocks, callable $callback, string $prefix = '' ): void {
		foreach ( $blocks as $i => $block ) {
			$path = $prefix === '' ? (string) $i : $prefix . '.' . $i;
			$callback( $block, $path );
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $callback, $path );
			}
		}
	}

	/**
	 * Assemble a validation result.
	 *
	 * @param string $confidence  Confidence constant.
	 * @param bool   $parseable   Whether parsing produced a tree.
	 * @param int    $block_count Top-level block count.
	 * @param array  $errors      Hard errors.
	 * @param array  $warnings    Advisory warnings.
	 * @param array  $unknown     Unregistered block names.
	 * @return array
	 */
	private static function result( string $confidence, bool $parseable, int $block_count, array $errors, array $warnings, array $unknown ): array {
		$result = [
			'confidence'  => $confidence,
			'parseable'   => $parseable,
			'block_count' => $block_count,
			'errors'      => array_values( $errors ),
			'warnings'    => array_values( $warnings ),
			'limitation'  => 'Server-side validation cannot execute a block\'s JavaScript save() function, which is what the editor compares saved markup against. Markup passing these checks may still be flagged in the editor. Opening the post in the block editor is the only conclusive check.',
		];

		if ( ! empty( $unknown ) ) {
			$result['unknown_blocks'] = array_values( $unknown );
			$result['unknown_note']   = 'These block names are not registered on the PHP side. ' . Registry::registry_caveat() . ' This is NOT reported as an error — the blocks may be perfectly valid and registered only in JavaScript.';
		}

		return $result;
	}

	/**
	 * Human-readable one-liner describing a confidence level.
	 *
	 * @param string $confidence Confidence constant.
	 * @return string
	 */
	public static function explain( string $confidence ): string {
		switch ( $confidence ) {
			case self::CONFIDENCE_SCHEMA:
				return 'Parsed cleanly, all blocks registered, and all attributes match their registered schemas. Highest confidence PHP can offer.';
			case self::CONFIDENCE_REGISTERED:
				return 'Parsed cleanly and all blocks are registered, but there are schema or nesting warnings worth reviewing.';
			case self::CONFIDENCE_STRUCTURAL:
				return 'Markup parses and delimiters balance. Registration and schema checks were not performed.';
			case self::CONFIDENCE_UNKNOWN:
				return 'Markup is structurally sound, but one or more blocks are not registered server-side, so their attributes could not be checked. Not an error.';
			case self::CONFIDENCE_INVALID:
				return 'Markup is malformed in a way that is provable server-side. Do not write this content.';
			default:
				return 'Unrecognized confidence level.';
		}
	}
}
