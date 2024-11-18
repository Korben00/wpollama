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
            echo '<input type="number" name="ollama_timeout" value="' . esc_attr(get_option('ollama_timeout', 300)) . '" class="small-text" /> seconds';
        }
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
            update_option('ollama_timeout', (int) ($_POST['ollama_timeout'] ?? 300));
        }

        add_action('admin_notices', function () {
            echo '<div class="updated notice"><p>Settings saved successfully.</p></div>';
        });
    }
}
