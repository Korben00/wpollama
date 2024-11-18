<?php

declare(strict_types=1);

namespace OllamaPress;

defined('ABSPATH') || exit;

/**
 * Plugin Name: OllamaPress
 * Description: A plugin to interact with Ollama's API via WordPress REST endpoints.
 * Version: 0.1.4
 * Author: Carmelo Santana
 * Author URI: https://carmelosantana.com
 */

/**
 * Include the necessary files.
 */
require_once __DIR__ . '/src/Api.php';
require_once __DIR__ . '/src/Admin/Options.php';

/**
 * Initialize REST API routes.
 */
function ollama_press_init(): void
{
    $controller = new RestAPIController();
    $controller->register_routes();
}
add_action('rest_api_init', __NAMESPACE__ . '\ollama_press_init');

/**
 * Initialize the settings page.
 */

use OllamaPress\Admin\Options;

add_action('admin_menu', function () {
    add_submenu_page(
        'options-general.php',
        'Ollama API Settings',
        'Ollama API',
        'manage_options',
        'ollama-api-settings',
        function () {
            $options = new Options();
            $options->renderAdminPanel();
        }
    );
});

add_action('admin_init', function () {
    $options = new Options();
    $options->saveSettings();
});
