<?php

declare(strict_types=1);

namespace OllamaPress;

/**
 * Plugin Manager - Allows other plugins to extend WPOllama functionality
 */
class PluginManager
{
    private static ?PluginManager $instance = null;
    private array $registered_services = [];
    private array $middleware = [];
    private array $custom_endpoints = [];

    private function __construct() {}

    public static function getInstance(): PluginManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a service that can process Ollama requests
     */
    public function registerService(string $service_id, array $config): bool
    {
        if (isset($this->registered_services[$service_id])) {
            return false; // Already registered
        }

        $this->registered_services[$service_id] = array_merge([
            'name' => $service_id,
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'endpoints' => [],
            'middleware' => [],
            'hooks' => [],
            'permissions' => 'read', // 'read' or 'manage'
            'priority' => 10
        ], $config);

        // Register custom endpoints if provided
        if (!empty($config['endpoints'])) {
            $this->registerCustomEndpoints($service_id, $config['endpoints']);
        }

        // Register middleware if provided
        if (!empty($config['middleware'])) {
            $this->registerMiddleware($service_id, $config['middleware']);
        }

        do_action('ollamapress_service_registered', $service_id, $this->registered_services[$service_id]);

        return true;
    }

    /**
     * Get all registered services
     */
    public function getRegisteredServices(): array
    {
        return $this->registered_services;
    }

    /**
     * Get a specific service
     */
    public function getService(string $service_id): ?array
    {
        return $this->registered_services[$service_id] ?? null;
    }

    /**
     * Check if a service is registered
     */
    public function hasService(string $service_id): bool
    {
        return isset($this->registered_services[$service_id]);
    }

    /**
     * Register custom REST endpoints
     */
    private function registerCustomEndpoints(string $service_id, array $endpoints): void
    {
        foreach ($endpoints as $endpoint => $config) {
            $full_endpoint = "/ollama/v1/extensions/{$service_id}{$endpoint}";
            $this->custom_endpoints[$full_endpoint] = array_merge($config, [
                'service_id' => $service_id
            ]);
        }
    }

    /**
     * Register middleware for request processing
     */
    private function registerMiddleware(string $service_id, array $middleware_list): void
    {
        foreach ($middleware_list as $middleware) {
            if (is_callable($middleware)) {
                $this->middleware[] = [
                    'service_id' => $service_id,
                    'callback' => $middleware,
                    'priority' => $this->registered_services[$service_id]['priority']
                ];
            }
        }
        
        // Sort middleware by priority
        usort($this->middleware, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Process request through registered middleware
     */
    public function processMiddleware(\WP_REST_Request $request, string $endpoint): \WP_REST_Request
    {
        foreach ($this->middleware as $middleware) {
            $request = call_user_func($middleware['callback'], $request, $endpoint);
        }
        return $request;
    }

    /**
     * Get custom endpoints for REST API registration
     */
    public function getCustomEndpoints(): array
    {
        return $this->custom_endpoints;
    }

    /**
     * Unregister a service
     */
    public function unregisterService(string $service_id): bool
    {
        if (!isset($this->registered_services[$service_id])) {
            return false;
        }

        unset($this->registered_services[$service_id]);
        
        // Remove associated middleware
        $this->middleware = array_filter($this->middleware, function($middleware) use ($service_id) {
            return $middleware['service_id'] !== $service_id;
        });

        // Remove custom endpoints
        $this->custom_endpoints = array_filter($this->custom_endpoints, function($endpoint) use ($service_id) {
            return $endpoint['service_id'] !== $service_id;
        });

        do_action('ollamapress_service_unregistered', $service_id);

        return true;
    }

    /**
     * Execute hooks for a specific action
     */
    public function executeHooks(string $hook_name, ...$args): void
    {
        foreach ($this->registered_services as $service_id => $service) {
            if (isset($service['hooks'][$hook_name]) && is_callable($service['hooks'][$hook_name])) {
                call_user_func($service['hooks'][$hook_name], ...$args);
            }
        }
    }
}