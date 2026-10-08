<?php
namespace More_MCP\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Classifier {

	const GROUP_FALLBACK = 'integrations';

	const READ_VERBS = array(
		'get', 'list', 'count', 'search', 'find', 'fetch', 'read', 'view',
		'show', 'check', 'audit', 'validate', 'inspect', 'lookup', 'preview',
		'describe', 'diff', 'query',
	);

	const WRITE_VERBS = array(
		'create', 'update', 'delete', 'add', 'remove', 'set', 'save', 'replace',
		'insert', 'move', 'reorder', 'restore', 'trash', 'untrash', 'spam',
		'approve', 'unapprove', 'upload', 'process', 'import', 'export', 'clone',
		'duplicate', 'sync', 'purge', 'flush', 'clear', 'empty', 'start', 'stop',
		'run', 'generate', 'activate', 'deactivate', 'install', 'uninstall',
		'revert', 'batch', 'publish', 'unpublish', 'send', 'submit', 'execute',
		'invoke', 'reset', 'regenerate', 'optimize', 'compress', 'cancel',
		'refund', 'assign', 'unassign', 'enable', 'disable', 'toggle', 'write',
		'reply', 'rename', 'merge', 'convert', 'translate', 'truncate', 'undo',
		'redo', 'rollback', 'resend', 'retry', 'repair', 'append', 'prepend',
		'attach', 'detach', 'subscribe', 'unsubscribe', 'register', 'unregister',
	);

	const STRONG_WRITE_VERBS = array(
		'create', 'update', 'delete', 'add', 'remove', 'save', 'replace',
		'insert', 'restore', 'trash', 'purge', 'flush', 'import', 'activate',
		'deactivate', 'install', 'publish', 'upload', 'approve', 'reorder',
		'revert', 'clone', 'duplicate', 'uninstall', 'truncate', 'rollback',
	);

	const DATA_PROVIDER_PREFIXES = array(
		'semrush_', 'dataforseo_', 'seranking_', 'ahrefs_', 'ga4_', 'gsc_',
	);

	const READ_EXACT = array(
		'ga4_run_report',
		'ga4_run_pivot_report',
		'more_mcp_connection_health',
		'wp_rest_routes',
	);

	const READ_PREFIXES = array(
		'wc_report_',
	);

	const ADVANCED_PREFIXES = array(
		'wp_rest_', 'wp_privileged_', 'wp_history_', 'wp_audit_', 'wp_send_feedback',
	);

	private static function slug_groups(): array {
		return array(
			'woocommerce'            => 'commerce',
			'edd'                    => 'commerce',
			'fluentcart'             => 'commerce',

			'elementor'              => 'builders',
			'divi'                   => 'builders',
			'beaver-builder'         => 'builders',
			'siteorigin-panels'      => 'builders',

			'acf'                    => 'fields',
			'metabox'                => 'fields',
			'toolset-types'          => 'fields',
			'pods'                   => 'fields',
			'cptui'                  => 'fields',
			'mbcpt'                  => 'fields',

			'redirection'            => 'seo',
			'analytics'              => 'seo_data',

			'email'                  => 'forms',
			'forms'                  => 'forms',

			'litespeed'              => 'performance',
			'wp-rocket'              => 'performance',
			'w3tc'                   => 'performance',
			'redis-cache'            => 'performance',
			'autoptimize'            => 'performance',
			'wp-optimize'            => 'performance',
			'imagify'                => 'performance',
			'ewww'                   => 'performance',
			'smush'                  => 'performance',
			'shortpixel'             => 'performance',

			'updraftplus'            => 'security',
			'backwpup'               => 'security',
			'duplicator'             => 'security',
			'wpvivid'                => 'security',
			'ai1wm'                  => 'security',
			'wordfence'              => 'security',
			'defender'               => 'security',
			'solid-security'         => 'security',
			'sucuri'                 => 'security',
			'really-simple-security' => 'security',
			'akismet'                => 'security',
			'patchstack'             => 'security',

			'instant-images'         => 'media',
		);
	}

	public static function groups(): array {
		$groups = array(
			'content'      => __( 'Posts, pages, terms, meta and revisions', 'mordenhost-mcp-server' ),
			'media'        => __( 'Media library and stock images', 'mordenhost-mcp-server' ),
			'comments'     => __( 'Comments', 'mordenhost-mcp-server' ),
			'users'        => __( 'Users', 'mordenhost-mcp-server' ),
			'settings'     => __( 'Options, permalinks and site diagnostics', 'mordenhost-mcp-server' ),
			'appearance'   => __( 'Menus, widgets, theme mods and custom CSS', 'mordenhost-mcp-server' ),
			'plugins'      => __( 'Plugin and theme install, update and activation', 'mordenhost-mcp-server' ),
			'seo'          => __( 'SEO meta and redirects', 'mordenhost-mcp-server' ),
			'seo_data'     => __( 'External SEO and analytics data', 'mordenhost-mcp-server' ),
			'builders'     => __( 'Page builders and the block editor', 'mordenhost-mcp-server' ),
			'commerce'     => __( 'Stores (WooCommerce, EDD, FluentCart)', 'mordenhost-mcp-server' ),
			'fields'       => __( 'Custom fields and content types', 'mordenhost-mcp-server' ),
			'forms'        => __( 'Forms and email', 'mordenhost-mcp-server' ),
			'performance'  => __( 'Caching and image optimization', 'mordenhost-mcp-server' ),
			'security'     => __( 'Backups and security', 'mordenhost-mcp-server' ),
			'webhooks'     => __( 'Webhooks', 'mordenhost-mcp-server' ),
			'advanced'     => __( 'Advanced: REST access, database search-replace, file reading, change history', 'mordenhost-mcp-server' ),
			'imported'     => __( 'Imported abilities from other plugins', 'mordenhost-mcp-server' ),
			'integrations' => __( 'Other plugin integrations', 'mordenhost-mcp-server' ),
		);

		

		
		
		if ( class_exists( \More_MCP\Knowledge\Experimental::class ) && \More_MCP\Knowledge\Experimental::is_enabled() ) {
			$groups['memory'] = __( 'Shared agent memory (experimental)', 'mordenhost-mcp-server' );
		}

		return $groups;
	}

	public static function is_read_only( string $tool ): bool {
		if ( in_array( $tool, self::READ_EXACT, true ) ) {
			return true;
		}
		foreach ( self::READ_PREFIXES as $prefix ) {
			if ( 0 === strpos( $tool, $prefix ) ) {
				return true;
			}
		}

		static $read   = null;
		static $write  = null;
		static $strong = null;
		if ( null === $read ) {
			$read   = array_flip( self::READ_VERBS );
			$write  = array_flip( self::WRITE_VERBS );
			$strong = array_flip( self::STRONG_WRITE_VERBS );
		}

		$tokens   = explode( '_', $tool );
		$verb     = null;
		$is_read  = false;
		foreach ( $tokens as $token ) {
			if ( isset( $read[ $token ] ) ) {
				$verb    = $token;
				$is_read = true;
				break;
			}
			if ( isset( $write[ $token ] ) ) {
				return false;
			}
		}

		if ( null === $verb ) {

			$is_read = false;
			foreach ( self::DATA_PROVIDER_PREFIXES as $prefix ) {
				if ( 0 === strpos( $tool, $prefix ) ) {
					$is_read = true;
					break;
				}
			}
		}

		if ( ! $is_read ) {
			return false;
		}

		foreach ( $tokens as $token ) {
			if ( isset( $strong[ $token ] ) ) {
				return false;
			}
		}
		return true;
	}

	public static function group_of( string $tool ): string {
		if ( 'more_mcp_connection_health' === $tool || 'more_mcp_undo_last_operation' === $tool ) {
			return 'system';
		}

		if ( 0 === strpos( $tool, 'discovered_' ) ) {
			return 'imported';
		}

		foreach ( self::DATA_PROVIDER_PREFIXES as $prefix ) {
			if ( 0 === strpos( $tool, $prefix ) ) {
				return 'seo_data';
			}
		}

		if ( 0 === strpos( $tool, 'webhook_' ) ) {
			return 'webhooks';
		}
		if ( 0 === strpos( $tool, 'memory_' ) ) {
			return 'memory';
		}
		if ( 0 === strpos( $tool, 'blocks_' ) ) {
			return 'builders';
		}
		if ( 0 === strpos( $tool, 'ai_' ) ) {
			return 'media';
		}
		if ( 0 === strpos( $tool, 'seo_' ) ) {
			return 'seo';
		}

		if ( 0 === strpos( $tool, 'wp_' ) ) {
			foreach ( self::ADVANCED_PREFIXES as $prefix ) {
				if ( 0 === strpos( $tool, $prefix ) ) {
					return 'advanced';
				}
			}
			return self::core_group( substr( $tool, 3 ) );
		}

		$slug = \More_MCP\Capabilities\Toggles::slug_for_tool( $tool );
		if ( '' !== $slug ) {
			$map = self::slug_groups();
			return $map[ $slug ] ?? self::GROUP_FALLBACK;
		}

		return self::GROUP_FALLBACK;
	}

	private static function core_group( string $rest ): string {
		$tokens = explode( '_', $rest );
		$has    = static function ( array $words ) use ( $tokens ): bool {
			return (bool) array_intersect( $words, $tokens );
		};

		if ( $has( array( 'theme' ) ) && $has( array( 'mod', 'mods' ) ) ) {
			return 'appearance';
		}
		if ( $has( array( 'menu', 'menus', 'widget', 'widgets', 'sidebars' ) ) || $has( array( 'css' ) ) ) {
			return 'appearance';
		}
		if ( $has( array( 'plugin' ) ) && $has( array( 'settings' ) ) ) {
			return 'settings';
		}
		if ( $has( array( 'plugin', 'plugins', 'theme', 'themes' ) ) ) {
			return 'plugins';
		}
		if ( $has( array( 'seo' ) ) ) {
			return 'seo';
		}
		if ( $has( array( 'media' ) ) || $has( array( 'image' ) ) ) {
			return 'media';
		}
		if ( $has( array( 'comment', 'comments' ) ) ) {
			return 'comments';
		}
		if ( $has( array( 'user', 'users' ) ) ) {
			return 'users';
		}
		if ( $has( array( 'option', 'permalink', 'site', 'error', 'cron' ) ) ) {
			return 'settings';
		}
		if ( 'set_front_page' === $rest ) {
			return 'settings';
		}
		return 'content';
	}
}
