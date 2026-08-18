<?php
/**
 * Theme lifecycle operations.
 *
 * Same shape as Plugins: capability, resolve, protect, preview or apply, read
 * back. Themes differ in one way that matters — switching one changes what every
 * visitor sees immediately, with no per-page opt-in. The preview therefore
 * always names both the outgoing and incoming theme, so a caller confirming a
 * switch has seen what it is replacing.
 */

namespace More_MCP\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Themes {

	/**
	 * Resolve a caller-supplied stylesheet to an installed theme.
	 *
	 * @param array  $args Tool arguments.
	 * @param string $key  Argument name holding the stylesheet.
	 * @return \WP_Theme
	 * @throws \Exception When missing or not installed.
	 */
	private static function resolve( array $args, string $key = 'stylesheet' ): \WP_Theme {
		$ref = isset( $args[ $key ] ) ? trim( (string) $args[ $key ] ) : '';
		if ( '' === $ref ) {
			throw new \Exception( sprintf( '%s is required — the theme directory name, e.g. "twentytwentyfive".', esc_html( $key ) ) );
		}

		// Stylesheet names are directory names. Reject traversal outright rather
		// than letting wp_get_theme() interpret it, since this value reaches
		// filesystem operations on delete.
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

	/**
	 * Snapshot one theme's state.
	 *
	 * @param \WP_Theme $theme Theme.
	 * @return array
	 */
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

	/**
	 * wp_get_themes_status handler.
	 *
	 * @return array
	 */
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

	/**
	 * wp_activate_theme handler — switches the active theme.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
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

		// A theme WordPress considers unusable (missing stylesheet, broken parent)
		// would leave the site without a working front end.
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

	/**
	 * wp_update_theme handler.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
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

	/**
	 * wp_delete_theme handler.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 * @throws \Exception
	 */
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

		// Deleting the parent of the active child theme breaks the front end just
		// as thoroughly as deleting the active theme itself.
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
