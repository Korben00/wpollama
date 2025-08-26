jQuery(document).ready(function($) {
    'use strict';
    
    const { apiUrl, nonce } = contentGenerator;
    
    // Gestionnaire de formulaire
    $('#content-form').on('submit', function(e) {
        e.preventDefault();
        generateContent();
    });
    
    // Bouton copier
    $('#copy-content').on('click', function() {
        copyToClipboard($('#generated-content').text());
    });
    
    // Bouton créer post
    $('#create-post').on('click', function() {
        createWordPressPost();
    });
    
    /**
     * Générer du contenu
     */
    function generateContent() {
        const formData = {
            topic: $('#topic').val(),
            type: $('#type').val(),
            length: $('#length').val(),
            tone: $('#tone').val()
        };
        
        if (!formData.topic.trim()) {
            alert('Veuillez saisir un sujet');
            return;
        }
        
        showLoading(true);
        hideResult();
        
        $.ajax({
            url: apiUrl + '/generate-post',
            method: 'POST',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: formData,
            success: function(response) {
                if (response.success) {
                    displayResult(response);
                } else {
                    alert('Erreur: ' + (response.message || 'Génération échouée'));
                }
            },
            error: function(xhr) {
                let message = 'Erreur de connexion';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert('Erreur: ' + message);
            },
            complete: function() {
                showLoading(false);
            }
        });
    }
    
    /**
     * Afficher le résultat
     */
    function displayResult(response) {
        const content = response.content || '';
        const metadata = response.metadata || {};
        
        $('#generated-content').text(content);
        
        // Ajouter les métadonnées
        let metaInfo = '';
        if (metadata.word_count) {
            metaInfo += `Mots: ${metadata.word_count} | `;
        }
        if (metadata.type) {
            metaInfo += `Type: ${metadata.type} | `;
        }
        if (metadata.tone) {
            metaInfo += `Ton: ${metadata.tone}`;
        }
        
        if (metaInfo) {
            $('#generated-content').append(`\n\n---\n${metaInfo}`);
        }
        
        showResult();
    }
    
    /**
     * Copier dans le presse-papier
     */
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showNotice('Contenu copié dans le presse-papier', 'success');
            });
        } else {
            // Fallback pour navigateurs plus anciens
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotice('Contenu copié dans le presse-papier', 'success');
        }
    }
    
    /**
     * Créer un article WordPress
     */
    function createWordPressPost() {
        const content = $('#generated-content').text();
        const topic = $('#topic').val();
        
        if (!content.trim()) {
            alert('Aucun contenu à publier');
            return;
        }
        
        // Créer un brouillon via l'API WordPress
        $.ajax({
            url: '/wp-json/wp/v2/posts',
            method: 'POST',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: {
                title: topic,
                content: content.replace(/\n/g, '<br>'),
                status: 'draft'
            },
            success: function(response) {
                if (response.id) {
                    const editUrl = `/wp-admin/post.php?post=${response.id}&action=edit`;
                    showNotice(`Article créé en brouillon. <a href="${editUrl}" target="_blank">Modifier</a>`, 'success');
                }
            },
            error: function(xhr) {
                alert('Erreur lors de la création de l\'article: ' + xhr.status);
            }
        });
    }
    
    /**
     * Afficher/masquer le chargement
     */
    function showLoading(show) {
        if (show) {
            $('#loading').show();
        } else {
            $('#loading').hide();
        }
    }
    
    /**
     * Afficher/masquer les résultats
     */
    function showResult() {
        $('#result-container').show();
    }
    
    function hideResult() {
        $('#result-container').hide();
    }
    
    /**
     * Afficher une notification
     */
    function showNotice(message, type = 'info') {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-info';
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible" style="margin: 20px 0;">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Ignorer cette notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss après 5 secondes
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // Gestionnaire de bouton dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }
    
    /**
     * Suggestions automatiques de sujets (bonus)
     */
    const commonTopics = [
        'Intelligence Artificielle',
        'WordPress pour débutants',
        'Marketing digital',
        'Sécurité web',
        'SEO et référencement',
        'E-commerce',
        'Réseaux sociaux',
        'Productivité au travail'
    ];
    
    // Autocomplétion simple
    $('#topic').on('focus', function() {
        if (!$(this).data('autocomplete-added')) {
            const datalist = $('<datalist id="topic-suggestions"></datalist>');
            commonTopics.forEach(topic => {
                datalist.append(`<option value="${topic}">`);
            });
            $('body').append(datalist);
            $(this).attr('list', 'topic-suggestions');
            $(this).data('autocomplete-added', true);
        }
    });
});