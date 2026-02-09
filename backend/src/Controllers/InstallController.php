<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use PDO;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class InstallController extends BaseController
{
    /**
     * Pings endpoint to check installation status.
     * @return void
     */
    public function ping(): void
    {
        $isInstalled = file_exists(CONFIG_FILE);
        $this->jsonHalt(['ping' => true, 'isInstalled' => $isInstalled]);
    }

    /**
     * Checks installation requirements.
     * @return void
     */
    public function checkRequirements(): void
    {
        $requirements = [
            'php' => [
                'installed' => true,
                'version' => PHP_VERSION,
                'required' => '8.3+',
                'meetsRequirement' => version_compare(PHP_VERSION, '8.3.0', '>=')
            ],
            'extensions' => [
                'pdo' => [
                    'installed' => extension_loaded('pdo'),
                    'required' => true
                ],
                'json' => [
                    'installed' => extension_loaded('json'),
                    'required' => true
                ],
                'mbstring' => [
                    'installed' => extension_loaded('mbstring'),
                    'required' => true
                ],
                'curl' => [
                    'installed' => extension_loaded('curl'),
                    'required' => true
                ]
            ],
            'permissions' => [
                'configDir' => [
                    'writable' => $this->checkConfigDirWritable(),
                    'required' => true
                ],
                'entityDir' => [
                    'writable' => $this->checkEntityDirWritable(),
                    'required' => false
                ]
            ]
        ];

        // Check if all requirements are met
        $allMet = $requirements['php']['meetsRequirement']
            && $requirements['extensions']['pdo']['installed']
            && $requirements['extensions']['json']['installed']
            && $requirements['extensions']['mbstring']['installed']
            && $requirements['extensions']['curl']['installed']
            && $requirements['permissions']['configDir']['writable'];

        $this->jsonResponse([
            'success' => $allMet,
            'requirements' => $requirements
        ]);
    }

    /**
     * Checks if config directory is writable.
     * @return bool True if writable
     */
    private function checkConfigDirWritable(): bool
    {
        // Check if directory exists and is writable
        if (is_dir(CONFIG_DIR)) {
            return is_writable(CONFIG_DIR);
        }

        // Check if parent directory is writable (so we can create config dir)
        $parentDir = dirname(CONFIG_DIR);
        return is_writable($parentDir);
    }
    /**
     * Checks if entity directory is writable.
     * @return bool True if writable
     */
    private function checkEntityDirWritable(): bool
    {
        // Check if directory exists and is writable
        if (is_dir(ENTITY_DIR)) {
            return is_writable(ENTITY_DIR);
        }

        // Check if parent directory is writable (so we can create entity dir)
        $parentDir = dirname(ENTITY_DIR);
        return is_writable($parentDir);
    }

    /**
     * Handles application installation.
     * @return void
     */
    public function install(): void
    {
        if (file_exists(CONFIG_FILE)) {
            $this->jsonHalt(['error' => 'Installation is locked, remove install.php to run installation again'], 423);
        }

        $data = json_decode(Flight::request()->getBody(), true);
        
        // Extract and validate required fields
        $dbName = $data['dbName'] ?? null;
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $instancename = $data['instancename'] ?? null;
        $dbType = $data['dbType'] ?? null;
        $environment = !empty($data['environment']) ? strtolower($data['environment']) : 'dev';
        $allowedOrigins = $data['allowedOrigins'] ?? [];
        
        // Validate environment value
        if (!in_array($environment, ['dev', 'prod', 'production'])) {
            $environment = 'dev';
        }
        if ($environment === 'production') {
            $environment = 'prod';
        }

        // Validate and extract required fields
        $requiredFields = ['dbName', 'username', 'password', 'instancename', 'dbType', 'environment'];
        $missingFields = [];
        if ($data['dbType'] != 'sqlite') {
            $requiredFields = array_merge($requiredFields, ['dbHost', 'dbPort', 'dbUser']);
            $dbHost = $data['dbHost'] ?? null;
            $dbPort = $data['dbPort'] ?? null;
            $dbUser = $data['dbUser'] ?? null;
            $dbPass = $data['dbPass'] ?? '';
        } else {
            $data['dbHost'] = $data['dbPort'] = $data['dbUser'] = '';
            $dbHost = '';
            $dbPort = '';
            $dbUser = '';
            $dbPass = '';
        }

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $this->jsonHalt([
                'error' => 'Missing required fields: ' . implode(', ', $missingFields),
            ], 400);
        }
        
        // Sanitize instance name to prevent XSS
        $instancename = $this->sanitizeInstanceName($instancename);
        if (empty($instancename)) {
            $this->jsonHalt(['error' => 'Instance name is required and cannot be empty after sanitization'], 400);
        }
        
        if (!$this->hasValidConnectionParams($dbType, $dbHost, $dbPort, $dbName, $dbUser)) {
            $this->jsonHalt(['error' => 'Database configuration is incomplete'], 400);
        }
        switch (strtolower($dbType)) {
            case 'mysql':
                $connection = [
                    'driver' => 'mysql',
                    'host' => $dbHost ?? '127.0.0.1',
                    'port' => $dbPort ?? '3306',
                    'database' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPass ?? '',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => false,
                ];
                break;

            case 'sqlite':
                $dbName = $dbName . '.db';
                $connection = [
                    'driver' => 'sqlite',
                    'database' => $dbName,
                    'prefix' => '',
                ];
                break;

            case 'pgsql':
                $connection = [
                    'driver' => 'pgsql',
                    'host' => $dbHost ?? '127.0.0.1',
                    'port' => $dbPort ?? '5432',
                    'database' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPass ?? '',
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ];
                break;

            case 'mssql':
                $connection = [
                    'driver' => 'sqlsrv',
                    'host' => $dbHost ?? 'localhost',
                    'port' => $dbPort ?? '1433',
                    'database' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPass ?? '',
                    'charset' => 'utf8',
                    'prefix' => '',
                ];
                break;

            default:
                $this->jsonHalt([
                    'error' => "Unsupported database type: {$dbType}"
                ], 400);
        }


        if (!$this->isValidDbName($dbName)) {
            $this->jsonHalt([
                'error' => 'Database Name is not valid: Database name must be 1-63 characters long, must not end with a space or a dot, must not use reserved Windows names (e.g., PRN, AUX, CLOCK$, NUL, CON, COM1-COM9, LPT1-LPT9), and must not contain invalid characters like \ / : * ? " < > | or control characters.'
            ], 400);
        }
        if ($this->doesDatabaseExistNative($connection)) {
            $this->jsonHalt([
                'error' => 'Database already exists. Please choose a different database name.'
            ], 409);
        }

        try {
            $this->createDatabaseNative($connection);
            $this->hydrateDatabase($username, $password, $connection);
            $this->writeConfigToFile($connection, $instancename, $environment, $allowedOrigins);
            $this->writeMetadataFile($instancename);
            $this->jsonResponse(['success' => true, 'message' => 'Installation complete']);
        } catch (Exception $e) {
            if (file_exists(CONFIG_FILE)) {
                unlink(CONFIG_FILE);
            }
            if (file_exists(METADATA_FILE)) {
                unlink(METADATA_FILE);
            }
            $this->jsonHalt(['error' => 'Installation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ensures config directory exists and is writable.
     * @return void
     */
    private function ensureConfigDirectory(): void
    {
        if (!is_dir(CONFIG_DIR)) {
            if (!mkdir(CONFIG_DIR, 0755, true)) {
                $this->jsonHalt(['error' => 'Failed to create config directory'], 500);
            }
        }

        if (!is_writable(CONFIG_DIR)) {
            $this->jsonHalt(['error' => 'Config directory is not writable'], 500);
        }
    }

    /**
     * Creates empty config file.
     * @return bool True if file was created, false if already exists
     */
    private function createConfigFile(): bool
    {
        if (!file_exists(CONFIG_FILE)) {
            try {
                file_put_contents(CONFIG_FILE, '');
                return true;
            } catch (Exception $e) {
                $this->jsonHalt(['error' => 'Failed to create config file'], 500);
            }
        }

        if (!is_writable(CONFIG_FILE)) {
            $this->jsonHalt(['error' => 'Config file is not writable'], 500);
        }

        return false;
    }

    /**
     * Creates database tables and initial admin user.
     * @param string $username Admin username
     * @param string $password Admin password
     * @param array $connection Database connection config
     * @return void
     */
    private function hydrateDatabase($username, $password, $connection): void
    {
        // --- 1. Initialize Capsule & DB connection dynamically ---

        $GLOBALS['config']['database'] = $connection;
        doInitDB();

        // --- 2. Create core tables using Capsule schema builder ---

        // Users table
        if (!Capsule::schema()->hasTable('users')) {
            Capsule::schema()->create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->timestamp('date_created')->nullable();
                $table->timestamp('date_modified')->nullable();
                $table->string('password_hash');
                $table->boolean('isadmin')->default(0);
            });
        }

        // Tokens table
        if (!Capsule::schema()->hasTable('tokens')) {
            Capsule::schema()->create('tokens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('token');
                $table->string('status')->default('active'); // active or inactive
                $table->timestamp('expires_at')->nullable();
                $table->string('owner')->nullable();
                $table->timestamp('date_created')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('date_modified')->default(Capsule::raw('CURRENT_TIMESTAMP'));

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // Login attempts table for rate limiting
        if (!Capsule::schema()->hasTable('login_attempts')) {
            Capsule::schema()->create('login_attempts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ip_address', 45)->index();
                $table->string('username')->nullable();
                $table->integer('attempts')->default(1);
                $table->timestamp('first_attempt')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('last_attempt')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('blocked_until')->nullable()->index();
                $table->index(['ip_address', 'username'], 'idx_ip_username');
            });
        }

        // Endpoints data table
        if (!Capsule::schema()->hasTable('endpoints')) {
            Capsule::schema()->create('endpoints', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->index();
                $table->string('owner')->nullable();
                $table->string('status')->nullable();
                $table->string('token', 64)->unique()->nullable()->index();
                $table->timestamp('date_created')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('date_modified')->default(Capsule::raw('CURRENT_TIMESTAMP'));
            });
        }

        // Raw endpoint data table
        if (!Capsule::schema()->hasTable('rawendpointdata')) {
            Capsule::schema()->create('rawendpointdata', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('endpoint_name')->index();
                $table->uuid('endpoint_id')->nullable();
                $table->string('owner')->nullable();
                $table->longText('request_body')->nullable();
                $table->string('status')->nullable();

                $table->timestamp('date_created')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('date_modified')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->foreign('endpoint_id')->references('id')->on('endpoints')->onDelete('cascade');

            });
        }

        // Dashboards table
        if (!Capsule::schema()->hasTable('dashboards')) {
            Capsule::schema()->create('dashboards', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->uuid('owner')->nullable()->index();
                $table->boolean('is_default')->default(0)->index();
                $table->longText('widgets')->nullable();
                $table->timestamp('date_created')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('date_modified')->default(Capsule::raw('CURRENT_TIMESTAMP'));

                $table->foreign('owner')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // Saved filters table
        if (!Capsule::schema()->hasTable('saved_filters')) {
            Capsule::schema()->create('saved_filters', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->index();
                $table->string('entity')->index();
                $table->longText('definition')->nullable();
                $table->longText('description')->nullable();
                $table->string('color')->nullable();
                $table->longText('tags')->nullable();
                $table->boolean('is_shared')->default(0);
                $table->uuid('owner')->nullable()->index();
                $table->timestamp('date_created')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('date_modified')->default(Capsule::raw('CURRENT_TIMESTAMP'));

                $table->foreign('owner')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // --- 3. Insert admin user via your Entity class ---
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $users = $this->getEntityClass('users');
        $users->create([
            'name' => $username,
            'password_hash' => $hashedPassword,
            'isadmin' => 1
        ]);
    }

    /**
     * Creates database using native PDO connection.
     * @param array $connection Database connection config
     * @return void
     */
    private function createDatabaseNative(array $connection): void
    {
        switch (strtolower($connection['driver'])) {
            case 'mysql':
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;charset=%s',
                        $connection['host'],
                        $connection['port'] ?? '3306',
                        $connection['charset'] ?? 'utf8mb4'
                    );

                    $pdo = new PDO($dsn, $connection['username'], $connection['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = sprintf(
                        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                        $connection['database'],
                        $connection['charset'] ?? 'utf8mb4',
                        $connection['collation'] ?? 'utf8mb4_unicode_ci'
                    );

                    $pdo->exec($sql);
                } catch (Exception $e) {
                    $this->jsonHalt(['error' => 'MySQL database creation failed: ' . $e->getMessage()], 500);
                }
                break;

            case 'pgsql':
                try {
                    // Connect to PostgreSQL default 'postgres' database
                    $dsn = sprintf(
                        'pgsql:host=%s;port=%s;dbname=postgres',
                        $connection['host'],
                        $connection['port'] ?? '5432'
                    );

                    $pdo = new PDO($dsn, $connection['username'], $connection['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // PostgreSQL doesn't support IF NOT EXISTS in CREATE DATABASE before 9.1
                    $sql = sprintf(
                        'SELECT 1 FROM pg_database WHERE datname = :db'
                    );
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['db' => $connection['database']]);
                    if (!$stmt->fetchColumn()) {
                        $sqlCreate = sprintf(
                            'CREATE DATABASE "%s"',
                            $connection['database']
                        );
                        $pdo->exec($sqlCreate);
                    }
                } catch (Exception $e) {
                    $this->jsonHalt(['error' => 'PostgreSQL database creation failed: ' . $e->getMessage()], 500);
                }
                break;

            case 'sqlsrv':
                try {
                    // Connect to SQL Server without specifying a database
                    $dsn = sprintf(
                        'sqlsrv:Server=%s%s',
                        $connection['host'],
                        !empty($connection['port']) ? ',' . $connection['port'] : ''
                    );

                    $pdo = new PDO($dsn, $connection['username'], $connection['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Check if the database exists
                    $stmt = $pdo->prepare('SELECT name FROM sys.databases WHERE name = :db');
                    $stmt->execute(['db' => $connection['database']]);
                    if (!$stmt->fetchColumn()) {
                        $sqlCreate = sprintf(
                            'CREATE DATABASE [%s]',
                            $connection['database']
                        );
                        $pdo->exec($sqlCreate);
                    }
                } catch (Exception $e) {
                    $this->jsonHalt(['error' => 'SQL Server database creation failed: ' . $e->getMessage()], 500);
                }
                break;

            case 'sqlite':
                $dbPath = ROOT_DIR . '/' . $connection['database'];
                if (!file_exists($dbPath)) {
                    touch($dbPath);
                    chmod($dbPath, 0666);
                }
                break;

            default:
                $this->jsonHalt(['error' => 'Unsupported database driver: ' . $connection['driver']], 400);
        }
    }

    /**
     * Checks if database exists using native PDO connection.
     * @param array $connection Database connection config
     * @return bool True if database exists
     */
    private function doesDatabaseExistNative(array $connection): bool
    {
        switch (strtolower($connection['driver'])) {
            case 'mysql':
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;charset=%s',
                        $connection['host'],
                        $connection['port'] ?? '3306',
                        $connection['charset'] ?? 'utf8mb4'
                    );

                    $pdo = new PDO($dsn, $connection['username'], $connection['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :db');
                    $stmt->execute(['db' => $connection['database']]);
                    return (bool)$stmt->fetchColumn();
                } catch (Exception $e) {
                    $this->jsonHalt(['error' => 'MySQL database existence check failed: ' . $e->getMessage()], 400);
                }

            case 'pgsql':
                try {
                    // Connect to PostgreSQL to the default 'postgres' database
                    $dsn = sprintf(
                        'pgsql:host=%s;port=%s;dbname=postgres',
                        $connection['host'],
                        $connection['port'] ?? '5432'
                    );

                    $pdo = new PDO($dsn, $connection['username'], $connection['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Check if the database exists
                    $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :db');
                    $stmt->execute(['db' => $connection['database']]);
                    return (bool)$stmt->fetchColumn();
                } catch (Exception $e) {
                    $this->jsonHalt(['error' => 'PostgreSQL database existence check failed: ' . $e->getMessage()], 400);
                }

            case 'sqlsrv':
                try {
                    // Connect to SQL Server without specifying a database
                    $dsn = sprintf(
                        'sqlsrv:Server=%s%s',
                        $connection['host'],
                        !empty($connection['port']) ? ',' . $connection['port'] : ''
                    );

                    $pdo = new PDO($dsn, $connection['username'], $connection['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Check if the database exists
                    $stmt = $pdo->prepare('SELECT name FROM sys.databases WHERE name = :db');
                    $stmt->execute(['db' => $connection['database']]);
                    return (bool)$stmt->fetchColumn();
                } catch (Exception $e) {
                    $this->jsonHalt(['error' => 'SQL Server database existence check failed: ' . $e->getMessage()], 400);
                }

            case 'sqlite':
                $dbPath = ROOT_DIR . '/' . $connection['database'];
                return file_exists($dbPath);

            default:
                $this->jsonHalt(['error' => 'Unsupported database driver: ' . $connection['driver']], 400);
        }

        return true; // unreachable, but stops code analyzer warnings
    }


    /**
     * Validates database name format.
     * @param string $filename Database name
     * @return bool True if valid
     */
    private function isValidDbName(string $filename): bool
    {
        $length = strlen($filename);
        if ($length === 0 || $length > 63) {
            return false;
        }

        if (substr($filename, -1) === ' ' || substr($filename, -1) === '.') {
            return false;
        }

        if (preg_match('/^(PRN|AUX|CLOCK\$|NUL|CON|COM[1-9]|LPT[1-9])(\..*)?$/i', $filename)) {
            return false;
        }

        if (preg_match('/[^\x20-\x7E]|[\\\\\/:*?"<>|]/', $filename)) {
            return false;
        }

        return true;
    }

    /**
     * Validates database connection parameters.
     * @param string $dbType Database type
     * @param string $dbHost Database host
     * @param string $dbPort Database port
     * @param string $dbName Database name
     * @param string $dbUser Database user
     * @return bool True if valid
     */
    private function hasValidConnectionParams($dbType, $dbHost, $dbPort, $dbName, $dbUser): bool
    {
        $dbType = strtolower($dbType);
        switch ($dbType) {
            case 'mysql':
            case 'pgsql':
            case 'mssql':
                return !empty($dbHost)
                    && !empty($dbPort)
                    && !empty($dbName)
                    && !empty($dbUser);

            case 'sqlite':
                return !empty($dbName);
            default:
                return false;
        }
    }


    /**
     * Sanitizes instance name to prevent XSS attacks.
     * @param string $instancename Instance name
     * @return string Sanitized instance name
     */
    private function sanitizeInstanceName(string $instancename): string
    {
        // Trim whitespace
        $instancename = trim($instancename);
        
        // Remove HTML tags
        $instancename = strip_tags($instancename);
        
        // Remove null bytes and control characters
        $instancename = preg_replace('/[\x00-\x1F\x7F]/', '', $instancename);
        
        // Limit length (reasonable limit for instance name)
        $instancename = mb_substr($instancename, 0, 100);
        
        // Remove any remaining potentially dangerous characters
        // Allow alphanumeric, spaces, hyphens, underscores, and common punctuation
        $instancename = preg_replace('/[^a-zA-Z0-9\s\-_.,!?()]/', '', $instancename);
        
        // Trim again after cleaning
        $instancename = trim($instancename);
        
        return $instancename;
    }

    /**
     * Writes configuration to file.
     * @param array $connection Database connection config
     * @param string $instancename Instance name
     * @param string $environment Environment (dev/prod)
     * @param array $allowedOrigins Allowed CORS origins
     * @return void
     * @throws Exception If file write fails
     */
    private function writeConfigToFile(array $connection, string $instancename, string $environment = 'dev', array $allowedOrigins = []): void
    {
        // Ensure the config directory exists
        if (!is_dir(CONFIG_DIR)) {
            if (!mkdir(CONFIG_DIR, 0755, true)) {
                throw new Exception('Failed to create config directory: ' . CONFIG_DIR);
            }
        }

        // Ensure the config directory is writable
        if (!is_writable(CONFIG_DIR)) {
            throw new Exception('Config directory is not writable: ' . CONFIG_DIR);
        }

        // Sanitize instance name again before writing (defense in depth)
        $instancename = $this->sanitizeInstanceName($instancename);
        
        // Validate environment
        $environment = strtolower($environment);
        if (!in_array($environment, ['dev', 'prod', 'production'])) {
            $environment = 'dev';
        }
        if ($environment === 'production') {
            $environment = 'prod';
        }
        
        // Sanitize allowed origins (only in production)
        $sanitizedOrigins = [];
        if ($environment === 'prod' && !empty($allowedOrigins)) {
            foreach ($allowedOrigins as $origin) {
                $origin = trim($origin);
                if (!empty($origin)) {
                    // Basic URL validation
                    if (filter_var($origin, FILTER_VALIDATE_URL) || preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+(:\d+)?$/', $origin)) {
                        $sanitizedOrigins[] = $origin;
                    }
                }
            }
        }

        // Prepare the config array
        $configArray = [
            'database' => $connection,
            'instance' => $instancename,
            'environment' => $environment, // 'dev' or 'prod'
            'allowed_origins' => $sanitizedOrigins, // Whitelisted origins for production CORS
            'token_expiration' => 86400, // 24 hours in seconds
            'allow_query_token' => false, // Disable query string tokens by default for security
            'use_httponly_cookies' => ($environment === 'prod'), // Use httpOnly cookies for tokens in production with HTTPS
            'rate_limit_max_attempts' => 5,        // Max login attempts per window
            'rate_limit_time_window' => 900,        // 15 minutes in seconds
            'rate_limit_block_duration' => 1800     // 30 minutes block duration in seconds
        ];

        // Write the config file
        $bytesWritten = file_put_contents(CONFIG_FILE, '<?php return ' . var_export($configArray, true) . ';');
        if ($bytesWritten === false) {
            throw new Exception("Failed to write config file: " . CONFIG_FILE);
        }
    }

    /**
     * Writes initial metadata file with default structure.
     * @param string $instancename Instance name
     * @return void
     * @throws Exception If file write fails
     */
    private function writeMetadataFile(string $instancename): void
    {
        // Ensure the config directory exists
        if (!is_dir(CONFIG_DIR)) {
            if (!mkdir(CONFIG_DIR, 0755, true)) {
                throw new Exception('Failed to create config directory: ' . CONFIG_DIR);
            }
        }

        // Ensure the config directory is writable
        if (!is_writable(CONFIG_DIR)) {
            throw new Exception('Config directory is not writable: ' . CONFIG_DIR);
        }

        // Sanitize instance name
        $instancename = $this->sanitizeInstanceName($instancename);
        $appTitle = !empty($instancename) ? $instancename : 'Application';

        // Prepare the initial metadata structure with field definitions
        $metadataArray = [
            'entities' => [
                'users' => [
                    'fields' => [
                        'id' => ['type' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'date_created' => ['type' => 'datetime', 'readonly' => true],
                        'date_modified' => ['type' => 'datetime', 'readonly' => true],
                        'password_hash' => ['type' => 'string'],
                        'isadmin' => ['type' => 'boolean'],
                    ],
                    'capabilities' => [
                        'action-console' =>
                            [
                                'active' => 'true',
                                'requires_admin' => true,
                            ],
                        'list-filter-suggestions' =>
                            [
                                'active' => 'true',
                                'requires_admin' => true,
                            ],
                    ]
                ],
                'tokens' => [
                    'fields' => [
                        'id' => ['type' => 'uuid'],
                        'user_id' => ['type' => 'relationship', 'entity' => 'users'],
                        'token' => ['type' => 'string'],
                        'status' => [
                            'type' => 'select',
                            'options' => [
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ],
                        ],
                        'expires_at' => ['type' => 'datetime'],
                        'owner' => ['type' => 'relationship', 'entity' => 'users'],
                        'date_created' => ['type' => 'datetime', 'readonly' => true],
                        'date_modified' => ['type' => 'datetime', 'readonly' => true],
                    ],
                    'capabilities' => [
                        'action-console' =>
                            [
                                'active' => 'false',
                                'requires_admin' => true,
                            ],
                        'list-filter-suggestions' =>
                            [
                                'active' => 'false',
                                'requires_admin' => true,
                            ],
                    ]

                ],
                'endpoints' => [
                    'fields' => [
                        'id' => ['type' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'owner' => ['type' => 'relationship', 'entity' => 'users'],
                        'status' => [
                            'type' => 'select',
                            'options' => [
                                'active' => 'Active',
                                'inactive' => 'Inactive'
                            ],
                        ],
                        'token' => ['type' => 'string'],
                        'date_created' => ['type' => 'datetime', 'readonly' => true],
                        'date_modified' => ['type' => 'datetime', 'readonly' => true],
                    ],
                    'module_views' => [
                        'record' => [
                            'layout' => [
                                ['name', 'owner'],
                                ['status'],
                                ['date_created', 'date_modified'],
                            ],
                        ],
                        'list' => [
                            'isdefault' => true,
                            'layout' => [
                                'name',
                                'status',
                                'owner',
                                'date_created',
                                'date_modified',
                            ],
                        ],
                        'subpanels' => [],
                    ],
                    'capabilities' => [
                        'action-console' =>
                            [
                                'active' => 'true',
                                'requires_admin' => true,
                            ],
                        'list-filter-suggestions' =>
                            [
                                'active' => 'true',
                                'requires_admin' => true,
                            ],
                    ]
                ],
                'rawendpointdata' => [
                    'fields' => [
                        'id' => ['type' => 'uuid'],
                        'endpoint_name' => ['type' => 'string'],
                        'endpoint_id' => ['type' => 'relationship', 'entity' => 'endpoints'],
                        'owner' => ['type' => 'relationship', 'entity' => 'users'],
                        'request_body' => ['type' => 'textarea'],
                        'status' => [
                            'type' => 'select',
                            'options' => [
                                'pending' => 'Pending',
                                'processed' => 'Processed',
                                'failed' => 'Failed',
                            ],
                        ],
                        'date_created' => ['type' => 'datetime', 'readonly' => true],
                        'date_modified' => ['type' => 'datetime', 'readonly' => true],
                    ],
                    'capabilities' => [
                        'action-console' =>
                            [
                                'active' => 'false',
                                'requires_admin' => true,
                            ],
                        'list-filter-suggestions' =>
                            [
                                'active' => 'false',
                                'requires_admin' => true,
                            ],
                    ]
                ],
                'dashboards' => [
                    'fields' => [
                        'id' => ['type' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'owner' => ['type' => 'relationship', 'entity' => 'users'],
                        'is_default' => ['type' => 'boolean'],
                        'widgets' => ['type' => 'collection'],
                        'date_created' => ['type' => 'datetime', 'readonly' => true],
                        'date_modified' => ['type' => 'datetime', 'readonly' => true],
                    ],
                    'capabilities' => [
                        'action-console' =>
                            [
                                'active' => 'false',
                                'requires_admin' => false,
                            ],
                        'list-filter-suggestions' =>
                            [
                                'active' => 'false',
                                'requires_admin' => false,
                            ],
                    ]
                ],
                'saved_filters' => [
                    'fields' => [
                        'id' => ['type' => 'uuid'],
                        'name' => ['type' => 'string'],
                        'entity' => ['type' => 'string'],
                        'definition' => ['type' => 'text'],
                        'description' => ['type' => 'textarea'],
                        'color' => ['type' => 'string'],
                        'tags' => ['type' => 'collection'],
                        'is_shared' => ['type' => 'boolean'],
                        'owner' => ['type' => 'relationship', 'entity' => 'users'],
                        'date_created' => ['type' => 'datetime', 'readonly' => true],
                        'date_modified' => ['type' => 'datetime', 'readonly' => true],
                    ],
                    'module_views' => [
                        'list' => [
                            'isdefault' => true,
                            'layout' => [
                                'name',
                                'entity',
                                'owner',
                                'date_modified',
                            ],
                        ],
                    ],
                    'capabilities' => [
                        'action-console' =>
                            [
                                'active' => 'false',
                                'requires_admin' => false,
                            ],
                        'list-filter-suggestions' =>
                            [
                                'active' => 'false',
                                'requires_admin' => false,
                            ],
                    ]
                ],
                'modulebuilder' => [
                    'module_views' => [
                        'mbstudio' => ['isdefault' => 'true'],
                    ],
                    'capabilities' => [
                        'action-console' =>
                            [
                                'active' => 'false',
                                'requires_admin' => true,
                            ],
                        'list-filter-suggestions' =>
                            [
                                'active' => 'false',
                                'requires_admin' => true,
                            ],
                    ]
                ],
            ],
            'navigation_entities' => [],
            'backendview_entities' => ['endpoints'],
            'relationships' => [],
            'languageStrings' => [
                'en_us' => [
                    'entityStrings' => [
                        'settings' => [
                            'LBL_MODULE_NAME' => 'Settings',
                            'LBL_DATETIME_FORMAT' => 'Date Time Format',
                            'LBL_DATETIME_FORMAT_DESCRIPTION' => 'Format the display of datetime fields according to a PHP format string. For example (\'Y-m-d H:i:s\')',
                        ]
                    ]
                ]
            ],
            'appTitle' => $appTitle,
        ];

        // Write the metadata file
        $bytesWritten = file_put_contents(METADATA_FILE, '<?php return ' . var_export($metadataArray, true) . ';');
        if ($bytesWritten === false) {
            throw new Exception("Failed to write metadata file: " . METADATA_FILE);
        }
    }
}
