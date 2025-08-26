# Security Guidelines for WPOllama

## ⚠️ Important Security Considerations

WPOllama creates a bridge between your WordPress site and local Ollama installation. This document outlines security best practices and potential risks.

## 🔐 Authentication & Authorization

### User Authentication Required
- **All endpoints require user login** - Anonymous access is blocked
- Regular users: Limited to whitelisted models only
- Administrators: Full access to all models and management functions

### Permission Levels
- **Read Operations** (`generate`, `chat`, `embed`, `models`, `info`, `running`): Requires `is_user_logged_in()`
- **Management Operations** (`create`, `copy`, `delete`, `pull`, `push`): Requires `manage_options` capability

## 🛡️ Model Whitelist System

### Default Allowed Models
```php
['llama3.2', 'llama2', 'codellama', 'mistral', 'phi', 'gemma']
```

### Customizing Allowed Models
```php
// In your theme's functions.php or plugin
add_filter('ollamapress_allowed_models', function($models) {
    return ['llama2', 'custom-model']; // Only allow specific models
});
```

## ⚡ Performance & DoS Protection

### Timeout Settings
- **Default timeout**: 30 seconds (reduced from 300s)
- Configure via `wp-config.php`: `define('OLLAMA_TIMEOUT', 30);`

### Rate Limiting Recommendations
Consider implementing additional rate limiting:
```php
// Example: Limit requests per user
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    if (strpos($request->get_route(), '/ollama/v1/') === 0) {
        // Implement your rate limiting logic
    }
    return $result;
}, 10, 3);
```

## 🌐 Network Security

### SSH Tunnel Configuration
For remote WordPress + local Ollama:
```bash
# On your local machine
ssh -R 11434:localhost:11434 -o ServerAliveInterval=30 user@yoursite.com
```

### WordPress Configuration
```php
// In wp-config.php
define('OLLAMA_URL', 'http://127.0.0.1:11434/api');
define('OLLAMA_TIMEOUT', 30);
```

## 🚨 Known Risks

### 1. Resource Consumption
- AI model requests can consume significant CPU/memory
- Long-running requests may impact server performance
- **Mitigation**: Reduced timeout, user authentication

### 2. Data Privacy
- All prompts/conversations pass through your WordPress server
- Consider data retention policies for logs
- **Mitigation**: Ensure HTTPS, monitor access logs

### 3. Model Access
- Users could potentially access sensitive or expensive models
- **Mitigation**: Whitelist system, admin-only management

### 4. Network Exposure
- SSH tunnels expose local Ollama to internet
- **Mitigation**: Strong SSH keys, firewall rules, monitoring

## ✅ Security Checklist

- [ ] Enable HTTPS on WordPress site
- [ ] Use strong SSH keys for tunneling
- [ ] Configure firewall to limit SSH access
- [ ] Monitor WordPress access logs
- [ ] Regularly update WordPress and plugins
- [ ] Use strong passwords for WordPress users
- [ ] Consider additional rate limiting
- [ ] Review allowed models regularly
- [ ] Monitor server resource usage

## 🔧 Configuration Examples

### Strict Security Setup
```php
// wp-config.php - Most restrictive
define('OLLAMA_URL', 'http://127.0.0.1:11434/api');
define('OLLAMA_TIMEOUT', 15); // Very short timeout

// functions.php - Only allow one safe model
add_filter('ollamapress_allowed_models', function() {
    return ['llama2']; // Single approved model
});
```

### Development Setup
```php
// wp-config.php - Development environment
define('OLLAMA_URL', 'http://localhost:11434/api');
define('OLLAMA_TIMEOUT', 60); // Longer for development

// Allow more models for testing
add_filter('ollamapress_allowed_models', function() {
    return ['llama3.2', 'llama2', 'codellama', 'mistral'];
});
```

## 🆘 Incident Response

### If Compromised
1. Immediately deactivate WPOllama plugin
2. Check WordPress access logs for suspicious activity
3. Kill SSH tunnels: `pkill -f "ssh.*11434"`
4. Review Ollama logs for unauthorized access
5. Update all passwords and SSH keys

### Monitoring Commands
```bash
# Check SSH tunnel status
ps aux | grep "ssh.*11434"

# Monitor WordPress logs
tail -f /var/log/wordpress/access.log | grep "ollama/v1"

# Check Ollama process
ps aux | grep ollama
```

## 📞 Support

For security issues, please:
1. Do not report publicly
2. Contact plugin maintainer directly
3. Include WordPress version, PHP version, and error details

## 📝 Version History

- **v1.0.0**: Added user authentication requirement
- **v1.0.1**: Implemented model whitelist system
- **v1.0.2**: Reduced default timeout to 30s

---

**Remember**: AI models can be powerful tools but require careful security consideration. Always err on the side of caution when exposing AI capabilities through web interfaces.