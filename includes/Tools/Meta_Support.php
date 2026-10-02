<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Support {

	public static function sanitize_meta_value( $value ) {

		

		
		if (is_string($value) && preg_match('/^(?:a|O|s):\d+[:{"]|^(?:i|d):-?\d+(?:\.\d+)?;|^b:[01];|^N;$/', $value)) {
			throw new \Exception('Value looks like a PHP-serialized string. Pass the structured value (array/object) directly. WordPress will serialize it for you.');
		}
		if (is_array($value)) {
			$out = [];
			foreach ($value as $k => $v) {
				$key = is_string($k) ? sanitize_text_field($k) : $k;
				$out[$key] = self::sanitize_meta_value($v);
			}
			return $out;
		}
		if (is_object($value)) {
			$out = [];
			foreach (get_object_vars($value) as $k => $v) {
				$out[sanitize_text_field($k)] = self::sanitize_meta_value($v);
			}
			return $out;
		}
		if (is_string($value)) {

			
			return current_user_can('unfiltered_html') ? $value : wp_kses_post($value);
		}
		
		return $value;
	}

	public static function filter_meta_value( $raw, $meta_key, $object_id, $tool_name ) {
		$sanitized = self::sanitize_meta_value($raw);
		return apply_filters(
			'more_mcp_meta_value_sanitizer',
			$sanitized,
			$raw,
			(string) $meta_key,
			(int) $object_id,
			(string) $tool_name
		);
	}

	public static function write_meta_verified( $meta_type, $object_id, $meta_key, $value, $add = false, $unique = false ) {
		$object_id = (int) $object_id;
		$is_term   = ('term' === $meta_type);

		
		$to_store = wp_slash($value);

		$meta_id = null;
		if ($add) {
			$result = $is_term
				? add_term_meta($object_id, $meta_key, $to_store, $unique)
				: add_post_meta($object_id, $meta_key, $to_store, $unique);
			$written = ($result !== false);
			$meta_id = $written ? (int) $result : null;
		} else {
			$result  = $is_term
				? update_term_meta($object_id, $meta_key, $to_store)
				: update_post_meta($object_id, $meta_key, $to_store);

			$written = ($result !== false);
		}

		$stored = $is_term
			? get_term_meta($object_id, $meta_key, true)
			: get_post_meta($object_id, $meta_key, true);

		$warnings = [];
		$verified = ($stored === $value);

		if (!$verified) {
			if (gettype($stored) !== gettype($value)) {
				$warnings[] = sprintf(
					'Stored type is %s but %s was sent. Callers that parse this value should re-read it rather than assume the sent shape.',
					gettype($stored),
					gettype($value)
				);
			} elseif (is_string($stored) && is_string($value)) {
				$delta = strlen($stored) - strlen($value);
				$warnings[] = sprintf(
					'Stored string differs from what was sent (%s%d bytes). A sanitizer, a more_mcp_meta_value_sanitizer filter, or kses filtering modified it.',
					$delta >= 0 ? '+' : '',
					$delta
				);

				if (substr_count($value, '\\"') > substr_count($stored, '\\"')) {
					$warnings[] = 'Backslash escapes before double quotes were removed. If this value is JSON it will no longer parse.';
				}
				if (stripos($value, '<script') !== false && stripos($stored, '<script') === false) {
					$warnings[] = 'A <script> tag was removed but its inner text was kept. Callers without the unfiltered_html capability cannot store script tags in meta.';
				}
			} else {
				$warnings[] = 'Stored value differs from what was sent. Re-read the value to inspect the stored result.';
			}
		}

		return [
			'written'       => $written,
			'meta_id'       => $meta_id,
			'verified'      => $verified,
			'warnings'      => $warnings,
			'stored_type'   => gettype($stored),
			'stored_length' => is_string($stored) ? strlen($stored) : null,
		];
	}

	public static function meta_write_report( array $write ) {
		$report = [
			'saved_value_matches_sent' => $write['verified'],
			'stored_type'              => $write['stored_type'],
		];
		if (null !== $write['stored_length']) {
			$report['stored_length'] = $write['stored_length'];
		}
		if (!empty($write['warnings'])) {
			$report['warnings'] = $write['warnings'];
		}
		return $report;
	}
}
