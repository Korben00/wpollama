# OllamaPress

OllamaPress is a WordPress plugin that provides a bridge between WordPress and Ollama's API, allowing you to interact with large language models directly from your WordPress installation.

## Features

OllamaPress exposes Ollama's API endpoints through WordPress REST API routes, making it easy to integrate AI capabilities into your WordPress site. Whether you want to generate text completions, have interactive chats, or work with AI models, OllamaPress provides the necessary infrastructure.

Complete Ollama API integration including:

- Text generation and completions
- Interactive chat capabilities
- Model management (create, list, copy, delete)
- Embedding generation
- Model information retrieval
- Support for streaming responses
- Support for multimodal models (like llava)

## Requirements

- WordPress installation
- Running Ollama instance
- WordPress REST API enabled

## Installation

1. Download the plugin files
2. Upload them to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your Ollama API URL in the plugin settings (default: `http://localhost:11434/api`)

If WordPress is running in Docker and Ollama is installed on your localhost you can use `http://host.docker.internal:11434/api` as the API URL.

## Configuration

You can save these values in the options panel or define them in your `wp-config.php`:

```php
define('OLLAMA_URL', 'http://host.docker.internal:11434/api');
define('OLLAMA_TIMEOUT', 300);
```

## API Endpoints

OllamaPress exposes the following WordPress REST API endpoints (namespace: `ollama/v1`):

### Chat & Generation

- `POST /wp-json/ollama/v1/generate` - Generate text completions
- `POST /wp-json/ollama/v1/chat` - Interactive chat with models
- `POST /wp-json/ollama/v1/embed` - Generate embeddings

### Model Management

- `GET /wp-json/ollama/v1/models` - List available models
- `POST /wp-json/ollama/v1/create` - Create a new model
- `POST /wp-json/ollama/v1/info` - Get model information
- `POST /wp-json/ollama/v1/copy` - Copy a model
- `DELETE /wp-json/ollama/v1/delete` - Delete a model
- `POST /wp-json/ollama/v1/pull` - Pull a model from Ollama
- `POST /wp-json/ollama/v1/push` - Push a model to Ollama
- `GET /wp-json/ollama/v1/running` - List currently running models

## Usage Examples

### Generate Text

```php
$response = wp_remote_post(rest_url('ollama/v1/generate'), [
    'body' => wp_json_encode([
        'model' => 'llama2',
        'prompt' => 'Write a short blog post about WordPress',
        'stream' => false
    ]),
    'headers' => ['Content-Type' => 'application/json'],
]);
```

### Chat Interaction

```php
$response = wp_remote_post(rest_url('ollama/v1/chat'), [
    'body' => wp_json_encode([
        'model' => 'llama2',
        'messages' => [
            ['role' => 'user', 'content' => 'What is WordPress?']
        ],
        'stream' => false
    ]),
    'headers' => ['Content-Type' => 'application/json'],
]);
```

### Generate Embeddings

```php
$response = wp_remote_post(rest_url('ollama/v1/embed'), [
    'body' => wp_json_encode([
        'model' => 'all-minilm',
        'input' => 'Text to embed'
    ]),
    'headers' => ['Content-Type' => 'application/json'],
]);
```

## License

This plugin is licensed under the GPL v2 or later.

## Support

Commercial support is available for this plugin. [Book a consultation](https://cal.com/carmelosantana/easy-rest-api) to get started.
