
<!-- Platform Item Template -->
<script type="text/template" id="platform-item-template">
    <div class="platform-item" data-index="{{index}}" data-platform="{{platform_id}}">
        <div class="platform-header">
            <div class="platform-info">
                <span class="platform-icon" style="background-color: {{color}}">
                    {{icon_letter}}
                </span>
                <div class="platform-details">
                    <h3 class="platform-name">{{label}}</h3>
                    <span class="platform-description">{{description}}</span>
                </div>
            </div>
            <div class="platform-actions">
                <label class="switch small">
                    <input type="checkbox"
                           name="more_mcp_settings[platforms][{{index}}][enabled]"
                           value="1"
                           checked>
                    <span class="slider"></span>
                </label>
                <button type="button" class="button platform-toggle" aria-label="<?php esc_attr_e('Expand / collapse', 'more-mcp'); ?>">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <button type="button" class="button remove-platform" aria-label="<?php esc_attr_e('Remove provider', 'more-mcp'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        <div class="platform-config">
            <input type="hidden"
                   name="more_mcp_settings[platforms][{{index}}][platform]"
                   value="{{platform_id}}">

            <table class="form-table platform-fields">
                {{fields_html}}
            </table>

            <div class="platform-footer">
                <div class="platform-links">
                    {{#api_key_url}}
                    <a href="{{api_key_url}}" target="_blank" class="button button-link">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('Get API Key', 'more-mcp'); ?>
                    </a>
                    {{/api_key_url}}
                    {{#docs_url}}
                    <a href="{{docs_url}}" target="_blank" class="button button-link">
                        <span class="dashicons dashicons-book"></span>
                        <?php esc_html_e('Documentation', 'more-mcp'); ?>
                    </a>
                    {{/docs_url}}
                </div>
                <div class="platform-test">
                    <button type="button" class="button test-connection">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Test Connection', 'more-mcp'); ?>
                    </button>
                    <span class="connection-status"></span>
                </div>
            </div>
        </div>
    </div>
</script>
