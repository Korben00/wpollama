<?php

declare(strict_types=1);

namespace OllamaPress\Admin;

class Options
{
    public function renderAdminPanel(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Ollama API Settings</h1>';
        echo '<form method="post" action="">';
        wp_nonce_field('save_ollama_permissions', '_wpnonce');

        // Environment Settings
        echo '<h2>Environment Settings</h2>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">OLLAMA_URL</th>';
        echo '<td>';
        if (defined('OLLAMA_URL')) {
            echo '<code>' . esc_html(OLLAMA_URL) . '</code>';
            echo '<p class="description">This value is set via <code>wp-config.php</code>.</p>';
        } else {
            echo '<input type="text" name="ollama_url" value="' . esc_attr(get_option('ollama_url', 'http://localhost:11434/api')) . '" class="regular-text" />';
        }
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">OLLAMA_TIMEOUT</th>';
        echo '<td>';
        if (defined('OLLAMA_TIMEOUT')) {
            echo '<code>' . esc_html(OLLAMA_TIMEOUT) . '</code>';
            echo '<p class="description">This value is set via <code>wp-config.php</code>.</p>';
        } else {
            echo '<input type="number" name="ollama_timeout" value="' . esc_attr(get_option('ollama_timeout', 30)) . '" class="small-text" /> seconds';
        }
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        // Security Settings
        echo '<h2>Security Settings</h2>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">External Access</th>';
        echo '<td>';
        echo '<label>';
        $allow_external = get_option('ollama_allow_external_access', false);
        echo '<input type="checkbox" name="ollama_allow_external_access" value="1" ' . checked($allow_external, true, false) . ' />';
        echo ' Allow external access to Ollama API (Not recommended)';
        echo '</label>';
        echo '<p class="description">⚠️ <strong>Security Warning:</strong> Enabling this allows external applications to directly access your Ollama instance. This should only be enabled for development or if you have proper security measures in place.</p>';
        echo '<p class="description">When disabled (default), only authenticated WordPress users and registered plugins can access Ollama through the WordPress REST API.</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">Allowed Origins</th>';
        echo '<td>';
        $allowed_origins = get_option('ollama_allowed_origins', '');
        echo '<textarea name="ollama_allowed_origins" rows="4" cols="50" class="regular-text" placeholder="http://localhost:3000' . "\n" . 'https://trusted-site.com">' . esc_textarea($allowed_origins) . '</textarea>';
        echo '<p class="description">One origin per line. Only applies if external access is enabled. Leave empty to allow all origins (not recommended).</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">API Rate Limiting</th>';
        echo '<td>';
        echo '<label>';
        $rate_limit = get_option('ollama_rate_limit_enabled', true);
        echo '<input type="checkbox" name="ollama_rate_limit_enabled" value="1" ' . checked($rate_limit, true, false) . ' />';
        echo ' Enable rate limiting for API requests';
        echo '</label>';
        echo '<p class="description">Limits requests to 60 per minute per user to prevent abuse.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '<p class="submit">';
        echo '<button type="submit" class="button-primary">Save Changes</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
    }

    public function saveSettings(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_ollama_permissions')) {
            return;
        }

        if (!defined('OLLAMA_URL')) {
            update_option('ollama_url', sanitize_text_field($_POST['ollama_url'] ?? 'http://localhost:11434/api'));
        }

        if (!defined('OLLAMA_TIMEOUT')) {
            update_option('ollama_timeout', (int) ($_POST['ollama_timeout'] ?? 30));
        }

        // Save security settings
        update_option('ollama_allow_external_access', isset($_POST['ollama_allow_external_access']) ? 1 : 0);
        update_option('ollama_allowed_origins', sanitize_textarea_field($_POST['ollama_allowed_origins'] ?? ''));
        update_option('ollama_rate_limit_enabled', isset($_POST['ollama_rate_limit_enabled']) ? 1 : 0);

        add_action('admin_notices', function () {
            echo '<div class="updated notice"><p>Settings saved successfully.</p></div>';
        });
    }
}
