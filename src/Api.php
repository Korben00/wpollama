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

    public function __construct()
    {
        $this->namespace = 'ollama/v1';
        $this->url = defined('OLLAMA_URL') ? OLLAMA_URL : get_option('ollama_url', 'http://localhost:11434/api');
        $this->timeout = defined('OLLAMA_TIMEOUT') ? OLLAMA_TIMEOUT : (int) get_option('ollama_timeout', 30);
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
     * Check if user is logged in.
     * Following WordPress security best practices.
     */
    public function permissions_read($request)
    {
        // Require user to be logged in
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('You must be logged in to access this resource.', 'ollamapress'),
                ['status' => 401]
            );
        }
        
        return true;
    }

    /**
     * Check if the current user is an admin.
     */
    public function permissions_manage($request)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('rest_forbidden', esc_html__('You cannot access this resource.', 'ollamapress'), ['status' => 401]);
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
            $body['model'] = (string) $model;
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
            $body['model'] = (string) $model;
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
            $body['model'] = (string) $model;
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
