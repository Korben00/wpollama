<?php

declare(strict_types=1);

namespace OllamaPress;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Extended API Controller with plugin support
 */
class ExtensibleApi extends RestAPIController
{
    private PluginManager $pluginManager;

    public function __construct()
    {
        parent::__construct();
        $this->pluginManager = PluginManager::getInstance();
    }

    /**
     * Register API routes including extensions
     */
    public function register_routes(): void
    {
        // Register core routes
        parent::register_routes();

        // Register plugin management routes
        register_rest_route($this->namespace, '/extensions', [
            'methods' => 'GET',
            'callback' => [$this, 'list_extensions'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route($this->namespace, '/extensions/(?P<service_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_extension_info'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        // Register custom endpoints from plugins
        $this->register_custom_endpoints();
    }

    /**
     * Register custom endpoints from registered plugins
     */
    private function register_custom_endpoints(): void
    {
        $custom_endpoints = $this->pluginManager->getCustomEndpoints();
        
        foreach ($custom_endpoints as $endpoint => $config) {
            register_rest_route($this->namespace, $endpoint, [
                'methods' => $config['methods'] ?? 'POST',
                'callback' => [$this, 'handle_custom_endpoint'],
                'permission_callback' => $this->get_permission_callback($config),
                'args' => $config['args'] ?? [],
                '_endpoint_config' => $config
            ]);
        }
    }

    /**
     * Get permission callback based on service configuration
     */
    private function get_permission_callback(array $config): callable
    {
        $service = $this->pluginManager->getService($config['service_id']);
        
        if ($service && $service['permissions'] === 'manage') {
            return [$this, 'permissions_manage'];
        }
        
        return [$this, 'permissions_read'];
    }

    /**
     * Handle custom endpoints from plugins
     */
    public function handle_custom_endpoint(WP_REST_Request $request): WP_REST_Response
    {
        $route = $request->get_route();
        $custom_endpoints = $this->pluginManager->getCustomEndpoints();
        
        if (!isset($custom_endpoints[$route])) {
            return rest_ensure_response(new WP_Error(
                'endpoint_not_found',
                'Custom endpoint not found',
                ['status' => 404]
            ));
        }

        $config = $custom_endpoints[$route];
        
        // Process middleware
        $request = $this->pluginManager->processMiddleware($request, $route);
        
        // Execute the callback
        if (isset($config['callback']) && is_callable($config['callback'])) {
            try {
                $result = call_user_func($config['callback'], $request, $this);
                return rest_ensure_response($result);
            } catch (\Exception $e) {
                return rest_ensure_response(new WP_Error(
                    'endpoint_error',
                    'Error executing custom endpoint: ' . $e->getMessage(),
                    ['status' => 500]
                ));
            }
        }

        return rest_ensure_response(new WP_Error(
            'no_callback',
            'No callback defined for this endpoint',
            ['status' => 500]
        ));
    }

    /**
     * List all registered extensions
     */
    public function list_extensions(): WP_REST_Response
    {
        $services = $this->pluginManager->getRegisteredServices();
        
        $formatted_services = array_map(function($service) {
            return [
                'id' => $service['name'],
                'name' => $service['name'],
                'description' => $service['description'],
                'version' => $service['version'],
                'author' => $service['author'],
                'endpoints_count' => count($service['endpoints']),
                'middleware_count' => count($service['middleware']),
                'permissions' => $service['permissions']
            ];
        }, $services);

        return rest_ensure_response([
            'extensions' => array_values($formatted_services),
            'total' => count($formatted_services)
        ]);
    }

    /**
     * Get specific extension information
     */
    public function get_extension_info(WP_REST_Request $request): WP_REST_Response
    {
        $service_id = $request->get_param('service_id');
        $service = $this->pluginManager->getService($service_id);

        if (!$service) {
            return rest_ensure_response(new WP_Error(
                'extension_not_found',
                'Extension not found',
                ['status' => 404]
            ));
        }

        return rest_ensure_response($service);
    }

    /**
     * Override parent methods to add plugin hooks
     */
    public function generate_completion(WP_REST_Request $request): WP_REST_Response
    {
        // Execute pre-generation hooks
        $this->pluginManager->executeHooks('pre_generate', $request);
        
        $response = parent::generate_completion($request);
        
        // Execute post-generation hooks
        $this->pluginManager->executeHooks('post_generate', $request, $response);
        
        return $response;
    }

    public function generate_chat(WP_REST_Request $request): WP_REST_Response
    {
        $this->pluginManager->executeHooks('pre_chat', $request);
        
        $response = parent::generate_chat($request);
        
        $this->pluginManager->executeHooks('post_chat', $request, $response);
        
        return $response;
    }

    /**
     * Expose the internal make_request method for plugins
     */
    public function make_ollama_request(string $endpoint, array $body = [], string $method = 'POST'): array
    {
        return $this->make_request($endpoint, $body, $method);
    }
}