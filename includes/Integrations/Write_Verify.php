<?php

namespace More_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Write_Verify {

	const LANDED = 'landed';

	const TRANSFORMED = 'transformed';

	const DISCARDED = 'discarded';

	const UNKNOWN = 'unknown';

	const UNREADABLE = "\0more_mcp_unreadable\0";

	const NO_PRIOR = "\0more_mcp_no_prior\0";

	public static function state_of( $stored, $sent, $prior = self::NO_PRIOR ) {
		if ( self::UNREADABLE === $stored ) {
			return self::UNKNOWN;
		}
		if ( self::matches( $stored, $sent ) ) {
			return self::LANDED;
		}
		if ( self::NO_PRIOR === $prior ) {
			
			return self::TRANSFORMED;
		}
		if ( self::matches( $stored, $prior ) ) {
			return self::DISCARDED;
		}
		return self::TRANSFORMED;
	}

	public static function state_of_presence( $observed ) {
		return $observed ? self::LANDED : self::DISCARDED;
	}

	public static function combine( array $states ) {
		$states = array_values( array_filter( $states, 'is_string' ) );
		if ( empty( $states ) ) {
			return self::LANDED;
		}
		if ( in_array( self::UNKNOWN, $states, true ) ) {
			return self::UNKNOWN;
		}
		if ( count( array_unique( $states ) ) === 1 && self::DISCARDED === $states[0] ) {
			return self::DISCARDED;
		}
		if ( in_array( self::DISCARDED, $states, true ) || in_array( self::TRANSFORMED, $states, true ) ) {

			return self::TRANSFORMED;
		}
		return self::LANDED;
	}

	public static function refuse_if_discarded( $state, $subject, $cause = '' ) {
		if ( self::DISCARDED !== $state ) {
			return;
		}
		$message = sprintf(
			/* translators: %s: what was written, e.g. "field value". */
			__( 'The %s was not stored: the store kept its own value, so nothing changed and no undo token was issued.', 'more-mcp' ),
			(string) $subject
		);
		if ( '' !== (string) $cause ) {
			$message .= ' ' . (string) $cause;
		}
		throw new \Exception( esc_html( $message ) );
	}

	public static function report( $state, $subject, $advice = '' ) {
		$state   = (string) $state;
		$subject = (string) $subject;

		switch ( $state ) {
			case self::LANDED:
				$note = sprintf(
					/* translators: %s: what was written, e.g. "field value". */
					__( 'The stored %s matches what was sent.', 'more-mcp' ),
					$subject
				);
				break;

			case self::TRANSFORMED:
				$note = sprintf(
					/* translators: %s: what was written, e.g. "field value". */
					__( 'The write landed but the stored %s is not byte-for-byte what was sent: something between this tool and the database rewrote it. The undo token is valid. Re-read before writing again.', 'more-mcp' ),
					$subject
				);
				break;

			case self::DISCARDED:

				$note = sprintf(
					/* translators: %s: what was written, e.g. "field value". */
					__( 'The %s was not stored at all: storage still holds its pre-write value. Do not rely on the undo token, it would reverse a change that did not happen.', 'more-mcp' ),
					$subject
				);
				break;

			default:
				$state = self::UNKNOWN;
				$note  = sprintf(
					/* translators: %s: what was written, e.g. "field value". */
					__( 'Whether the %s was stored could not be confirmed: reading it back failed. Treat this as unknown rather than as success, and re-read before writing again.', 'more-mcp' ),
					$subject
				);
				break;
		}

		if ( '' !== (string) $advice && self::LANDED !== $state ) {
			$note .= ' ' . (string) $advice;
		}

		return array(
			'verified'    => ( self::LANDED === $state ),
			'write_state' => $state,
			'verify_note' => $note,
		);
	}

	public static function matches( $stored, $sent ) {

		if ( self::UNREADABLE === $stored || self::UNREADABLE === $sent ) {
			return false;
		}
		if ( self::NO_PRIOR === $stored || self::NO_PRIOR === $sent ) {
			return false;
		}

		if ( is_array( $stored ) && is_array( $sent ) ) {
			if ( count( $stored ) !== count( $sent ) ) {
				return false;
			}
			foreach ( $sent as $key => $value ) {
				if ( ! array_key_exists( $key, $stored ) ) {
					return false;
				}
				if ( ! self::matches( $stored[ $key ], $value ) ) {
					return false;
				}
			}
			return true;
		}

		if ( is_array( $stored ) !== is_array( $sent ) ) {
			return false;
		}

		
		return $stored == $sent; 
	}
}
