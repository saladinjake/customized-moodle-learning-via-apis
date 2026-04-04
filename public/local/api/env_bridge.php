<?php
/**
 * Headless OAuth2 Environment Bridge
 * 
 * Synchronizes core Moodle OAuth2 Issuers with values from the project .env file.
 * This ensures that for headless deployments, you only need to manage one configuration.
 */

function sync_oauth_from_env() {
    global $DB, $CFG;

    // 1. Simple .env parser (since we can't assume phpdotenv is installed)
    $env_path = $CFG->dirroot . '/.env';
    if (!file_exists($env_path)) return;

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value);
    }
    
    global $PROJECT_ENV;
    $PROJECT_ENV = $env;

    // 2. Provisioners for standard Headless providers
    $providers = [
        'GOOGLE'   => ['template' => 'google',   'name' => 'Google'],
        'FACEBOOK' => ['template' => 'facebook', 'name' => 'Facebook'],
        'LINKEDIN' => ['template' => 'linkedin', 'name' => 'LinkedIn'],
        'GITHUB'   => ['template' => 'github',   'name' => 'GitHub'],
    ];

    foreach ($providers as $key => $config) {
        $client_id     = $env["{$key}_CLIENT_ID"] ?? '';
        $client_secret = $env["{$key}_CLIENT_SECRET"] ?? '';

        // Only sync if both ID and Secret are provided and not placeholders
        if (!empty($client_id) && !empty($client_secret) && strpos($client_id, 'your-') === false) {
            
            // Check if issuer exists by name or template
            // Moodle doesn't store the template name in the issuer record, so we match on name.
            $issuer = $DB->get_record('oauth2_issuer', ['name' => $config['name']]);

            if (!$issuer) {
                // If it doesn't exist, create it using core Moodle factory
                try {
                    $issuer_obj = \core\oauth2\api::create_standard_issuer($config['template']);
                    $issuer_obj->set('name', $config['name']);
                    $issuer_obj->set('clientid', $client_id);
                    $issuer_obj->set('clientsecret', $client_secret);
                    $issuer_obj->set('enabled', 1);
                    $issuer_obj->set('showonloginpage', 1);
                    $issuer_obj->save();
                    error_log("Provisioned OAuth2 Issuer from .env: {$config['name']}");
                } catch (Exception $e) {
                    error_log("Failed to provision OAuth2 Issuer {$config['name']}: " . $e->getMessage());
                }
            } else {
                // If it exists, update it if values differ
                if ($issuer->clientid !== $client_id || $issuer->clientsecret !== $client_secret) {
                    $issuer->clientid = $client_id;
                    $issuer->clientsecret = $client_secret;
                    $issuer->enabled = 1;
                    $DB->update_record('oauth2_issuer', $issuer);
                    error_log("Updated OAuth2 Issuer from .env: {$config['name']}");
                }
            }
        }
    }
}

// Global execution on API bootstrap
sync_oauth_from_env();
