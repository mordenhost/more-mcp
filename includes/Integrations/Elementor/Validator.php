<?php

namespace Mordenhost_MCP\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Validator {

	const MAX_BYTES = 5242880;

	const MAX_ELEMENTS = 20000;

	const MAX_DEPTH = 64;

	const MAX_ISSUES = 150;

	const TEMPLATE_TYPES = array(
		'page', 'section', 'container', 'widget', 'popup', 'header', 'footer',
		'single', 'single-post', 'single-page', 'archive', 'search-results',
		'error-404', 'loop-item', 'product', 'product-archive', 'landing-page',
		'kit', 'code_snippet',
	);

	const NESTING_FALLBACK = array( 'nested-tabs', 'nested-accordion', 'nested-carousel', 'mega-menu' );

	private $options;

	private $format = null;

	private $target = 'mcp';

	private $errors = array();

	private $warnings = array();

	private $suppressed = array( 'errors' => 0, 'warnings' => 0 );

	private $once = array();

	private $ids = array();

	private $stats;

	private $limit_hit = false;

	private $registry;

	private $documents = array();

	private function __construct( array $options ) {
		$this->options = array(
			'format'           => in_array( $options['format'] ?? 'auto', array( 'auto', 'elements', 'template', 'wxr' ), true ) ? ( $options['format'] ?? 'auto' ) : 'auto',
			'target'           => in_array( $options['target'] ?? null, array( 'mcp', 'raw' ), true ) ? $options['target'] : null,
			'classify'         => isset( $options['classify'] ) && is_callable( $options['classify'] ) ? $options['classify'] : null,
			'supports_nesting' => isset( $options['supports_nesting'] ) && is_callable( $options['supports_nesting'] ) ? $options['supports_nesting'] : null,
		);
		$this->registry = $this->options['classify'] ? 'checked' : 'skipped';
		$this->stats    = self::empty_stats();
	}

	public static function validate( $input, array $options = array() ): array {
		$validator = new self( $options );
		$validator->run( $input );
		return $validator->result();
	}

	private function run( $input ): void {
		$format = $this->options['format'];

		if ( is_object( $input ) || is_array( $input ) ) {
			
			$encoded = json_encode( $input );
			if ( false !== $encoded ) {
				$input = json_decode( $encoded, true );
			}
		}

		if ( is_string( $input ) ) {
			if ( strlen( $input ) > self::MAX_BYTES ) {
				$this->target = $this->options['target'] ?? 'mcp';
				$this->error( 'too_large', '$', sprintf( 'The input is %d bytes; the validator accepts up to %d. Validate one document or template at a time.', strlen( $input ), self::MAX_BYTES ) );
				return;
			}
			if ( 0 === strncmp( $input, "\xEF\xBB\xBF", 3 ) ) {
				$input = substr( $input, 3 );
			}
			$trimmed = ltrim( $input );
			if ( '' === $trimmed ) {
				$this->target = $this->options['target'] ?? 'mcp';
				$this->error( 'empty_input', '$', 'The input is empty.' );
				return;
			}
			$looks_xml = '<' === $trimmed[0];
			if ( 'wxr' === $format || ( 'auto' === $format && $looks_xml ) ) {
				$this->format = 'wxr';
				$this->target = $this->options['target'] ?? 'raw';
				$this->validate_wxr( $trimmed );
				return;
			}
			$this->target = $this->options['target'] ?? 'mcp';
			if ( $looks_xml ) {
				$this->error( 'format_mismatch', '$', sprintf( 'The input is XML but format is "%s". Pass format "wxr" (or "auto") for a WordPress export file.', $format ) );
				return;
			}
			$decoded = $this->decode_json( $input, '$' );
			if ( null === $decoded ) {
				return;
			}
			$this->validate_decoded( $decoded, '$', $format, true );
			return;
		}

		$this->target = $this->options['target'] ?? 'mcp';
		if ( 'wxr' === $format ) {
			$this->error( 'format_mismatch', '$', 'format "wxr" needs the export file as an XML string.' );
			return;
		}
		$this->validate_decoded( $input, '$', $format, true );
	}

	

	
	private function decode_json( string $json, string $path, int $unwrap = 0 ) {
		$decoded = json_decode( $json, true, 512, JSON_BIGINT_AS_STRING );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$message = json_last_error_msg();
			$where   = self::locate_json_error( $json );
			$this->error(
				'json_syntax',
				$path,
				sprintf( 'Invalid JSON at line %d, column %d: %s (PHP: %s).', $where['line'], $where['column'], $where['hint'], $message ),
				array(
					'line'   => $where['line'],
					'column' => $where['column'],
					'before' => $where['before'],
					'after'  => $where['after'],
				)
			);
			return null;
		}
		if ( is_string( $decoded ) && $unwrap < 2 ) {
			$inner = ltrim( $decoded );
			if ( '' !== $inner && ( '[' === $inner[0] || '{' === $inner[0] ) ) {
				$this->warning( 'double_encoded', $path, 'The JSON is a string that itself holds JSON (encoded twice). It was decoded again to validate it, but Elementor stores the inner JSON once: send the array itself, not a string of it.' );
				return $this->decode_json( $decoded, $path, $unwrap + 1 );
			}
		}
		if ( null === $decoded ) {
			$this->error( 'wrong_shape', $path, 'The JSON is null. Elementor data is an array of elements, or a template export object with a "content" array.' );
		}
		return $decoded;
	}

	public static function locate_json_error( string $json ): array {
		$i   = 0;
		$err = null;
		self::skip_ws( $json, $i );
		if ( self::scan_value( $json, $i, $err, 0 ) ) {
			self::skip_ws( $json, $i );
			if ( $i < strlen( $json ) ) {
				$err = array( $i, 'unexpected text after the end of the JSON value' );
			}
		}
		if ( null === $err ) {
			$bad = self::first_invalid_utf8( $json );
			$err = null === $bad
				? array( strlen( $json ), 'the document could not be decoded' )
				: array( $bad, 'invalid UTF-8 byte sequence: re-save the text as UTF-8' );
		}
		list( $offset, $hint ) = $err;
		$head   = substr( $json, 0, $offset );
		$line   = substr_count( $head, "\n" ) + 1;
		$nl     = strrpos( $head, "\n" );
		$column = false === $nl ? $offset + 1 : $offset - $nl;
		$clean  = static function ( $s ) {
			return (string) preg_replace( '/\s+/', ' ', $s );
		};
		return array(
			'offset' => $offset,
			'line'   => $line,
			'column' => $column,
			'hint'   => $hint,
			'before' => $clean( substr( $json, max( 0, $offset - 30 ), min( 30, $offset ) ) ),
			'after'  => $clean( substr( $json, $offset, 30 ) ),
		);
	}

	private static function skip_ws( string $s, int &$i ): void {
		$i += strspn( $s, " \t\r\n", $i );
	}

	private static function scan_value( string $s, int &$i, ?array &$err, int $depth ): bool {
		$n = strlen( $s );
		if ( $depth > 512 ) {
			$err = array( $i, 'nesting is deeper than 512 levels' );
			return false;
		}
		if ( $i >= $n ) {
			$err = array( $i, 'the JSON ends where a value was expected (truncated?)' );
			return false;
		}
		$c = $s[ $i ];
		if ( '{' === $c ) {
			$i++;
			self::skip_ws( $s, $i );
			if ( $i < $n && '}' === $s[ $i ] ) {
				$i++;
				return true;
			}
			while ( true ) {
				self::skip_ws( $s, $i );
				if ( $i >= $n ) {
					$err = array( $i, 'the JSON ends inside an object: a closing } is missing' );
					return false;
				}
				if ( '"' !== $s[ $i ] ) {
					$k = $s[ $i ];
					if ( '}' === $k ) {
						$err = array( $i, 'trailing comma before }' );
					} elseif ( "'" === $k ) {
						$err = array( $i, 'object keys must use double quotes, not single quotes' );
					} elseif ( '/' === $k ) {
						$err = array( $i, 'comments are not allowed in JSON' );
					} else {
						$err = array( $i, 'object keys must be double-quoted strings' );
					}
					return false;
				}
				if ( ! self::scan_string( $s, $i, $err ) ) {
					return false;
				}
				self::skip_ws( $s, $i );
				if ( $i >= $n || ':' !== $s[ $i ] ) {
					$err = array( $i, 'expected : after the object key' );
					return false;
				}
				$i++;
				self::skip_ws( $s, $i );
				if ( ! self::scan_value( $s, $i, $err, $depth + 1 ) ) {
					return false;
				}
				self::skip_ws( $s, $i );
				if ( $i < $n && ',' === $s[ $i ] ) {
					$i++;
					continue;
				}
				if ( $i < $n && '}' === $s[ $i ] ) {
					$i++;
					return true;
				}
				$err = $i >= $n
					? array( $i, 'the JSON ends inside an object: a closing } is missing' )
					: array( $i, '"' === $s[ $i ] ? 'missing comma between object members' : 'expected , or } after the value' );
				return false;
			}
		}
		if ( '[' === $c ) {
			$i++;
			self::skip_ws( $s, $i );
			if ( $i < $n && ']' === $s[ $i ] ) {
				$i++;
				return true;
			}
			while ( true ) {
				self::skip_ws( $s, $i );
				if ( $i < $n && ']' === $s[ $i ] ) {
					$err = array( $i, 'trailing comma before ]' );
					return false;
				}
				if ( ! self::scan_value( $s, $i, $err, $depth + 1 ) ) {
					return false;
				}
				self::skip_ws( $s, $i );
				if ( $i < $n && ',' === $s[ $i ] ) {
					$i++;
					continue;
				}
				if ( $i < $n && ']' === $s[ $i ] ) {
					$i++;
					return true;
				}
				$err = $i >= $n
					? array( $i, 'the JSON ends inside an array: a closing ] is missing' )
					: array( $i, false !== strpos( '{["-0123456789tfn', $s[ $i ] ) ? 'missing comma between array items' : 'expected , or ] after the value' );
				return false;
			}
		}
		if ( '"' === $c ) {
			return self::scan_string( $s, $i, $err );
		}
		foreach ( array( 'true', 'false', 'null' ) as $literal ) {
			if ( 0 === substr_compare( $s, $literal, $i, strlen( $literal ) ) ) {
				$i += strlen( $literal );
				return true;
			}
		}
		if ( '-' === $c || ( $c >= '0' && $c <= '9' ) ) {
			if ( preg_match( '/\G-?(?:0|[1-9][0-9]*+)(?:\.[0-9]++)?(?:[eE][+-]?[0-9]++)?/', $s, $m, 0, $i ) && '' !== $m[0] && '-' !== $m[0] ) {
				$i += strlen( $m[0] );
				return true;
			}
			$err = array( $i, 'invalid number' );
			return false;
		}
		if ( "'" === $c ) {
			$err = array( $i, 'strings must use double quotes, not single quotes' );
		} elseif ( '/' === $c ) {
			$err = array( $i, 'comments are not allowed in JSON' );
		} elseif ( preg_match( '/\G(?:undefined|NaN|-?Infinity|True|False|None)\b/', $s, $m, 0, $i ) ) {
			$err = array( $i, sprintf( '"%s" is not a JSON value (use null, true, false or a number)', $m[0] ) );
		} else {
			$err = array( $i, sprintf( 'unexpected character "%s"', ( ord( $c ) >= 32 && ord( $c ) < 127 ) ? $c : sprintf( '\\x%02X', ord( $c ) ) ) );
		}
		return false;
	}

	private static function scan_string( string $s, int &$i, ?array &$err ): bool {
		static $stop = null;
		if ( null === $stop ) {
			$stop = "\"\\";
			for ( $b = 0; $b < 32; $b++ ) {
				$stop .= chr( $b );
			}
		}
		$n     = strlen( $s );
		$start = $i;
		$i++;
		while ( true ) {
			$i += strcspn( $s, $stop, $i );
			if ( $i >= $n ) {
				$err = array( $start, 'unterminated string: the closing " is missing' );
				return false;
			}
			$c = $s[ $i ];
			if ( '"' === $c ) {
				$i++;
				return true;
			}
			if ( '\\' === $c ) {
				$e = $s[ $i + 1 ] ?? '';
				if ( 'u' === $e ) {
					if ( 1 !== preg_match( '/\G[0-9a-fA-F]{4}/', $s, $m, 0, $i + 2 ) ) {
						$err = array( $i, 'invalid \\u escape: four hex digits must follow' );
						return false;
					}
					$i += 6;
					continue;
				}
				if ( '' === $e || false === strpos( '"\\/bfnrt', $e ) ) {
					$err = array( $i, sprintf( 'invalid escape sequence "\\%s" (a backslash must be written as \\\\)', $e ) );
					return false;
				}
				$i += 2;
				continue;
			}
			$err = array( $i, 'a raw control character (such as a line break or tab) is inside a string: write it as \\n or \\t' );
			return false;
		}
	}

	private static function first_invalid_utf8( string $s ): ?int {
		if ( 1 === preg_match( '//u', $s ) ) {
			return null;
		}
		$ok = preg_match( '/\A(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})*+/', $s, $m );
		return 1 === $ok ? strlen( $m[0] ) : 0;
	}

	

	
	private function validate_decoded( $data, string $path, string $format, bool $top ): void {
		if ( ! is_array( $data ) ) {
			$this->error( 'wrong_shape', $path, sprintf( 'Expected an array of elements or a template export object, got %s.', self::type_of( $data ) ) );
			return;
		}

		if ( self::is_list( $data ) ) {
			if ( $top ) {
				$this->format = 'elements';
				if ( 'template' === $format ) {
					$this->warning( 'format_mismatch', $path, 'format is "template" but the input is a bare element array, not an export object with "content". It was validated as an element tree.' );
				}
			}
			$this->validate_tree( $data, $path );
			return;
		}

		if ( ! $top ) {
			$this->error( 'wrong_shape', $path, '_elementor_data must be an array of elements; this is an object.' );
			return;
		}

		if ( array_key_exists( 'content', $data ) ) {
			if ( $top ) {
				$this->format = 'template';
			}
			$this->validate_envelope( $data, $path );
			if ( ! is_array( $data['content'] ) || ! self::is_list( $data['content'] ) ) {
				$this->error( 'content_not_array', $path . '.content', sprintf( '"content" must be an array of elements, got %s.', self::type_of( $data['content'] ) ) );
				return;
			}
			if ( array() === $data['content'] ) {
				$this->error( 'empty_content', $path . '.content', 'The template has no elements. Elementor refuses to import a template whose content is empty.' );
				return;
			}
			$this->validate_tree( $data['content'], $path . '.content' );
			return;
		}

		if ( array_key_exists( 'elType', $data ) ) {
			if ( $top ) {
				$this->format = 'elements';
			}
			$this->warning( 'single_element', $path, 'A single element object was given. Elementor data is an array of elements: wrap it in [ ].' );
			$this->ids = array();
			$this->check_element( $data, $path, array( 'kind' => 'root' ), 1 );
			return;
		}

		$keys = array_slice( array_map( 'strval', array_keys( $data ) ), 0, 8 );
		$this->error( 'wrong_shape', $path, sprintf( 'This object is neither an element (no "elType") nor a template export (no "content"). Keys found: %s.', implode( ', ', $keys ) ) );
	}

	private function validate_envelope( array $data, string $path ): void {
		if ( ! isset( $data['version'] ) || ! is_string( $data['version'] ) || '' === $data['version'] ) {
			$this->warning( 'missing_version', $path . '.version', 'No "version". Elementor reads it on import to decide which data upgrades to run; exports carry e.g. "0.4".' );
		}
		if ( ! isset( $data['title'] ) || ! is_string( $data['title'] ) || '' === trim( $data['title'] ) ) {
			$this->warning( 'missing_title', $path . '.title', 'No "title": the imported template gets no name in the library.' );
		}
		if ( ! isset( $data['type'] ) || ! is_string( $data['type'] ) || '' === $data['type'] ) {
			$this->issue(
				'raw' === $this->target,
				'missing_type',
				$path . '.type',
				'No "type". Elementor\'s template importer needs it to create the document; elementor_import_template takes the type from its own template_type argument instead.'
			);
		} elseif ( ! in_array( $data['type'], self::TEMPLATE_TYPES, true ) ) {
			$this->warning( 'unknown_template_type', $path . '.type', sprintf( 'Template type "%s" is not one Elementor or Elementor Pro defines (%s). An add-on may define it; otherwise the import fails.', $data['type'], implode( ', ', self::TEMPLATE_TYPES ) ) );
		}
		if ( array_key_exists( 'page_settings', $data ) && ! is_array( $data['page_settings'] ) ) {
			$this->warning( 'page_settings_not_object', $path . '.page_settings', sprintf( '"page_settings" must be an object (or an empty array), got %s; it is ignored.', self::type_of( $data['page_settings'] ) ) );
		}
	}

	

	private function validate_tree( array $elements, string $path ): void {
		$this->ids = array();
		if ( array() === $elements ) {
			$this->warning( 'empty_tree', $path, 'The element array is empty: the page would render no Elementor content.' );
			return;
		}
		foreach ( $elements as $index => $element ) {
			$this->check_element( $element, $path . '[' . $index . ']', array( 'kind' => 'root' ), 1 );
			if ( $this->limit_hit ) {
				return;
			}
		}
	}

	private function check_element( $element, string $path, array $parent, int $depth ): void {
		if ( $this->limit_hit ) {
			return;
		}
		if ( ++$this->stats['elements'] > self::MAX_ELEMENTS ) {
			$this->limit_hit = true;
			$this->error( 'too_many_elements', $path, sprintf( 'More than %d elements: validation stopped here. Split the page or validate templates one at a time.', self::MAX_ELEMENTS ) );
			return;
		}
		if ( $depth > self::MAX_DEPTH ) {
			$this->error( 'too_deep', $path, sprintf( 'Elements are nested more than %d levels deep; this branch was not checked further.', self::MAX_DEPTH ) );
			return;
		}
		$this->stats['max_depth'] = max( $this->stats['max_depth'], $depth );

		if ( ! is_array( $element ) || self::is_list( $element ) ) {
			$this->error( 'element_not_object', $path, sprintf( 'Each element must be an object with id, elType, settings and elements; got %s.', self::type_of( $element ) ) );
			return;
		}

		$id = $element['id'] ?? null;
		if ( null === $id || '' === $id ) {
			$this->id_issue( 'missing_id', $path, 'The element has no id.' );
		} elseif ( ! ( is_string( $id ) || is_int( $id ) ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $id ) ) {
			$this->id_issue( 'malformed_id', $path . '.id', sprintf( 'The id %s is not a short string of letters, digits, _ or -.', is_scalar( $id ) ? '"' . (string) $id . '"' : self::type_of( $id ) ) );
		} elseif ( isset( $this->ids[ (string) $id ] ) ) {
			$this->id_issue( 'duplicate_id', $path . '.id', sprintf( 'The id "%s" is already used at %s.', (string) $id, $this->ids[ (string) $id ] ) );
		} else {
			$this->ids[ (string) $id ] = $path;
		}

		$el_type = $element['elType'] ?? null;
		$kind    = 'unknown';
		if ( ! is_string( $el_type ) || '' === $el_type ) {
			$this->error( 'missing_eltype', $path, 'The element has no elType. Use "container", "section", "column" or "widget".' );
		} elseif ( in_array( $el_type, array( 'section', 'column', 'container', 'widget' ), true ) ) {
			$kind = $el_type;
		} elseif ( self::is_atomic( $el_type ) ) {
			$kind = 'atomic';
		} else {
			$this->error( 'unknown_eltype', $path . '.elType', sprintf( 'elType "%s" is not one Elementor renders. Use "container", "section", "column" or "widget" (or an Editor V4 "e-*" / "a-*" element).', $el_type ) );
		}
		if ( isset( $this->stats[ $kind . 's' ] ) ) {
			$this->stats[ $kind . 's' ]++;
		}

		$is_inner = ! empty( $element['isInner'] );
		$this->check_placement( $kind, $is_inner, $parent, $path );

		$widget_type = null;
		if ( 'widget' === $kind ) {
			$widget_type = $this->check_widget_type( $element, $path );
		} elseif ( isset( $element['widgetType'] ) && '' !== $element['widgetType'] && 'unknown' !== $kind ) {
			$this->warning( 'widget_type_ignored', $path . '.widgetType', sprintf( 'widgetType is only read on elType "widget"; on a %s it is ignored.', $kind ) );
		}

		if ( array_key_exists( 'settings', $element ) ) {
			$settings = $element['settings'];
			if ( ! is_array( $settings ) || ( array() !== $settings && self::is_list( $settings ) ) ) {
				$this->error( 'settings_not_object', $path . '.settings', sprintf( '"settings" must be an object keyed by control name, got %s.', is_array( $settings ) ? 'a list' : self::type_of( $settings ) ) );
			} else {
				$this->check_settings( $settings, $path . '.settings', $kind, (string) $widget_type );
			}
		}

		if ( ! array_key_exists( 'elements', $element ) ) {
			return;
		}
		$children = $element['elements'];
		if ( ! is_array( $children ) || ( array() !== $children && ! self::is_list( $children ) ) ) {
			$this->error( 'elements_not_array', $path . '.elements', sprintf( '"elements" must be an array of child elements, got %s.', self::type_of( $children ) ) );
			return;
		}
		if ( array() === $children ) {
			if ( 'section' === $kind ) {
				$this->warning( 'empty_section', $path, 'The section has no columns: Elementor renders it as an empty row.' );
			}
			return;
		}

		
		$context = array( 'kind' => $kind, 'inner' => 'column' === $kind ? ! empty( $parent['inner'] ) : $is_inner );
		if ( 'widget' === $kind && null !== $widget_type && self::is_atomic( $widget_type ) ) {
			
			$context['kind'] = 'atomic';
		} elseif ( 'widget' === $kind ) {
			$nests            = null === $widget_type ? null : $this->widget_nests( $widget_type );
			$context['nests'] = $nests;
			if ( false === $nests ) {
				$this->error( 'widget_children_dropped', $path . '.elements', sprintf( 'Widget "%s" does not hold child elements, so Elementor drops these %d child element(s). Put them in a container next to the widget instead.', (string) $widget_type, count( $children ) ) );
			} elseif ( null === $nests ) {
				$this->warning( 'widget_children_unverified', $path . '.elements', sprintf( 'Widget "%s" has child elements, but no widget registry is available to confirm it nests them. Only nesting widgets (nested-tabs, nested-accordion, nested-carousel, mega-menu) keep children; any other widget drops them.', (string) $widget_type ) );
			} else {
				$this->check_nested_panels( $element, $path );
			}
		}
		foreach ( $children as $index => $child ) {
			$this->check_element( $child, $path . '.elements[' . $index . ']', $context, $depth + 1 );
			if ( $this->limit_hit ) {
				return;
			}
		}
	}

	private function check_placement( string $kind, bool $is_inner, array $parent, string $path ): void {
		$p = $parent['kind'];
		if ( 'unknown' === $kind || 'unknown' === $p ) {
			return;
		}
		if ( 'column' === $kind && 'section' !== $p ) {
			$this->error( 'column_outside_section', $path, sprintf( 'A column must sit directly inside a section; this one is inside %s. Use a container instead of section/column, or wrap the column in a section.', 'root' === $p ? 'the page root' : 'a ' . $p ) );
			return;
		}
		switch ( $p ) {
			case 'root':
				if ( 'widget' === $kind ) {
					$this->warning( 'widget_at_root', $path, 'A widget sits at the top level. Pages and section templates hold containers or sections at the top; only a "widget" type template holds a bare widget. Wrap it in a container.' );
				} elseif ( 'section' === $kind && $is_inner ) {
					$this->warning( 'inner_section_at_root', $path, 'This top-level section is marked isInner. Only a section inside a column is an inner section; remove isInner.' );
				}
				break;
			case 'section':
				if ( 'column' !== $kind ) {
					$this->error( 'section_child_not_column', $path, sprintf( 'A section holds columns only; this child is a %s. Wrap it in a column, or build the layout with containers.', $kind ) );
				}
				break;
			case 'column':
				if ( 'section' === $kind ) {
					if ( ! empty( $parent['inner'] ) ) {
						$this->error( 'inner_section_too_deep', $path, 'This section sits in a column of an inner section. Elementor allows one level of inner sections only.' );
					} elseif ( ! $is_inner ) {
						$this->warning( 'inner_section_flag', $path . '.isInner', 'A section inside a column is an inner section and needs "isInner": true; without it Elementor lays it out as a top-level section.' );
					}
				} elseif ( 'widget' !== $kind ) {
					$this->error( 'column_child_invalid', $path, sprintf( 'A column holds widgets and inner sections only; this child is a %s.', 'atomic' === $kind ? 'V4 atomic element' : $kind ) );
				}
				break;
			case 'container':
			case 'atomic':
				if ( 'section' === $kind ) {
					$this->error( 'section_in_container', $path, sprintf( 'A section cannot sit inside a %s. Use a nested container instead.', 'atomic' === $p ? 'V4 atomic element' : 'container' ) );
				}
				break;
			case 'widget':
				if ( true === ( $parent['nests'] ?? null ) && 'container' !== $kind ) {
					$this->error( 'nested_child_not_container', $path, sprintf( 'A nesting widget holds one container per panel; this child is a %s. Put the content inside a container.', $kind ) );
				}
				break;
		}
	}

	private function check_widget_type( array $element, string $path ): ?string {
		$type = $element['widgetType'] ?? null;
		if ( ! is_string( $type ) || '' === $type ) {
			$this->error( 'missing_widget_type', $path, 'elType is "widget" but there is no widgetType (e.g. "heading", "text-editor", "button", "image").' );
			return null;
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$/', $type ) ) {
			$this->error( 'malformed_widget_type', $path . '.widgetType', sprintf( 'widgetType "%s" is not a widget slug (letters, digits, -, _ and .).', $type ) );
			return null;
		}
		if ( ! isset( $this->stats['widget_types'][ $type ] ) && count( $this->stats['widget_types'] ) >= 60 ) {
			$this->stats['widget_types']['(other)'] = ( $this->stats['widget_types']['(other)'] ?? 0 ) + 1;
		} else {
			$this->stats['widget_types'][ $type ] = ( $this->stats['widget_types'][ $type ] ?? 0 ) + 1;
		}

		if ( null === $this->options['classify'] ) {
			return $type;
		}
		$class = (string) call_user_func( $this->options['classify'], $type );
		if ( 'unregistered' === $class ) {
			$this->error( 'unregistered_widget', $path . '.widgetType', sprintf( 'Widget "%s" is not registered on this site: Elementor renders it as an empty placeholder. Check the slug with elementor_list_widget_types, or install the add-on that provides it.', $type ) );
		} elseif ( 'unavailable' === $class ) {
			$this->registry = 'unavailable';
			$this->warning_once( 'registry_unavailable', $path, 'Elementor\'s widget registry could not be reached, so widget slugs were not confirmed as registered.' );
		} elseif ( 'atomic' === $class ) {
			$this->warning_once( 'atomic_not_checked', $path, 'Editor V4 atomic widgets (e-*) are present. Their settings schema is not public, so only their place in the tree was checked.' );
		}
		return $type;
	}

	private function widget_nests( string $type ): ?bool {
		if ( null !== $this->options['supports_nesting'] && 'unavailable' !== $this->registry && null !== $this->options['classify'] ) {
			$class = (string) call_user_func( $this->options['classify'], $type );
			if ( 'registered' === $class ) {
				return (bool) call_user_func( $this->options['supports_nesting'], $type );
			}
			if ( 'unregistered' === $class ) {
				return null; 
			}
		}
		if ( in_array( $type, self::NESTING_FALLBACK, true ) ) {
			return true;
		}
		return null;
	}

	private function check_nested_panels( array $element, string $path ): void {
		$panels = 0;
		foreach ( (array) $element['elements'] as $child ) {
			if ( is_array( $child ) && 'container' === ( $child['elType'] ?? '' ) ) {
				$panels++;
			}
		}
		$repeaters = array();
		foreach ( is_array( $element['settings'] ?? null ) ? $element['settings'] : array() as $key => $value ) {
			if ( is_array( $value ) && isset( $value[0] ) && is_array( $value[0] ) && array_key_exists( '_id', $value[0] ) ) {
				$repeaters[ (string) $key ] = count( $value );
			}
		}
		if ( 1 !== count( $repeaters ) ) {
			return;
		}
		$key   = (string) key( $repeaters );
		$items = (int) current( $repeaters );
		if ( $items !== $panels ) {
			$this->warning( 'nested_panel_mismatch', $path, sprintf( 'This widget has %d panel container(s) but %d entr%s in settings.%s. One panel renders per entry, so the extra ones stay hidden: make the counts match.', $panels, $items, 1 === $items ? 'y' : 'ies', $key ) );
		}
	}

	

	private function check_settings( array $settings, string $path, string $kind, string $widget_type ): void {
		if ( 'widget' !== $kind && 'unknown' !== $kind && ! empty( $settings['_css_classes'] ) ) {
			$this->warning( 'class_key_ignored', $path . '._css_classes', sprintf( 'A %s reads its CSS classes from "css_classes"; "_css_classes" is the widget key and is ignored here.', $kind ) );
		}
		if ( 'widget' === $kind && ! empty( $settings['css_classes'] ) ) {
			$this->warning( 'class_key_ignored', $path . '.css_classes', 'A widget reads its CSS classes from "_css_classes"; "css_classes" is the container key and is ignored here.' );
		}

		if ( array_key_exists( '__dynamic__', $settings ) ) {
			$dynamic = $settings['__dynamic__'];
			if ( ! is_array( $dynamic ) || ( array() !== $dynamic && self::is_list( $dynamic ) ) ) {
				$this->error( 'dynamic_not_object', $path . '.__dynamic__', '"__dynamic__" must be an object mapping a setting key to a dynamic tag string.' );
			} else {
				foreach ( $dynamic as $key => $tag ) {
					if ( ! is_string( $tag ) ) {
						$this->error( 'dynamic_value_not_string', $path . '.__dynamic__.' . $key, sprintf( 'A dynamic binding is a tag string such as [elementor-tag id="..." name="post-title" settings="..."]; got %s.', self::type_of( $tag ) ) );
					} elseif ( '' !== $tag && false === strpos( $tag, '[elementor-tag' ) ) {
						$this->warning( 'dynamic_tag_malformed', $path . '.__dynamic__.' . $key, 'This dynamic binding is not an [elementor-tag ...] string, so Elementor ignores it.' );
					}
				}
			}
		}

		if ( array_key_exists( '__globals__', $settings ) ) {
			$globals = $settings['__globals__'];
			if ( ! is_array( $globals ) || ( array() !== $globals && self::is_list( $globals ) ) ) {
				$this->error( 'globals_not_object', $path . '.__globals__', '"__globals__" must be an object mapping a setting key to a global reference such as "globals/colors?id=primary".' );
			} else {
				foreach ( $globals as $key => $ref ) {
					if ( ! is_string( $ref ) ) {
						$this->error( 'global_ref_not_string', $path . '.__globals__.' . $key, sprintf( 'A global reference is a string such as "globals/colors?id=primary"; got %s.', self::type_of( $ref ) ) );
					} elseif ( '' !== $ref && 0 !== strpos( $ref, 'globals/' ) ) {
						$this->warning( 'global_ref_malformed', $path . '.__globals__.' . $key, sprintf( '"%s" is not a global reference ("globals/colors?id=..." or "globals/typography?id=..."), so it resolves to nothing.', $ref ) );
					}
				}
			}
		}

		foreach ( array( 'html', 'editor' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && false !== stripos( $settings[ $key ], '<script' ) ) {
				$this->warning( 'script_in_content', $path . '.' . $key, 'This content holds a <script> tag. It is stored only for users with the unfiltered_html capability (stripped for everyone else), and runs for every visitor: confirm it is intended.' );
			}
		}

		$this->walk_settings( $settings, $path, 0 );
	}

	private function walk_settings( array $values, string $path, int $depth ): void {
		if ( $depth > 8 ) {
			return;
		}
		foreach ( $values as $key => $value ) {
			$key  = (string) $key;
			$here = is_numeric( $key ) ? $path . '[' . $key . ']' : $path . '.' . $key;
			if ( '__dynamic__' === $key || '__globals__' === $key ) {
				continue;
			}

			if ( is_string( $value ) ) {
				$looks_url = 1 === preg_match( '#^(?:https?:)?//|^/[^/]|^\#[A-Za-z]|^(?:mailto|tel):#i', $value );
				if ( $looks_url && ( 'link' === $key || '_link' === substr( $key, -5 ) ) && 0 !== strpos( $key, 'show_' ) ) {
					$this->warning( 'link_not_object', $here, sprintf( 'Link controls store an object {"url": "...", "is_external": "", "nofollow": ""}; a plain string renders no link. Use {"url": "%s"}.', $value ) );
				} elseif ( $looks_url && ( 'image' === $key || '_image' === substr( $key, -6 ) ) ) {
					$this->warning( 'image_not_object', $here, sprintf( 'Image controls store an object {"url": "...", "id": attachment ID}; a plain string renders no image. Use {"url": "%s", "id": ""}.', $value ) );
				}
				if ( 'url' === $key && self::is_unsafe_url( $value ) ) {
					$this->warning( 'unsafe_url', $here, 'This link uses a javascript:, vbscript: or data: URL, which runs code when clicked. Elementor does not block it.' );
				}
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( self::is_list( $value ) && array() !== $value ) {
				$this->check_repeater( $value, $here );
			}
			$this->walk_settings( $value, $here, $depth + 1 );
		}
	}

	private function check_repeater( array $items, string $path ): void {
		$has_id = false;
		foreach ( $items as $item ) {
			if ( is_array( $item ) && array_key_exists( '_id', $item ) ) {
				$has_id = true;
				break;
			}
		}
		if ( ! $has_id ) {
			return;
		}
		$seen = array();
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_id = $item['_id'] ?? null;
			if ( ! is_string( $item_id ) || '' === $item_id ) {
				$this->warning( 'repeater_item_missing_id', $path . '[' . $index . ']', 'This repeater item has no "_id". Elementor keys repeater rows by _id; give each row a unique short id.' );
			} elseif ( isset( $seen[ $item_id ] ) ) {
				$this->warning( 'repeater_duplicate_id', $path . '[' . $index . ']._id', sprintf( 'Repeater _id "%s" is also used by item %d: the editor treats the two rows as one.', $item_id, $seen[ $item_id ] ) );
			} else {
				$seen[ $item_id ] = (int) $index;
			}
		}
	}

	private static function is_atomic( string $type ): bool {
		return 1 === preg_match( '/^[ae]-[A-Za-z0-9_-]+$/', $type );
	}

	private static function is_unsafe_url( string $url ): bool {
		$clean = strtolower( (string) preg_replace( '/[\x00-\x20]+/', '', $url ) );
		foreach ( array( 'javascript:', 'vbscript:', 'data:' ) as $scheme ) {
			if ( 0 === strpos( $clean, $scheme ) ) {
				return true;
			}
		}
		return false;
	}

	

	private function validate_wxr( string $xml ): void {

		
		if ( 1 === preg_match( '/<!(?:DOCTYPE|ENTITY)/i', $xml ) ) {
			$this->error( 'xml_doctype_refused', '$', 'The XML declares a DOCTYPE or ENTITY. A WordPress export never does, and these declarations are how entity-expansion and external-entity attacks work, so the file was not parsed.' );
			return;
		}

		if ( false !== strpos( $xml, "\0" ) ) {
			$this->error( 'xml_encoding_refused', '$', 'The XML is not UTF-8 (it contains NUL bytes, as UTF-16 does). WordPress exports are UTF-8; re-save the file as UTF-8.' );
			return;
		}
		if ( preg_match( '/^<\?xml[^>]*encoding\s*=\s*["\']([^"\']+)["\']/i', $xml, $m ) && ! in_array( strtolower( $m[1] ), array( 'utf-8', 'utf8', 'us-ascii' ), true ) ) {
			$this->error( 'xml_encoding_refused', '$', sprintf( 'The XML declares encoding "%s". WordPress exports are UTF-8; re-save the file as UTF-8.', $m[1] ) );
			return;
		}
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			$this->error( 'xml_unsupported', '$', 'This PHP build has no SimpleXML extension, so XML cannot be validated here.' );
			return;
		}

		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();
		$doc    = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		$issues = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $doc ) {
			foreach ( array_slice( $issues, 0, 5 ) as $issue ) {
				$this->error(
					'xml_syntax',
					'$',
					sprintf( 'Invalid XML at line %d, column %d: %s.', (int) $issue->line, (int) $issue->column, trim( (string) $issue->message ) ),
					array( 'line' => (int) $issue->line, 'column' => (int) $issue->column )
				);
			}
			if ( ! $issues ) {
				$this->error( 'xml_syntax', '$', 'The XML could not be parsed.' );
			}
			return;
		}

		$namespaces = $doc->getNamespaces( true );
		$wp_ns      = $namespaces['wp'] ?? null;
		if ( 'rss' !== $doc->getName() || ! isset( $doc->channel ) || null === $wp_ns ) {
			$this->error( 'not_wxr', '$', 'This XML is not a WordPress export (WXR): expected an <rss> root with a <channel> and the wp: namespace, as written by Tools > Export.' );
			return;
		}

		$channel = $doc->channel;
		if ( '' === trim( (string) $channel->children( $wp_ns )->wxr_version ) ) {
			$this->warning( 'missing_wxr_version', '$.channel', 'No <wp:wxr_version>. The WordPress importer refuses a file without it.' );
		}

		$total = 0;
		foreach ( $channel->item as $item ) {
			$index = $total++;
			$wp    = $item->children( $wp_ns );
			$meta  = array();
			foreach ( $wp->postmeta as $row ) {
				$fields                              = $row->children( $wp_ns );
				$meta[ (string) $fields->meta_key ] = (string) $fields->meta_value;
			}
			if ( ! array_key_exists( '_elementor_data', $meta ) ) {
				continue;
			}
			$this->validate_wxr_item( $item, $wp, $meta, $index );
		}

		if ( 0 === $total ) {
			$this->error( 'no_items', '$.channel', 'The export has no <item> elements.' );
		} elseif ( ! $this->documents ) {
			$this->error( 'no_elementor_items', '$.channel', sprintf( 'None of the %d item(s) carries _elementor_data, so there is no Elementor content in this export.', $total ) );
		}
		$this->stats['items'] = $total;
	}

	private function validate_wxr_item( \SimpleXMLElement $item, \SimpleXMLElement $wp, array $meta, int $index ): void {
		$title     = trim( (string) $item->title );
		$post_type = (string) $wp->post_type;
		$path      = sprintf( 'item[%d]', $index ) . ( '' !== $title ? ' "' . $title . '"' : '' );
		$errors    = count( $this->errors ) + $this->suppressed['errors'];
		$warnings  = count( $this->warnings ) + $this->suppressed['warnings'];
		$before    = $this->stats['elements'];

		if ( 'builder' !== ( $meta['_elementor_edit_mode'] ?? '' ) ) {
			$this->warning( 'not_builder_mode', $path . '._elementor_edit_mode', '_elementor_edit_mode is not "builder", so WordPress shows the post content instead of the Elementor layout.' );
		}
		if ( 'elementor_library' === $post_type && '' === ( $meta['_elementor_template_type'] ?? '' ) ) {
			$this->warning( 'missing_template_type', $path . '._elementor_template_type', 'This library item has no _elementor_template_type, so the Templates screen cannot place it.' );
		}
		if ( '' === ( $meta['_elementor_version'] ?? '' ) ) {
			$this->warning( 'missing_elementor_version', $path . '._elementor_version', 'No _elementor_version. Elementor uses it to run data upgrades on old content.' );
		}
		$page_settings = ltrim( $meta['_elementor_page_settings'] ?? '' );
		if ( '' !== $page_settings ) {
			if ( '{' === $page_settings[0] || '[' === $page_settings[0] ) {
				$this->warning( 'page_settings_json', $path . '._elementor_page_settings', 'An export stores _elementor_page_settings PHP-serialized (a:2:{...}); this value is JSON, which the importer saves as a plain string that Elementor ignores.' );
			} elseif ( 1 !== preg_match( '/^a:\d+:\{/', $page_settings ) ) {
				$this->warning( 'page_settings_unreadable', $path . '._elementor_page_settings', 'An export stores _elementor_page_settings as a PHP-serialized array (a:N:{...}); this value is not one.' );
			}
		}

		$data = $this->decode_json( $meta['_elementor_data'], $path . '._elementor_data' );
		if ( null !== $data ) {
			$this->validate_decoded( $data, $path . '._elementor_data', 'elements', false );
		}

		if ( count( $this->documents ) < 100 ) {
			$this->documents[] = array(
				'item'          => $index,
				'title'         => $title,
				'post_type'     => $post_type,
				'post_id'       => (int) $wp->post_id,
				'status'        => (string) $wp->status,
				'template_type' => (string) ( $meta['_elementor_template_type'] ?? '' ),
				'valid'         => count( $this->errors ) + $this->suppressed['errors'] === $errors,
				'errors'        => count( $this->errors ) + $this->suppressed['errors'] - $errors,
				'warnings'      => count( $this->warnings ) + $this->suppressed['warnings'] - $warnings,
				'elements'      => $this->stats['elements'] - $before,
			);
		}
	}

	

	private function id_issue( string $code, string $path, string $message ): void {
		if ( 'raw' === $this->target ) {
			$this->error( $code, $path, $message . ' Elementor scopes each element\'s CSS by its id, so a missing or shared id applies one element\'s styles to others. Give every element a unique id (7 hex characters, as Elementor does).' );
			return;
		}
		$this->warning( $code, $path, $message . ' elementor_set_page_data and elementor_import_template assign a fresh unique id here; written any other way, a missing or shared id makes Elementor apply one element\'s styles to others.' );
	}

	private function issue( bool $is_error, string $code, string $path, string $message ): void {
		if ( $is_error ) {
			$this->error( $code, $path, $message );
		} else {
			$this->warning( $code, $path, $message );
		}
	}

	private function error( string $code, string $path, string $message, array $extra = array() ): void {
		if ( count( $this->errors ) >= self::MAX_ISSUES ) {
			$this->suppressed['errors']++;
			return;
		}
		$this->errors[] = array_merge( array( 'code' => $code, 'path' => $path, 'message' => $message ), $extra );
	}

	private function warning( string $code, string $path, string $message ): void {
		if ( count( $this->warnings ) >= self::MAX_ISSUES ) {
			$this->suppressed['warnings']++;
			return;
		}
		$this->warnings[] = array( 'code' => $code, 'path' => $path, 'message' => $message );
	}

	private function warning_once( string $code, string $path, string $message ): void {
		if ( isset( $this->once[ $code ] ) ) {
			return;
		}
		$this->once[ $code ] = true;
		$this->warning( $code, $path, $message );
	}

	private function result(): array {
		$error_count   = count( $this->errors ) + $this->suppressed['errors'];
		$warning_count = count( $this->warnings ) + $this->suppressed['warnings'];
		$result        = array(
			'valid'           => 0 === $error_count,
			'format'          => $this->format,
			'target'          => $this->target,
			'summary'         => sprintf(
				'%s: %d error(s), %d warning(s) across %d element(s).',
				0 === $error_count ? 'Valid' : 'Not valid',
				$error_count,
				$warning_count,
				$this->stats['elements']
			),
			'errors'          => $this->errors,
			'warnings'        => $this->warnings,
			'stats'           => $this->stats,
			'widget_registry' => $this->registry,
		);
		if ( 'wxr' === $this->format ) {
			$result['documents'] = $this->documents;
		}
		if ( $this->suppressed['errors'] || $this->suppressed['warnings'] ) {
			$result['suppressed'] = $this->suppressed;
		}
		return $result;
	}

	private static function empty_stats(): array {
		return array(
			'elements'     => 0,
			'containers'   => 0,
			'sections'     => 0,
			'columns'      => 0,
			'widgets'      => 0,
			'atomics'      => 0,
			'max_depth'    => 0,
			'widget_types' => array(),
		);
	}

	private static function is_list( array $value ): bool {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function type_of( $value ): string {
		if ( is_array( $value ) ) {
			return self::is_list( $value ) ? 'an array' : 'an object';
		}
		if ( null === $value ) {
			return 'null';
		}
		if ( is_string( $value ) ) {
			return 'a string';
		}
		if ( is_bool( $value ) ) {
			return 'a boolean';
		}
		return is_int( $value ) || is_float( $value ) ? 'a number' : gettype( $value );
	}
}
