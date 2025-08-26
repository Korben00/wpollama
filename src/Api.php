<?php

declare(strict_types=1);

namespace OllamaPress;

use WP_Error;
use WP_REST_Controller;

/**
 * Class OllamaAPIController
 *
 * Manages REST API routes for Ollama features.
 */
class RestAPIController extends WP_REST_Controller
{
    private string $url;
    private int $timeout;
    
    /**
     * Allowed models for security - can be configured via filter
     * @var array
     */
    private array $allowed_models;

    public function __construct()
    {
        $this->namespace = 'ollama/v1';
        $this->url = defined('OLLAMA_URL') ? OLLAMA_URL : get_option('ollama_url', 'http://localhost:11434/api');
        $this->timeout = defined('OLLAMA_TIMEOUT') ? OLLAMA_TIMEOUT : (int) get_option('ollama_timeout', 30);
        
        // Default allowed models - can be filtered by themes/plugins
        $default_models = ['llama3.2', 'llama3.2:latest', 'llama2', 'codellama', 'mistral', 'phi', 'gemma', 'qwen3:latest'];
        $this->allowed_models = apply_filters('ollamapress_allowed_models', $default_models);
    }

    /**
     * Register API routes.
     */
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_completion'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route($this->namespace, '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_chat'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route($this->namespace, '/embed', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_embedding'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route($this->namespace, '/models', [
            'methods' => 'GET',
            'callback' => [$this, 'list_models'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route($this->namespace, '/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_model'],
            'permission_callback' => [$this, 'permissions_manage'],
        ]);

        register_rest_route($this->namespace, '/info', [
            'methods' => 'POST',
            'callback' => [$this, 'model_info'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route($this->namespace, '/copy', [
            'methods' => 'POST',
            'callback' => [$this, 'copy_model'],
            'permission_callback' => [$this, 'permissions_manage'],
        ]);

        register_rest_route($this->namespace, '/delete', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_model'],
            'permission_callback' => [$this, 'permissions_manage'],
        ]);

        register_rest_route($this->namespace, '/pull', [
            'methods' => 'POST',
            'callback' => [$this, 'pull_model'],
            'permission_callback' => [$this, 'permissions_manage'],
        ]);

        register_rest_route($this->namespace, '/push', [
            'methods' => 'POST',
            'callback' => [$this, 'push_model'],
            'permission_callback' => [$this, 'permissions_manage'],
        ]);

        register_rest_route($this->namespace, '/running', [
            'methods' => 'GET',
            'callback' => [$this, 'list_running_models'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);
    }

    /**
     * Check if user is logged in or if external access is allowed.
     * Following WordPress security best practices.
     */
    public function permissions_read($request)
    {
        // Method 1: Check if user is authenticated (covers WordPress admin, AJAX, REST with auth)
        // In REST context, this works if proper authentication is set
        if (is_user_logged_in()) {
            error_log('WPOllama - User is logged in (ID: ' . get_current_user_id() . '), allowing access');
            return true;
        }
        
        // Method 2: Check if this is an internal WordPress request
        $is_internal = $this->is_internal_request($request);
        
        if ($is_internal) {
            error_log('WPOllama - Internal request detected, allowing access');
            return true;
        }
        
        // Method 3: For external requests, check if external access is allowed
        $allow_external = get_option('ollama_allow_external_access', false);
        
        if (!$allow_external) {
            // External access disabled, require authentication
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'rest_forbidden',
                    esc_html__('You must be logged in to access this resource. External access is disabled.', 'ollamapress'),
                    ['status' => 401]
                );
            }
        } else {
            // External access is allowed, check origin restrictions
            $allowed_origins = get_option('ollama_allowed_origins', '');
            if (!empty($allowed_origins)) {
                $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
                $allowed_list = array_map('trim', explode("\n", $allowed_origins));
                
                if (!empty($origin) && !in_array($origin, $allowed_list, true)) {
                    return new WP_Error(
                        'rest_forbidden_origin',
                        esc_html__('Access from this origin is not allowed.', 'ollamapress'),
                        ['status' => 403]
                    );
                }
            }
            
            // Apply rate limiting if enabled
            if (get_option('ollama_rate_limit_enabled', true)) {
                $rate_check = $this->check_rate_limit($request);
                if (is_wp_error($rate_check)) {
                    return $rate_check;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Check if this is an internal WordPress request
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    private function is_internal_request($request): bool
    {
        // Debug logging
        error_log('WPOllama - Checking if internal request...');
        
        // Method 1: Check for registered service token
        // This is the cleanest way for service-to-service auth
        $service_token = $request->get_header('X-WPOllama-Service-Token');
        if ($service_token) {
            error_log('WPOllama - Service token found, verifying...');
            // Verify the token matches a registered service
            $registered_services = get_option('wpollama_registered_services', []);
            foreach ($registered_services as $service_id => $service_data) {
                $expected_token = wp_hash('wpollama_service_' . $service_id . '_' . get_site_url());
                if (hash_equals($expected_token, $service_token)) {
                    error_log('WPOllama - Valid service token for: ' . $service_id);
                    return true;
                }
            }
        }
        
        // Method 2: Check for WordPress plugin header 
        // Accept any plugin that identifies itself for now
        $plugin_header = $request->get_header('X-WPOllama-Plugin');
        if ($plugin_header) {
            error_log('WPOllama - Plugin header found: ' . $plugin_header);
            // For now, trust any plugin that identifies itself
            // This allows WordPress plugins to communicate
            return true;
        }
        
        // Method 3: Check for valid WordPress nonce (internal AJAX)
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            error_log('WPOllama - Valid WP nonce found');
            return true;
        }
        
        // Method 4: Check if this is called from admin-ajax.php
        if (defined('DOING_AJAX') && DOING_AJAX) {
            error_log('WPOllama - DOING_AJAX detected');
            return true;
        }
        
        // Method 5: Check for internal IP or localhost
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (in_array($remote_addr, ['127.0.0.1', '::1'], true)) {
            error_log('WPOllama - Localhost request detected');
            return true;
        }
        
        error_log('WPOllama - Not detected as internal request');
        return false;
    }

    /**
     * Check if the current user is an admin.
     * Management endpoints should never be accessible externally.
     */
    public function permissions_manage($request)
    {
        // Management operations require authentication regardless of external access settings
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('Authentication required for management operations.', 'ollamapress'),
                ['status' => 401]
            );
        }
        
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('Administrator privileges required.', 'ollamapress'),
                ['status' => 403]
            );
        }
        
        return true;
    }

    /**
     * Check rate limit for API requests
     * 
     * @param \WP_REST_Request $request
     * @return bool|WP_Error
     */
    private function check_rate_limit($request)
    {
        $user_identifier = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . $_SERVER['REMOTE_ADDR'];
        $transient_key = 'ollama_rate_limit_' . md5($user_identifier);
        
        // Get current request count
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request within the time window
            set_transient($transient_key, 1, 60); // 60 seconds window
            return true;
        }
        
        // Check if limit exceeded (60 requests per minute)
        if ($requests >= 60) {
            return new WP_Error(
                'rate_limit_exceeded',
                esc_html__('Rate limit exceeded. Please try again later.', 'ollamapress'),
                ['status' => 429]
            );
        }
        
        // Increment counter
        set_transient($transient_key, $requests + 1, 60);
        return true;
    }

    /**
     * Validate if the requested model is allowed.
     * 
     * @param string $model The model name to validate
     * @return bool|WP_Error True if allowed, WP_Error if not
     */
    private function validate_model(string $model)
    {
        // Allow admins to bypass model restrictions
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check if model is in allowed list
        if (!in_array($model, $this->allowed_models, true)) {
            return new WP_Error(
                'invalid_model',
                sprintf(
                    esc_html__('Model "%s" is not allowed. Allowed models: %s', 'ollamapress'),
                    $model,
                    implode(', ', $this->allowed_models)
                ),
                ['status' => 403]
            );
        }
        
        return true;
    }

    /**
     * Handle Generate Completion request.
     */
    public function generate_completion(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [];

        if ($model = $request->get_param('model')) {
            $model_name = (string) $model;
            
            // Validate model
            $validation = $this->validate_model($model_name);
            if (is_wp_error($validation)) {
                return rest_ensure_response($validation);
            }
            
            $body['model'] = $model_name;
        } else {
            $body['model'] = 'default-model'; // Fallback default
        }

        if ($prompt = $request->get_param('prompt')) {
            $body['prompt'] = (string) $prompt;
        } else {
            $body['prompt'] = 'Please enter a prompt.'; // Fallback default
        }

        if (!is_null($request->get_param('stream'))) {
            $body['stream'] = (bool) $request->get_param('stream');
        }

        if ($suffix = $request->get_param('suffix')) {
            $body['suffix'] = (string) $suffix;
        }

        if ($images = $request->get_param('images')) {
            $body['images'] = (array) $images;
        }

        if ($format = $request->get_param('format')) {
            $body['format'] = (string) $format;
        }

        if ($options = $request->get_param('options')) {
            $body['options'] = (array) $options;
        }

        if ($system = $request->get_param('system')) {
            $body['system'] = (string) $system;
        }

        if ($template = $request->get_param('template')) {
            $body['template'] = (string) $template;
        }

        if ($context = $request->get_param('context')) {
            $body['context'] = $context;
        }

        if (!is_null($request->get_param('raw'))) {
            $body['raw'] = (bool) $request->get_param('raw');
        }

        if ($keep_alive = $request->get_param('keep_alive')) {
            $body['keep_alive'] = (string) $keep_alive;
        } else {
            $body['keep_alive'] = '5m'; // Default to '5m'
        }

        $response = $this->make_request('/generate', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle Generate Chat request.
     */
    public function generate_chat(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [];

        if ($model = $request->get_param('model')) {
            $model_name = (string) $model;
            
            // Validate model
            $validation = $this->validate_model($model_name);
            if (is_wp_error($validation)) {
                return rest_ensure_response($validation);
            }
            
            $body['model'] = $model_name;
        } else {
            $body['model'] = 'default-model'; // Fallback default
        }

        if ($messages = $request->get_param('messages')) {
            $body['messages'] = (array) $messages;
        } else {
            $body['messages'] = [['role' => 'user', 'content' => 'Hello!']]; // Default message
        }

        if (!is_null($request->get_param('stream'))) {
            $body['stream'] = (bool) $request->get_param('stream');
        }

        if ($tools = $request->get_param('tools')) {
            $body['tools'] = (array) $tools;
        }

        if ($format = $request->get_param('format')) {
            $body['format'] = (string) $format;
        }

        if ($options = $request->get_param('options')) {
            $body['options'] = (array) $options;
        }

        if ($keep_alive = $request->get_param('keep_alive')) {
            $body['keep_alive'] = (string) $keep_alive;
        } else {
            $body['keep_alive'] = '5m'; // Default to '5m'
        }

        $response = $this->make_request('/chat', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle Create Model request.
     */
    public function create_model(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = array_filter([
            'name' => $request->get_param('name'),
            'modelfile' => $request->get_param('modelfile'),
            'stream' => $request->get_param('stream'),
            'path' => $request->get_param('path'),
        ]);

        $response = $this->make_request('/create', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle List Models request.
     */
    public function list_models(): \WP_REST_Response
    {
        $response = $this->make_request('/tags', [], 'GET');
        return rest_ensure_response($response);
    }

    /**
     * Handle Show Model Information request.
     */
    public function model_info(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [
            'name' => (string) $request->get_param('name'),
            'verbose' => (bool) $request->get_param('verbose', false),
        ];

        $response = $this->make_request('/show', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle Copy Model request.
     */
    public function copy_model(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [
            'source' => (string) $request->get_param('source'),
            'destination' => (string) $request->get_param('destination'),
        ];

        $response = $this->make_request('/copy', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle Delete Model request.
     */
    public function delete_model(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [
            'name' => (string) $request->get_param('name'),
        ];

        $response = $this->make_request('/delete', $body, 'DELETE');
        return rest_ensure_response($response);
    }

    /**
     * Handle Pull Model request.
     */
    public function pull_model(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [
            'name' => (string) $request->get_param('name'),
            'insecure' => (bool) $request->get_param('insecure', false),
            'stream' => (bool) $request->get_param('stream', false),
        ];

        $response = $this->make_request('/pull', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle Push Model request.
     */
    public function push_model(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [
            'name' => (string) $request->get_param('name'),
            'insecure' => (bool) $request->get_param('insecure', false),
            'stream' => (bool) $request->get_param('stream', false),
        ];

        $response = $this->make_request('/push', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle Generate Embedding request.
     */
    public function generate_embedding(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = [];

        if ($model = $request->get_param('model')) {
            $model_name = (string) $model;
            
            // Validate model
            $validation = $this->validate_model($model_name);
            if (is_wp_error($validation)) {
                return rest_ensure_response($validation);
            }
            
            $body['model'] = $model_name;
        }

        if ($input = $request->get_param('input')) {
            $body['input'] = (array) $input;
        }

        if (!is_null($request->get_param('truncate'))) {
            $body['truncate'] = (bool) $request->get_param('truncate');
        }

        if ($options = $request->get_param('options')) {
            $body['options'] = (array) $options;
        }

        if ($keep_alive = $request->get_param('keep_alive')) {
            $body['keep_alive'] = (string) $keep_alive;
        }

        $response = $this->make_request('/embed', $body);
        return rest_ensure_response($response);
    }

    /**
     * Handle List Running Models request.
     */
    public function list_running_models(): \WP_REST_Response
    {
        $response = $this->make_request('/ps', [], 'GET');
        return rest_ensure_response($response);
    }

    /**
     * Make a request to the Ollama API.
     */
    private function make_request(string $endpoint, array $body = [], string $method = 'POST'): array
    {
        $url = $this->url . $endpoint;
        $args = [
            'method' => $method,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $method === 'GET' ? null : wp_json_encode($body),
            'timeout' => $this->timeout,
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return is_null($data) ? ['error' => 'Invalid API response'] : $data;
    }
}
