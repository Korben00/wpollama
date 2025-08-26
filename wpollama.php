<?php
/**
 * Plugin Name: WPOllama
 * Description: Passerelle simple entre WordPress et Ollama pour utilisation interne
 * Version: 1.0.0
 * Author: WPOllama
 * Text Domain: wpollama
 */

namespace WPOllama;

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}

// Constantes
define('WPOLLAMA_VERSION', '1.0.0');
define('WPOLLAMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPOLLAMA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Client Ollama simple pour WordPress
 */
class Client
{
    private string $url;
    private int $timeout;
    
    public function __construct()
    {
        $this->url = get_option('wpollama_url', 'http://localhost:11434/api');
        $this->timeout = (int) get_option('wpollama_timeout', 30);
    }
    
    /**
     * Générer du texte
     */
    public function generate(string $prompt, array $options = []): array
    {
        $defaults = [
            'model' => get_option('wpollama_default_model', 'qwen3:latest'),
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
     * Chat
     */
    public function chat(array $messages, array $options = []): array
    {
        $defaults = [
            'model' => get_option('wpollama_default_model', 'qwen3:latest'),
            'stream' => false
        ];
        
        $params = array_merge($defaults, $options);
        $params['messages'] = $messages;
        
        return $this->request('/chat', $params);
    }
    
    /**
     * Lister les modèles disponibles
     */
    public function listModels(): array
    {
        return $this->request('/tags', [], 'GET');
    }
    
    /**
     * Vérifier la connexion
     */
    public function ping(): bool
    {
        $base_url = str_replace('/api', '', $this->url);
        $response = wp_remote_get($base_url, ['timeout' => 5]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
    
    /**
     * Faire une requête à Ollama
     */
    private function request(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url = $this->url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => ['Content-Type' => 'application/json']
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
            return [
                'error' => true,
                'message' => $response->get_error_message()
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [
                'error' => true,
                'message' => 'HTTP error ' . $code
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'error' => true,
                'message' => 'Invalid JSON response',
                'raw' => $body
            ];
        }
        
        // Nettoyer les think tags si présentes (pour qwen3 et autres)
        if (isset($data['response']) && strpos($data['response'], '<think>') !== false) {
            $data['response'] = preg_replace('/<think>.*?<\/think>/s', '', $data['response']);
            $data['response'] = trim($data['response']);
        }
        
        return $data ?: [];
    }
}

/**
 * Page d'administration
 */
class Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    public function addMenu(): void
    {
        add_options_page(
            'WPOllama',
            'WPOllama',
            'manage_options',
            'wpollama',
            [$this, 'renderPage']
        );
    }
    
    public function registerSettings(): void
    {
        register_setting('wpollama_settings', 'wpollama_url');
        register_setting('wpollama_settings', 'wpollama_timeout');
        register_setting('wpollama_settings', 'wpollama_default_model');
    }
    
    public function renderPage(): void
    {
        $client = new Client();
        $is_connected = $client->ping();
        $models = [];
        
        if ($is_connected) {
            $models_response = $client->listModels();
            if (isset($models_response['models'])) {
                $models = $models_response['models'];
            }
        }
        ?>
        <div class="wrap">
            <h1>WPOllama - Configuration</h1>
            
            <?php if ($is_connected): ?>
                <div class="notice notice-success">
                    <p>✅ Connexion à Ollama réussie</p>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p>❌ Impossible de se connecter à Ollama. Vérifiez qu'il est lancé avec: <code>ollama serve</code></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpollama_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">URL Ollama</th>
                        <td>
                            <input type="text" 
                                   name="wpollama_url" 
                                   value="<?php echo esc_attr(get_option('wpollama_url', 'http://localhost:11434/api')); ?>" 
                                   class="regular-text" />
                            <p class="description">URL de l'API Ollama (par défaut: http://localhost:11434/api)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Timeout</th>
                        <td>
                            <input type="number" 
                                   name="wpollama_timeout" 
                                   value="<?php echo esc_attr(get_option('wpollama_timeout', 30)); ?>" 
                                   min="5" 
                                   max="300" 
                                   class="small-text" /> secondes
                            <p class="description">Temps maximum d'attente pour une réponse</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Modèle par défaut</th>
                        <td>
                            <?php $current_model = get_option('wpollama_default_model', 'llama3.2'); ?>
                            
                            <?php if (!empty($models)): ?>
                                <select name="wpollama_default_model">
                                    <?php foreach ($models as $model): ?>
                                        <option value="<?php echo esc_attr($model['name']); ?>" 
                                                <?php selected($current_model, $model['name']); ?>>
                                            <?php echo esc_html($model['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" 
                                       name="wpollama_default_model" 
                                       value="<?php echo esc_attr($current_model); ?>" 
                                       class="regular-text" />
                            <?php endif; ?>
                            
                            <p class="description">Modèle IA à utiliser par défaut</p>
                        </td>
                    </tr>
                </table>
                
                <?php if ($is_connected && !empty($models)): ?>
                    <h2>Modèles disponibles</h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Modèle</th>
                                <th>Taille</th>
                                <th>Modifié</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($models as $model): ?>
                                <tr>
                                    <td><?php echo esc_html($model['name']); ?></td>
                                    <td>
                                        <?php 
                                        if (isset($model['size'])) {
                                            echo round($model['size'] / 1024 / 1024 / 1024, 2) . ' GB';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($model['modified_at'])) {
                                            echo date('Y-m-d H:i', strtotime($model['modified_at']));
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php submit_button('Enregistrer'); ?>
            </form>
            
            <h2>Test rapide</h2>
            <p>
                <button type="button" id="wpollama-test" class="button">Tester la connexion</button>
                <span id="wpollama-test-result"></span>
            </p>
            
            <script>
            document.getElementById('wpollama-test').addEventListener('click', function() {
                const button = this;
                const result = document.getElementById('wpollama-test-result');
                
                button.disabled = true;
                result.textContent = 'Test en cours...';
                
                // Ici on pourrait faire un appel AJAX pour tester
                setTimeout(() => {
                    button.disabled = false;
                    result.textContent = <?php echo $is_connected ? '"✅ Connexion OK"' : '"❌ Échec de connexion"'; ?>;
                }, 1000);
            });
            </script>
        </div>
        <?php
    }
}

/**
 * Fonction globale pour obtenir le client Ollama
 */
function wpollama_client(): Client
{
    static $instance = null;
    
    if ($instance === null) {
        $instance = new Client();
    }
    
    return $instance;
}

/**
 * Fonction helper pour générer du texte
 */
function wpollama_generate(string $prompt, array $options = []): array
{
    return wpollama_client()->generate($prompt, $options);
}

/**
 * Fonction helper pour le chat
 */
function wpollama_chat(array $messages, array $options = []): array
{
    return wpollama_client()->chat($messages, $options);
}

// Initialisation
add_action('init', function() {
    if (is_admin()) {
        new Admin();
    }
});

// Activation/Désactivation
register_activation_hook(__FILE__, function() {
    add_option('wpollama_url', 'http://localhost:11434/api');
    add_option('wpollama_timeout', 30);
    add_option('wpollama_default_model', 'qwen3:latest');
});

register_deactivation_hook(__FILE__, function() {
    // Nettoyage si nécessaire
});