# Guide du Développeur WPOllama

## 🚀 Utiliser WPOllama comme Plateforme d'Extension

WPOllama peut servir de point d'entrée pour d'autres plugins WordPress qui souhaitent utiliser les capacités d'IA d'Ollama. Cette documentation explique comment créer des extensions.

## 📋 Architecture d'Extension

### Composants Principaux

1. **PluginManager** : Gère l'enregistrement des services
2. **ExtensibleApi** : API étendue avec support des plugins
3. **Système de Hooks** : Points d'extension pour les événements
4. **Endpoints Personnalisés** : Routes API personnalisées

## 🔧 Enregistrement d'un Service

### Méthode Simple

```php
// Dans votre plugin
add_action('plugins_loaded', function() {
    if (function_exists('ollamapress_register_service')) {
        ollamapress_register_service('mon-plugin', [
            'name' => 'mon-plugin',
            'description' => 'Mon extension pour WPOllama',
            'version' => '1.0.0',
            'author' => 'Votre Nom',
            'permissions' => 'read', // 'read' ou 'manage'
            'priority' => 10
        ]);
    }
});
```

### Configuration Avancée

```php
$config = [
    'name' => 'content-generator',
    'description' => 'Générateur de contenu automatisé',
    'version' => '1.2.0',
    'author' => 'MonEntreprise',
    'permissions' => 'manage',
    'priority' => 5,
    
    // Endpoints personnalisés
    'endpoints' => [
        '/generate-post' => [
            'methods' => 'POST',
            'callback' => [$this, 'generate_post_content'],
            'args' => [
                'topic' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'length' => [
                    'default' => 'medium',
                    'type' => 'string'
                ]
            ]
        ]
    ],
    
    // Middleware pour traiter les requêtes
    'middleware' => [
        [$this, 'validate_content_request'],
        [$this, 'log_generation_request']
    ],
    
    // Hooks pour les événements
    'hooks' => [
        'pre_generate' => [$this, 'before_ai_generation'],
        'post_generate' => [$this, 'after_ai_generation']
    ]
];

ollamapress_register_service('content-generator', $config);
```

## 🛠️ Types d'Extensions

### 1. Générateur de Contenu

```php
class ContentGeneratorExtension {
    
    public function __construct() {
        add_action('plugins_loaded', [$this, 'register_service']);
    }
    
    public function register_service() {
        if (!function_exists('ollamapress_register_service')) {
            return;
        }
        
        ollamapress_register_service('content-generator', [
            'name' => 'content-generator',
            'description' => 'Génère du contenu automatiquement',
            'version' => '1.0.0',
            'author' => 'MonPlugin',
            'permissions' => 'read',
            'endpoints' => [
                '/generate-article' => [
                    'methods' => 'POST',
                    'callback' => [$this, 'generate_article']
                ]
            ]
        ]);
    }
    
    public function generate_article(\WP_REST_Request $request, $api_controller) {
        $topic = $request->get_param('topic');
        $length = $request->get_param('length') ?? 500;
        
        // Utiliser l'API WPOllama pour générer du contenu
        $prompt = "Écris un article de {$length} mots sur le sujet : {$topic}";
        
        $result = $api_controller->make_ollama_request('/generate', [
            'model' => 'llama3.2',
            'prompt' => $prompt
        ]);
        
        return [
            'success' => true,
            'content' => $result['response'] ?? '',
            'topic' => $topic
        ];
    }
}

new ContentGeneratorExtension();
```

### 2. Système de Chat Avancé

```php
class AdvancedChatExtension {
    
    public function register_service() {
        ollamapress_register_service('advanced-chat', [
            'name' => 'advanced-chat',
            'description' => 'Chat avec mémoire et personnalité',
            'version' => '1.0.0',
            'permissions' => 'read',
            'endpoints' => [
                '/chat-with-memory' => [
                    'methods' => 'POST',
                    'callback' => [$this, 'chat_with_memory']
                ],
                '/reset-memory' => [
                    'methods' => 'POST',
                    'callback' => [$this, 'reset_chat_memory']
                ]
            ],
            'hooks' => [
                'pre_chat' => [$this, 'inject_personality'],
                'post_chat' => [$this, 'save_conversation']
            ]
        ]);
    }
    
    public function chat_with_memory(\WP_REST_Request $request, $api_controller) {
        $user_id = get_current_user_id();
        $message = $request->get_param('message');
        
        // Récupérer l'historique
        $history = get_user_meta($user_id, 'chat_history', true) ?: [];
        
        // Ajouter le message utilisateur
        $history[] = ['role' => 'user', 'content' => $message];
        
        // Utiliser l'API chat avec l'historique
        $result = $api_controller->make_ollama_request('/chat', [
            'model' => 'llama3.2',
            'messages' => $history
        ]);
        
        // Sauvegarder la réponse
        if (isset($result['message'])) {
            $history[] = $result['message'];
            update_user_meta($user_id, 'chat_history', array_slice($history, -20)); // Garder 20 derniers messages
        }
        
        return $result;
    }
    
    public function inject_personality(\WP_REST_Request $request) {
        // Modifier la requête pour ajouter une personnalité
        $personality = "Tu es un assistant WordPress expert et amical.";
        $messages = $request->get_param('messages') ?: [];
        
        if (!empty($messages) && $messages[0]['role'] !== 'system') {
            array_unshift($messages, ['role' => 'system', 'content' => $personality]);
            $request->set_param('messages', $messages);
        }
    }
}
```

### 3. Middleware de Validation

```php
class ValidationMiddleware {
    
    public function validate_request(\WP_REST_Request $request, string $endpoint) {
        // Log de la requête
        error_log("WPOllama Request: {$endpoint} by user " . get_current_user_id());
        
        // Validation personnalisée
        if (strpos($endpoint, '/generate') !== false) {
            $prompt = $request->get_param('prompt');
            if (strlen($prompt) > 10000) {
                throw new \Exception('Prompt trop long (max 10000 caractères)');
            }
        }
        
        return $request;
    }
    
    public function rate_limit_middleware(\WP_REST_Request $request, string $endpoint) {
        $user_id = get_current_user_id();
        $rate_key = "ollama_requests_{$user_id}_" . date('H');
        
        $current_requests = (int) get_transient($rate_key);
        
        if ($current_requests >= 60) { // 60 requêtes par heure
            throw new \Exception('Limite de requêtes dépassée. Réessayez dans une heure.');
        }
        
        set_transient($rate_key, $current_requests + 1, HOUR_IN_SECONDS);
        
        return $request;
    }
}
```

## 🔍 API de Découverte

### Lister les Extensions

```javascript
// Frontend JavaScript
fetch('/wp-json/ollama/v1/extensions')
    .then(response => response.json())
    .then(data => {
        console.log('Extensions disponibles:', data.extensions);
    });
```

### Informations d'une Extension

```javascript
fetch('/wp-json/ollama/v1/extensions/content-generator')
    .then(response => response.json())
    .then(data => {
        console.log('Info extension:', data);
    });
```

## 📡 Utilisation des Endpoints Personnalisés

```javascript
// Utiliser un endpoint personnalisé
fetch('/wp-json/ollama/v1/extensions/content-generator/generate-article', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        topic: 'Intelligence Artificielle',
        length: 800
    })
})
.then(response => response.json())
.then(data => {
    console.log('Article généré:', data.content);
});
```

## 🎣 Hooks Disponibles

### Hooks de Génération
- `pre_generate` : Avant génération de texte
- `post_generate` : Après génération de texte
- `pre_chat` : Avant chat
- `post_chat` : Après chat

### Hooks de Service
- `ollamapress_service_registered` : Service enregistré
- `ollamapress_service_unregistered` : Service désenregistré

```php
// Écouter les événements
add_action('ollamapress_service_registered', function($service_id, $config) {
    error_log("Service enregistré: {$service_id}");
});
```

## 🛡️ Sécurité des Extensions

### Validation des Permissions

```php
public function secure_endpoint(\WP_REST_Request $request, $api_controller) {
    // Vérifier les permissions spécifiques
    if (!current_user_can('edit_posts')) {
        return new \WP_Error('insufficient_permissions', 'Permissions insuffisantes');
    }
    
    // Valider les données
    $content = sanitize_textarea_field($request->get_param('content'));
    
    // Votre logique...
}
```

### Nonces et Sécurité

```php
// Dans votre JavaScript
const nonce = wpApiSettings.nonce;

fetch('/wp-json/ollama/v1/extensions/mon-plugin/action', {
    headers: {
        'X-WP-Nonce': nonce
    }
});
```

## 📦 Exemple Complet

Voir le fichier `examples/complete-extension.php` pour un exemple complet d'extension avec toutes les fonctionnalités.

## 🆘 Support et Débogage

### Vérifier si WPOllama est Disponible

```php
if (!function_exists('ollamapress_register_service')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>WPOllama n\'est pas installé ou activé.</p></div>';
    });
    return;
}
```

### Logging et Débogage

```php
// Activer le débogage WordPress
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Dans votre plugin
error_log('WPOllama Extension: ' . print_r($data, true));
```

---

Cette architecture permet de créer des extensions riches qui exploitent pleinement les capacités d'Ollama tout en respectant les standards de sécurité WordPress.