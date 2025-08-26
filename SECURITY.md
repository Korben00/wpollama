# Security Guidelines for WPOllama

## ⚠️ Important Security Considerations

WPOllama creates a bridge between your WordPress site and local Ollama installation. This document outlines security best practices and potential risks.

## 🔐 Authentication & Authorization

### External Access Control (NEW - v0.2.0)
- **Default**: External access is DISABLED
- **Configuration**: Settings > Ollama API > Security Settings
- **Options**:
  - Enable/disable external access
  - Configure allowed origins for CORS
  - Enable rate limiting (60 req/min default)

### User Authentication Required
- **Default behavior**: All endpoints require WordPress login
- **External access disabled** (default): Only authenticated WordPress users
- **External access enabled**: Configurable origin restrictions apply
- Regular users: Limited to whitelisted models only
- Administrators: Full access to all models and management functions

### Permission Levels
- **Read Operations** (`generate`, `chat`, `embed`, `models`, `info`, `running`): 
  - With external access disabled: Requires `is_user_logged_in()`
  - With external access enabled: Subject to origin restrictions and rate limiting
- **Management Operations** (`create`, `copy`, `delete`, `pull`, `push`): 
  - ALWAYS requires authentication and `manage_options` capability
  - NEVER accessible externally

## 🛡️ Security Configuration

### External Access Settings
```php
// Access these in WordPress Admin > Settings > Ollama API > Security Settings

// External Access (checkbox)
get_option('ollama_allow_external_access', false); // Default: false

// Allowed Origins (textarea, one per line)
get_option('ollama_allowed_origins', ''); // Default: empty

// Rate Limiting (checkbox)
get_option('ollama_rate_limit_enabled', true); // Default: true
```

### Recommended Production Configuration
1. **Keep external access DISABLED** (default)
2. If external access needed:
   - Enable only for specific development needs
   - Configure allowed origins strictly
   - Keep rate limiting enabled
   - Use HTTPS only

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

### Built-in Rate Limiting
- **Default**: 60 requests per minute per user/IP
- **Configurable**: Via admin settings
- **Applies to**: External requests when enabled

### Timeout Settings
- **Default timeout**: 30 seconds (reduced from 300s)
- Configure via `wp-config.php`: `define('OLLAMA_TIMEOUT', 30);`

### Additional Rate Limiting
```php
// Example: Custom rate limiting
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    if (strpos($request->get_route(), '/ollama/v1/') === 0) {
        // Implement your rate limiting logic
    }
    return $result;
}, 10, 3);
```

## 🌐 Network Security

### SSH Tunnel Configuration (When External Access Disabled)
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

## 🚨 Security Best Practices

### For Site Administrators
1. **Keep external access disabled** unless absolutely necessary
2. **Use HTTPS** for all WordPress sites using WPOllama
3. **Restrict origins** if external access is enabled
4. **Monitor logs** for suspicious activity
5. **Keep rate limiting enabled** to prevent abuse
6. **Regular updates** of WordPress, WPOllama, and all plugins

### For Plugin Developers
1. **Never bypass** WPOllama security checks
2. **Use WordPress REST API** instead of direct Ollama access
3. **Register your plugin** with `ollamapress_register_service()`
4. **Use nonces** for all AJAX requests
5. **Never expose** Ollama endpoints directly to frontend JavaScript

## ✅ Security Checklist

- [ ] External access disabled (or properly configured if enabled)
- [ ] HTTPS enabled on WordPress site
- [ ] Rate limiting enabled
- [ ] Allowed origins configured (if external access enabled)
- [ ] Strong passwords for WordPress users
- [ ] Regular security updates applied
- [ ] Access logs monitored
- [ ] Model whitelist configured appropriately
- [ ] Server resources monitored

## 🔧 Configuration Examples

### Maximum Security Setup (Recommended)
```php
// wp-config.php
define('OLLAMA_URL', 'http://127.0.0.1:11434/api');
define('OLLAMA_TIMEOUT', 15);

// Admin Settings
// - External Access: DISABLED
// - Rate Limiting: ENABLED

// functions.php - Restrict models
add_filter('ollamapress_allowed_models', function() {
    return ['llama2']; // Single approved model
});
```

### Development Setup with External Access
```php
// wp-config.php
define('OLLAMA_URL', 'http://localhost:11434/api');
define('OLLAMA_TIMEOUT', 60);

// Admin Settings
// - External Access: ENABLED
// - Allowed Origins: http://localhost:3000
// - Rate Limiting: ENABLED
```

## 🆘 Incident Response

### If Compromised
1. **Immediately disable external access** in WPOllama settings
2. **Deactivate WPOllama** if necessary
3. Check WordPress access logs for suspicious activity
4. Kill SSH tunnels: `pkill -f "ssh.*11434"`
5. Review Ollama logs for unauthorized access
6. Update all passwords and API keys
7. Review and update security settings

### Monitoring Commands
```bash
# Check SSH tunnel status
ps aux | grep "ssh.*11434"

# Monitor WordPress REST API logs
tail -f /var/log/wordpress/access.log | grep "ollama/v1"

# Check Ollama process
ps aux | grep ollama

# Monitor rate limit transients (in WordPress)
wp transient list | grep ollama_rate_limit
```

## 📞 Support

For security issues:
1. **Do not report publicly**
2. Contact: security@carmelosantana.com
3. Include:
   - WordPress version
   - WPOllama version
   - PHP version
   - Security configuration status
   - Steps to reproduce

## 📝 Version History

- **v0.2.0**: Added external access control, rate limiting, origin restrictions
- **v0.1.4**: Added user authentication requirement
- **v0.1.3**: Implemented model whitelist system
- **v0.1.2**: Reduced default timeout to 30s

---

**Remember**: AI models can be powerful tools but require careful security consideration. Always err on the side of caution when exposing AI capabilities through web interfaces. Keep external access disabled unless absolutely necessary.