<?php
/**
 * Plugin Name: WPOllama Simple Extension
 * Description: Exemple simple d'extension pour WPOllama
 * Version: 1.0.0
 * Author: Votre Nom
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extension simple pour WPOllama
 */
class SimpleWPOllamaExtension
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init()
    {
        // Vérifier que WPOllama est disponible
        if (!function_exists('ollamapress_register_service')) {
            return;
        }
        
        // Enregistrer notre service simple
        ollamapress_register_service('simple-translator', [
            'name' => 'simple-translator',
            'description' => 'Traducteur simple utilisant l\'IA',
            'version' => '1.0.0',
            'author' => 'Mon Plugin',
            'permissions' => 'read',
            
            'endpoints' => [
                '/translate' => [
                    'methods' => 'POST',
                    'callback' => [$this, 'translate_text'],
                    'args' => [
                        'text' => [
                            'required' => true,
                            'type' => 'string'
                        ],
                        'target_language' => [
                            'required' => true,
                            'type' => 'string'
                        ]
                    ]
                ]
            ]
        ]);
    }
    
    /**
     * Traduire du texte
     */
    public function translate_text(\WP_REST_Request $request, $api_controller)
    {
        $text = sanitize_textarea_field($request->get_param('text'));
        $target_language = sanitize_text_field($request->get_param('target_language'));
        
        $prompt = "Traduis ce texte en {$target_language} :\n\n{$text}";
        
        $result = $api_controller->make_ollama_request('/generate', [
            'model' => 'llama3.2',
            'prompt' => $prompt
        ]);
        
        return [
            'success' => true,
            'original' => $text,
            'translated' => $result['response'] ?? '',
            'target_language' => $target_language
        ];
    }
}

// Initialiser l'extension
new SimpleWPOllamaExtension();