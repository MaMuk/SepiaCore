<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Ramsey\Uuid\Uuid;

class FilesController extends BaseController
{
    private const UPLOAD_DIR = 'upload';

    public function upload(): void
    {
        $this->ensureFilesInfrastructure();

        $request = Flight::request();
        $payload = $request->data->getData();

        $entity = $payload['entity'] ?? null;
        $field = $payload['field'] ?? null;

        if (!$entity || !$field) {
            $this->jsonHalt(['error' => 'Entity and field are required for file upload.'], 400);
        }

        $fieldDef = $GLOBALS['metadata']['entities'][$entity]['fields'][$field] ?? null;
        if (!$fieldDef || ($fieldDef['type'] ?? null) !== 'file') {
            $this->jsonHalt(['error' => 'Invalid file field.'], 400);
        }

        $uploadedFiles = $request->getUploadedFiles();
        $uploaded = $uploadedFiles['file'] ?? null;
        if (is_array($uploaded)) {
            if (count($uploaded) !== 1) {
                $this->jsonHalt(['error' => 'Multiple files are not supported for this field.'], 400);
            }
            $uploaded = $uploaded[0];
        }

        if (!$uploaded) {
            $this->jsonHalt(['error' => 'No file uploaded.'], 400);
        }

        $originalName = $uploaded->getClientFilename();
        $size = $uploaded->getSize();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = $this->detectMimeType($uploaded->getTempName(), $uploaded->getClientMediaType());

        $allowedTypes = $this->normalizeAllowedTypes($fieldDef['allowedTypes'] ?? []);
        if (!empty($allowedTypes) && !$this->mimeTypeAllowed($mimeType, $allowedTypes)) {
            $this->jsonHalt(['error' => 'File type is not allowed.'], 400);
        }

        $maxSize = $this->normalizeMaxSize($fieldDef['maxSize'] ?? null);
        if ($maxSize !== null && $size > $maxSize) {
            $this->jsonHalt(['error' => 'File exceeds the maximum allowed size.'], 400);
        }

        $uuid = Uuid::uuid4()->toString();
        $prefix = substr($uuid, 0, 2);
        $relativePath = self::UPLOAD_DIR . '/' . $prefix . '/' . $uuid . ($extension ? '.' . $extension : '');

        $this->ensureUploadDirectory(dirname($relativePath));

        try {
            $uploaded->moveTo($relativePath);
        } catch (Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }

        $now = date('Y-m-d H:i:s');
        $record = [
            'id' => $uuid,
            'name' => $originalName,
            'path' => $relativePath,
            'original_name' => $originalName,
            'size' => $size,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'owner' => $GLOBALS['user_id'] ?? null,
            'date_created' => $now,
            'date_modified' => $now,
        ];

        $insertRecord = $this->filterRecordByColumns($record);
        Capsule::table('files')->insert($insertRecord);

        $this->jsonResponse(['file' => $this->formatFileRecord($insertRecord)]);
    }

    public function download(string $id): void
    {
        $this->ensureFilesInfrastructure();

        $record = $this->getFileRecord($id);
        if (!$record) {
            $this->jsonHalt(['error' => 'File not found.'], 404);
        }

        $relativePath = $record['path'] ?? '';
        $absolutePath = ROOT_DIR . '/' . ltrim($relativePath, '/');

        if (!$this->isPathInUploads($absolutePath) || !is_file($absolutePath)) {
            $this->jsonHalt(['error' => 'File not found.'], 404);
        }

        $mimeType = $record['mime_type'] ?? 'application/octet-stream';
        $originalName = $record['name'] ?? ($record['original_name'] ?? basename($absolutePath));
        $disposition = str_starts_with($mimeType, 'image/') ? 'inline' : 'attachment';
        $safeName = str_replace('"', '\\"', $originalName);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
        header('X-Content-Type-Options: nosniff');

        if (is_file($absolutePath)) {
            header('Content-Length: ' . filesize($absolutePath));
        }

        readfile($absolutePath);
        exit();
    }

    public function metadata(string $id): void
    {
        $this->ensureFilesInfrastructure();

        $record = $this->getFileRecord($id);
        if (!$record) {
            $this->jsonHalt(['error' => 'File not found.'], 404);
        }

        $this->jsonResponse(['file' => $this->formatFileRecord($record)]);
    }

    private function ensureFilesInfrastructure(): void
    {
        $this->ensureFilesTable();
        $this->ensureFilesMetadata();
    }

    private function ensureFilesTable(): void
    {
        if (!Capsule::schema()->hasTable('files')) {
            Capsule::schema()->create('files', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->integer('size')->nullable();
                $table->string('mime_type')->nullable();
                $table->string('extension')->nullable();
                $table->uuid('owner')->nullable()->index();
                $table->timestamp('date_created')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('date_modified')->default(Capsule::raw('CURRENT_TIMESTAMP'));

                $table->foreign('owner')->references('id')->on('users')->onDelete('cascade');
            });
            return;
        }

        $this->ensureFilesTableColumns();
    }

    private function ensureFilesMetadata(): void
    {
        if (isset($GLOBALS['metadata']['entities']['files'])) {
            return;
        }

        $GLOBALS['metadata']['entities']['files'] = [
            'fields' => [
                'id' => ['type' => 'uuid'],
                'name' => ['type' => 'string'],
                'path' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
                'mime_type' => ['type' => 'string'],
                'extension' => ['type' => 'string'],
                'owner' => ['type' => 'relationship', 'entity' => 'users'],
                'date_created' => ['type' => 'datetime', 'readonly' => true],
                'date_modified' => ['type' => 'datetime', 'readonly' => true],
            ],
            'module_views' => [
                'record' => [
                    'layout' => [
                        ['name', 'owner'],
                        ['mime_type', 'size'],
                        ['date_created', 'date_modified'],
                    ],
                ],
                'list' => [
                    'isdefault' => true,
                    'layout' => [
                        'name',
                        'mime_type',
                        'size',
                        'date_created',
                    ],
                ],
                'subpanels' => [],
            ],
        ];

        if (!isset($GLOBALS['metadata']['protected_entities'])) {
            $GLOBALS['metadata']['protected_entities'] = [];
        }

        if (!in_array('files', $GLOBALS['metadata']['protected_entities'], true)) {
            $GLOBALS['metadata']['protected_entities'][] = 'files';
        }

        if (function_exists('saveMetadata')) {
            saveMetadata(null, null);
        }
    }

    private function ensureUploadDirectory(string $relativeDir): void
    {
        $relativeDir = trim($relativeDir, '/');
        $targetDir = ROOT_DIR . '/' . $relativeDir;

        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                $this->jsonHalt(['error' => 'Failed to create upload directory.'], 500);
            }
        }

        if (!is_writable($targetDir)) {
            $this->jsonHalt(['error' => 'Upload directory is not writable.'], 500);
        }
    }

    private function detectMimeType(string $tmpPath, ?string $fallback = null): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if ($detected) {
                return $detected;
            }
        }

        if ($fallback) {
            return $fallback;
        }

        $detected = mime_content_type($tmpPath);
        return $detected ?: 'application/octet-stream';
    }

    private function normalizeAllowedTypes($allowedTypes): array
    {
        if (is_string($allowedTypes)) {
            $allowedTypes = array_map('trim', explode(',', $allowedTypes));
        }
        if (!is_array($allowedTypes)) {
            return [];
        }
        return array_values(array_filter(array_map('strtolower', $allowedTypes)));
    }

    private function normalizeMaxSize($maxSize): ?int
    {
        if ($maxSize === null || $maxSize === '') {
            return null;
        }
        $numeric = is_numeric($maxSize) ? (int) $maxSize : null;
        return $numeric > 0 ? $numeric : null;
    }

    private function mimeTypeAllowed(string $mimeType, array $allowedTypes): bool
    {
        $mimeType = strtolower($mimeType);
        foreach ($allowedTypes as $allowed) {
            if ($allowed === $mimeType) {
                return true;
            }
            if (str_ends_with($allowed, '/*')) {
                $prefix = substr($allowed, 0, -1);
                if (str_starts_with($mimeType, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getFileRecord(string $id): ?array
    {
        $record = Capsule::table('files')->where('id', $id)->first();
        return $record ? (array) $record : null;
    }

    private function formatFileRecord(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'path' => $record['path'] ?? null,
            'name' => $record['name'] ?? ($record['original_name'] ?? null),
            'originalName' => $record['original_name'] ?? null,
            'size' => isset($record['size']) ? (int) $record['size'] : null,
            'mimeType' => $record['mime_type'] ?? null,
            'extension' => $record['extension'] ?? null,
            'createdAt' => $record['date_created'] ?? null,
        ];
    }

    private function ensureFilesTableColumns(): void
    {
        $schema = Capsule::schema();
        $missing = [];

        if (!$schema->hasColumn('files', 'name')) {
            $missing['name'] = 'string';
        }
        if (!$schema->hasColumn('files', 'path')) {
            $missing['path'] = 'string';
        }
        if (!$schema->hasColumn('files', 'original_name')) {
            $missing['original_name'] = 'string';
        }
        if (!$schema->hasColumn('files', 'size')) {
            $missing['size'] = 'integer';
        }
        if (!$schema->hasColumn('files', 'mime_type')) {
            $missing['mime_type'] = 'string';
        }
        if (!$schema->hasColumn('files', 'extension')) {
            $missing['extension'] = 'string';
        }
        if (!$schema->hasColumn('files', 'owner')) {
            $missing['owner'] = 'uuid';
        }
        if (!$schema->hasColumn('files', 'date_created')) {
            $missing['date_created'] = 'timestamp';
        }
        if (!$schema->hasColumn('files', 'date_modified')) {
            $missing['date_modified'] = 'timestamp';
        }

        if (empty($missing)) {
            return;
        }

        $schema->table('files', function (Blueprint $table) use ($missing) {
            foreach ($missing as $column => $type) {
                switch ($type) {
                    case 'string':
                        $table->string($column)->nullable();
                        break;
                    case 'integer':
                        $table->integer($column)->nullable();
                        break;
                    case 'uuid':
                        $table->uuid($column)->nullable()->index();
                        break;
                    case 'timestamp':
                        $table->timestamp($column)->nullable()->default(Capsule::raw('CURRENT_TIMESTAMP'));
                        break;
                }
            }
        });

        if ($schema->hasColumn('files', 'name') && $schema->hasColumn('files', 'original_name')) {
            Capsule::statement("UPDATE files SET name = original_name WHERE (name IS NULL OR name = '') AND original_name IS NOT NULL");
        }
    }

    private function filterRecordByColumns(array $record): array
    {
        $schema = Capsule::connection()->getSchemaBuilder();
        $columns = $schema->getColumnListing('files');
        if (empty($columns)) {
            return $record;
        }
        return array_intersect_key($record, array_flip($columns));
    }

    private function isPathInUploads(string $absolutePath): bool
    {
        $uploadsRoot = realpath(ROOT_DIR . '/' . self::UPLOAD_DIR);
        $targetPath = realpath($absolutePath);
        if (!$uploadsRoot || !$targetPath) {
            return false;
        }
        return str_starts_with($targetPath, $uploadsRoot);
    }
}
