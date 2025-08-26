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

### Authentication Required

⚠️ **All endpoints now require user authentication**. You need to be logged into WordPress to access the API.

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

## cURL Authentication Examples

### Method 1: Application Passwords (Recommended)

First, create an Application Password in WordPress Admin → Users → Your Profile → Application Passwords.

```bash
# List available models
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
     https://yoursite.com/wp-json/ollama/v1/models

# Generate text
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
     -H "Content-Type: application/json" \
     -X POST \
     -d '{"model": "llama2", "prompt": "Hello, how are you?", "stream": false}' \
     https://yoursite.com/wp-json/ollama/v1/generate

# Chat interaction  
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
     -H "Content-Type: application/json" \
     -X POST \
     -d '{"model": "llama2", "messages": [{"role": "user", "content": "What is AI?"}], "stream": false}' \
     https://yoursite.com/wp-json/ollama/v1/chat
```

### Method 2: Cookie Authentication

```bash
# First, login to get cookies
curl -c cookies.txt \
     -d "log=your-username&pwd=your-password&wp-submit=Log%20In&redirect_to=https://yoursite.com/wp-admin/&testcookie=1" \
     https://yoursite.com/wp-login.php

# Then use the cookie for API calls
curl -b cookies.txt \
     https://yoursite.com/wp-json/ollama/v1/models

curl -b cookies.txt \
     -H "Content-Type: application/json" \
     -X POST \
     -d '{"model": "llama2", "prompt": "Hello!"}' \
     https://yoursite.com/wp-json/ollama/v1/generate
```

### Method 3: JWT Token (Requires JWT Plugin)

If you have a JWT authentication plugin installed:

```bash
# Get JWT token
TOKEN=$(curl -X POST https://yoursite.com/wp-json/jwt-auth/v1/token \
     -H "Content-Type: application/json" \
     -d '{"username": "your-username", "password": "your-password"}' \
     | jq -r '.token')

# Use token for API calls
curl -H "Authorization: Bearer $TOKEN" \
     https://yoursite.com/wp-json/ollama/v1/models

curl -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -X POST \
     -d '{"model": "llama2", "prompt": "Hello!"}' \
     https://yoursite.com/wp-json/ollama/v1/generate
```

### JavaScript/AJAX from WordPress Frontend

```javascript
// If user is logged in to WordPress, this will work automatically
fetch('/wp-json/ollama/v1/generate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce // WordPress nonce
    },
    body: JSON.stringify({
        model: 'llama2',
        prompt: 'Hello from JavaScript!',
        stream: false
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

## Security

⚠️ **Important**: This plugin exposes AI model capabilities through WordPress REST API. Please review the [SECURITY.md](SECURITY.md) file for important security considerations and best practices.

Key security features:
- User authentication required for all endpoints
- Model whitelist system to prevent unauthorized access
- Reduced timeouts to prevent resource abuse
- Admin-only access for model management operations

## License

This plugin is licensed under the GPL v2 or later.

## Support

Commercial support is available for this plugin. [Book a consultation](https://cal.com/carmelosantana/easy-rest-api) to get started.
