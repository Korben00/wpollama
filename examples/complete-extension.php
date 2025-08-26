<?php
/**
 * Plugin Name: WPOllama Content Generator Extension
 * Description: Exemple complet d'extension pour WPOllama
 * Version: 1.0.0
 * Author: Votre Nom
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principale de l'extension
 */
class WPOllamaContentGeneratorExtension
{
    private const SERVICE_ID = 'content-generator';
    
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Hooks WordPress
        add_action('wp_ajax_generate_content', [$this, 'ajax_generate_content']);
        add_action('wp_ajax_nopriv_generate_content', [$this, 'ajax_generate_content']); // Si vous voulez permettre aux non-connectés
    }
    
    /**
     * Initialisation du plugin
     */
    public function init()
    {
        // Vérifier que WPOllama est disponible
        if (!function_exists('ollamapress_register_service')) {
            add_action('admin_notices', [$this, 'wpollama_missing_notice']);
            return;
        }
        
        // Enregistrer notre service
        $this->register_service();
        
        // Ajouter un menu admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }
    
    /**
     * Enregistrer le service avec WPOllama
     */
    private function register_service()
    {
        $config = [
            'name' => self::SERVICE_ID,
            'description' => 'Générateur de contenu automatisé utilisant l\'IA',
            'version' => '1.0.0',
            'author' => 'Votre Nom',
            'permissions' => 'read', // Les utilisateurs connectés peuvent l'utiliser
            'priority' => 10,
            
            // Endpoints personnalisés
            'endpoints' => [
                '/generate-post' => [
                    'methods' => 'POST',
                    'callback' => [$this, 'generate_post_content'],
                    'args' => [
                        'topic' => [
                            'required' => true,
                            'type' => 'string',
                            'description' => 'Sujet du contenu à générer'
                        ],
                        'type' => [
                            'default' => 'article',
                            'type' => 'string',
                            'enum' => ['article', 'summary', 'list'],
                            'description' => 'Type de contenu'
                        ],
                        'length' => [
                            'default' => 'medium',
                            'type' => 'string',
                            'enum' => ['short', 'medium', 'long'],
                            'description' => 'Longueur du contenu'
                        ],
                        'tone' => [
                            'default' => 'professional',
                            'type' => 'string',
                            'enum' => ['professional', 'casual', 'academic', 'creative'],
                            'description' => 'Ton du contenu'
                        ]
                    ]
                ],
                '/analyze-content' => [
                    'methods' => 'POST',
                    'callback' => [$this, 'analyze_content'],
                    'args' => [
                        'content' => [
                            'required' => true,
                            'type' => 'string',
                            'description' => 'Contenu à analyser'
                        ]
                    ]
                ],
                '/suggest-titles' => [
                    'methods' => 'POST',
                    'callback' => [$this, 'suggest_titles'],
                    'args' => [
                        'content' => [
                            'required' => true,
                            'type' => 'string',
                            'description' => 'Contenu pour générer des titres'
                        ],
                        'count' => [
                            'default' => 5,
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 10,
                            'description' => 'Nombre de titres à suggérer'
                        ]
                    ]
                ]
            ],
            
            // Middleware pour traitement des requêtes
            'middleware' => [
                [$this, 'log_requests'],
                [$this, 'validate_rate_limit'],
                [$this, 'enhance_prompts']
            ],
            
            // Hooks pour les événements WPOllama
            'hooks' => [
                'pre_generate' => [$this, 'before_generation'],
                'post_generate' => [$this, 'after_generation'],
                'pre_chat' => [$this, 'before_chat'],
                'post_chat' => [$this, 'after_chat']
            ]
        ];
        
        $success = ollamapress_register_service(self::SERVICE_ID, $config);
        
        if (!$success) {
            error_log('Erreur lors de l\'enregistrement du service Content Generator');
        }
    }
    
    /**
     * Générer du contenu pour un post
     */
    public function generate_post_content(\WP_REST_Request $request, $api_controller)
    {
        try {
            $topic = sanitize_text_field($request->get_param('topic'));
            $type = sanitize_text_field($request->get_param('type'));
            $length = sanitize_text_field($request->get_param('length'));
            $tone = sanitize_text_field($request->get_param('tone'));
            
            // Construire le prompt basé sur les paramètres
            $prompt = $this->build_content_prompt($topic, $type, $length, $tone);
            
            // Utiliser l'API WPOllama pour générer du contenu
            $result = $api_controller->make_ollama_request('/generate', [
                'model' => 'llama3.2',
                'prompt' => $prompt,
                'options' => [
                    'temperature' => 0.7,
                    'max_tokens' => $this->get_max_tokens($length)
                ]
            ]);
            
            if (isset($result['error'])) {
                throw new Exception($result['error']);
            }
            
            $generated_content = $result['response'] ?? '';
            
            // Sauvegarder l'historique de génération
            $this->save_generation_history([
                'topic' => $topic,
                'type' => $type,
                'length' => $length,
                'tone' => $tone,
                'content' => $generated_content,
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id()
            ]);
            
            return [
                'success' => true,
                'content' => $generated_content,
                'metadata' => [
                    'topic' => $topic,
                    'type' => $type,
                    'length' => $length,
                    'tone' => $tone,
                    'word_count' => str_word_count($generated_content),
                    'generated_at' => current_time('c')
                ]
            ];
            
        } catch (Exception $e) {
            return new \WP_Error(
                'generation_failed',
                'Erreur lors de la génération: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Analyser du contenu existant
     */
    public function analyze_content(\WP_REST_Request $request, $api_controller)
    {
        $content = sanitize_textarea_field($request->get_param('content'));
        
        $prompt = "Analyse ce contenu et fournis:\n";
        $prompt .= "1. Un résumé en 2-3 phrases\n";
        $prompt .= "2. Les mots-clés principaux\n";
        $prompt .= "3. Le ton utilisé\n";
        $prompt .= "4. Des suggestions d'amélioration\n\n";
        $prompt .= "Contenu à analyser:\n{$content}";
        
        $result = $api_controller->make_ollama_request('/generate', [
            'model' => 'llama3.2',
            'prompt' => $prompt,
            'options' => [
                'temperature' => 0.3 // Plus précis pour l'analyse
            ]
        ]);
        
        return [
            'success' => true,
            'analysis' => $result['response'] ?? '',
            'original_length' => strlen($content),
            'word_count' => str_word_count($content)
        ];
    }
    
    /**
     * Suggérer des titres
     */
    public function suggest_titles(\WP_REST_Request $request, $api_controller)
    {
        $content = sanitize_textarea_field($request->get_param('content'));
        $count = (int) $request->get_param('count');
        
        $prompt = "Basé sur ce contenu, suggère {$count} titres accrocheurs et SEO-friendly:\n\n{$content}";
        
        $result = $api_controller->make_ollama_request('/generate', [
            'model' => 'llama3.2',
            'prompt' => $prompt,
            'options' => [
                'temperature' => 0.8 // Plus créatif pour les titres
            ]
        ]);
        
        // Extraire les titres de la réponse
        $titles = $this->extract_titles_from_response($result['response'] ?? '');
        
        return [
            'success' => true,
            'titles' => $titles,
            'count' => count($titles)
        ];
    }
    
    /**
     * Middleware : Logger les requêtes
     */
    public function log_requests(\WP_REST_Request $request, string $endpoint)
    {
        $user_id = get_current_user_id();
        $log_data = [
            'endpoint' => $endpoint,
            'user_id' => $user_id,
            'timestamp' => current_time('c'),
            'params' => $request->get_params()
        ];
        
        // Sauvegarder dans un log personnalisé ou WordPress
        error_log('Content Generator Request: ' . json_encode($log_data));
        
        return $request;
    }
    
    /**
     * Middleware : Validation du rate limiting
     */
    public function validate_rate_limit(\WP_REST_Request $request, string $endpoint)
    {
        $user_id = get_current_user_id();
        $rate_key = "content_gen_rate_{$user_id}_" . date('H');
        
        $current_requests = (int) get_transient($rate_key);
        $limit = current_user_can('manage_options') ? 100 : 30; // Admins ont plus de requêtes
        
        if ($current_requests >= $limit) {
            throw new Exception("Limite de requêtes dépassée ({$limit}/heure). Réessayez plus tard.");
        }
        
        set_transient($rate_key, $current_requests + 1, HOUR_IN_SECONDS);
        
        return $request;
    }
    
    /**
     * Middleware : Améliorer les prompts
     */
    public function enhance_prompts(\WP_REST_Request $request, string $endpoint)
    {
        // Ajouter des instructions générales pour de meilleurs résultats
        if (strpos($endpoint, '/generate') !== false) {
            $prompt = $request->get_param('prompt');
            $enhanced_prompt = "Tu es un rédacteur professionnel WordPress. " . $prompt . "\n\nRéponds en français avec un style clair et engageant.";
            $request->set_param('prompt', $enhanced_prompt);
        }
        
        return $request;
    }
    
    /**
     * Hook : Avant génération
     */
    public function before_generation(\WP_REST_Request $request)
    {
        // Incrémenter un compteur de générations
        $count = (int) get_option('content_generator_total_generations', 0);
        update_option('content_generator_total_generations', $count + 1);
    }
    
    /**
     * Hook : Après génération
     */
    public function after_generation(\WP_REST_Request $request, $response)
    {
        // Optionnel : traitement post-génération
        do_action('content_generator_after_generation', $request, $response);
    }
    
    /**
     * Hook : Avant chat
     */
    public function before_chat(\WP_REST_Request $request)
    {
        // Logique avant chat si nécessaire
    }
    
    /**
     * Hook : Après chat
     */
    public function after_chat(\WP_REST_Request $request, $response)
    {
        // Logique après chat si nécessaire
    }
    
    /**
     * Ajouter menu admin
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            'Content Generator',
            'Content Generator',
            'edit_posts',
            'content-generator',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Page d'administration
     */
    public function admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Content Generator</h1>
            
            <div id="content-generator-app">
                <div class="card">
                    <h2>Générer du Contenu</h2>
                    <form id="content-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Sujet</th>
                                <td><input type="text" id="topic" name="topic" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row">Type</th>
                                <td>
                                    <select id="type" name="type">
                                        <option value="article">Article</option>
                                        <option value="summary">Résumé</option>
                                        <option value="list">Liste</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Longueur</th>
                                <td>
                                    <select id="length" name="length">
                                        <option value="short">Court</option>
                                        <option value="medium">Moyen</option>
                                        <option value="long">Long</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Ton</th>
                                <td>
                                    <select id="tone" name="tone">
                                        <option value="professional">Professionnel</option>
                                        <option value="casual">Décontracté</option>
                                        <option value="academic">Académique</option>
                                        <option value="creative">Créatif</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button-primary">Générer du Contenu</button>
                        </p>
                    </form>
                </div>
                
                <div id="result-container" style="display:none;">
                    <div class="card">
                        <h2>Contenu Généré</h2>
                        <div id="generated-content"></div>
                        <p>
                            <button type="button" class="button" id="copy-content">Copier</button>
                            <button type="button" class="button" id="create-post">Créer un Article</button>
                        </p>
                    </div>
                </div>
                
                <div id="loading" style="display:none;">
                    <p>Génération en cours... <span class="spinner is-active"></span></p>
                </div>
            </div>
            
            <div class="card">
                <h2>Statistiques</h2>
                <p>Total des générations : <?php echo get_option('content_generator_total_generations', 0); ?></p>
            </div>
        </div>
        
        <style>
        .card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        #generated-content {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            min-height: 200px;
            white-space: pre-wrap;
        }
        </style>
        <?php
    }
    
    /**
     * Charger les scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'tools_page_content-generator') {
            return;
        }
        
        wp_enqueue_script(
            'content-generator-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('content-generator-admin', 'contentGenerator', [
            'apiUrl' => rest_url('ollama/v1/extensions/' . self::SERVICE_ID),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
    
    /**
     * Charger les scripts frontend
     */
    public function enqueue_scripts()
    {
        // Scripts frontend si nécessaire
    }
    
    /**
     * Gestionnaire AJAX pour génération rapide
     */
    public function ajax_generate_content()
    {
        check_ajax_referer('content_generator_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }
        
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        
        if (empty($topic)) {
            wp_send_json_error('Sujet requis');
        }
        
        // Utiliser l'API REST interne
        $request = new \WP_REST_Request('POST', '/ollama/v1/extensions/' . self::SERVICE_ID . '/generate-post');
        $request->set_param('topic', $topic);
        
        // Simuler la requête REST
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            wp_send_json_error($response->as_error()->get_error_message());
        }
        
        wp_send_json_success($response->get_data());
    }
    
    /**
     * Notice si WPOllama manque
     */
    public function wpollama_missing_notice()
    {
        ?>
        <div class="notice notice-error">
            <p><strong>Content Generator Extension</strong> nécessite le plugin <strong>WPOllama</strong> pour fonctionner.</p>
        </div>
        <?php
    }
    
    // Méthodes utilitaires privées
    
    private function build_content_prompt(string $topic, string $type, string $length, string $tone): string
    {
        $length_map = [
            'short' => '200-300 mots',
            'medium' => '500-800 mots', 
            'long' => '1000-1500 mots'
        ];
        
        $type_instructions = [
            'article' => 'Écris un article complet avec introduction, développement et conclusion',
            'summary' => 'Crée un résumé structuré avec points clés',
            'list' => 'Présente les informations sous forme de liste organisée'
        ];
        
        $tone_instructions = [
            'professional' => 'ton professionnel et informatif',
            'casual' => 'ton décontracté et accessible',
            'academic' => 'ton académique avec références',
            'creative' => 'ton créatif et engageant'
        ];
        
        return sprintf(
            "%s sur le sujet '%s' en %s avec un %s. Le contenu doit faire environ %s.",
            $type_instructions[$type],
            $topic,
            'français',
            $tone_instructions[$tone],
            $length_map[$length]
        );
    }
    
    private function get_max_tokens(string $length): int
    {
        $token_map = [
            'short' => 400,
            'medium' => 1000,
            'long' => 2000
        ];
        
        return $token_map[$length] ?? 1000;
    }
    
    private function save_generation_history(array $data): void
    {
        $history = get_option('content_generator_history', []);
        array_unshift($history, $data);
        
        // Garder seulement les 100 dernières générations
        $history = array_slice($history, 0, 100);
        
        update_option('content_generator_history', $history);
    }
    
    private function extract_titles_from_response(string $response): array
    {
        // Extraire les titres de la réponse IA (logique simplifiée)
        $lines = explode("\n", $response);
        $titles = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && (
                strpos($line, '.') === 1 || // "1. Titre"
                strpos($line, '-') === 0 || // "- Titre"
                strpos($line, '*') === 0    // "* Titre"
            )) {
                $title = preg_replace('/^[0-9\.\-\*\s]+/', '', $line);
                if (!empty($title)) {
                    $titles[] = trim($title);
                }
            }
        }
        
        return array_slice($titles, 0, 10); // Max 10 titres
    }
}

// Initialiser l'extension
new WPOllamaContentGeneratorExtension();