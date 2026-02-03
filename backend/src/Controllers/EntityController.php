<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use SepiaCore\Utilities\Log;

class EntityController extends BaseController
{
    /**
     * Lists all records with pagination.
     * @param string $model Entity model name
     * @return void
     */
    public function index($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $request = Flight::request();
        $params = $this->getPaginationParams();

        $records = $this->entity->read(
            null,
            $params['page'],
            $params['limit'],
            $params['sortBy'],
            $params['sortOrder'],
            $params['search']
        );


        $viewData = $this->prepareListViewData($records, $params);
        Log::logMessage('Twig rendering is deprecated in EntityController::index()', 'warning');
        $data = ['innerHtml' => $this->entity->twig->render('list.html.twig', $viewData)];
        $data['records'] = $records;
        $data['total'] = $this->entity->count();
        $data['relationship'] = $viewData['relationship'] ?? [];


        $this->jsonResponse($data);
    }

    /**
     * Shows a specific record.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function show($model, $id): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $params = $this->getPaginationParams();
        $record = $this->entity->read($id);

        if (!$record) {
            $this->jsonHalt(['error' => 'Record not found'], 404);
        }

        $data = $this->prepareDetailViewData($record, $id, $params['sortBy'], $params['sortOrder']);

        $this->jsonResponse($data);
    }

    /**
     * Shows edit form for a record.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function edit($model, $id): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $record = $this->entity->read($id);

        if (!$record) {
            $this->jsonHalt(['error' => 'Record not found'], 404);
        }

        $data = $this->prepareEditViewData($record);

        $this->jsonResponse($data);
    }

    /**
     * Shows create form for a new record.
     * @param string $model Entity model name
     * @return void
     */
    public function create($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $record = $this->entity->newRecord();
        $data = $this->prepareEditViewData($record);

        $this->jsonResponse($data);
    }

    /**
     * Stores a new record.
     * @param string $model Entity model name
     * @return void
     */
    public function store($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $data = Flight::request()->data->getData();
        $params = $this->getPaginationParams();

        if (isset($data['id'])) {
            unset($data['id']);
        }
        $data = $this->filterEntityFields($data);

        try {
            $storedEntity = $this->entity->create($data);
            $record = $this->entity->read($storedEntity['id']);

            $responseData = $this->prepareDetailViewData(
                $record,
                $storedEntity['id'],
                $params['sortBy'],
                $params['sortOrder']
            );

            $this->jsonResponse($responseData);
        } catch (Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Updates an existing record.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function update($model, $id): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $data = Flight::request()->data->getData();
        $params = $this->getPaginationParams();

        if (isset($data['id'])) {
            unset($data['id']);
        }
        $data = $this->filterEntityFields($data);

        $storedEntity = $this->entity->read($id);

        if (!$storedEntity) {
            $this->jsonHalt(['error' => 'Record not found'], 404);
        }

        try {
            $this->entity->update($id, $data);
            $record = $this->entity->read($id);

            $responseData = $this->prepareDetailViewData(
                $record,
                $id,
                $params['sortBy'],
                $params['sortOrder']
            );

            $this->jsonResponse($responseData);
        } catch (Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Deletes a record.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function destroy($model, $id): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        if ($this->entity->delete($id)) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonHalt(['success' => false], 400);
        }
    }

    /**
     * Gets record count for entity.
     * @param string $model Entity model name
     * @return void
     */
    public function count($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $this->jsonResponse(['count' => $this->entity->count()]);
    }

    /**
     * Filters records by field parameters or stored filter id.
     * @param string $model Entity model name
     * @return void
     */
    public function filter($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $request = Flight::request();
        $params = $this->getPaginationParams();
        $payload = $request->data->getData();

        $filterId = $payload['filter_id'] ?? ($request->query['filter_id'] ?? null);
        $filters = $payload['filters'] ?? [];
        $storedFilter = null;

        if ($filterId !== null && $filterId !== '') {
            $storedFilter = $this->getStoredFilterDefinition($model, $filterId, $payload);
            if (!$storedFilter) {
                $this->jsonHalt(['error' => "Stored filter '$filterId' not found"], 404);
            }
            if (empty($filters)) {
                $filters = $storedFilter['filters'] ?? [];
            }
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        if (empty($normalizedFilters)) {
            $this->jsonHalt(['error' => 'No filters provided'], 400);
        }

        $result = $this->runFilterScan($normalizedFilters, $params, $payload);
        $response = [
            'records' => $result['records'],
            'total' => $result['total'],
        ];
        if ($storedFilter) {
            $response['filter'] = $storedFilter;
        }

        $this->jsonResponse($response);
    }

    /**
     * @param string $model
     * @param mixed $filterId
     * @param array $payload
     * @return array|null
     */
    protected function getStoredFilterDefinition(string $model, $filterId, array $payload): ?array
    {
        if ((int) $filterId !== 1) {
            return null;
        }

        $fields = $GLOBALS['metadata']['entities'][$model]['fields'] ?? [];
        $targetField = 'name';
        if (!array_key_exists($targetField, $fields)) {
            $targetField = array_key_exists('title', $fields) ? 'title' : null;
        }
        if (!$targetField) {
            $targetField = array_key_first($fields) ?: 'id';
        }

        $value = $payload['value'] ?? ($payload['query'] ?? null);
        $filters = [];
        if ($value !== null && $value !== '') {
            $filters[] = [
                'field' => $targetField,
                'operator' => 'contains',
                'value' => $value,
            ];
        } else {
            $filters[] = [
                'field' => $targetField,
                'operator' => 'not_empty',
            ];
        }

        return [
            'id' => 1,
            'name' => "Mock filter: {$targetField}",
            'filters' => $filters,
        ];
    }

    /**
     * @param mixed $filters
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    protected function normalizeFilters($filters): array
    {
        if (!is_array($filters)) {
            return [];
        }

        $normalized = [];
        $keys = array_keys($filters);
        $isAssoc = $keys !== range(0, count($keys) - 1);

        if ($isAssoc) {
            foreach ($filters as $field => $value) {
                if ($field === '') {
                    continue;
                }
                $normalized[] = [
                    'field' => $field,
                    'operator' => 'eq',
                    'value' => $value,
                ];
            }
            return $normalized;
        }

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $field = $filter['field'] ?? null;
            if (!$field) {
                continue;
            }
            $normalized[] = [
                'field' => $field,
                'operator' => $filter['operator'] ?? 'eq',
                'value' => $filter['value'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     * @param array $params
     * @param array $payload
     * @return array{records: array, total: int}
     */
    protected function runFilterScan(array $filters, array $params, array $payload): array
    {
        $limit = max(1, intval($payload['limit'] ?? $params['limit']));
        $page = max(1, intval($payload['page'] ?? $params['page']));
        $sortBy = $payload['sort'] ?? $params['sortBy'];
        $sortOrder = strtoupper($payload['order'] ?? $params['sortOrder']);
        $batchSize = min(200, max(50, $limit * 3));

        $offset = ($page - 1) * $limit;
        $matches = [];
        $totalMatches = 0;
        $pageIndex = 1;

        while (true) {
            $records = $this->entity->read(null, $pageIndex, $batchSize, $sortBy, $sortOrder, null);
            if (!is_array($records) || count($records) === 0) {
                break;
            }

            foreach ($records as $record) {
                if (!$this->recordMatchesFilters($record, $filters)) {
                    continue;
                }

                if ($totalMatches >= $offset && count($matches) < $limit) {
                    $matches[] = $record;
                }
                $totalMatches++;
            }

            if (count($records) < $batchSize) {
                break;
            }

            $pageIndex++;
        }

        return [
            'records' => $matches,
            'total' => $totalMatches,
        ];
    }

    /**
     * @param array $record
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     * @return bool
     */
    protected function recordMatchesFilters(array $record, array $filters): bool
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            if (!$field) {
                continue;
            }

            $operator = strtolower($filter['operator'] ?? 'eq');
            $expected = $filter['value'] ?? null;
            $actual = $record[$field] ?? null;

            switch ($operator) {
                case 'contains':
                    if ($actual === null) {
                        return false;
                    }
                    if (stripos((string) $actual, (string) $expected) === false) {
                        return false;
                    }
                    break;
                case 'starts_with':
                    if ($actual === null) {
                        return false;
                    }
                    $actualString = (string) $actual;
                    $expectedString = (string) $expected;
                    if (stripos($actualString, $expectedString) !== 0) {
                        return false;
                    }
                    break;
                case 'ends_with':
                    if ($actual === null) {
                        return false;
                    }
                    $actualString = (string) $actual;
                    $expectedString = (string) $expected;
                    if ($expectedString === '' || strcasecmp(substr($actualString, -strlen($expectedString)), $expectedString) !== 0) {
                        return false;
                    }
                    break;
                case 'in':
                    $list = is_array($expected) ? $expected : explode(',', (string) $expected);
                    if (!in_array($actual, $list, false)) {
                        return false;
                    }
                    break;
                case 'gt':
                    if (floatval($actual) <= floatval($expected)) {
                        return false;
                    }
                    break;
                case 'gte':
                    if (floatval($actual) < floatval($expected)) {
                        return false;
                    }
                    break;
                case 'lt':
                    if (floatval($actual) >= floatval($expected)) {
                        return false;
                    }
                    break;
                case 'lte':
                    if (floatval($actual) > floatval($expected)) {
                        return false;
                    }
                    break;
                case 'not_empty':
                    if ($actual === null || $actual === '') {
                        return false;
                    }
                    break;
                default:
                    if (is_array($expected)) {
                        if (!in_array($actual, $expected, false)) {
                            return false;
                        }
                    } elseif (is_numeric($expected) && is_numeric($actual)) {
                        if (floatval($actual) !== floatval($expected)) {
                            return false;
                        }
                    } else {
                        if ((string) $actual !== (string) $expected) {
                            return false;
                        }
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Prepares data for list view.
     * @param array $records Records array
     * @param array $params Pagination parameters
     * @return array Prepared view data
     */
    protected function prepareListViewData($records, $params): array
    {
        $fieldDefinitions = $GLOBALS['metadata']['entities'][$this->model]['fields'] ?? [];
        $totalItems = $this->entity->count();

        return [
            'records' => $records,
            'entity' => $this->model,
            'field_definitions' => $fieldDefinitions,
            'relationship' => $this->getRelationshipDataForList($records, $fieldDefinitions),
            'pagination' => [
                'page' => $params['page'],
                'limit' => $params['limit'],
                'totalPages' => ceil($totalItems / $params['limit']),
                'sortBy' => $params['sortBy'],
                'sortOrder' => $params['sortOrder'],
                'disable_pagination' => $this->entity->noLimit === true || $totalItems < 1,
            ],
        ];
    }

    /**
     * Filters incoming data to fields defined in metadata.
     * @param array $data Incoming request data
     * @return array Filtered data
     */
    protected function filterEntityFields(array $data): array
    {
        $fieldDefinitions = $GLOBALS['metadata']['entities'][$this->model]['fields'] ?? [];
        if (empty($fieldDefinitions)) {
            return $data;
        }

        return array_intersect_key($data, $fieldDefinitions);
    }

    /**
     * Prepares data for detail view.
     * @param array $record Record data
     * @param string $id Record ID
     * @param string $sortBy Sort field
     * @param string $sortOrder Sort order
     * @return array Prepared view data
     */
    protected function prepareDetailViewData($record, $id, $sortBy, $sortOrder): array
    {
        $fieldDefinitions = $GLOBALS['metadata']['entities'][$this->model]['fields'] ?? [];

        $data = $this->getCommonViewData($this->model);
        $data['record'] = $record;
        $data['relationship'] = $this->getRelationshipData($record, $fieldDefinitions);
        $data['layout_detail_view'] = $GLOBALS['metadata']['entities'][$this->model]['module_views']['record']['layout'] ?? [];
        $data['subpanel_view'] = $GLOBALS['metadata']['entities'][$this->model]['module_views']['subpanels'] ?? [];
        $data['prevId'] = $this->entity->previousRecord($id, $sortBy, $sortOrder);
        $data['nextId'] = $this->entity->nextRecord($id, $sortBy, $sortOrder);

        Log::logMessage('Twig rendering is deprecated in EntityController::prepareDetailViewData()', 'warning');
        $data['innerHtml'] = $this->entity->twig->render('detail.html.twig', $data);

        return $data;
    }

    /**
     * Prepares data for edit view.
     * @param array $record Record data
     * @return array Prepared view data
     */
    protected function prepareEditViewData($record): array
    {
        $fieldDefinitions = $GLOBALS['metadata']['entities'][$this->model]['fields'] ?? [];

        $data = $this->getCommonViewData($this->model);
        $data['record'] = $record;
        $data['relationship'] = $this->getRelationshipData($record, $fieldDefinitions);
        $data['layout_detail_view'] = $GLOBALS['metadata']['entities'][$this->model]['module_views']['record']['layout'] ?? [];
        Log::logMessage('Twig rendering is deprecated in EntityController::prepareEditViewData()', 'warning');
        $data['innerHtml'] = $this->entity->twig->render('edit.html.twig', $data);

        return $data;
    }
}
