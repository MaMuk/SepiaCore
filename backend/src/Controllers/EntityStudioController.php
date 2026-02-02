<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use SepiaCore\Entities\BaseEntity;
use SepiaCore\Utilities\Log;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

class EntityStudioController extends BaseController
{
    private array $newNavigationEntities;
    private Environment $twig;

    /**
     * Initializes controller.
     */
    public function __construct()
    {
        parent::__construct();
        $this->initTwig();
    }

    /**
     * Checks admin authorization.
     * @return void
     */
    private function checkAdmin(): void
    {
        if (!isAdmin()) {
            $this->jsonHalt(['error' => 'Unauthorized.'], 401);
        }
    }

    /**
     * Initializes Twig environment for rendering builder UI.
     */
    private function initTwig(): void
    {
        $loader = new FilesystemLoader(ROOT_DIR . '/Entities/ModuleBuilder/MBTemplates/');
        $environment = $GLOBALS['config']['environment'] ?? 'dev';
        $isDebug = ($environment === 'dev');
        
        $this->twig = new Environment($loader,
            [
                'debug' => $isDebug,
            ]);
        $this->twig->addGlobal('metadata', $GLOBALS['metadata']);
        $this->twig->addGlobal('settings', $GLOBALS['settings']);
        $this->twig->addGlobal('isAdmin', $GLOBALS['isAdmin']);
        
        if ($isDebug) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    /**
     * Gets entity studio UI.
     * @return void
     */
    public function index(): void
    {
        $this->checkAdmin();

        $return = [
            'innerHtml' => $this->renderBuilderUi(),
            'script' => $this->renderBuilderUiScript()
        ];

        $this->jsonResponse($return);
    }

    /**
     * Handles entity studio actions.
     * @param string $action Action name
     * @return void
     */
    public function action($action): void
    {
        $this->checkAdmin();

        $requestData = json_decode(Flight::request()->getBody(), true);

        try {
            switch ($action) {
                case 'updateFields':
                    $this->updateFields($requestData['entity'], $requestData['fieldDefs']);
                    break;

                case 'updateView':
                    $this->updateView($requestData);
                    break;

                case 'newEntity':
                    $this->createEntity($requestData);
                    break;

                case 'newRelationship':
                    $this->createRelationship($requestData);
                    break;

                case 'deleteEntity':
                    $result = $this->deleteEntity($requestData['entity']);
                    if (!empty($result['errors'])) {
                        $this->jsonResponse($result, 500);
                        return;
                    }
                    break;

                case 'deleteRelationship':
                    $this->deleteRelationship($requestData['relationship']);
                    break;

                case 'setNavigationEntities':
                    $this->setNavigationEntities($requestData);
                    break;

                case 'resetInstance':
                    $this->resetInstance($requestData);
                    break;

                case 'updateIcon':
                    $this->updateIcon($requestData['entity'], $requestData['icon']);
                    break;

                default:
                    throw new Exception('Unknown action: ' . $action);
            }

            $response = [
                'message' => $action . ' was successful',
                'metadata' => $GLOBALS['metadata'] ?? null,
            ];
            if (isset($result)) {
                $response = array_merge($response, $result);
            }
            $this->jsonHalt($response);
        } catch (Exception $e) {
            $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Renders builder UI HTML.
     * @return string
     */
    public function renderBuilderUi(): string
    {
        return $this->twig->render('index.html.twig', []);
    }

    /**
     * Renders builder UI script.
     * @return string
     */
    public function renderBuilderUiScript(): string
    {
        return $this->twig->render('script.js.twig', []);
    }

    /**
     * Deletes a relationship.
     * @param string $relationship Relationship name
     * @return bool
     */
    public function deleteRelationship(string $relationship): bool
    {
        $relDef = $GLOBALS['metadata']['relationships'][$relationship];
        $sharedTable = false;
        foreach ($GLOBALS['metadata']['relationships'] as $metaRel) {
            if ($metaRel['rel_name'] === $relationship) {
                continue;
            }
            if ($metaRel['rel_table'] === $relDef['rel_table']) {
                $sharedTable = true;
            }
        }
        if (!$sharedTable) {
            Capsule::schema()->drop($relDef['rel_table']);
        }
        
        // Remove subpanel references from both entities
        $lh_entity = $relDef['lh_entity'];
        $rh_entity = $relDef['rh_entity'];
        
        // Remove subpanels from lh_entity that reference this relationship
        if (isset($GLOBALS['metadata']['entities'][$lh_entity]['module_views']['subpanels'])) {
            $subpanels = &$GLOBALS['metadata']['entities'][$lh_entity]['module_views']['subpanels'];
            foreach ($subpanels as $key => $subpanel) {
                if (isset($subpanel['rel_name']) && $subpanel['rel_name'] === $relationship) {
                    unset($subpanels[$key]);
                }
            }
        }
        
        // Remove subpanels from rh_entity that reference this relationship
        if (isset($GLOBALS['metadata']['entities'][$rh_entity]['module_views']['subpanels'])) {
            $subpanels = &$GLOBALS['metadata']['entities'][$rh_entity]['module_views']['subpanels'];
            foreach ($subpanels as $key => $subpanel) {
                if (isset($subpanel['rel_name']) && $subpanel['rel_name'] === $relationship) {
                    unset($subpanels[$key]);
                }
            }
        }
        
        // Remove the relationship from metadata
        unset($GLOBALS['metadata']['relationships'][$relationship]);
        
        return $this->updateMetaDataFile('EntireFile');
    }

    /**
     * Deletes an entity.
     * @param string $entityName Entity name
     * @return bool
     */
    public function deleteEntity(string $entityName): array
    {
        $entityKey = strtolower($entityName);
        $protectedEntities = ['users', 'tokens', 'modulebuilder', 'dashboards'];
        $className = ucfirst($entityKey);

        if (in_array($entityKey, $protectedEntities, true)) {
            throw new \Exception("Deletion of $className entity is not allowed.");
        }

        $result = [
            'success' => false,
            'steps' => [],
            'errors' => [],
        ];

        $table = $this->getTableNameForEntity($entityKey);

        $recordStep = function (string $step, bool $success, ?string $error = null) use (&$result): void {
            $result['steps'][$step] = $success;
            if (!$success && $error) {
                $result['errors'][$step] = $error;
            }
        };

        try {
            if (!Capsule::schema()->hasTable($table)) {
                $recordStep('drop_table', false, "Table '$table' does not exist.");
            } else {
                Capsule::schema()->drop($table);
                $recordStep('drop_table', !Capsule::schema()->hasTable($table), "Failed to drop table '$table'.");
            }

            // Drop the entity's own table
            // Delete entity directory and files
            $entityDir = ROOT_DIR . '/Entities/' . $className;
            if (!is_dir($entityDir)) {
                $recordStep('delete_entity_dir', true);
            } else {
                try {
                    $this->deleteDirectory($entityDir);
                    $recordStep('delete_entity_dir', !is_dir($entityDir), "Failed to delete entity directory '$entityDir'.");
                } catch (\Throwable $e) {
                    $recordStep('delete_entity_dir', false, $e->getMessage());
                }
            }

            // Remove from navigation_entities
            if (in_array($entityKey, $GLOBALS['metadata']['navigation_entities'], true)) {
                $strippedNavigation = array_filter(
                    $GLOBALS['metadata']['navigation_entities'],
                    fn($e) => mb_strtolower($e) !== $entityKey
                );
                $GLOBALS['metadata']['navigation_entities'] = array_values($strippedNavigation);
            }
            $recordStep('navigation_entities', !in_array($entityKey, $GLOBALS['metadata']['navigation_entities'], true), "Failed to remove '$entityKey' from navigation_entities.");

            // Clean other entities' references
            foreach ($GLOBALS['metadata']['entities'] as $otherEntityKey => &$otherEntity) {
                $tableToAlter = null;
                $columnsToDrop = [];
                $changed = false;
                $otherClassName = ucfirst(strtolower($otherEntityKey));

                // Identify and collect relationship fields to drop
                if (isset($otherEntity['fields']) && is_array($otherEntity['fields'])) {
                    foreach ($otherEntity['fields'] as $fieldName => $fieldDef) {
                        if (
                            isset($fieldDef['type'], $fieldDef['entity']) &&
                            $fieldDef['type'] === 'relationship' &&
                            strtolower($fieldDef['entity']) === $entityKey
                        ) {
                            $tableToAlter ??= $this->getTableNameForEntity($otherEntityKey);
                            if (
                                $tableToAlter &&
                                Capsule::schema()->hasColumn($tableToAlter, $fieldName)
                            ) {
                                $columnsToDrop[] = $fieldName;
                            }

                            unset($otherEntity['fields'][$fieldName]);
                            $changed = true;
                        }
                    }
                }

                // Drop collected columns
                if ($tableToAlter && !empty($columnsToDrop)) {
                    try {
                        Capsule::schema()->table($tableToAlter, function ($table) use ($columnsToDrop) {
                            foreach ($columnsToDrop as $column) {
                                $table->dropColumn($column);
                            }
                        });
                    } catch (\Throwable $e) {
                        $recordStep("drop_columns:$otherEntityKey", false, $e->getMessage());
                    }
                }

                // Remove subpanels referencing deleted entity
                if (isset($otherEntity['module_views']['subpanels'])) {
                    foreach ($otherEntity['module_views']['subpanels'] as $subpanelKey => $subpanelDef) {
                    if (
                        isset($subpanelDef['entity']) &&
                        strtolower($subpanelDef['entity']) === $entityKey
                    ) {
                        unset($otherEntity['module_views']['subpanels'][$subpanelKey]);
                        $changed = true;
                    }
                    }
                }

                // Write updated defs if changes occurred
                if ($changed) {
                    try {
                        $this->writeFieldDefs(
                            $otherClassName,
                            ['fields' => $otherEntity['fields'] ?? []]
                        );
                        if (isset($otherEntity['module_views'])) {
                            $this->createViews($otherClassName, $otherEntity['module_views']);
                        }
                    } catch (\Throwable $e) {
                        $recordStep("update_other_entity:$otherEntityKey", false, $e->getMessage());
                    }
                }
            }

            // Remove relationship metadata and drop related relationship tables
            foreach ($GLOBALS['metadata']['relationships'] as $relKey => $relDef) {
                if (
                    strtolower($relDef['lh_entity']) === $entityKey ||
                    strtolower($relDef['rh_entity']) === $entityKey
                ) {
                    $relTable = $relDef['rel_table'] ?? null;
                    if ($relTable && Capsule::schema()->hasTable($relTable)) {
                        try {
                            Capsule::schema()->drop($relTable);
                            $recordStep("drop_relationship_table:$relTable", !Capsule::schema()->hasTable($relTable), "Failed to drop relationship table '$relTable'.");
                        } catch (\Throwable $e) {
                            $recordStep("drop_relationship_table:$relTable", false, $e->getMessage());
                        }
                    }
                    unset($GLOBALS['metadata']['relationships'][$relKey]);
                }
            }

            // Finally, remove the entity itself from metadata
            unset($GLOBALS['metadata']['entities'][$entityKey]);

            if (!$this->updateMetaDataFile('EntireFile')) {
                $recordStep('update_metadata_file', false, 'Failed to write metadata file.');
            } else {
                $recordStep('update_metadata_file', true);
            }

            $result['success'] = empty($result['errors']);
            return $result;

        } catch (\Exception $e) {
            $recordStep('delete_entity', false, $e->getMessage());
            return $result;
        }
    }

    /**
     * Helper method to recursively delete a directory.
     * @param string $dir Directory path
     * @return void
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Helper method to get table name of entity.
     * @param string $entityName Entity name
     * @return string|null
     */
    private function getTableNameForEntity(string $entityName): ?string
    {
        /** @var \SepiaCore\Entities\BaseEntity $entity */
        $entity = $this->getClassFromEntity($entityName);
        return $entity->getTableName();
    }

    /**
     * Gets entity class instance from entity name.
     * @param string $entityName Entity name
     * @return BaseEntity
     */
    private function getClassFromEntity(string $entityName): BaseEntity
    {
        $className = ucfirst($entityName);
        $fullClassName = "SepiaCore\\Entities\\$className\\$className";

        if (!class_exists($fullClassName)) {
            throw new \Exception($entityName . ' entity can not load Class ' . $fullClassName);
        }
        if (!is_subclass_of($fullClassName, 'SepiaCore\Entities\BaseEntity')) {
            throw new \Exception("$className must be a subclass of BaseEntity.");
        }
        /** @var \SepiaCore\Entities\BaseEntity $entity */
        $tableName = strtolower($entityName);
        return new $fullClassName($tableName);
    }

    /**
     * Resets instance (dev only).
     * @param mixed $requestData Request data
     * @return void
     */
    public function resetInstance(mixed $requestData)
    {
        // This functionality is not implemented anymore.
        // Resetting an instance would require:
        // - Deleting the database
        // - Deleting all entity files
        // - Resetting metadata
        // - Removing install.php
        // This operation is too dangerous to be exposed via API.
        throw new \Exception('Instance reset functionality is not available via API for security reasons.');
    }

    /**
     * Updates entity fields.
     * @param string $entity Entity name
     * @param array $fieldsArray Fields array
     * @return bool
     */
    public function updateFields($entity, $fieldsArray): bool
    {
        $entityClass = $this->getClassFromEntity($entity);

        $existingFields = $entityClass->getFieldDefs();
        $protectedFields = $entityClass->isPerson()
            ? ['id', 'first_name', 'last_name', 'date_created', 'date_modified', 'owner']
            : ['id', 'name', 'date_created', 'date_modified', 'owner'];

        // 1. Delete fields not in the new definition, unless protected
        foreach ($existingFields as $fieldName => $fieldDef) {
            if (!array_key_exists($fieldName, $fieldsArray) && !in_array($fieldName, $protectedFields)) {
                $this->deleteField($fieldName, $entityClass, false);  // exceptions will propagate
            }
        }

        // 2. Create new fields or update existing ones
        $entityKey = $entityClass->getEntityKey();
        foreach ($fieldsArray as $fieldName => $fieldDef) {
            if (!array_key_exists($fieldName, $existingFields)) {
                // Validate field name before creating
                $this->validateFieldName($fieldName);
                $this->createField($fieldName, $fieldDef, $entityClass, false);  // exceptions will propagate
            } else {
                // Field already exists - update its definition in metadata
                // Note: We don't alter the database column type for existing fields
                // as that could cause data loss. Only metadata is updated.
                $GLOBALS['metadata']['entities'][$entityKey]['fields'][$fieldName] = $fieldDef;
                $this->writeFieldDefs($entityClass->getClassName(), ['fields' => $GLOBALS['metadata']['entities'][$entityKey]['fields']]);
            }
        }

        // Ensure database columns exist for all defined fields.
        $this->ensureColumnsExist($entityClass, $fieldsArray);

        // 3. Final metadata file write
        if (!$this->updateMetaDataFile('UpdateFromEntities')) {
            throw new \Exception("Unable to update metadata file.");
        }

        return true;
    }

    /**
     * Deletes a field from an entity.
     * @param string $field Field name
     * @param BaseEntity $entityClass Entity class instance
     * @param bool $writeMetaData Whether to write metadata
     * @return void
     */
    private function deleteField(string $field, BaseEntity $entityClass, bool $writeMetaData = true): void
    {
        $entityKey = $entityClass->getEntityKey();
        $table = $entityClass->getTableName();

        if (!Capsule::schema()->hasTable($table)) {
            throw new \Exception("Table '$table' does not exist.");
        }

        if (!Capsule::schema()->hasColumn($table, $field)) {
            throw new \Exception("Field '$field' does not exist in table '$table'.");
        }

        Capsule::schema()->table($table, function (Blueprint $table) use ($field) {
            $table->dropColumn($field);
        });

        unset($GLOBALS['metadata']['entities'][$entityKey]['fields'][$field]);
        $this->writeFieldDefs($entityClass->getClassName(), ['fields' => $GLOBALS['metadata']['entities'][$entityKey]['fields']]);

        if ($writeMetaData) {
            $this->updateMetaDataFile('EntireFile');
        }
    }

    /**
     * Creates a field in an entity.
     * @param string $fieldName Field name
     * @param array $fieldDef Field definition
     * @param BaseEntity $entityClass Entity class instance
     * @param bool $writeMetaData Whether to write metadata
     * @return void
     */
    private function createField(string $fieldName, array $fieldDef, BaseEntity $entityClass, bool $writeMetaData = true): void
    {
        $type = $fieldDef['type'] ?? null;
        if (!$type) {
            throw new \Exception("Field type not specified for '$fieldName'");
        }

        $entityKey = $entityClass->getEntityKey();
        $tableName = $entityClass->getTableName();

        if (!Capsule::schema()->hasTable($tableName)) {
            throw new \Exception("Table '$tableName' does not exist.");
        }

        // Check if field already exists in metadata
        $fieldDefs = $GLOBALS['metadata']['entities'][$entityKey]['fields'] ?? [];
        if (array_key_exists($fieldName, $fieldDefs)) {
            throw new \Exception("Field '$fieldName' already exists in entity '$entityKey'.");
        }

        // Check if column already exists in database table
        if (Capsule::schema()->hasColumn($tableName, $fieldName)) {
            throw new \Exception("Column '$fieldName' already exists in table '$tableName'.");
        }

        // Update in-memory field definitions
        $fieldDefs[$fieldName] = $fieldDef;
        $GLOBALS['metadata']['entities'][$entityKey]['fields'] = $fieldDefs;

        // Persist field definitions
        $this->writeFieldDefs($entityClass->getClassName(), ['fields' => $fieldDefs]);

        // Alter table schema
        Capsule::schema()->table($tableName, function (Blueprint $table) use ($fieldName, $type) {
            $this->applyColumnDefinition($table, $fieldName, $type);
        });
        if ($type === 'relationship') {
            $rh_entity = $entityKey;
            $lh_entity = $fieldDef['entity'];

            $relDef = [
                'rh_entity' => $rh_entity,
                'lh_entity' => $lh_entity,
                'rel_field' => $fieldName,
            ];
            $this->createSubpanels($relDef, 'one_to_many', false);
        }

        if ($writeMetaData) {
            $this->updateMetaDataFile('EntireFile');
        }
    }

    /**
     * Ensures table columns exist for the provided field definitions.
     * @param BaseEntity $entityClass Entity class instance
     * @param array $fieldsArray Fields array
     * @return void
     */
    private function ensureColumnsExist(BaseEntity $entityClass, array $fieldsArray): void
    {
        $tableName = $entityClass->getTableName();

        if (!Capsule::schema()->hasTable($tableName)) {
            throw new \Exception("Table '$tableName' does not exist.");
        }

        foreach ($fieldsArray as $fieldName => $fieldDef) {
            if (!Capsule::schema()->hasColumn($tableName, $fieldName)) {
                $type = $fieldDef['type'] ?? 'text';
                Capsule::schema()->table($tableName, function (Blueprint $table) use ($fieldName, $type) {
                    $this->applyColumnDefinition($table, $fieldName, $type);
                });
            }
        }
    }

    /**
     * Validates field name.
     * @param string $fieldName Field name
     * @return void
     */
    private function validateFieldName(string $fieldName): void
    {
        // Field name must start with a letter or underscore, and contain only lowercase letters, numbers, and underscores
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $fieldName)) {
            throw new \Exception("Invalid field name: '$fieldName'. Only lowercase letters, numbers, and underscores are allowed, and must not start with a digit.");
        }

        // Reserved keywords (basic subset, can be expanded or made DB-specific)
        $reservedKeywords = [
            'select', 'from', 'where', 'group', 'order', 'limit', 'join', 'table', 'user',
            'index', 'primary', 'key', 'foreign', 'by', 'as', 'into', 'and', 'or', 'not', 'null',
        ];

        if (in_array(strtolower($fieldName), $reservedKeywords)) {
            throw new \Exception("Field name '$fieldName' is a reserved SQL keyword and cannot be used.");
        }
    }

    /**
     * Applies column definition to table blueprint.
     * @param Blueprint $table Table blueprint
     * @param string $name Column name
     * @param string $type Column type
     * @return void
     */
    private function applyColumnDefinition(Blueprint $table, string $name, string $type): void
    {
        $this->validateFieldName($name);

        switch ($type) {
            case 'uuid':
                $table->uuid($name)->nullable();
                break;
            case 'datetime':
                $table->dateTime($name)->nullable();
                break;
            case 'date':
                $table->date($name)->nullable();
                break;
            case 'text':
            case 'textarea':
                $table->text($name)->nullable();
                break;
            case 'string':
            case 'select':
            case 'relationship':
                $table->string($name)->nullable();
                break;
            case 'int':
            case 'integer':
                $table->integer($name)->nullable();
                break;
            case 'float':
                $table->float($name)->nullable();
                break;
            case 'boolean':
            case 'checkbox':
                $table->boolean($name)->default(false);
                break;
            case 'collection':
                $table->json($name)->nullable();
                break;
            default:
                throw new \Exception("Unsupported field type: '$type' for column '$name'");
        }
    }

    /**
     * Creates a new entity.
     * @param array $newEntityData Entity data
     * @return bool
     * @throws \Exception
     */
    public function createEntity($newEntityData): bool
    {
        // Validate required fields
        $requiredKeys = ['name', 'tableName', 'className', 'type'];
        foreach ($requiredKeys as $key) {
            if (empty($newEntityData[$key])) {
                throw new \Exception('Malformed request');
            }
        }

        $className = $newEntityData['className'];
        $tableName = $newEntityData['tableName'];
        $entityKey = strtolower($newEntityData['className']);
        $type = $newEntityData['type'];

        // Basic validation to prevent path traversal or malicious code
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $className)) {
            throw new \Exception('Invalid class name');
        }

        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $tableName)) {
            throw new \Exception('Invalid table name');
        }

        // Create the full path: ROOT_DIR/Entities/[ClassName]/[ClassName].php
        $entityDirPath = ROOT_DIR . '/Entities/';
        $dirPath = $entityDirPath . $className;
        $filePath = $dirPath . '/' . $className . '.php';

        if (file_exists($filePath)) {
            throw new \Exception('Entity already exists');
        }

        if (!is_writable($entityDirPath)) {
            throw new \Exception('Entity directory is not writeable');
        }
        if (!is_writable(CONFIG_DIR)) {
            throw new \Exception('Config directory is not writeable');
        }

        // Ensure the directory exists
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0775, true)) {
                throw new \Exception('Entity directory does not exist and can\'t be created');
            }
        }
        // Build class code
        $namespace = 'SepiaCore\\Entities\\' . $className;
        $classCode = <<<PHP
<?php
namespace $namespace;

use SepiaCore\Entities\BaseEntity;

class $className extends BaseEntity
{
    public function __construct(\$table = '$tableName')
    {
        parent::__construct(\$table);
PHP;

        if ($type === 'person') {
            $classCode .= "\n        \$this->person = true;";
        }

        $classCode .= "\n    }\n}";

        // Write to file
        if (file_put_contents($filePath, $classCode) === false) {
            throw new \Exception('Entity file could not be created');
        }
        if (!isset($newEntityData['views']['list']['layout']) || empty($newEntityData['views']['list']['layout'])) {
            if ($type === 'person') {
                $newEntityData['views']['list']['layout'] = [0 => 'first_name', 1 => 'last_name', 2 => 'date_created', 3 => 'date_modified', 4 => 'owner',];
            } else {
                $newEntityData['views']['list']['layout'] = [0 => 'name', 1 => 'date_created', 2 => 'date_modified', 3 => 'owner',];
            }
        }
        if (!isset($newEntityData['views']['record']['layout']) || empty($newEntityData['views']['record']['layout'])) {
            if ($type === 'person') {
                $newEntityData['views']['record']['layout'] =
                    [
                        0 =>
                            [
                                0 => 'first_name',
                                1 => 'last_name',
                            ],
                        1 => [0 => 'owner'],
                        2 =>
                            [
                                0 => 'date_created',
                                1 => 'date_modified',
                            ]];
            } else {
                $newEntityData['views']['record']['layout'] =
                    [0 =>
                        [
                            0 => 'name',
                            1 => 'owner',
                        ],
                        1 =>
                            [
                                0 => 'date_created',
                                1 => 'date_modified',
                            ]];
            }
        }
        if (!$this->createViews($className, $newEntityData['views'])) {
            throw new \Exception('Unable to create view');
        }
        if ($type === 'person') {
            $GLOBALS['metadata']['entities'][$entityKey]['isPerson'] = true;
        } else {
            $GLOBALS['metadata']['entities'][$entityKey]['isPerson'] = false;
        }
        //set capabilities
        $GLOBALS['metadata']['entities'][$entityKey]['capabilities'] = [
            'action-console' =>
                [
                    'active' => 'true',
                    'requires_admin' => false,
                ],
        ];

        // Store icon if provided
        if (!empty($newEntityData['icon'])) {
            $GLOBALS['metadata']['entities'][$entityKey]['icon'] = $newEntityData['icon'];
        }

        if ($this->createTable($tableName, $newEntityData['fields'] ?? [], $type)) {
            $this->buildFieldDefs($className, $newEntityData['fields'] ?? [], $type);
        }
        if (!$this->updateMetaDataFile()) {
            throw new \Exception('Unable to update metadata file');
        }

        return true;
    }

    /**
     * Creates a database table.
     * @param string $table Table name
     * @param array $fields Fields array
     * @param string $type Entity type
     * @return bool
     */
    public function createTable(string $table, array $fields, string $type): bool
    {
        try {
            if (Capsule::schema()->hasTable($table)) {
                throw new \Exception("Table '$table' already exists.");
            }

            $defaultFields = [
                ['name' => 'id', 'type' => 'uuid'],
                ['name' => 'date_created', 'type' => 'datetime'],
                ['name' => 'date_modified', 'type' => 'datetime'],
                ['name' => 'owner', 'type' => 'uuid'],
            ];

            $nameFields = match ($type) {
                'person' => [
                    ['name' => 'first_name', 'type' => 'text'],
                    ['name' => 'last_name', 'type' => 'text'],
                ],
                'relationship' => [],
                default => [
                    ['name' => 'name', 'type' => 'text'],
                ]
            };

            if ($type === 'relationship') {
                $defaultFields = [['name' => 'id', 'type' => 'uuid']];
            }

            $fieldsWithNames = [];
            foreach ($defaultFields as $field) {
                $fieldsWithNames[] = $field;
                if ($field['name'] === 'id') {
                    $fieldsWithNames = array_merge($fieldsWithNames, $nameFields);
                }
            }

            $finalFields = array_merge($fieldsWithNames, $fields);

            Capsule::schema()->create($table, function (Blueprint $tableBlueprint) use ($finalFields) {
                foreach ($finalFields as $field) {
                    $name = $field['name'];
                    $type = $field['type'] ?? 'text';
                    $this->applyColumnDefinition($tableBlueprint, $name, $type);
                }
            });

            return true;

        } catch (\Exception $e) {
            throw new \Exception("Failed to create table: " . $e->getMessage());
        }
    }

    /**
     * Builds field definitions for an entity.
     * @param string $className Class name
     * @param array $fields Fields array
     * @param string $type Entity type
     * @return void
     */
    public function buildFieldDefs(string $className, array $fields = [], string $type): void
    {
        $defaultFields = [
            ['name' => 'id', 'type' => 'uuid'],
            ['name' => 'date_created', 'type' => 'datetime', 'readonly' => true],
            ['name' => 'date_modified', 'type' => 'datetime', 'readonly' => true],
            ['name' => 'owner', 'type' => 'relationship', 'entity' => 'users'],
        ];

        switch ($type) {
            case 'person':
                $nameFields = [
                    ['name' => 'first_name', 'type' => 'text'],
                    ['name' => 'last_name', 'type' => 'text'],
                ];
                break;
            case 'basic':
            default:
                $nameFields = [
                    ['name' => 'name', 'type' => 'text'],
                ];
        }

        // Merge fields: insert nameFields after 'id'
        $fieldsWithNames = [];
        foreach ($defaultFields as $field) {
            $fieldsWithNames[] = $field;
            if ($field['name'] === 'id') {
                $fieldsWithNames = array_merge($fieldsWithNames, $nameFields);
            }
        }

        $finalFields = array_merge($fieldsWithNames, $fields);

        // Build final array for export
        $fieldDefs = ['fields' => []];
        foreach ($finalFields as $field) {
            $name = $field['name'];
            $def = ['type' => $field['type'] ?? 'text'];

            if (!empty($field['readonly'])) {
                $def['readonly'] = true;
            }
            if (!empty($field['entity'])) {
                $def['entity'] = $field['entity'];
            }

            $fieldDefs['fields'][$name] = $def;
        }

        $this->writeFieldDefs($className, $fieldDefs);
    }

    /**
     * Writes field definitions to file.
     * @param string $className Class name
     * @param array $fieldDefs Field definitions
     * @return void
     */
    private function writeFieldDefs(string $className, array $fieldDefs): void
    {
        $path = ROOT_DIR . '/Entities/' . $className;
        if (!is_dir($path)) {
            throw new \Exception("Directory '$path' does not exists. Can't write fielddefs.");
        }
        if (!is_writable($path)) {
            throw new \Exception("Directory '$path' is not writeable. Can't write fielddefs.");
        }

        $filePath = $path . '/fielddefs.php';
        $content = "<?php\n\nreturn " . var_export($fieldDefs, true) . ";\n";

        file_put_contents($filePath, $content);
    }

    /**
     * Creates a relationship.
     * @param array $relDef Relationship definition
     * @return bool
     */
    public function createRelationship($relDef)
    {
        if (
            !empty($relDef['rel_name']) &&
            !empty($relDef['rh_entity']) &&
            !empty($relDef['lh_entity']) &&
            !empty($relDef['rel_table'])
        ) {
            $allowed_keys = ['rel_name', 'rh_entity', 'lh_entity', 'rel_table'];
            $filtered = array_intersect_key($relDef, array_flip($allowed_keys));
            extract($filtered, EXTR_SKIP);
            if (isset($GLOBALS['metadata']['relationships'][$rel_name])) {
                throw new \Exception('relationship already exists');
            } else {
                if (!Capsule::schema()->hasTable($rel_table)) {
                    $fields = [
                        ['name' => $lh_entity . '_id', 'type' => 'uuid'],
                        ['name' => $rh_entity . '_id', 'type' => 'uuid'],

                    ];
                    // createTable() uses schema()->create() which auto-commits DDL statements
                    // No need to wrap in a transaction as DDL statements cannot be rolled back
                    if (!$this->createTable($rel_table, $fields, 'relationship')) {
                        throw new \Exception('could not create relationship table ' . $rel_table . '.');
                    }
                }
                $GLOBALS['metadata']['relationships'][$rel_name] = $filtered;
                $this->createSubpanels($filtered, 'many_to_many', false);
                return $this->updateMetaDataFile('EntireFile');
            }
        } else {
            throw new \Exception('Malformed relationship definition');
        }
    }

    /**
     * Creates views for an entity.
     * @param string $className Class name
     * @param array $views Views array
     * @return bool
     */
    public function createViews($className, $views): bool
    {
        $path = ROOT_DIR . '/Entities/' . $className;
        if (!is_dir($path)) {
            throw new \Exception("Directory '$path' does not exists. Can\'t create view.");
        }
        if (!is_writable($path)) {
            throw new \Exception('Entity directory is not writeable. Can\'t create view.');
        }

        $filePath = $path . '/viewdefs.php';
        $content = "<?php\n\nreturn " . var_export($views, true) . ";\n";

        return (file_put_contents($filePath, $content) !== false);
    }

    /**
     * Updates a view.
     * @param array $requestData Request data
     * @return bool
     */
    public function updateView($requestData): bool
    {
        $entity = $requestData['entity'];
        $view = $requestData['view'];
        $className = ucfirst(strtolower($entity));
        $viewDef = $requestData['viewDef'];
        $GLOBALS['metadata']['entities'][$entity]['module_views'][$view] = $viewDef;
        $this->createViews($className, $GLOBALS['metadata']['entities'][$entity]['module_views']);

        if (!$this->updateMetaDataFile()) {
            throw new \Exception("Unable to update metadata file");
        }

        return true;
    }

    /**
     * Updates entity icon.
     * @param string $entity Entity name
     * @param string $icon Icon name
     * @return bool
     */
    public function updateIcon(string $entity, string $icon): bool
    {
        $entityKey = strtolower($entity);
        
        // Validate entity exists
        if (!isset($GLOBALS['metadata']['entities'][$entityKey])) {
            throw new \Exception("Entity '$entity' does not exist.");
        }
        
        // Update icon in metadata
        $GLOBALS['metadata']['entities'][$entityKey]['icon'] = $icon;
        
        // Save metadata file
        if (!$this->updateMetaDataFile('EntireFile')) {
            throw new \Exception("Unable to update metadata file.");
        }
        
        return true;
    }

    /**
     * Sets navigation entities.
     * @param array $navigation_entities Navigation entities array
     * @return bool
     */
    public function setNavigationEntities(array $navigation_entities): bool
    {
        if (!empty($navigation_entities)) {
            $this->newNavigationEntities = $navigation_entities;
            if ($this->updateMetaDataFile('UpdateNavigationEntities')) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Updates metadata file.
     * @param string $action Action type
     * @param bool $doBackup Whether to create backup
     * @param bool $writeToFile Whether to write to file
     * @return bool
     */
    public function updateMetaDataFile($action = 'UpdateFromEntities', $doBackup = true, $writeToFile = true)
    {
        // Define the path to the Entities directory
        $entitiesDir = ROOT_DIR . '/Entities/';
        $configDir = CONFIG_DIR;

        // Make sure the directories exist
        if (!is_dir($entitiesDir) || !is_dir($configDir)) {
            Log::logMessage("[updateMetaDataFile] entitiesDir or configDir is not a directory", "error");

            return false;
        }
        if (!is_writable($entitiesDir) || !is_writable($configDir)) {
            Log::logMessage("[updateMetaDataFile] entitiesDir or configDir is not writeable", "error");

            return false;
        }

        if (!isset($GLOBALS['metadata']) || !isset($GLOBALS['metadata']['entities'])) {
            Log::logMessage("[updateMetaDataFile] metadata is not set globally");

            return false;
        }

        if ($doBackup) {
            $datetime = date('Ymd_His');
            $backupFile = $configDir . "/metadata_{$datetime}.php";
            file_put_contents($backupFile, '<?php return ' . var_export($GLOBALS['metadata'], true) . ';');
        }
        // Traverse entity directories
        switch ($action) {
            case 'UpdateFromEntities':
                $dirIterator = new \DirectoryIterator($entitiesDir);
                foreach ($dirIterator as $fileInfo) {
                    if ($fileInfo->isDot() || !$fileInfo->isDir()) {
                        continue;
                    }

                    $entityName = mb_strtolower($fileInfo->getFilename());
                    $fieldDefsFile = $fileInfo->getPathname() . '/fielddefs.php';
                    $viewDefsFile = $fileInfo->getPathname() . '/viewdefs.php';

                    if (file_exists($fieldDefsFile)) {
                        // Load and extract fields array from the returned structure
                        $definition = include $fieldDefsFile;
                        $fields = $definition['fields'] ?? [];
                        if (!isset($GLOBALS['metadata']['entities'][$entityName])) {
                            $GLOBALS['metadata']['entities'][$entityName] = [];
                        }

                        $GLOBALS['metadata']['entities'][$entityName]['fields'] = $fields;
                    }
                    if (file_exists($viewDefsFile)) {
                        // Load and extract view defs array from the returned structure
                        $views = include $viewDefsFile;
                        if (!isset($GLOBALS['metadata']['entities'][$entityName])) {
                            $GLOBALS['metadata']['entities'][$entityName] = [];
                        }

                        $GLOBALS['metadata']['entities'][$entityName]['module_views'] = $views;
                    }
                }
                break;
            case 'UpdateNavigationEntities':
                if (isset($GLOBALS['metadata']['navigation_entities'])) {
                    $GLOBALS['metadata']['navigation_entities'] = $this->newNavigationEntities;
                }
                break;
        }


        // Write updated metadata
        if ($writeToFile) {
            file_put_contents(METADATA_FILE, '<?php return ' . var_export($GLOBALS['metadata'], true) . ';');
        }
        return true;
    }

    /**
     * Creates subpanels for relationships.
     * @param array $relationshipDef Relationship definition
     * @param string $rel_type Relationship type
     * @param bool $writeMetaData Whether to write metadata
     * @return void
     */
    private function createSubpanels(array $relationshipDef, $rel_type, bool $writeMetaData = false)
    {
        $rel_name = $relationshipDef['rel_name'] ?? null;
        $rh_entity = $relationshipDef['rh_entity'];
        $lh_entity = $relationshipDef['lh_entity'];
        $rel_table = $relationshipDef['rel_table'] ?? null;
        $rel_field = $relationshipDef['rel_field'] ?? null;

        // Ensure subpanels arrays exist
        if (!isset($GLOBALS['metadata']['entities'][$rh_entity]['module_views']['subpanels'])) {
            $GLOBALS['metadata']['entities'][$rh_entity]['module_views']['subpanels'] = [];
        }

        if (!isset($GLOBALS['metadata']['entities'][$lh_entity]['module_views']['subpanels'])) {
            $GLOBALS['metadata']['entities'][$lh_entity]['module_views']['subpanels'] = [];
        }

        if ($rel_type === 'many_to_many') {

            foreach ([$rh_entity, $lh_entity] as $targetEntity) {
                $subpanels = &$GLOBALS['metadata']['entities'][$targetEntity]['module_views']['subpanels'];
                $relatedEntity = ($targetEntity === $rh_entity) ? $lh_entity : $rh_entity;

                // Determine unique key
                $baseKey = $relatedEntity;
                $uniqueKey = $baseKey;
                $counter = 2;
                while (array_key_exists($uniqueKey, $subpanels)) {
                    $uniqueKey = $baseKey . '_' . $counter;
                    $counter++;
                }

                // Check if already defined
                $exists = false;
                foreach ($subpanels as $def) {
                    if (isset($def['rel_name']) && $def['rel_name'] === $rel_name) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $fields = $this->getDefaultFieldsForEntity($relatedEntity);
                    $subpanels[$uniqueKey] = [
                        'entity' => $relatedEntity,
                        'fields' => $fields,
                        'rel_type' => 'many_to_many',
                        'rel_table' => $rel_table,
                        'rel_name' => $rel_name,
                    ];
                    $entityClass = $this->getClassFromEntity($targetEntity);
                    $this->createViews($entityClass->getClassName(), $GLOBALS['metadata']['entities'][$targetEntity]['module_views']);
                }
            }
        } elseif ($rel_type === 'one_to_many') {

            $subpanels = &$GLOBALS['metadata']['entities'][$lh_entity]['module_views']['subpanels'];
            $baseKey = $rh_entity;
            $uniqueKey = $baseKey;
            $counter = 2;
            while (array_key_exists($uniqueKey, $subpanels)) {
                $uniqueKey = $baseKey . '_' . $counter;
                $counter++;
            }

            $exists = false;
            foreach ($subpanels as $def) {
                if (isset($def['rel_field']) && $def['rel_field'] === $rel_field) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $fields = $this->getDefaultFieldsForEntity($rh_entity);
                $subpanels[$uniqueKey] = [
                    'entity' => $rh_entity,
                    'fields' => $fields,
                    'rel_type' => 'one_to_many',
                    'rel_field' => $rel_field,
                ];
                $entityClass = $this->getClassFromEntity($lh_entity);
                $this->createViews($entityClass->getClassName(), $GLOBALS['metadata']['entities'][$lh_entity]['module_views']);
            }
        }
        if ($writeMetaData) {
            $this->updateMetaDataFile('EntireFile');
        }
    }

    /**
     * Gets default fields for an entity.
     * @param string $entity Entity name
     * @return array
     */
    private function getDefaultFieldsForEntity(string $entity): array
    {
        $entityClass = $this->getClassFromEntity($entity);

        if (method_exists($entityClass, 'isPerson') && $entityClass->isPerson()) {
            return ['first_name', 'last_name', 'date_created', 'date_modified'];
        }

        return ['name', 'date_created', 'date_modified'];
    }
}
