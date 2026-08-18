<?php
/**
 * API key generation and format validation.
 *
 * Keys look like:
 *
 *     mmcp_live_7Kq2Rn4vXbTz8LmWpYdA3s
 *     └───┬───┘ └──────────┬─────────┘
 *      prefix          22 chars base58
 *
 * Three properties drive the design:
 *
 * 1. The `mmcp_live_` prefix makes a leaked key identifiable at a glance —
 *    in a log line, a pasted config, or a secret scanner. It also tells an
 *    admin which plugin the credential belongs to without having to look it
 *    up, which the old bare 32-hex string could not.
 *
 * 2. The random part uses Bitcoin's base58 alphabet, which omits 0, O, I,
 *    and l. Those four are the characters people transcribe wrongly when
 *    copying a key by hand or reading it aloud, and the old lowercase-hex
 *    format still contained 0 and o-adjacent shapes. 22 base58 characters
 *    carry log2(58) * 22 ≈ 128.9 bits of entropy — comfortably above the
 *    128-bit floor, and stronger than the 128 bits of the previous
 *    32-character hex key while being 10 characters shorter to type.
 *
 * 3. `live` is a channel slot. It is the only channel today, but reserving
 *    the position now means a future `mmcp_test_` key can be told apart from
 *    a production one by prefix alone, without a second storage field.
 */

namespace More_MCP\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Key {

	/**
	 * Prefix on every key this class mints.
	 */
	const PREFIX = 'mmcp_live_';

	/**
	 * Length of the random portion, in base58 characters.
	 *
	 * 22 chars * log2(58) ≈ 128.9 bits.
	 */
	const RANDOM_LENGTH = 22;

	/**
	 * Base58 alphabet — no 0, O, I, or l, the four glyphs that get
	 * transcribed wrongly by hand.
	 */
	const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	/**
	 * Mint a new API key.
	 *
	 * @return string e.g. "mmcp_live_7Kq2Rn4vXbTz8LmWpYdA3s"
	 */
	public static function generate(): string {
		$alphabet = self::ALPHABET;
		$max      = strlen( $alphabet ) - 1;
		$out      = '';

		for ( $i = 0; $i < self::RANDOM_LENGTH; $i++ ) {
			// random_int() is cryptographically secure and, unlike a modulo
			// over random_bytes(), introduces no bias across a 58-character
			// alphabet that does not divide 256 evenly.
			$out .= $alphabet[ random_int( 0, $max ) ];
		}

		return self::PREFIX . $out;
	}

	/**
	 * Whether a string is a well-formed key of the current format.
	 *
	 * This is a SHAPE check, not an authentication check — it says nothing
	 * about whether the key matches the one stored for this site. Callers
	 * still have to compare against stored settings with hash_equals().
	 *
	 * Its purpose is to reject the legacy 32-hex format outright, so a key
	 * carried over from before this format change fails with a clear
	 * "regenerate your key" message rather than a bare "invalid key".
	 *
	 * @param mixed $key Candidate value.
	 * @return bool
	 */
	public static function is_valid_format( $key ): bool {
		if ( ! is_string( $key ) || $key === '' ) {
			return false;
		}

		$pattern = '/^' . preg_quote( self::PREFIX, '/' )
			. '[' . preg_quote( self::ALPHABET, '/' ) . ']{' . self::RANDOM_LENGTH . '}$/';

		return (bool) preg_match( $pattern, $key );
	}

	/**
	 * Whether a string looks like the legacy pre-0.1.5 key: 32 lowercase hex
	 * characters, no prefix.
	 *
	 * Used only to produce a more helpful error than "invalid key" when an
	 * old credential is presented. Legacy keys are NOT accepted.
	 *
	 * @param mixed $key Candidate value.
	 * @return bool
	 */
	public static function is_legacy_format( $key ): bool {
		return is_string( $key ) && (bool) preg_match( '/^[0-9a-f]{32}$/', $key );
	}

	/**
	 * A display-safe preview: prefix plus the last 4 characters.
	 *
	 * For log lines and support conversations, where the full key must never
	 * appear but an admin still needs to tell two keys apart.
	 *
	 * @param mixed $key Key to mask.
	 * @return string e.g. "mmcp_live_…YdA3s"
	 */
	public static function mask( $key ): string {
		if ( ! is_string( $key ) || $key === '' ) {
			return '';
		}
		if ( strlen( $key ) <= 8 ) {
			return str_repeat( '*', strlen( $key ) );
		}
		return self::PREFIX . '…' . substr( $key, -4 );
	}
}
