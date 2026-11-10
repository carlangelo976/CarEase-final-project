
<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * LavaLust Framework Bootstrap
 */

require_once SYSTEM_DIR . 'kernel/Registry.php';
require_once SYSTEM_DIR . 'kernel/Routine.php';

/**
 * Load .env without overriding existing server variables.
 */
if (file_exists(ROOT_DIR . '.env')) {
    foreach (file(ROOT_DIR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }

        if (
            strlen($value) >= 2 &&
            in_array($value[0], ['"', "'"], true) &&
            $value[-1] === $value[0]
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Application base URL
 */
define('BASE_URL', config_item('base_url'));

/**
 * Composer autoload
 */
if ($composer_autoload = config_item('composer_autoload')) {
    if ($composer_autoload === TRUE) {
        file_exists(ROOT_DIR . 'vendor/autoload.php')
            ? require_once ROOT_DIR . 'vendor/autoload.php'
            : show_404('404 Not Found', 'Composer config file not found.');
    } elseif (file_exists($composer_autoload)) {
        require_once $composer_autoload;
    } else {
        show_404('404 Not Found', 'Composer config file not found.');
    }
}

/**
 * Benchmark
 */
$performance = load_class('performance', 'kernel');
$performance->start('lavalust');

/**
 * CLI mode
 */
define('IS_CLI', php_sapi_name() === 'cli');

/**
 * Environment and error handling
 */
switch (strtolower(config_item('environment'))) {
    case 'development':
        _handlers();
        error_reporting(-1);
        ini_set('display_errors', 1);
        break;

    case 'testing':
    case 'production':
        ini_set('display_errors', 0);
        error_reporting(0);
        _handlers();
        break;

    default:
        _handlers();
        error_reporting(-1);
        ini_set('display_errors', 1);
        break;
}

/**
 * Error handlers
 */
function _handlers()
{
    set_error_handler('_error_handler');
    set_exception_handler('_exception_handler');
    register_shutdown_function('_shutdown_handler');
}

/**
 * Load framework classes
 */
$config = load_class('config', 'kernel');
$logger = load_class('logger', 'kernel');
$security = load_class('security', 'kernel');
$io = load_class('io', 'kernel');
$request = load_class('request', 'kernel');
$response = load_class('response', 'kernel');
$lang = load_class('lang', 'kernel');

/**
 * Proxies
 */
if (config_item('proxy_enabled')) {
    require_once SYSTEM_DIR . 'kernel/Proxies.php';
}

/**
 * Base controller
 */
require_once SYSTEM_DIR . 'kernel/Controller.php';

/**
 * Router
 */
$router = load_class('router', 'kernel', array(new Controller));

lava_instance()->router = $router;

require_once APP_DIR . 'config/routes.php';

/**
 * Application kernel extensions
 */
if (is_dir(APP_DIR . 'kernel/')) {
    foreach (
        glob(
            APP_DIR . 'kernel/' .
            config_item('subclass_prefix') .
            '*.php'
        ) as $my_file
    ) {
        require_once $my_file;
    }
}

/**
 * Framework instance
 */
function lava_instance()
{
    return Controller::instance();
}

$performance->stop('lavalust');

/**
 * Resolve requested route
 */
if (IS_CLI) {
    $raw_url = $argv[1] ?? '';
    $raw_url = ltrim($raw_url, '/');

    $url = $router->sanitize_url($raw_url);

    if (empty($url)) {
        $url = '/';
    } else {
        $url = '/' . ltrim($url, '/');
    }

    $method = 'GET';

} else {
    /*
     * Support Apache rewrites and Render deployment.
     * The query parameter is supplied by public/.htaccess.
     */
    $raw_url = $_GET['carease_route']
        ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    $raw_url = (string) $raw_url;

    // Remove index.php prefix when present.
    $raw_url = preg_replace(
        '#^/index\.php(?=/|$)#',
        '',
        $raw_url
    );

    $url = $router->sanitize_url($raw_url);

    if (empty($url)) {
        $url = '/';
    } else {
        $url = '/' . ltrim($url, '/');
    }

    $method = strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );
}

$router->initiate($url, $method);
