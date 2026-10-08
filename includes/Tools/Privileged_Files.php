<?php

namespace More_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Privileged_Files implements Handler {

	const TOOL = 'wp_privileged_files_read_query';

	const MAX_READ_BYTES = 262144;

	const MAX_SEARCH_FILE_BYTES = 2097152;

	const MAX_LIST_ENTRIES = 500;

	const NOISE_DIRS = array( 'node_modules', 'vendor', '.git', '.svn', 'cache' );

	const BLOCKED_NAMES = array(
		'.env', '.env.*', '*.env', '.htpasswd', '.htaccess.bak', '*.pem', '*.key', '*.crt', '*.cer', '*.p12', '*.pfx', '*.jks',
		'id_rsa*', 'id_dsa*', 'id_ecdsa*', 'id_ed25519*', '.git-credentials', '.netrc', '.npmrc', 'auth.json', 'credentials*',
		'wp-config*.php', 'wp-config.*', '*.sql', '*.sql.gz', '*.sqlite', '*.sqlite3', '*.db', '*.kdbx', 'secrets.*',
	);

	const DEFAULT_EXTENSIONS = array( 'php', 'js', 'css', 'json', 'txt', 'md', 'html', 'htm', 'log', 'yml', 'yaml', 'xml', 'ini', 'twig', 'scss', 'ts' );

	public static function get_tools(): array {
		return array(
			array(
				'name'        => self::TOOL,
				'description' => 'Look at files under wp-content (read only). action "list" shows a directory, "read" returns a line range of a text file, "search" finds text inside files below a directory. Paths are relative to wp-content, for example plugins/my-plugin/readme.txt or debug.log. Keys, certificates, .env files, wp-config and database dumps are never shown. Needs manage_options and privileged tools enabled.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'action'      => array( 'type' => 'string', 'enum' => array( 'list', 'read', 'search' ), 'description' => 'list (default), read or search.' ),
						'path'        => array( 'type' => 'string', 'description' => 'Path inside wp-content. Empty means wp-content itself.' ),
						'offset'      => array( 'type' => 'integer', 'description' => 'read: first line to return (1-based, default 1). A negative number counts from the end, so -200 is the last 200 lines (handy for logs).' ),
						'limit'       => array( 'type' => 'integer', 'description' => 'read: lines to return (default 200, max 2000).' ),
						'query'       => array( 'type' => 'string', 'description' => 'search: text to find.' ),
						'regex'       => array( 'type' => 'boolean', 'description' => 'search: treat query as a regular expression (PCRE, no delimiters).' ),
						'extensions'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'search: file extensions to include (default: common code and text types).' ),
						'max_results' => array( 'type' => 'integer', 'description' => 'search: matches to return (default 50, max 200).' ),
						'context'     => array( 'type' => 'integer', 'description' => 'search: lines of context around each match (0-3, default 0).' ),
						'recursive'   => array( 'type' => 'boolean', 'description' => 'list: include subdirectories (up to 3 levels deep).' ),
						'include_vendor' => array( 'type' => 'boolean', 'description' => 'list/search: also walk node_modules, vendor, .git and cache directories.' ),
					),
				),
			),
		);
	}

	public static function supports( string $name ): bool {
		return self::TOOL === $name;
	}

	public static function execute_tool( string $name, array $args ) {
		if ( self::TOOL !== $name ) {
			throw new \Exception( 'Unknown tool: ' . esc_html( $name ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'manage_options capability required.' );
		}

		$action = isset( $args['action'] ) ? strtolower( (string) $args['action'] ) : 'list';
		$path   = self::resolve( isset( $args['path'] ) ? (string) $args['path'] : '' );

		switch ( $action ) {
			case 'list':
				return self::list_dir( $path, $args );
			case 'read':
				return self::read_file( $path, $args );
			case 'search':
				return self::search( $path, $args );
		}
		throw new \Exception( 'action must be list, read or search.' );
	}

	

	private static function root(): string {
		$root = realpath( WP_CONTENT_DIR );
		if ( false === $root ) {
			throw new \Exception( 'wp-content could not be resolved.' );
		}
		return rtrim( $root, '/\\' );
	}

	private static function resolve( string $path ): array {
		$root = self::root();
		$path = trim( str_replace( '\\', '/', $path ), '/' );

		if ( preg_match( '#(^|/)\.\.(/|$)#', $path ) || false !== strpos( $path, "\0" ) ) {
			throw new \Exception( 'Paths cannot contain "..".' );
		}
		$real = '' === $path ? $root : realpath( $root . '/' . $path );
		if ( false === $real ) {
			throw new \Exception( 'Not found: ' . esc_html( $path ) . '. Paths are relative to wp-content.' );
		}
		if ( $real !== $root && 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
			throw new \Exception( 'That path resolves outside wp-content, which this tool cannot read.' );
		}
		if ( self::blocked( $real ) ) {
			throw new \Exception( 'That file type is never exposed (keys, certificates, environment files, credentials and database dumps).' );
		}
		return array(
			'real' => $real,
			'rel'  => ltrim( str_replace( '\\', '/', substr( $real, strlen( $root ) ) ), '/' ),
		);
	}

	private static function blocked( string $file ): bool {
		$base = strtolower( basename( $file ) );
		foreach ( self::BLOCKED_NAMES as $pattern ) {
			if ( fnmatch( strtolower( $pattern ), $base ) ) {
				return true;
			}
		}
		return false;
	}

	private static function inside( string $real ): bool {
		$root = self::root();
		return $real === $root || 0 === strpos( $real, $root . DIRECTORY_SEPARATOR );
	}

	private static function is_binary( string $file ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local file under wp-content.
		$head = file_get_contents( $file, false, null, 0, 4096 );
		return false !== $head && false !== strpos( $head, "\0" );
	}

	

	private static function list_dir( array $path, array $args ): array {
		if ( ! is_dir( $path['real'] ) ) {
			throw new \Exception( 'That path is a file. Use action "read" for its contents.' );
		}
		$recursive = ! empty( $args['recursive'] ) && ! in_array( $args['recursive'], array( 'false', '0' ), true );
		$vendor    = ! empty( $args['include_vendor'] ) && ! in_array( $args['include_vendor'], array( 'false', '0' ), true );

		$entries   = array();
		$truncated = false;
		self::walk_list( $path['real'], $path['rel'], $recursive ? 3 : 0, $vendor, $entries, $truncated );

		return array(
			'path'      => $path['rel'],
			'count'     => count( $entries ),
			'truncated' => $truncated,
			'entries'   => $entries,
		);
	}

	private static function walk_list( string $dir, string $rel, int $depth, bool $vendor, array &$entries, bool &$truncated ): void {
		$names = scandir( $dir );
		if ( false === $names ) {
			return;
		}
		sort( $names );
		foreach ( $names as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			if ( count( $entries ) >= self::MAX_LIST_ENTRIES ) {
				$truncated = true;
				return;
			}
			$full = $dir . DIRECTORY_SEPARATOR . $name;
			$real = realpath( $full );
			if ( false === $real || ! self::inside( $real ) || self::blocked( $real ) ) {
				continue;
			}
			$is_dir = is_dir( $real );
			$entry  = array(
				'path'     => ( '' === $rel ? '' : $rel . '/' ) . $name,
				'type'     => $is_dir ? 'dir' : 'file',
				'modified' => gmdate( 'c', (int) filemtime( $real ) ),
			);
			if ( ! $is_dir ) {
				$entry['size'] = (int) filesize( $real );
			}
			$entries[] = $entry;
			if ( $is_dir && $depth > 0 && ( $vendor || ! in_array( $name, self::NOISE_DIRS, true ) ) && ! is_link( $full ) ) {
				self::walk_list( $real, $entry['path'], $depth - 1, $vendor, $entries, $truncated );
			}
		}
	}

	

	private static function read_file( array $path, array $args ): array {
		if ( ! is_file( $path['real'] ) ) {
			throw new \Exception( 'That path is a directory. Use action "list" to see what is in it.' );
		}
		$size = (int) filesize( $path['real'] );
		$meta = array(
			'path'     => $path['rel'],
			'size'     => $size,
			'modified' => gmdate( 'c', (int) filemtime( $path['real'] ) ),
		);
		if ( self::is_binary( $path['real'] ) ) {
			return $meta + array(
				'binary'  => true,
				'content' => null,
				'note'    => 'Binary file: only its size is shown.',
			);
		}

		$limit  = max( 1, min( 2000, (int) ( $args['limit'] ?? 200 ) ) );
		$offset = (int) ( $args['offset'] ?? 1 );

		$file = new \SplFileObject( $path['real'], 'r' );
		$file->setFlags( \SplFileObject::DROP_NEW_LINE );

		$total = null;
		if ( $size <= self::MAX_SEARCH_FILE_BYTES * 4 ) {
			$file->seek( PHP_INT_MAX );
			$total = $file->key() + ( '' === (string) $file->current() ? 0 : 1 );
			$file->rewind();
		}
		if ( $offset < 0 ) {
			if ( null === $total ) {
				throw new \Exception( 'A negative offset needs a file small enough to count lines. Pass a positive offset.' );
			}
			$offset = max( 1, $total + $offset + 1 );
		}
		$offset = max( 1, $offset );

		$lines = array();
		$bytes = 0;
		$cut   = false;
		$file->seek( $offset - 1 );
		for ( $i = 0; $i < $limit && $file->valid(); $i++ ) {
			$line = (string) $file->current();
			if ( $file->eof() && '' === $line ) {
				break;
			}
			$bytes += strlen( $line ) + 1;
			if ( $bytes > self::MAX_READ_BYTES ) {
				$cut = true;
				break;
			}
			$lines[] = $line;
			$file->next();
		}

		$last = $offset + count( $lines ) - 1;
		return $meta + array(
			'binary'      => false,
			'from_line'   => $offset,
			'to_line'     => max( $offset - 1, $last ),
			'total_lines' => $total,
			'truncated'   => $cut || ( null !== $total && $last < $total ),
			'content'     => self::utf8( implode( "\n", $lines ) ),
		);
	}

	private static function utf8( string $text ): string {
		return function_exists( 'mb_convert_encoding' ) ? (string) mb_convert_encoding( $text, 'UTF-8', 'UTF-8' ) : $text;
	}

	

	private static function search( array $path, array $args ): array {
		$query = isset( $args['query'] ) ? (string) $args['query'] : '';
		if ( '' === $query || strlen( $query ) > 500 ) {
			throw new \Exception( 'query is required for a search (up to 500 characters).' );
		}
		$regex = ! empty( $args['regex'] ) && ! in_array( $args['regex'], array( 'false', '0' ), true );
		if ( $regex ) {
			$pattern = '~' . str_replace( '~', '\~', $query ) . '~u';
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Probing whether the caller's pattern compiles; failure is reported below.
			if ( false === @preg_match( $pattern, '' ) ) {
				throw new \Exception( 'query is not a valid regular expression.' );
			}
		}
		$max     = max( 1, min( 200, (int) ( $args['max_results'] ?? 50 ) ) );
		$context = max( 0, min( 3, (int) ( $args['context'] ?? 0 ) ) );
		$vendor  = ! empty( $args['include_vendor'] ) && ! in_array( $args['include_vendor'], array( 'false', '0' ), true );
		$exts    = isset( $args['extensions'] ) && is_array( $args['extensions'] ) && $args['extensions']
			? array_map( static function ( $e ) {
				return strtolower( ltrim( (string) $e, '.' ) );
			}, $args['extensions'] )
			: self::DEFAULT_EXTENSIONS;

		$files = array();
		if ( is_file( $path['real'] ) ) {
			$files[] = $path['real'];
		} else {
			self::collect( $path['real'], $vendor, $exts, $files );
		}

		$matches  = array();
		$scanned  = 0;
		$deadline = microtime( true ) + 15;
		$stopped  = false;

		foreach ( $files as $file ) {
			if ( microtime( true ) > $deadline ) {
				$stopped = true;
				break;
			}
			if ( filesize( $file ) > self::MAX_SEARCH_FILE_BYTES || self::is_binary( $file ) ) {
				continue;
			}
			++$scanned;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local file under wp-content.
			$text = file_get_contents( $file );
			if ( false === $text ) {
				continue;
			}
			if ( $regex ? ! preg_match( $pattern, $text ) : false === strpos( $text, $query ) ) {
				continue;
			}
			$rel   = ltrim( str_replace( '\\', '/', substr( $file, strlen( self::root() ) ) ), '/' );
			$lines = preg_split( '/\r\n|\n|\r/', $text );
			foreach ( (array) $lines as $index => $line ) {
				$hit = $regex ? (bool) preg_match( $pattern, $line ) : false !== strpos( $line, $query );
				if ( ! $hit ) {
					continue;
				}
				$entry = array(
					'path' => $rel,
					'line' => $index + 1,
					'text' => self::utf8( strlen( $line ) > 300 ? substr( $line, 0, 300 ) . '…' : $line ),
				);
				if ( $context > 0 ) {
					$from           = max( 0, $index - $context );
					$entry['context'] = array_map(
						static function ( $l ) {
							return strlen( $l ) > 300 ? substr( $l, 0, 300 ) . '…' : $l;
						},
						array_slice( (array) $lines, $from, $index - $from + $context + 1 )
					);
					$entry['context_from_line'] = $from + 1;
				}
				$matches[] = $entry;
				if ( count( $matches ) >= $max ) {
					break 2;
				}
			}
		}

		return array(
			'query'         => $query,
			'path'          => $path['rel'],
			'files_scanned' => $scanned,
			'count'         => count( $matches ),
			'truncated'     => count( $matches ) >= $max || $stopped,
			'matches'       => $matches,
		);
	}

	private static function collect( string $dir, bool $vendor, array $exts, array &$files ): void {
		if ( count( $files ) >= 5000 ) {
			return;
		}
		$names = scandir( $dir );
		if ( false === $names ) {
			return;
		}
		foreach ( $names as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$full = $dir . DIRECTORY_SEPARATOR . $name;
			$real = realpath( $full );
			if ( false === $real || ! self::inside( $real ) || self::blocked( $real ) || is_link( $full ) ) {
				continue;
			}
			if ( is_dir( $real ) ) {
				if ( $vendor || ! in_array( $name, self::NOISE_DIRS, true ) ) {
					self::collect( $real, $vendor, $exts, $files );
				}
				continue;
			}
			if ( in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), $exts, true ) ) {
				$files[] = $real;
			}
		}
	}
}
