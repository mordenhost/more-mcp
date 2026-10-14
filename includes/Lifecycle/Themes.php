<?php

namespace Mordenhost_MCP\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Themes {

	private static function resolve( array $args, string $key = 'stylesheet' ): \WP_Theme {
		$ref = isset( $args[ $key ] ) ? trim( (string) $args[ $key ] ) : '';
		if ( '' === $ref ) {
			throw new \Exception( sprintf( '%s is required: the theme directory name, e.g. "twentytwentyfive".', esc_html( $key ) ) );
		}

		
		
		if ( false !== strpos( $ref, '/' ) || false !== strpos( $ref, '\\' ) || false !== strpos( $ref, '..' ) ) {
			throw new \Exception( 'The stylesheet must be a plain theme directory name, not a path.' );
		}

		$theme = wp_get_theme( $ref );
		if ( ! $theme->exists() ) {
			throw new \Exception(
				sprintf( 'No installed theme matches "%s". Use wp_get_themes to list what is installed.', esc_html( $ref ) )
			);
		}
		return $theme;
	}

	private static function state( \WP_Theme $theme ): array {
		$parent = $theme->parent();
		return array(
			'stylesheet' => $theme->get_stylesheet(),
			'name'       => $theme->get( 'Name' ),
			'version'    => $theme->get( 'Version' ),
			'active'     => ( get_stylesheet() === $theme->get_stylesheet() ),
			'parent'     => $parent ? $parent->get_stylesheet() : null,
			'is_block_theme' => method_exists( $theme, 'is_block_theme' ) ? $theme->is_block_theme() : null,
		);
	}

	public static function list_status(): array {
		Guard::require_cap( 'switch_themes', 'view theme status' );

		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_themes();
		$updates = get_theme_updates();

		$rows = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$row = self::state( $theme );
			$row['update_available'] = isset( $updates[ $stylesheet ] );
			$row['new_version']      = $updates[ $stylesheet ]->update['new_version'] ?? null;
			$rows[] = $row;
		}

		return array(
			'count'           => count( $rows ),
			'active'          => get_stylesheet(),
			'updates_pending' => count( $updates ),
			'themes'          => $rows,
		);
	}

	public static function activate( array $args ): array {
		Guard::require_cap( 'switch_themes', 'switch themes' );
		$theme = self::resolve( $args );
		$state = self::state( $theme );

		if ( $state['active'] ) {
			return array_merge(
				array( 'written' => false, 'already' => true, 'message' => 'This theme is already active. Nothing to do.' ),
				$state
			);
		}

		
		$errors = $theme->errors();
		if ( $errors ) {
			throw new \Exception(
				sprintf(
					'Theme "%s" cannot be activated: %s',
					esc_html( $state['stylesheet'] ),
					esc_html( $errors->get_error_message() )
				)
			);
		}

		$outgoing = self::state( wp_get_theme() );

		if ( ! Guard::is_confirmed( $args, $state['stylesheet'] ) ) {
			return Guard::preview(
				'wp_activate_theme',
				$state['stylesheet'],
				sprintf(
					'Would switch the active theme from %s (%s) to %s (%s).',
					$outgoing['name'],
					$outgoing['stylesheet'],
					$state['name'],
					$state['stylesheet']
				),
				array(
					'outgoing' => $outgoing,
					'incoming' => $state,
					'caution'  => 'Switching themes changes the site for every visitor immediately. Widget placement, menu locations, and Customizer settings are per-theme, so layouts built for the outgoing theme may not carry over.',
				)
			);
		}

		switch_theme( $state['stylesheet'] );

		$now      = get_stylesheet();
		$switched = ( $now === $state['stylesheet'] );
		return array_merge(
			array(
				'written'         => true,
				'verified'        => $switched,
				'previous_theme'  => $outgoing['stylesheet'],
				'message'         => $switched
					? sprintf( 'Active theme switched to %s.', $state['stylesheet'] )
					: sprintf( 'Switch reported no error but the active theme is %s. A filter may be forcing the theme.', $now ),
			),
			self::state( wp_get_theme() )
		);
	}

	public static function update( array $args ): array {
		Guard::require_cap( 'update_themes', 'update themes' );
		$theme  = self::resolve( $args );
		$before = self::state( $theme );

		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_themes();
		$updates     = get_theme_updates();
		$stylesheet  = $before['stylesheet'];
		$new_version = $updates[ $stylesheet ]->update['new_version'] ?? null;

		if ( null === $new_version ) {
			return array_merge(
				array( 'written' => false, 'already' => true, 'message' => 'No update is available for this theme.' ),
				$before
			);
		}

		if ( ! Guard::is_confirmed( $args, $stylesheet ) ) {
			return Guard::preview(
				'wp_update_theme',
				$stylesheet,
				sprintf( 'Would update %s from %s to %s.', $before['name'], $before['version'], $new_version ),
				array(
					'current'     => $before,
					'new_version' => $new_version,
					'caution'     => $before['active']
						? 'This is the active theme, so the update takes effect on the live site immediately. A theme update overwrites its files — any direct edits to them are lost.'
						: 'A theme update overwrites its files. Direct edits to them are lost.',
				)
			);
		}

		Guard::require_upgrader();

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( $stylesheet );
		Guard::assert_ok( $result, 'update theme ' . $stylesheet );

		wp_clean_themes_cache();

		$after = self::state( wp_get_theme( $stylesheet ) );
		$moved = ( $after['version'] !== $before['version'] );

		return array_merge(
			array(
				'written'          => true,
				'verified'         => $moved,
				'previous_version' => $before['version'],
				'message'          => $moved
					? sprintf( 'Updated from %s to %s.', $before['version'], $after['version'] )
					: 'The upgrader reported success but the installed version did not change. Treat this as a no-op.',
				'skin_messages'    => $skin->get_upgrade_messages(),
			),
			$after
		);
	}

	public static function install( array $args ): array {
		Guard::require_cap( 'install_themes', 'install themes' );

		$slug = isset( $args['slug'] ) ? trim( (string) $args['slug'] ) : '';
		if ( '' === $slug ) {
			throw new \Exception( 'slug is required: a WordPress.org theme slug such as "astra".' );
		}

		if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug ) ) {
			throw new \Exception(
				sprintf(
					'"%s" is not a valid WordPress.org theme slug. Only lowercase letters, digits, and hyphens are accepted — package URLs and file paths are deliberately not supported.',
					esc_html( $slug )
				)
			);
		}

		$activate = ! empty( $args['activate'] );
		if ( $activate ) {
			Guard::require_cap( 'switch_themes', 'switch themes' );
		}

		$existing = wp_get_theme( $slug );
		if ( $existing->exists() ) {
			return array_merge(
				array(
					'written' => false,
					'already' => true,
					'message' => 'A theme with this slug is already installed. Use wp_update_theme to update it, or wp_activate_theme to switch to it.',
				),
				self::state( $existing )
			);
		}

		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		
		$info = themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections'        => false,
					'tags'            => false,
					'active_installs' => true,
					'parent'          => true,
					'template'        => true,
				),
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
		$info = (object) $info;
		if ( empty( $info->download_link ) ) {
			throw new \Exception( sprintf( 'WordPress.org returned no download for "%s".', esc_html( $slug ) ) );
		}

		$author = $info->author ?? '';
		if ( is_array( $author ) || is_object( $author ) ) {
			$author = (array) $author;
			$author = $author['display_name'] ?? ( $author['author'] ?? '' );
		}
		$parent_slug = '';
		if ( ! empty( $info->template ) && $info->template !== $slug ) {
			$parent_slug = (string) $info->template;
		} elseif ( ! empty( $info->parent ) ) {
			$parent      = (array) $info->parent;
			$parent_slug = (string) ( $parent['slug'] ?? '' );
		}
		$parent_installed = '' !== $parent_slug && wp_get_theme( $parent_slug )->exists();

		if ( ! Guard::is_confirmed( $args, $slug ) ) {
			$summary = sprintf(
				'Would download and install the theme "%s" version %s from WordPress.org%s%s.',
				$info->name ?? $slug,
				$info->version ?? '?',
				( '' !== $parent_slug && ! $parent_installed ) ? sprintf( ', plus its parent theme "%s"', $parent_slug ) : '',
				$activate ? ', then make it the active theme' : ''
			);
			return Guard::preview(
				'wp_install_theme',
				$slug,
				$summary,
				array(
					'source'           => 'wordpress.org',
					'name'             => $info->name ?? '',
					'version'          => $info->version ?? '',
					'author'           => wp_strip_all_tags( (string) $author ),
					'active_installs'  => isset( $info->active_installs ) ? (int) $info->active_installs : null,
					'last_updated'     => (string) ( $info->last_updated ?? '' ),
					'requires'         => $info->requires ?? null,
					'requires_php'     => $info->requires_php ?? null,
					'parent'           => '' !== $parent_slug ? $parent_slug : null,
					'parent_installed' => '' !== $parent_slug ? $parent_installed : null,
					'will_activate'    => $activate,
					'caution'          => $activate
						? 'This installs third-party code and switches the theme every visitor sees. Confirm the name, author, and install count above before proceeding.'
						: 'This installs third-party code on the site; it does not change the active theme. Confirm the name, author, and install count above before proceeding.',
				)
			);
		}

		Guard::require_upgrader();

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( $info->download_link );
		Guard::assert_ok( $result, 'install theme ' . $slug );

		wp_clean_themes_cache();

		
		$installed = $upgrader->theme_info();
		if ( ! $installed || ! $installed->exists() ) {
			$installed = wp_get_theme( $slug );
		}
		if ( ! $installed->exists() ) {
			throw new \Exception( 'The package installed but the theme could not be located afterwards. Check Appearance > Themes in wp-admin.' );
		}
		$stylesheet = $installed->get_stylesheet();

		$parent_warning = null;
		if ( $installed->parent() === false && '' !== $parent_slug ) {
			$parent_warning = sprintf( 'The parent theme "%s" is not installed, so this child theme cannot be used until it is.', $parent_slug );
		}

		$activation_error = null;
		if ( $activate ) {
			$errors = $installed->errors();
			if ( $errors ) {
				$activation_error = $errors->get_error_message();
			} else {
				switch_theme( $stylesheet );
				if ( get_stylesheet() !== $stylesheet ) {
					$activation_error = sprintf( 'the active theme is still %s; a filter may be forcing the theme', get_stylesheet() );
				}
			}
		}

		$after = self::state( wp_get_theme( $stylesheet ) );
		if ( null !== $activation_error ) {
			$message = sprintf( 'Installed version %s, but it was not activated: %s. The theme is installed and inactive.', $after['version'], $activation_error );
		} else {
			$message = sprintf( 'Installed version %s.%s', $after['version'], $activate ? ' It is now the active theme.' : ' The active theme is unchanged.' );
		}
		return array_merge(
			array(
				'written'          => true,
				'verified'         => ( '' !== (string) $after['version'] ),
				'source'           => 'wordpress.org',
				'activation_error' => $activation_error,
				'parent_warning'   => $parent_warning,
				'message'          => $message,
				'skin_messages'    => $skin->get_upgrade_messages(),
			),
			$after
		);
	}

	public static function delete( array $args ): array {
		Guard::require_cap( 'delete_themes', 'delete themes' );
		$theme = self::resolve( $args );
		$state = self::state( $theme );

		if ( $state['active'] ) {
			throw new \Exception(
				sprintf(
					'"%s" is the active theme and cannot be deleted. Switch to another theme first with wp_activate_theme.',
					esc_html( $state['stylesheet'] )
				)
			);
		}

		
		$active_parent = wp_get_theme()->parent();
		if ( $active_parent && $active_parent->get_stylesheet() === $state['stylesheet'] ) {
			throw new \Exception(
				sprintf(
					'"%s" is the parent of the active theme (%s) and cannot be deleted — the active child theme depends on its files.',
					esc_html( $state['stylesheet'] ),
					esc_html( get_stylesheet() )
				)
			);
		}

		if ( ! Guard::is_confirmed( $args, $state['stylesheet'] ) ) {
			return Guard::preview(
				'wp_delete_theme',
				$state['stylesheet'],
				sprintf( 'Would permanently delete the files for %s (version %s). This cannot be undone.', $state['name'], $state['version'] ),
				array(
					'current' => $state,
					'caution' => 'Deletion removes the theme directory from disk.',
				)
			);
		}

		Guard::require_upgrader();

		if ( ! function_exists( 'delete_theme' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		$result = delete_theme( $state['stylesheet'] );
		Guard::assert_ok( $result, 'delete theme ' . $state['stylesheet'] );

		wp_clean_themes_cache();

		$gone = ! wp_get_theme( $state['stylesheet'] )->exists();
		return array(
			'written'    => true,
			'verified'   => $gone,
			'stylesheet' => $state['stylesheet'],
			'name'       => $state['name'],
			'version'    => $state['version'],
			'message'    => $gone
				? 'Theme files deleted.'
				: 'WordPress reported success but the theme is still installed. Check filesystem permissions.',
		);
	}
}
