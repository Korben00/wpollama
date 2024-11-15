<?php

declare(strict_types=1);

namespace OllamaPress;

use WP_REST_Controller;

defined('ABSPATH') || exit;

/**
 * Plugin Name: OllamaPress
 * Description: A plugin to interact with Ollama's API via WordPress REST endpoints.
 * Version: 0.1.1
 * Author: Carmelo Santana
 * Author URI: https://carmelosantana.com
 */

define('OLLAMA_API_URL', 'http://host.docker.internal:11434/api'); // Base URL for Ollama API
define('OLLAMA_TIMEOUT', 300); // Timeout in seconds for API requests

/**
 * Class OllamaAPIController
 *
 * Manages REST API routes for Ollama features.
 */
class OllamaAPIController extends WP_REST_Controller
{
    /**
     * Register API routes.
     */
    public function register_routes(): void
    {
        $namespace = 'ollama/v1';

        register_rest_route($namespace, '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_completion'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_chat'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/models', [
            'methods' => 'GET',
            'callback' => [$this, 'list_models'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/model-info', [
            'methods' => 'POST',
            'callback' => [$this, 'model_info'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/copy', [
            'methods' => 'POST',
            'callback' => [$this, 'copy_model'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/delete', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_model'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/pull', [
            'methods' => 'POST',
            'callback' => [$this, 'pull_model'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/push', [
            'methods' => 'POST',
            'callback' => [$this, 'push_model'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/embed', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_embedding'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/running', [
            'methods' => 'GET',
            'callback' => [$this, 'list_running_models'],
            'permission_callback' => '__return_true',
        ]);
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
            $body['messages'] = $messages;
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
        $body = [
            'model' => (string) $request->get_param('model'),
            'input' => (array) $request->get_param('input'),
            'truncate' => (bool) $request->get_param('truncate', true),
            'options' => (array) $request->get_param('options', []),
            'keep_alive' => (string) $request->get_param('keep_alive', '5m'),
        ];

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
     *
     * @param string $endpoint
     * @param array $body
     * @param string $method
     * @return array
     */
    private function make_request(string $endpoint, array $body = [], string $method = 'POST'): array
    {
        $url = OLLAMA_API_URL . $endpoint;
        $args = [
            'method' => $method,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $method === 'GET' ? null : wp_json_encode($body),
            'timeout' => OLLAMA_TIMEOUT,
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (is_null($data)) {
            return ['error' => 'Failed to decode JSON response from Ollama API.'];
        }

        return $data;
    }
}

/**
 * Initialize the OllamaPress plugin.
 */
function ollama_press_init(): void
{
    $controller = new OllamaAPIController();
    $controller->register_routes();
}

add_action('rest_api_init', __NAMESPACE__ . '\ollama_press_init');
