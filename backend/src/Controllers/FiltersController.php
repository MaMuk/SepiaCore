<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use Illuminate\Database\Capsule\Manager as Capsule;

class FiltersController extends BaseController
{
    public function index(): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $entityName = trim(Flight::request()->query['entity'] ?? '');

        $query = Capsule::table('saved_filters')
            ->where('owner', $userId);

        if ($entityName !== '') {
            $query->where('entity', $entityName);
        }

        $records = $query
            ->orderBy('date_modified', 'DESC')
            ->get()
            ->map(fn($item) => (array) $item)
            ->all();

        $records = array_map([$this, 'hydrateFilterRecord'], $records);

        $this->jsonResponse([
            'records' => $records,
            'total' => count($records),
        ]);
    }

    public function show($id): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $record = $this->loadFilterRecord($id);
        if (!$record) {
            $this->jsonHalt(['error' => 'Filter not found'], 404);
        }

        if (($record['owner'] ?? null) !== $userId) {
            $this->jsonHalt(['error' => 'Forbidden'], 403);
        }

        $this->jsonResponse($record);
    }

    public function store(): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $data = Flight::request()->data->getData();

        $name = trim($data['name'] ?? '');
        $entityName = trim($data['entity'] ?? '');
        $definition = $data['definition'] ?? null;

        if ($name === '') {
            $this->jsonHalt(['error' => 'Filter name is required'], 400);
        }
        if ($entityName === '' || empty($GLOBALS['metadata']['entities'][$entityName])) {
            $this->jsonHalt(['error' => 'Invalid entity'], 400);
        }

        $normalizedDefinition = $this->normalizeDefinitionPayload($definition);
        if (!$normalizedDefinition) {
            $this->jsonHalt(['error' => 'Filter definition is required'], 400);
        }
        $this->validateFiltersForEntity($entityName, $normalizedDefinition['filters']);

        $payload = [
            'name' => $name,
            'entity' => $entityName,
            'definition' => json_encode($normalizedDefinition, JSON_UNESCAPED_SLASHES),
            'owner' => $userId,
        ];

        if (!empty($data['description'])) {
            $payload['description'] = $data['description'];
        }
        if (!empty($data['color'])) {
            $payload['color'] = $data['color'];
        }
        if (array_key_exists('tags', $data)) {
            $payload['tags'] = $this->normalizeTags($data['tags']);
        }
        if (array_key_exists('is_shared', $data)) {
            $payload['is_shared'] = $this->toBoolean($data['is_shared']);
        }

        $this->model = 'saved_filters';
        $this->entity = $this->getEntityClass('saved_filters');

        try {
            $stored = $this->entity->create($payload);
            $record = $this->loadFilterRecord($stored['id'] ?? null);
            $this->jsonResponse($record, 201);
        } catch (Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    public function update($id): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $record = $this->loadFilterRecord($id, false);
        if (!$record) {
            $this->jsonHalt(['error' => 'Filter not found'], 404);
        }
        if (($record['owner'] ?? null) !== $userId) {
            $this->jsonHalt(['error' => 'Forbidden'], 403);
        }

        $data = Flight::request()->data->getData();
        $updates = [];

        if (array_key_exists('name', $data)) {
            $name = trim($data['name'] ?? '');
            if ($name === '') {
                $this->jsonHalt(['error' => 'Filter name is required'], 400);
            }
            $updates['name'] = $name;
        }

        $entityName = $record['entity'] ?? '';
        if (array_key_exists('entity', $data)) {
            $entityName = trim($data['entity'] ?? '');
            if ($entityName === '' || empty($GLOBALS['metadata']['entities'][$entityName])) {
                $this->jsonHalt(['error' => 'Invalid entity'], 400);
            }
            $updates['entity'] = $entityName;
        }

        if (array_key_exists('definition', $data)) {
            $normalizedDefinition = $this->normalizeDefinitionPayload($data['definition']);
            if (!$normalizedDefinition) {
                $this->jsonHalt(['error' => 'Filter definition is required'], 400);
            }
            $this->validateFiltersForEntity($entityName, $normalizedDefinition['filters']);
            $updates['definition'] = json_encode($normalizedDefinition, JSON_UNESCAPED_SLASHES);
        }

        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if (array_key_exists('color', $data)) {
            $updates['color'] = $data['color'];
        }
        if (array_key_exists('tags', $data)) {
            $updates['tags'] = $this->normalizeTags($data['tags']);
        }
        if (array_key_exists('is_shared', $data)) {
            $updates['is_shared'] = $this->toBoolean($data['is_shared']);
        }

        if (empty($updates)) {
            $this->jsonHalt(['error' => 'No updates provided'], 400);
        }

        $this->model = 'saved_filters';
        $this->entity = $this->getEntityClass('saved_filters');

        try {
            $this->entity->update($id, $updates);
            $record = $this->loadFilterRecord($id);
            $this->jsonResponse($record);
        } catch (Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $record = $this->loadFilterRecord($id, false);
        if (!$record) {
            $this->jsonHalt(['error' => 'Filter not found'], 404);
        }
        if (($record['owner'] ?? null) !== $userId) {
            $this->jsonHalt(['error' => 'Forbidden'], 403);
        }

        $this->model = 'saved_filters';
        $this->entity = $this->getEntityClass('saved_filters');

        if ($this->entity->delete($id)) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonHalt(['error' => 'Delete failed'], 400);
        }
    }

    private function loadFilterRecord($id, bool $hydrate = true): ?array
    {
        if (!$id) {
            return null;
        }
        $this->model = 'saved_filters';
        $this->entity = $this->getEntityClass('saved_filters');
        $record = $this->entity->read($id);
        if (!$record) {
            return null;
        }

        return $hydrate ? $this->hydrateFilterRecord($record) : $record;
    }

    private function hydrateFilterRecord(array $record): array
    {
        $record['definition'] = $this->decodeDefinition($record['definition'] ?? null);
        $tags = $record['tags'] ?? null;
        if (!is_array($tags)) {
            $record['tags'] = $this->decodeTags($tags);
        }
        return $record;
    }

    private function decodeDefinition($definition): array
    {
        if (is_array($definition)) {
            return $definition;
        }
        if (is_string($definition)) {
            $decoded = json_decode($definition, true);
            return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }
        return [];
    }

    private function normalizeDefinitionPayload($definition): ?array
    {
        if (is_string($definition)) {
            $decoded = json_decode($definition, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return null;
            }
            $definition = $decoded;
        }

        if (!is_array($definition)) {
            return null;
        }

        $filters = $definition['filters'] ?? $definition;
        $normalizedFilters = $this->normalizeFilters($filters);
        if (empty($normalizedFilters)) {
            return null;
        }

        return [
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param mixed $filters
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    private function normalizeFilters($filters): array
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
     */
    private function validateFiltersForEntity(string $entityName, array $filters): void
    {
        $fields = $GLOBALS['metadata']['entities'][$entityName]['fields'] ?? [];
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            if (!$field) {
                continue;
            }
            $operator = strtolower($filter['operator'] ?? 'eq');
            if (in_array($operator, ['not_empty', 'empty'], true)) {
                continue;
            }
            $fieldType = $fields[$field]['type'] ?? null;
            if (!$this->isOperatorAllowedForType($operator, $fieldType)) {
                $this->jsonHalt([
                    'error' => "Operator '{$operator}' is not supported for '{$field}' ({$fieldType})"
                ], 400);
            }
        }
    }

    private function isOperatorAllowedForType(string $operator, ?string $fieldType): bool
    {
        if (!$fieldType) {
            return true;
        }
        if ($operator === 'empty') {
            return true;
        }

        $map = [
            'boolean' => ['eq'],
            'checkbox' => ['eq'],
            'select' => ['eq', 'in', 'not_empty'],
            'date' => ['eq', 'gt', 'gte', 'lt', 'lte', 'not_empty'],
            'datetime' => ['eq', 'gt', 'gte', 'lt', 'lte', 'not_empty'],
            'relationship' => ['eq', 'in', 'not_empty'],
        ];

        if (!array_key_exists($fieldType, $map)) {
            return true;
        }

        return in_array($operator, $map[$fieldType], true);
    }

    private function normalizeTags($tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map('strval', $tags)));
        }
        if (is_string($tags)) {
            $parts = array_map('trim', explode(',', $tags));
            return array_values(array_filter($parts, fn($tag) => $tag !== ''));
        }
        return [];
    }

    private function decodeTags($tags): array
    {
        if (is_array($tags)) {
            return $tags;
        }
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'y', 'on'], true);
        }
        return false;
    }
}
