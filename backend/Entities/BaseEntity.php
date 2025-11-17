<?php
namespace SepiaCore\Entities;

use SepiaCoreUtilities\Log;
use SepiaCoreUtilities\ScriptSandbox;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Ramsey\Uuid\Uuid;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

class BaseEntity {
    //protected \PDO $pdo;
    protected string $table;
    protected string $entityKey; //always the class name in all lower cases
    protected bool $person = false;
    protected array $fields = [];
    protected array $fieldDefs = [];
    public bool $noLimit = false;
    public Environment $twig;

    public function __construct($table) {
        $this->entityKey = strtolower($this->getClassName());
        $this->initDatabase($table);
        $this->loadFields();
        $this->initTwig();

    }

    protected function initDatabase($table) {
        //$this->pdo = $GLOBALS['pdo'];
        $this->table = $table;
        //$this->db = DB::table($table);   // Laravel Query Builder
    }

    protected function initTwig(): void
    {
        $loader = new FilesystemLoader(ROOT_DIR . '/src/res/tpl/');
        $environment = $GLOBALS['config']['environment'] ?? 'dev';
        $isDebug = ($environment === 'dev');
        
        $this->twig = new Environment($loader,
            [
                'debug' => $isDebug,
            ]);
        $this->twig->addGlobal('metadata', $GLOBALS['metadata']);
        $this->twig->addGlobal('settings', $GLOBALS['settings']);
        $this->twig->addGlobal('isAdmin', $GLOBALS['isAdmin']);
        $this->twig->addGlobal('entityKey', $this->entityKey);
        
        if ($isDebug) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    protected function loadFields(): void
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        $this->fields = $schema->getColumnListing($this->table);
        $this->fieldDefs = $GLOBALS['metadata']['entities'][$this->entityKey]['fields'] ?? [];
    }

        public function getFields(): array
    {
        return $this->fields;
    }
    public function getTableName(): string {
        return $this->table;
    }
    public function getEntityKey(): string{
        return $this->entityKey;
    }
    public function getFieldDefs(): array{
        return $this->fieldDefs;
    }
    public function getFieldDef(string $field):array{
        return $this->fieldDefs[$field];
    }
    public function isPerson(): bool {
        return $this->person;
    }
    public function getClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

/*    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
    public function getFieldDefinitions(): array
    {
        return $this->fieldDefs;
    }
    public function generateFieldDefs()
    {
        //todo: scope decision outstanding, should this be handled by entity itself or the entity studio
        $columns = $this->pdo->query("PRAGMA table_info(`{$this->table}`)")->fetchAll(PDO::FETCH_ASSOC);
        $fields = [];

        foreach ($columns as $col) {
            $fields[$col['name']] = [
                'type' => strtoupper($col['type']),
                'null' => $col['notnull'] == 0,
            ];
            if ($col['pk']) {
                $fields[$col['name']]['primary'] = true;
            }
        }

        // Resolve path: ROOT_DIR/Entities/ClassName/fielddefs.php
        $className = (new \ReflectionClass($this))->getShortName();
        $dir = ROOT_DIR . '/Entities/' . $className;
        $outputFile = $dir . '/fielddefs.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $export = "<?php\nreturn " . var_export($fields, true) . ";\n";
        file_put_contents($outputFile, $export);
    }
    public function syncSchemaFromFieldDefs()
    {
        //todo: scope decision outstanding, should this be handled by entity itself or the modulebuilder
        $className = (new \ReflectionClass($this))->getShortName();
        $fieldDefPath = ROOT_DIR . '/Entities/' . $className . '/fielddefs.php';

        if (!file_exists($fieldDefPath)) {
            return;
        }

        $fields = include $fieldDefPath;

        // Check if table exists
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$this->table]);

        if (!$stmt->fetch()) {
            // Table does not exist — create it
            $cols = [];
            foreach ($fields as $name => $meta) {
                $colDef = "`$name` {$meta['type']}";
                $colDef .= empty($meta['null']) ? " NOT NULL" : "";
                $cols[] = $colDef;
            }

            foreach ($fields as $name => $meta) {
                if (!empty($meta['primary'])) {
                    $cols[] = "PRIMARY KEY(`$name`)";
                    break;
                }
            }

            $sql = "CREATE TABLE `{$this->table}` (" . implode(", ", $cols) . ")";
            $this->pdo->exec($sql);
            echo "Created table '{$this->table}'.\n";
        } else {
            // Modify table
            $existing = $this->pdo->query("PRAGMA table_info(`{$this->table}`)")->fetchAll(PDO::FETCH_ASSOC);
            $existingCols = array_column($existing, null, 'name');

            foreach ($fields as $name => $meta) {
                if (!isset($existingCols[$name])) {
                    $colDef = "`$name` {$meta['type']}";
                    $colDef .= empty($meta['null']) ? " NOT NULL" : "";
                    $sql = "ALTER TABLE `{$this->table}` ADD COLUMN $colDef";
                    $this->pdo->exec($sql);
                } else {
                    $current = $existingCols[$name];
                    $typeChanged = strtoupper($current['type']) !== strtoupper($meta['type']);
                    $nullChanged = ($current['notnull'] == 0) === empty($meta['null']) ? false : true;

                    if ($typeChanged || $nullChanged) {
                        //todo: implement logger
                        fwrite(fopen('error.log', 'a'), "Column '$name' in '{$this->table}' differs from definition. Manual migration required.\n");
                    }
                }
            }
        }
    }*/


//CRUD
    public function create($data) {
        $data['id'] = $data['id'] ?? Uuid::uuid4()->toString();

        if (empty($data['owner']) && $this->table !== 'users' && $this->table !== 'rawendpointdata') {
            $data['owner'] = $GLOBALS['user_id'];
        }
        $now = date('Y-m-d H:i:s');
        $data['date_modified'] = $now;
        $data['date_created'] = $now;
        $data_raw = $this->executeScript($data, 'before_save');
        $data = $this->encodeJsonFields($data_raw);

        try {
            Capsule::table($this->table)->insert($data);
            $data_raw = $this->executeScript($data_raw, 'after_save');

            return $data_raw;
        } catch (\Throwable $e) {
            throw new \Exception("Failed to insert record into {$this->table}: " . $e->getMessage(), 0, $e);
        }
    }

    public function read($id = null, $page = 1, $limit = 10, $sortBy = 'date_created', $sortOrder = 'DESC', $search = null) {
        $query = Capsule::table($this->table);

        // Fetch single record by ID
        if ($id) {
            $record = $query->where('id', $id)->first();
            return $record ? $this->transformRecord($this->decodeJsonFields((array) $record)) : null;
        }

        // Apply search filters if any
        if ($search && is_array($search)) {
            if ($this->person && isset($search['name'])) {
                $search['first_name'] = $search['name'];
                $search['last_name'] = $search['name'];
                unset($search['name']);
            }

            $query->where(function ($q) use ($search) {
                foreach ($search as $field => $value) {
                    $q->orWhere($field, 'like', '%' . $value . '%');
                }
            });

            $this->noLimit = true;
        } elseif ($search) {  // $search is a string
            $query->where(function ($q) use ($search) {
                if ($this->person) {
                    $q->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                } else {
                    $q->orWhere('name', 'like', '%' . $search . '%');
                }
            });
            $this->noLimit = true;
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination unless disabled
        if (!$this->noLimit) {
            $offset = ($page - 1) * $limit;
            $query->limit($limit)->offset($offset);
        }

        $records = $query->get();

        return array_map(function ($record) {
            $decoded = $this->decodeJsonFields((array) $record);
            return $this->transformRecord($decoded);
        }, $records->all());
    }

    public function update($id, $data)
    {
        // Override date fields, they are only a concern of the system
        $data['date_modified'] = date('Y-m-d H:i:s'); //todo: what about timezones? system timezone is not a solid basis
        if (isset($data['date_created'])) {
            unset($data['date_created']);
        }
        $data_raw = $this->executeScript($data, 'before_save');
        $data = $this->encodeJsonFields($data_raw);

        try {
                $affectedRows = Capsule::table($this->table)
                ->where('id', $id)
                ->update($data);
            $data_raw = $this->executeScript($data_raw, 'after_save');

            if($affectedRows > 0){
                return $data_raw;
            }else{ return false; }
        } catch (\Exception $e) {
            throw $e; // Re-throw the exception
        }

    }

    public function delete($id) {
        return Capsule::table($this->table)
                ->where('id', $id)
                ->delete() > 0;
    }

    public function find($field, $value, $firstresult = true) {
        $query = Capsule::table($this->table)->where($field, $value);

        if ($firstresult) {
            $result = $query->first();
            return $result ? (array) $result : null;
        } else {
            $results = $query->get();
            return $results->map(fn($item) => (array) $item)->all();
        }
    }

    public function count() {
        return Capsule::table($this->table)->count();
    }

    public function getRelatedRecords(string|array $relationship, $parentId): array {
        if (!is_array($relationship)) {
            $relationship = $GLOBALS['metadata']['relationships'][$relationship] ?? null;
            if (empty($relationship)) {
                return [];
            }
        }

        $this->ensureRelationshipTableExists($relationship);

        $relTable = $relationship['rel_table'];
        $rhEntity = $relationship['rh_entity'];
        $lhEntity = $relationship['lh_entity'];

        if ($this->entityKey === $rhEntity) {
            $wantedEntity = $lhEntity;
            $wantedId = "{$lhEntity}_id";
            $searchId = "{$rhEntity}_id";
        } elseif ($this->entityKey === $lhEntity) {
            $wantedEntity = $rhEntity;
            $wantedId = "{$rhEntity}_id";
            $searchId = "{$lhEntity}_id";
        } else {
            return [];
        }
        /** @var \SepiaCore\Entities\BaseEntity $wantedEntityClass */
        $wantedEntityClass = $this->getClassFromEntity($wantedEntity);
        $wantedTable = $wantedEntityClass->getTableName();


        $relatedIds = Capsule::table($relTable)
            ->where($searchId, $parentId)
            ->pluck($wantedId)
            ->toArray();

        if (!empty($relatedIds)) {
            return Capsule::table($wantedTable)
                ->whereIn('id', $relatedIds)
                ->get()
                ->map(fn($record) => (array) $record)
                ->all();
        }

        return [];
    }

    public function addRelationship(string|array $relationship, $parentId, $linkId): bool {
        if (!is_array($relationship)) {
            $relationship = $GLOBALS['metadata']['relationships'][$relationship] ?? null;
            if (empty($relationship)) {
                throw new \Exception('Relationship not found');
            }
        }

        $this->ensureRelationshipTableExists($relationship);

        $relTable = $relationship['rel_table'];
        $rhEntity = $relationship['rh_entity'];
        $lhEntity = $relationship['lh_entity'];

        if ($this->entityKey === $lhEntity) {
            $parentField = "{$lhEntity}_id";
            $childField = "{$rhEntity}_id";
        } else {
            $parentField = "{$rhEntity}_id";
            $childField = "{$lhEntity}_id";
        }

        $exists = Capsule::table($relTable)
            ->where($parentField, $parentId)
            ->where($childField, $linkId)
            ->exists();

        if ($exists) {
            return true;
        }

        $inserted = Capsule::table($relTable)->insert([
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            $parentField => $parentId,
            $childField => $linkId,
        ]);

        if (!$inserted) {
            throw new \Exception('Failed to insert relationship');
        }

        return true;
    }

    public function removeRelationship(string|array $relationship, $parentId, $linkId): bool {
        if (!is_array($relationship)) {
            $relationship = $GLOBALS['metadata']['relationships'][$relationship] ?? null;
            if (empty($relationship)) {
                throw new \Exception('Relationship not found');
            }
        }

        $relTable = $relationship['rel_table'];
        $rhEntity = $relationship['rh_entity'];
        $lhEntity = $relationship['lh_entity'];

        if ($this->entityKey === $lhEntity) {
            $parentField = "{$lhEntity}_id";
            $childField = "{$rhEntity}_id";
        } else {
            $parentField = "{$rhEntity}_id";
            $childField = "{$lhEntity}_id";
        }

        $deleted = Capsule::table($relTable)
            ->where($parentField, $parentId)
            ->where($childField, $linkId)
            ->delete();

        return $deleted > 0;
    }

    private function ensureRelationshipTableExists(array $relationship): void
    {
        $relTable = $relationship['rel_table'];
        $rhEntity = $relationship['rh_entity'];
        $lhEntity = $relationship['lh_entity'];

        $schema = Capsule::schema();
        if (!$schema->hasTable($relTable)) {
            $schema->create($relTable, function (Blueprint $table) use ($rhEntity, $lhEntity) {
                $table->uuid('id')->primary();
                $table->uuid("{$rhEntity}_id");
                $table->uuid("{$lhEntity}_id");
            });
        }
    }
    public function setRelation($field, $relRecord, $parentRecord) {
        $relId = is_array($relRecord) ? $relRecord['id'] ?? null : $relRecord;
        $parentId = is_array($parentRecord) ? $parentRecord['id'] ?? null : $parentRecord;

        if (!empty($parentId) && !empty($relId) && !empty($field)) {
            return $this->update($relId, [$field => $parentId]);
        }

        return false;
    }

    public function newRecord() {
        $record = [];
        foreach ($this->fields as $field) {
            $record[$field] = null; // Initialize each field with null
        }
        if(empty($record['owner'])) {
            $record['owner'] = $GLOBALS['user_id'];
        }
        return $record;
    }

    public function nextRecord($id, $sortBy = 'date_created', $sortOrder = 'DESC', $filters = null)
    {
        return $this->getAdjacentRecord($id, $sortBy, $sortOrder, $filters, true);
    }

    public function previousRecord($id, $sortBy = 'date_created', $sortOrder = 'DESC', $filters = null)
    {
        return $this->getAdjacentRecord($id, $sortBy, $sortOrder, $filters, false);
    }
    private function getAdjacentRecord(string $id, string $sortBy, string $sortOrder, ?array $filters, bool $next = true): ?string
    {
        $reference = Capsule::table($this->table)->where('id', $id)->first([$sortBy]);

        if (!$reference) {
            return null;
        }

        $operator = $next
            ? ($sortOrder === 'ASC' ? '>' : '<')
            : ($sortOrder === 'ASC' ? '<' : '>');

        $orderDir = $next ? $sortOrder : ($sortOrder === 'ASC' ? 'DESC' : 'ASC');

        $query = Capsule::table($this->table);

        $query->where(function ($q) use ($sortBy, $operator, $reference, $id) {
            $q->where($sortBy, $operator, $reference->{$sortBy})
                ->orWhere(function ($q2) use ($sortBy, $reference, $operator, $id) {
                    $q2->where($sortBy, '=', $reference->{$sortBy})
                        ->where('id', $operator, $id);
                });
        });

        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                $query->where($field, 'LIKE', '%' . $value . '%');
            }
        }

        return $query
            ->orderBy($sortBy, $orderDir)
            ->orderBy('id', $orderDir)
            ->limit(1)
            ->value('id');
    }
    protected function transformRecord($record) {
        if($this->person){
            $record['name'] = trim(($record['first_name'] ?? '') . ($record['first_name']?' ':'') . ($record['last_name'] ?? ''));
            $record['name'] = ($record['name']?? 'N/A');
            return $record;
        }else{
            return $record;
        }
    }
    private function encodeJsonFields(array $data): array {
        $fieldDefs = $this->getFieldDefs();
        if (empty($fieldDefs)) {
            return $data;
        }

        foreach ($data as $field => $value) {
            $fieldDef = $fieldDefs[$field] ?? null;
            if (!empty($fieldDef) && ($fieldDef['type'] ?? null) === 'collection') {
                if (is_string($value) || is_null($value)) {
                    $value = [$value];
                } elseif (!is_array($value)) {
                    $value = [(string) $value];
                }

                // Filter out empty strings or nulls if desired
                $value = array_filter($value, fn($v) => $v !== null && $v !== '');

                $data[$field] = json_encode(array_values($value));
            }
        }

        return $data;
    }

    private function decodeJsonFields(array $data): array {
        $fieldDefs = $this->getFieldDefs();
        if (empty($fieldDefs)) {
            return $data;
        }

        foreach ($data as $field => $value) {
            $fieldDef = $fieldDefs[$field] ?? null;

            if (!empty($fieldDef) && ($fieldDef['type'] ?? null) === 'collection') {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $data[$field] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                } elseif (is_array($value)) {
                    $data[$field] = $value;
                } else {
                    $data[$field] = [];
                }
            }
        }

        return $data;
    }
    private function getClassFromEntity(string $entityName): BaseEntity{
        $className = ucfirst($entityName);
        $fullClassName = "SepiaCore\\Entities\\$className\\$className";

        if (!class_exists($fullClassName)) {
            throw new \Exception($entityName . ' entity can not load Class '.$fullClassName);
        }
        if (!is_subclass_of($fullClassName, 'SepiaCore\Entities\BaseEntity')) {
            throw new \Exception("$className must be a subclass of BaseEntity.");
        }
        /** @var \SepiaCore\Entities\BaseEntity $entity */
        $tableName = strtolower($entityName);
        return new $fullClassName($tableName);
    }

    private function executeScript($data, string $event):array
    {
        $scriptArray = ScriptSandbox::getScriptArray($event, $this->getClassName());
        if(!empty($scriptArray)){
            $data = ScriptSandbox::execute($data, $scriptArray);
            return $data;
        }else{
            Log::logMessage("no custom code","debug");
            return $data;
        }
    }


}