<?php
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

// ==========================================
// Constants & Configuration
// ==========================================

define("UUID_REGEX", "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i");
define('ROOT_DIR', __DIR__);
define('CONFIG_DIR', ROOT_DIR . '/config');
define('CONFIG_FILE', CONFIG_DIR . '/install.php');
define('METADATA_FILE', CONFIG_DIR . '/metadata.php');
define('ENTITY_DIR', ROOT_DIR . '/Entities');

// Load config if exists
if (file_exists(CONFIG_FILE)) {
    $loaded = include CONFIG_FILE;
    if (is_array($loaded)) {
        $GLOBALS['config'] = $loaded;
    }
}

// ==========================================
// Autoloading
// ==========================================

require 'vendor/autoload.php';

// Custom autoloader for Entities
spl_autoload_register(function ($class) {
    $baseNamespace = 'SepiaCore\\Entities\\';
    if (strpos($class, $baseNamespace) === 0) {
        $relativeClass = str_replace($baseNamespace, '', $class);
        $subfolder = strstr($relativeClass, '\\', true);
        $filePath = ROOT_DIR . '/Entities/';

        if ($subfolder) {
            $filePath .= $subfolder . '/' . str_replace('\\', '/', substr($relativeClass, strlen($subfolder))) . '.php';
        } else {
            $filePath .= str_replace('\\', '/', $relativeClass) . '.php';
        }

        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
});

// ==========================================
// Global Variables
// ==========================================

$GLOBALS['isAdmin'] = false;

// ==========================================
// Initialize
// ==========================================

loadMetadata();

// ==========================================
// Middleware
// ==========================================

// CORS headers for all requests
Flight::before('start', function(&$params, &$output) {
    setCorsHeaders();
});

// Handle OPTIONS preflight
Flight::route('OPTIONS /*', function() {
    setCorsHeaders();
    header("Content-Length: 0");
    header("HTTP/1.1 200 OK");
    exit();
});

// Database & Authentication
Flight::before('start', function() {
    initDatabase();
    if (!checkApiToken()) {
        Flight::halt(401, json_encode(["error" => "Unauthorized"]));
    }
});

// ==========================================
// Routes
// ==========================================

require_once ROOT_DIR . '/routes.php';

// ==========================================
// Helper Functions
// ==========================================

/**
 * Gets an entity class instance by model name
 * @param string $model Model name
 * @return object Entity instance
 */
function getEntityClass($model)
{
    return \SepiaCore\Controllers\BaseController::getEntityClass($model);
}

/**
 * Verifies API token from request and sets user context
 * @return bool True if authenticated or route is excluded
 */
function checkApiToken() {
    // Allow unauthenticated access to some routes
    if (isExcludedRoute([
        'POST /login',
        'GET /',
        'GET /openapi',
        'GET /ping',
        'POST /install',
        'GET /install/requirements',
        'POST /endpoint/*'
    ])) {
        return true;
    }

    // Use AuthController static methods for token extraction and verification
    $token = \SepiaCore\Controllers\AuthController::extractTokenFromRequest();
    $result = \SepiaCore\Controllers\AuthController::verifyToken($token);

    if (!$result['valid']) {
        return false;
    }

    // Set global user context
    $GLOBALS['user_id'] = $result['user']['id'];
    $GLOBALS['isAdmin'] = (bool) $result['user']['isadmin'];

    return true;
}

/**
 * Initializes database connection for non-excluded routes
 */
function initDatabase() {
    if (!isExcludedRoute([
        'GET /ping',
        'POST /install',
        'GET /install/requirements'
    ])) {
        try {
            doInitDB();
        } catch (Exception $e) {
            Flight::jsonHalt(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
        }
    }
}

/**
 * Initializes Eloquent database connection
 */
function doInitDB()
{
    $connection = $GLOBALS['config']['database'];
    if (strtolower($connection['driver'] ?? '') === 'sqlite') {
        $connection['database'] = ROOT_DIR . '/' . $connection['database'];
    }
    $capsule = new Capsule;
    $capsule->addConnection($connection);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
}

/**
 * Checks if current request matches any excluded route pattern
 * @param array $routes Array of route patterns (e.g., ['GET /ping', 'POST /login'])
 * @return bool True if route is excluded or method is OPTIONS
 */
function isExcludedRoute(array $routes): bool {
    $request = Flight::request();
    $method = $request->method;
    $path = $request->url;

    // Allow OPTIONS method for CORS
    if ($method === 'OPTIONS') {
        return true;
    }

    // Check if current request matches any allowed route
    foreach ($routes as $route) {
        [$routeMethod, $routePath] = explode(' ', $route, 2);

        // Skip if method doesn't match
        if ($method !== $routeMethod) {
            continue;
        }

        // Handle wildcard (*) safely
        // Escape everything, then replace escaped '*' with '.*' for regex match
        $pattern = '#^' . str_replace('\*', '.*', preg_quote($routePath, '#')) . '$#';

        if (preg_match($pattern, $path)) {
            return true;
        }
    }

    return false;
}

/**
 * Sets CORS headers based on configuration
 * Handles httpOnly cookies mode requiring specific origin (not wildcard)
 */
function setCorsHeaders() {
    $useHttpOnlyCookies = $GLOBALS['config']['use_httponly_cookies'] ?? false;
    $environment = $GLOBALS['config']['environment'] ?? 'dev';
    $allowedOrigins = $GLOBALS['config']['allowed_origins'] ?? [];
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
    
    if ($useHttpOnlyCookies) {
        // When httpOnly cookies are enabled, we need credentials support
        // This requires a specific origin (not *)
        if ($environment === 'prod') {
            // Production: Use whitelist from config
            if (!empty($allowedOrigins) && $requestOrigin && in_array($requestOrigin, $allowedOrigins)) {
                $allowedOrigin = $requestOrigin;
            } else {
                // Origin not in whitelist - deny (but allow if whitelist is empty for flexibility)
                $allowedOrigin = !empty($allowedOrigins) ? null : ($requestOrigin ?? '*');
            }
        } else {
            // Development: Echo back the origin that was sent
            $allowedOrigin = $requestOrigin ?? '*';
        }
        
        if ($allowedOrigin) {
            header("Access-Control-Allow-Origin: " . $allowedOrigin);
            header("Access-Control-Allow-Credentials: true");
        }
    } else {
        // Standard CORS without credentials
        header("Access-Control-Allow-Origin: *");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

/**
 * Checks if user is admin
 * @param int|null $id User ID (optional, defaults to current user)
 * @return bool True if user is admin
 */
function isAdmin($id = null) {
    if (!$id) {
        return $GLOBALS['isAdmin'];
    } else {
        // Load user and check admin flag
        try {
            $users = getEntityClass('Users');
            $user = $users->read($id);
            return $user && (bool)($user['isadmin'] ?? false);
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Sanitizes table and field names to lowercase alphanumeric with underscores
 * @param string $value Input value
 * @return string Sanitized value
 */
function tableAndFieldNameSanitation(string $value): string
{
    return preg_replace("/^[^a-z]+|[^a-z]+$/", "", preg_replace("/[^a-z_]/", "", $value));
}

/**
 * Loads metadata and settings from config files or uses defaults
 */
function loadMetadata() {
    $settingsFile = ROOT_DIR . '/config/settings.php';

    // Load settings
    if (file_exists($settingsFile)) {
        $GLOBALS['settings'] = require $settingsFile;
    } else {
        $GLOBALS['settings'] = [
            'datetime_format' => 'd.m.Y H:i:s',
        ];
    }

    // Check if we're on an installation route - skip metadata loading if file doesn't exist
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    // Remove query string for matching
    $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? $requestUri;
    $isInstallRoute = preg_match('#^/(ping|install(/requirements)?)(/|$)#', $requestPath);
    
    // Load metadata - always from file, no fallback
    if (file_exists(METADATA_FILE)) {
        $GLOBALS['metadata'] = require METADATA_FILE;
    } else {
        // Only allow missing metadata file during installation routes
        if ($isInstallRoute) {
            // Set empty metadata during installation
            $GLOBALS['metadata'] = [];
        } else {
            // For non-installation routes, metadata file must exist
            throw new Exception('Metadata file not found: ' . METADATA_FILE . '. Please run installation first.');
        }
    }

    if (isset($GLOBALS['config'])) {
        // Sanitize instance name to prevent XSS when used in HTML title
        $instance = $GLOBALS['config']['instance'] ?? 'Application';
        $instance = htmlspecialchars(strip_tags($instance), ENT_QUOTES, 'UTF-8');
        $instance = preg_replace('/[\x00-\x1F\x7F]/', '', $instance); // Remove control characters
        $instance = mb_substr(trim($instance), 0, 100); // Limit length
        $GLOBALS['metadata']['appTitle'] = $instance;
    }
}

/**
 * Saves metadata to config file
 * @param mixed $path Metadata path (unused, kept for compatibility)
 * @param mixed $value Metadata value (unused, kept for compatibility)
 * @throws Exception If metadata is not initialized or file write fails
 */
function saveMetadata($path, $value)
{
    // Will be moved to ModuleBuilder - keep code as reference

    if (!isset($GLOBALS['metadata']) || !is_array($GLOBALS['metadata'])) {
        throw new Exception("Metadata is not initialized properly.");
    }

    if (!file_exists(METADATA_FILE)) {
        if (!is_dir(CONFIG_DIR)) {
            if (mkdir(CONFIG_DIR, 0777, true) === false) {
                throw new Exception("/config directory does not exist and could not be created");
            }
        }
    }

    $content = "<?php\nreturn " . var_export($GLOBALS['metadata'], true) . ";\n";

    if (file_put_contents(METADATA_FILE, $content) === false) {
        throw new Exception("Failed to save metadata to file.");
    }
}


// ==========================================
// Start Application
// ==========================================

Flight::start();