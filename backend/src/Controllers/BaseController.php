<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;

abstract class BaseController
{
    protected $entity;
    protected $model;

    /**
     * Initializes controller with optional entity model.
     * @param string|null $model Entity model name
     */
    public function __construct($model = null)
    {
        $this->model = $model;
        if ($model) {
            $this->entity = $this->getEntityClass($model);
        }
    }

    /**
     * Gets entity class instance by model name.
     * @param string $model Model name
     * @return object Entity instance
     */
    public static function getEntityClass($model)
    {
        $className = 'SepiaCore\\Entities\\' . ucfirst($model) . '\\' . ucfirst($model);

        if (!class_exists($className)) {
            Flight::jsonHalt(["error" => "Entity '$className' not found"], 404);
        }

        if (!is_subclass_of($className, 'SepiaCore\Entities\BaseEntity')) {
            Flight::jsonHalt(["error" => "'$className' is not a valid entity class"], 400);
        }

        $tableName = strtolower($model);
        return new $className($tableName);
    }

    /**
     * Gets relationship data for a record.
     * @param array $record Record data
     * @param array $fieldDefinitions Field definitions
     * @return array Relationship data
     */
    protected function getRelationshipData($record, $fieldDefinitions): array
    {
        $relationships = [];

        foreach ($record as $key => $value) {
            if (!empty($fieldDefinitions[$key]) && $fieldDefinitions[$key]['type'] === 'relationship') {
                $relEntity = $this->getEntityClass($fieldDefinitions[$key]['entity']);
                $relEntityRecord = $relEntity->read($value);

                $relationships[$key] = [
                    'id' => $value,
                    'entity' => $fieldDefinitions[$key]['entity'],
                    'name' => $relEntityRecord['name'] ?? '',
                ];
            }
        }

        return $relationships;
    }

    /**
     * Gets relationship data for multiple records.
     * @param array $records Array of records
     * @param array $fieldDefinitions Field definitions
     * @return array Relationship data indexed by record ID
     */
    protected function getRelationshipDataForList($records, $fieldDefinitions): array
    {
        $relationships = [];

        foreach ($records as $record) {
            foreach ($record as $key => $value) {
                if (!empty($value) && !empty($fieldDefinitions[$key]) && $fieldDefinitions[$key]['type'] === 'relationship') {
                    $relEntity = $this->getEntityClass($fieldDefinitions[$key]['entity']);
                    $relEntityRecord = $relEntity->read($value);

                    $relationships[$record['id']][$key] = [
                        'id' => $value,
                        'entity' => $fieldDefinitions[$key]['entity'],
                        'name' => $relEntityRecord['name'] ?? '',
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Gets common view data for templates.
     * @param string $model Entity model name
     * @return array Common view data
     */
    protected function getCommonViewData($model): array
    {
        return [
            'field_definitions' => $GLOBALS['metadata']['entities'][$model]['fields'] ?? [],
            'settings' => $GLOBALS['settings'],
            'model' => $model,
        ];
    }

    /**
     * Gets pagination parameters from request.
     * @return array{page: int, limit: int, search: string|null, sortBy: string, sortOrder: string}
     */
    protected function getPaginationParams(): array
    {
        $request = Flight::request();

        return [
            'page' => max(1, intval($request->query['page'] ?? 1)),
            'limit' => max(1, intval($request->query['limit'] ?? 10)),
            'search' => $request->query['search'] ?? null,
            'sortBy' => $request->query['sort'] ?? 'date_modified',
            'sortOrder' => strtoupper($request->query['order'] ?? 'DESC'),
        ];
    }

    /**
     * Checks if HTML rendering is requested.
     * @return bool True if renderToHtml query parameter is set
     */
    protected function shouldRenderToHtml(): bool
    {
        return Flight::request()->query['renderToHtml'] ?? false;
    }

    /**
     * Sends JSON response.
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return void
     */
    protected function jsonResponse($data, $status = 200): void
    {
        Flight::json($data, $status);
    }

    /**
     * Sends JSON response and halts execution.
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return void
     */
    protected function jsonHalt($data, $status = 200): void
    {
        Flight::jsonHalt($data, $status);
    }
}