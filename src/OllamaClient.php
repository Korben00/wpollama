<?php

declare(strict_types=1);

namespace OllamaPress;

/**
 * Simple Ollama client for internal WordPress plugin use
 * This provides a direct PHP interface to Ollama without REST API overhead
 */
class OllamaClient
{
    private string $url;
    private int $timeout;
    
    public function __construct()
    {
        $this->url = defined('OLLAMA_URL') ? OLLAMA_URL : get_option('ollama_url', 'http://localhost:11434/api');
        $this->timeout = defined('OLLAMA_TIMEOUT') ? OLLAMA_TIMEOUT : (int) get_option('ollama_timeout', 30);
    }
    
    /**
     * Generate text completion
     * 
     * @param string $prompt The prompt to send
     * @param array $options Options for generation (model, temperature, etc.)
     * @return array|WP_Error Response data or error
     */
    public function generate(string $prompt, array $options = [])
    {
        $defaults = [
            'model' => 'llama3.2',
            'stream' => false,
            'options' => [
                'temperature' => 0.7,
                'num_predict' => 500
            ]
        ];
        
        $params = array_merge($defaults, $options);
        $params['prompt'] = $prompt;
        
        return $this->request('/generate', $params);
    }
    
    /**
     * Chat completion
     * 
     * @param array $messages Chat messages
     * @param array $options Options for chat
     * @return array|WP_Error Response data or error
     */
    public function chat(array $messages, array $options = [])
    {
        $defaults = [
            'model' => 'llama3.2',
            'stream' => false,
            'options' => [
                'temperature' => 0.7,
                'num_predict' => 500
            ]
        ];
        
        $params = array_merge($defaults, $options);
        $params['messages'] = $messages;
        
        return $this->request('/chat', $params);
    }
    
    /**
     * Generate embeddings
     * 
     * @param string $input Text to embed
     * @param string $model Model to use for embeddings
     * @return array|WP_Error Response data or error
     */
    public function embed(string $input, string $model = 'all-minilm')
    {
        return $this->request('/embed', [
            'model' => $model,
            'input' => $input
        ]);
    }
    
    /**
     * List available models
     * 
     * @return array|WP_Error List of models or error
     */
    public function listModels()
    {
        return $this->request('/tags', [], 'GET');
    }
    
    /**
     * Get model information
     * 
     * @param string $model Model name
     * @return array|WP_Error Model info or error
     */
    public function modelInfo(string $model)
    {
        return $this->request('/show', ['name' => $model]);
    }
    
    /**
     * Make a request to Ollama API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error Response data or error
     */
    private function request(string $endpoint, array $data = [], string $method = 'POST')
    {
        $url = $this->url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ];
        
        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = wp_json_encode($data);
            }
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new \WP_Error(
                'ollama_api_error',
                sprintf('Ollama API returned status %d', $response_code),
                ['body' => $body, 'status' => $response_code]
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'ollama_json_error',
                'Failed to decode Ollama API response',
                ['body' => $body]
            );
        }
        
        return $data;
    }
}

/**
 * Get the global Ollama client instance
 * 
 * @return OllamaClient
 */
function ollama_client(): OllamaClient
{
    static $instance = null;
    
    if ($instance === null) {
        $instance = new OllamaClient();
    }
    
    return $instance;
}