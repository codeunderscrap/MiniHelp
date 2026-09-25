<?php
// config/mmos.php — MM OS integration configuration

class MMOSConfig {
    public string $os_url;
    public string $service_slug;
    public string $service_key;
    public string $issuer;
    public string $cookie_name;
    public string $environment;

    public function __construct() {
        $this->os_url       = getenv('MMOS_OS_URL')       ?: 'https://m-mines.in';
        $this->service_slug = getenv('MMOS_SERVICE_SLUG') ?: 'servicedesk';
        $this->service_key  = getenv('MMOS_SERVICE_KEY')  ?: '';
        $this->issuer       = getenv('MMOS_ISSUER')       ?: 'https://m-mines.in';
        $this->environment  = getenv('MMOS_ENVIRONMENT')  ?: 'production';
        $this->cookie_name  = $this->service_slug . '_mmos_at';
    }

    public function jwks_url(): string {
        return rtrim($this->os_url, '/') . '/.well-known/jwks.json';
    }

    public function heartbeat_url(): string {
        return rtrim($this->os_url, '/') . '/api/agent/heartbeat';
    }
}

function mmos_config(): MMOSConfig {
    static $instance = null;
    if ($instance === null) {
        $instance = new MMOSConfig();
    }
    return $instance;
}
