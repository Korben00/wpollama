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
require_once __DIR__ . '/src/OllamaClient.php';
require_once __DIR__ . '/src/Api.php';
require_once __DIR__ . '/src/PluginManager.php';
require_once __DIR__ . '/src/ExtensibleApi.php';
require_once __DIR__ . '/src/Admin/Options.php';

/**
 * Initialize REST API routes with extensibility support.
 */
function ollama_press_init(): void
{
    $controller = new ExtensibleApi();
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

/**
 * Helper function for other plugins to register with WPOllama
 */
function ollamapress_register_service(string $service_id, array $config): bool
{
    $manager = \OllamaPress\PluginManager::getInstance();
    return $manager->registerService($service_id, $config);
}

/**
 * Helper function to check if a service is registered
 */
function ollamapress_has_service(string $service_id): bool
{
    $manager = \OllamaPress\PluginManager::getInstance();
    return $manager->hasService($service_id);
}

/**
 * Helper function to get plugin manager instance
 */
function ollamapress_get_manager(): \OllamaPress\PluginManager
{
    return \OllamaPress\PluginManager::getInstance();
}
