<?php
/**
 * Plugin lifecycle operations.
 *
 * Every mutation here follows the same shape, in this order:
 *
 *   capability -> resolve target -> protection checks -> preview OR apply
 *                                                     -> read back actual state
 *
 * The read-back is not decoration. WordPress's upgrader can report success on a
 * no-op, and `activate_plugin()` returns null both when activation worked and
 * when it was already active. Returning the state observed afterwards is the
 * only way a caller can distinguish those.
 */

namespace More_MCP\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugins {

	/**
	 * All installed plugins, keyed by plugin file.
	 *
	 * @return array
	 */
	private static function all(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}

	/**
	 * Resolve a caller-supplied reference to an installed plugin file.
	 *
	 * Accepts the full file path ("akismet/akismet.php") or a bare directory
	 * slug ("akismet"). The slug form is accepted because it is what a person
	 * naturally types and what wp.org uses, but it is only honoured when it
	 * resolves to exactly one installed plugin — an ambiguous match is an error
	 * rather than a guess, since guessing here means operating on the wrong code.
	 *
	 * @param array $args Tool arguments.
	 * @return string Plugin file path.
	 * @throws \Exception When missing, unknown, or ambiguous.
	 */
	private static function resolve( array $args ): string {
		$ref = isset( $args['plugin'] ) ? trim( (string) $args['plugin'] ) : '';
		if ( '' === $ref ) {
			throw new \Exception( 'plugin is required — the file path from wp_get_plugins, e.g. "akismet/akismet.php".' );
		}

		// Reject anything that tries to escape the plugins directory. These
		// values reach filesystem operations, so a traversal attempt is not
		// treated as a lookup miss.
		if ( false !== strpos( $ref, '..' ) || 0 === strpos( $ref, '/' ) || preg_match( '#^[a-zA-Z]:#', $ref ) ) {
			throw new \Exception( 'plugin must be a plugin file path relative to the plugins directory, not an absolute or traversing path.' );
		}

		$installed = self::all();

		if ( isset( $installed[ $ref ] ) ) {
			return $ref;
		}

		$matches = array();
		foreach ( array_keys( $installed ) as $file ) {
			if ( Guard::slug_of( $file ) === $ref ) {
				$matches[] = $file;
			}
		}

		if ( 1 === count( $matches ) ) {
			return $matches[0];
		}
		if ( count( $matches ) > 1 ) {
			throw new \Exception(
				sprintf(
					'"%s" matches more than one installed plugin (%s). Pass the full file path.',
					esc_html( $ref ),
					esc_html( implode( ', ', $matches ) )
				)
			);
		}

		throw new \Exception(
			sprintf( 'No installed plugin matches "%s". Use wp_get_plugins to list what is installed.', esc_html( $ref ) )
		);
	}

	/**
	 * Current state of one plugin, read from WordPress.
	 *
	 * @param string $file Plugin file path.
	 * @return array
	 */
	private static function state( string $file ): array {
		$installed = self::all();
		$data      = $installed[ $file ] ?? array();
		return array(
			'plugin'  => $file,
			'slug'    => Guard::slug_of( $file ),
			'name'    => $data['Name'] ?? '',
			'version' => $data['Version'] ?? '',
			'active'  => is_plugin_active( $file ),
		);
	}

	/**
	 * wp_get_plugin_updates handler.
	 *
	 * @return array
	 */
	public static function list_updates(): array {
		Guard::require_cap( 'update_plugins', 'view plugin updates' );

		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		// Ask WordPress to refresh its cached update data, otherwise this can
		// report stale results for up to 12 hours.
		wp_update_plugins();

		$updates = get_plugin_updates();
		$rows    = array();
		foreach ( $updates as $file => $plugin ) {
			$rows[] = array(
				'plugin'          => $file,
				'slug'            => Guard::slug_of( $file ),
				'name'            => $plugin->Name ?? '',
				'current_version' => $plugin->Version ?? '',
				'new_version'     => $plugin->update->new_version ?? '',
				'active'          => is_plugin_active( $file ),
			);
		}

		return array(
			'count'   => count( $rows ),
			'updates' => $rows,
			'note'    => empty( $rows ) ? 'All installed plugins are up to date.' : 'Use wp_update_plugin with one plugin file path at a time.',
		);
	}

	/**
	 * wp_activate_plugin handler.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
	public static function activate( array $args ): array {
		Guard::require_cap( 'activate_plugins', 'activate plugins' );
		$file  = self::resolve( $args );
		$state = self::state( $file );

		if ( $state['active'] ) {
			return array_merge(
				array( 'written' => false, 'already' => true, 'message' => 'Plugin is already active. Nothing to do.' ),
				$state
			);
		}

		Guard::refuse_protected( $file, 'activate' );

		if ( ! Guard::is_confirmed( $args, $file ) ) {
			return Guard::preview(
				'wp_activate_plugin',
				$file,
				sprintf( 'Would activate %s (version %s).', $state['name'] ?: $file, $state['version'] ),
				array( 'current' => $state )
			);
		}

		// activate_plugin() returns a WP_Error when the plugin fatals during
		// activation, which is exactly the case worth surfacing rather than
		// swallowing: the plugin stays inactive and the caller learns why.
		$result = activate_plugin( $file );
		if ( is_wp_error( $result ) ) {
			throw new \Exception(
				sprintf(
					'Activation failed for %s: %s. The plugin was left inactive.',
					esc_html( $file ),
					esc_html( $result->get_error_message() )
				)
			);
		}

		$after = self::state( $file );
		return array_merge(
			array(
				'written'  => true,
				'verified' => $after['active'],
				'message'  => $after['active']
					? 'Plugin activated.'
					: 'WordPress reported no error but the plugin is not active. Check for a conflicting must-use plugin or a filter blocking activation.',
			),
			$after
		);
	}

	/**
	 * wp_deactivate_plugin handler.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
	public static function deactivate( array $args ): array {
		Guard::require_cap( 'activate_plugins', 'deactivate plugins' );
		$file = self::resolve( $args );

		Guard::refuse_self( $file, 'deactivate' );
		Guard::refuse_protected( $file, 'deactivate' );

		$state = self::state( $file );
		if ( ! $state['active'] ) {
			return array_merge(
				array( 'written' => false, 'already' => true, 'message' => 'Plugin is already inactive. Nothing to do.' ),
				$state
			);
		}

		if ( ! Guard::is_confirmed( $args, $file ) ) {
			return Guard::preview(
				'wp_deactivate_plugin',
				$file,
				sprintf( 'Would deactivate %s (version %s). Any feature it provides stops working immediately.', $state['name'] ?: $file, $state['version'] ),
				array( 'current' => $state )
			);
		}

		deactivate_plugins( array( $file ) );

		$after = self::state( $file );
		return array_merge(
			array(
				'written'  => true,
				'verified' => ! $after['active'],
				'message'  => $after['active']
					? 'Plugin still reports as active after deactivation. Something re-activated it — check for a must-use plugin or an activation filter.'
					: 'Plugin deactivated.',
			),
			$after
		);
	}

	/**
	 * wp_update_plugin handler.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
	public static function update( array $args ): array {
		Guard::require_cap( 'update_plugins', 'update plugins' );
		$file = self::resolve( $args );

		Guard::refuse_protected( $file, 'update' );

		$before = self::state( $file );

		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_plugins();
		$updates     = get_plugin_updates();
		$new_version = $updates[ $file ]->update->new_version ?? null;

		if ( null === $new_version ) {
			return array_merge(
				array(
					'written' => false,
					'already' => true,
					'message' => 'No update is available for this plugin.',
				),
				$before
			);
		}

		if ( ! Guard::is_confirmed( $args, $file ) ) {
			return Guard::preview(
				'wp_update_plugin',
				$file,
				sprintf( 'Would update %s from %s to %s.', $before['name'] ?: $file, $before['version'], $new_version ),
				array(
					'current'     => $before,
					'new_version' => $new_version,
					'active'      => $before['active'],
					'caution'     => $before['active'] ? 'This plugin is active, so the update takes effect on the live site immediately.' : null,
				)
			);
		}

		Guard::require_upgrader();

		// Automatic_Upgrader_Skin keeps the upgrader from echoing progress HTML
		// into the JSON-RPC response body.
		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $file );
		Guard::assert_ok( $result, 'update ' . $file );

		// Clear the cached plugin data so the read-back sees the new version
		// rather than the value loaded at the start of the request.
		wp_clean_plugins_cache();

		$after = self::state( $file );
		$moved = ( $after['version'] !== $before['version'] );

		return array_merge(
			array(
				'written'          => true,
				'verified'         => $moved,
				'previous_version' => $before['version'],
				'message'          => $moved
					? sprintf( 'Updated from %s to %s.', $before['version'], $after['version'] )
					: 'The upgrader reported success but the installed version did not change. Treat this as a no-op and check the update source.',
				'skin_messages'    => $skin->get_upgrade_messages(),
			),
			$after
		);
	}

	/**
	 * wp_install_plugin handler.
	 *
	 * Slug-only by design. See the Manager docblock: accepting a package URL
	 * would turn this into a download-and-execute primitive.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
	public static function install( array $args ): array {
		Guard::require_cap( 'install_plugins', 'install plugins' );

		$slug = isset( $args['slug'] ) ? trim( (string) $args['slug'] ) : '';
		if ( '' === $slug ) {
			throw new \Exception( 'slug is required — a WordPress.org plugin slug such as "wp-super-cache".' );
		}

		// A wp.org slug is lowercase alphanumerics and hyphens. Rejecting
		// anything else here is what keeps URLs, paths, and traversal attempts
		// out of the install path entirely, rather than relying on a later check.
		if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug ) ) {
			throw new \Exception(
				sprintf(
					'"%s" is not a valid WordPress.org plugin slug. Only lowercase letters, digits, and hyphens are accepted — package URLs and file paths are deliberately not supported.',
					esc_html( $slug )
				)
			);
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		// Ask wp.org about the slug before writing anything. This both validates
		// that the plugin exists and gives the preview something meaningful to
		// show — name, author, version, install count — so a caller can tell a
		// legitimate plugin from a typo-squat before confirming.
		$info = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'short_description' => true, 'sections' => false, 'versions' => false ),
			)
		);
		if ( is_wp_error( $info ) ) {
			throw new \Exception(
				sprintf(
					'Could not find "%s" on WordPress.org: %s',
					esc_html( $slug ),
					esc_html( $info->get_error_message() )
				)
			);
		}

		// Already installed? Report it rather than reinstalling over the top.
		foreach ( array_keys( self::all() ) as $existing ) {
			if ( Guard::slug_of( $existing ) === $slug ) {
				return array_merge(
					array(
						'written' => false,
						'already' => true,
						'message' => 'A plugin with this slug is already installed. Use wp_update_plugin to update it, or wp_activate_plugin to activate it.',
					),
					self::state( $existing )
				);
			}
		}

		$activate = ! empty( $args['activate'] );

		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			return Guard::preview(
				'wp_install_plugin',
				$slug,
				sprintf(
					'Would download and install "%s" version %s from WordPress.org%s.',
					$info->name ?? $slug,
					$info->version ?? '?',
					$activate ? ', then activate it' : ''
				),
				array(
					'source'            => 'wordpress.org',
					'name'              => $info->name ?? '',
					'version'           => $info->version ?? '',
					'author'            => wp_strip_all_tags( $info->author ?? '' ),
					'active_installs'   => $info->active_installs ?? null,
					'last_updated'      => $info->last_updated ?? '',
					'requires_php'      => $info->requires_php ?? null,
					'tested_up_to'      => $info->tested ?? null,
					'short_description' => $info->short_description ?? '',
					'will_activate'     => $activate,
					'caution'           => 'This installs and runs third-party code on the site. Confirm the name, author, and install count above match the plugin you intend before proceeding.',
				)
			);
		}

		Guard::require_upgrader();

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $info->download_link );
		Guard::assert_ok( $result, 'install ' . $slug );

		wp_clean_plugins_cache();

		// Locate what was actually installed rather than assuming the directory
		// matches the slug — some packages unpack under a different name.
		$installed_file = $upgrader->plugin_info();
		if ( ! $installed_file ) {
			foreach ( array_keys( self::all() ) as $existing ) {
				if ( Guard::slug_of( $existing ) === $slug ) {
					$installed_file = $existing;
					break;
				}
			}
		}
		if ( ! $installed_file ) {
			throw new \Exception( 'The package installed but the plugin file could not be located afterwards. Check the plugins list in wp-admin.' );
		}

		$activation_error = null;
		if ( $activate ) {
			$activated = activate_plugin( $installed_file );
			if ( is_wp_error( $activated ) ) {
				// Installed but failed to activate is a real, distinct outcome —
				// report both halves instead of throwing away the install.
				$activation_error = $activated->get_error_message();
			}
		}

		$after = self::state( $installed_file );
		return array_merge(
			array(
				'written'          => true,
				'verified'         => ( '' !== $after['version'] ),
				'source'           => 'wordpress.org',
				'activation_error' => $activation_error,
				'message'          => $activation_error
					? sprintf( 'Installed version %s, but activation failed: %s. The plugin is installed and inactive.', $after['version'], $activation_error )
					: sprintf( 'Installed version %s.%s', $after['version'], $activate ? ' Activated.' : ' Not activated.' ),
				'skin_messages'    => $skin->get_upgrade_messages(),
			),
			$after
		);
	}

	/**
	 * wp_delete_plugin handler.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
	public static function delete( array $args ): array {
		Guard::require_cap( 'delete_plugins', 'delete plugins' );
		$file = self::resolve( $args );

		Guard::refuse_self( $file, 'delete' );
		Guard::refuse_protected( $file, 'delete' );

		$state = self::state( $file );

		// Deliberately does NOT deactivate first. Deletion is irreversible, and
		// requiring a separate deactivation gives the operation a checkpoint
		// where a mistake is still recoverable.
		if ( $state['active'] ) {
			throw new \Exception(
				sprintf(
					'%s is active. Deactivate it first with wp_deactivate_plugin — this tool will not deactivate on your behalf, because deletion cannot be undone.',
					esc_html( $file )
				)
			);
		}

		if ( ! Guard::is_confirmed( $args, $file ) ) {
			return Guard::preview(
				'wp_delete_plugin',
				$file,
				sprintf( 'Would permanently delete the files for %s (version %s). This cannot be undone.', $state['name'] ?: $file, $state['version'] ),
				array(
					'current' => $state,
					'caution' => 'Deletion removes the plugin directory from disk. Settings stored in the database are left behind unless the plugin has an uninstall routine.',
				)
			);
		}

		Guard::require_upgrader();

		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$result = delete_plugins( array( $file ) );
		Guard::assert_ok( $result, 'delete ' . $file );

		wp_clean_plugins_cache();

		$gone = ! isset( self::all()[ $file ] );
		return array(
			'written'  => true,
			'verified' => $gone,
			'plugin'   => $file,
			'slug'     => Guard::slug_of( $file ),
			'name'     => $state['name'],
			'version'  => $state['version'],
			'message'  => $gone
				? 'Plugin files deleted.'
				: 'WordPress reported success but the plugin is still listed as installed. Check filesystem permissions.',
		);
	}
}
