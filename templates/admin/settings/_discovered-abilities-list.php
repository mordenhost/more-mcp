<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$more_mcp_abilities_api_present = function_exists( 'wp_get_abilities' );
?>

<div class="mmcp-discovered-abilities">
	<?php if ( ! $more_mcp_abilities_api_present ) : ?>

		<p class="description">
			<?php esc_html_e( 'The WordPress Abilities API is not available on this site (it ships with WordPress 6.9 and later). There is nothing to import until it is present.', 'more-mcp' ); ?>
		</p>

	<?php elseif ( empty( $more_mcp_importable_abilities ) ) : ?>

		<p class="description">
			<?php esc_html_e( 'No other active plugin has registered any abilities yet. When one does, its abilities will appear here for you to enable individually.', 'more-mcp' ); ?>
		</p>

	<?php else : ?>

		<p class="description">
			<?php
			printf(
				/* translators: %s: number of importable abilities, already locale-formatted */
				esc_html__( '%s importable abilities were found. Tick the ones you want exposed as tools.', 'more-mcp' ),
				'<strong>' . esc_html( number_format_i18n( count( $more_mcp_importable_abilities ) ) ) . '</strong>'
			);
			?>
		</p>

		<ul class="mmcp-tool-list mmcp-discovered-list">
			<?php foreach ( $more_mcp_importable_abilities as $more_mcp_ability_name => $more_mcp_ability ) : ?>
				<?php
				$more_mcp_ab_label = is_object( $more_mcp_ability ) && method_exists( $more_mcp_ability, 'get_label' )
					? (string) $more_mcp_ability->get_label()
					: '';
				$more_mcp_ab_desc = is_object( $more_mcp_ability ) && method_exists( $more_mcp_ability, 'get_description' )
					? (string) $more_mcp_ability->get_description()
					: '';
				$more_mcp_ab_checked = isset( $more_mcp_enabled_abilities[ $more_mcp_ability_name ] );
				
				$more_mcp_ab_id = 'mmcp-disc-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $more_mcp_ability_name ) );
				?>
				<li<?php echo '' !== $more_mcp_ab_desc ? ' title="' . esc_attr( $more_mcp_ab_desc ) . '"' : ''; ?>>
					<label for="<?php echo esc_attr( $more_mcp_ab_id ); ?>" class="mmcp-discovered-item">
						<input type="checkbox"
						       id="<?php echo esc_attr( $more_mcp_ab_id ); ?>"
						       name="more_mcp_settings[discovered_abilities][]"
						       value="<?php echo esc_attr( $more_mcp_ability_name ); ?>"
						       <?php checked( $more_mcp_ab_checked ); ?>>
						<code><?php echo esc_html( $more_mcp_ability_name ); ?></code>
						<?php if ( '' !== $more_mcp_ab_label ) : ?>
							<span class="mmcp-discovered-label"><?php echo esc_html( $more_mcp_ab_label ); ?></span>
						<?php endif; ?>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>
</div>
