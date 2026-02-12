<?php

namespace SepiaCore\Reports;

use Illuminate\Database\Query\Builder;

class FilterTranslator
{
    private array $fieldDefs;
    private array $allowedFields;
    private string $table;

    private const ALLOWED_OPERATORS = [
        'eq',
        'contains',
        'starts_with',
        'ends_with',
        'not_empty',
        'empty',
        'gt',
        'gte',
        'lt',
        'lte',
        'in'
    ];

    public function __construct(array $fieldDefs, string $table, array $allowedFields = [])
    {
        $this->fieldDefs = $fieldDefs;
        $this->table = $table;
        $this->allowedFields = $allowedFields ?: array_keys($fieldDefs);
    }

    /**
     * Normalize filter expression into canonical group/filters shape.
     * @param mixed $filters
     * @return array{group: string, filters: array<int, array<string, mixed>>}|null
     */
    public function normalizeFilterExpression($filters): ?array
    {
        if (!is_array($filters)) {
            return null;
        }

        if (array_key_exists('filters', $filters) && !array_key_exists('group', $filters)) {
            return $this->normalizeFilterExpression($filters['filters']);
        }

        if (array_key_exists('group', $filters) && array_key_exists('filters', $filters)) {
            $group = strtoupper((string) $filters['group']);
            $children = $filters['filters'];
            if (!is_array($children)) {
                return null;
            }

            $normalizedChildren = [];
            foreach ($children as $child) {
                $normalizedChild = $this->normalizeFilterExpressionChild($child);
                if ($normalizedChild !== null) {
                    $normalizedChildren[] = $normalizedChild;
                }
            }

            if (empty($normalizedChildren)) {
                return null;
            }

            return [
                'group' => $group,
                'filters' => $normalizedChildren,
            ];
        }

        $keys = array_keys($filters);
        $isAssoc = $keys !== range(0, count($keys) - 1);

        if ($isAssoc) {
            $normalizedFilters = $this->normalizeFilters($filters);
            if (empty($normalizedFilters)) {
                return null;
            }
            return [
                'group' => 'AND',
                'filters' => $normalizedFilters,
            ];
        }

        $normalizedChildren = [];
        foreach ($filters as $child) {
            $normalizedChild = $this->normalizeFilterExpressionChild($child);
            if ($normalizedChild !== null) {
                $normalizedChildren[] = $normalizedChild;
            }
        }

        if (empty($normalizedChildren)) {
            return null;
        }

        return [
            'group' => 'AND',
            'filters' => $normalizedChildren,
        ];
    }

    /**
     * Validate filter expression and return error list.
     * @param array{group: string, filters: array<int, array<string, mixed>>} $filters
     * @return array<int, string>
     */
    public function validateFilterExpression(array $filters): array
    {
        $errors = [];
        $this->validateFilterGroup($filters, $errors);
        return $errors;
    }

    /**
     * Apply a normalized filter expression to a query builder.
     * @param Builder $query
     * @param array{group: string, filters: array<int, array<string, mixed>>} $filters
     * @return void
     */
    public function applyToQuery(Builder $query, array $filters): void
    {
        $this->applyFilterGroup($query, $filters, false);
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
     * @param mixed $child
     * @return array<string, mixed>|null
     */
    private function normalizeFilterExpressionChild($child): ?array
    {
        if (!is_array($child)) {
            return null;
        }

        if (array_key_exists('group', $child) && array_key_exists('filters', $child)) {
            return $this->normalizeFilterExpression($child);
        }

        $field = $child['field'] ?? null;
        if (!$field) {
            return null;
        }

        return [
            'field' => $field,
            'operator' => $child['operator'] ?? 'eq',
            'value' => $child['value'] ?? null,
        ];
    }

    private function validateFilterGroup(array $group, array &$errors): void
    {
        $operator = strtoupper((string) ($group['group'] ?? ''));
        if (!in_array($operator, ['AND', 'OR'], true)) {
            $errors[] = "Filter group '{$operator}' is not supported";
            return;
        }

        $children = $group['filters'] ?? null;
        if (!is_array($children) || empty($children)) {
            $errors[] = 'No filters provided';
            return;
        }

        foreach ($children as $child) {
            $this->validateFilterNode($child, $errors);
        }
    }

    private function validateFilterNode($node, array &$errors): void
    {
        if (!is_array($node)) {
            return;
        }

        if (array_key_exists('group', $node) && array_key_exists('filters', $node)) {
            $this->validateFilterGroup($node, $errors);
            return;
        }

        $field = $node['field'] ?? null;
        if (!$field) {
            return;
        }

        if (!in_array($field, $this->allowedFields, true)) {
            $errors[] = "Field '{$field}' is not allowed";
            return;
        }

        $operator = strtolower((string) ($node['operator'] ?? 'eq'));
        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            $errors[] = "Operator '{$operator}' is not supported";
            return;
        }

        $fieldType = $this->getFieldType($field);
        if (!$this->isOperatorAllowedForType($operator, $fieldType)) {
            $errors[] = "Operator '{$operator}' is not supported for '{$field}' ({$fieldType})";
            return;
        }

        if (!in_array($operator, ['not_empty', 'empty'], true)) {
            $value = $node['value'] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[] = "Filter '{$field}' requires a value";
                return;
            }
            if ($operator === 'in' && count($this->normalizeListValue($value)) === 0) {
                $errors[] = "Filter '{$field}' requires a list of values";
                return;
            }
        }
    }

    private function applyFilterGroup(Builder $query, array $group, bool $useOr): void
    {
        $method = $useOr ? 'orWhere' : 'where';
        $query->{$method}(function (Builder $innerQuery) use ($group) {
            $operator = strtoupper((string) ($group['group'] ?? 'AND'));
            $children = $group['filters'] ?? [];
            foreach ($children as $child) {
                if (is_array($child) && array_key_exists('group', $child) && array_key_exists('filters', $child)) {
                    $this->applyFilterGroup($innerQuery, $child, $operator === 'OR');
                } else {
                    $this->applyFilterCondition($innerQuery, $child, $operator === 'OR');
                }
            }
        });
    }

    private function applyFilterCondition(Builder $query, $node, bool $useOr): void
    {
        if (!is_array($node)) {
            return;
        }

        $field = $node['field'] ?? null;
        if (!$field) {
            return;
        }

        $operator = strtolower((string) ($node['operator'] ?? 'eq'));
        $value = $node['value'] ?? null;
        $fieldType = $this->getFieldType($field);
        $column = $this->qualifyColumn($field);

        switch ($operator) {
            case 'contains':
                $this->applyLike($query, $column, $value, "%{$value}%", $useOr);
                return;
            case 'starts_with':
                $this->applyLike($query, $column, $value, "{$value}%", $useOr);
                return;
            case 'ends_with':
                $this->applyLike($query, $column, $value, "%{$value}", $useOr);
                return;
            case 'in':
                $list = $this->normalizeListValue($value);
                if ($useOr) {
                    $query->orWhereIn($column, $list);
                } else {
                    $query->whereIn($column, $list);
                }
                return;
            case 'gt':
            case 'gte':
            case 'lt':
            case 'lte':
                $operatorMap = [
                    'gt' => '>',
                    'gte' => '>=',
                    'lt' => '<',
                    'lte' => '<=',
                ];
                $sqlOperator = $operatorMap[$operator];
                $normalizedValue = $this->normalizeComparableValue($value, $fieldType);
                if ($useOr) {
                    $query->orWhere($column, $sqlOperator, $normalizedValue);
                } else {
                    $query->where($column, $sqlOperator, $normalizedValue);
                }
                return;
            case 'not_empty':
                $this->applyNotEmpty($query, $column, $fieldType, $useOr);
                return;
            case 'empty':
                $this->applyEmpty($query, $column, $fieldType, $useOr);
                return;
            case 'eq':
            default:
                $normalizedValue = $this->normalizeComparableValue($value, $fieldType);
                if ($useOr) {
                    $query->orWhere($column, '=', $normalizedValue);
                } else {
                    $query->where($column, '=', $normalizedValue);
                }
                return;
        }
    }

    private function applyLike(Builder $query, string $column, $value, string $pattern, bool $useOr): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($useOr) {
            $query->orWhere($column, 'like', $pattern);
        } else {
            $query->where($column, 'like', $pattern);
        }
    }

    private function applyNotEmpty(Builder $query, string $column, ?string $fieldType, bool $useOr): void
    {
        if (in_array($fieldType, ['boolean', 'checkbox'], true)) {
            if ($useOr) {
                $query->orWhere($column, '=', 1);
            } else {
                $query->where($column, '=', 1);
            }
            return;
        }

        $method = $useOr ? 'orWhere' : 'where';
        $query->{$method}(function (Builder $innerQuery) use ($column) {
            $innerQuery->whereNotNull($column)
                ->where($column, '!=', '');
        });
    }

    private function applyEmpty(Builder $query, string $column, ?string $fieldType, bool $useOr): void
    {
        if (in_array($fieldType, ['boolean', 'checkbox'], true)) {
            if ($useOr) {
                $query->orWhere($column, '=', 0);
            } else {
                $query->where($column, '=', 0);
            }
            return;
        }

        $method = $useOr ? 'orWhere' : 'where';
        $query->{$method}(function (Builder $innerQuery) use ($column) {
            $innerQuery->whereNull($column)
                ->orWhere($column, '=', '');
        });
    }

    private function getFieldType(string $field): ?string
    {
        return $this->fieldDefs[$field]['type'] ?? null;
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

    private function qualifyColumn(string $field): string
    {
        return $this->table . '.' . $field;
    }

    private function normalizeListValue($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn($item) => $item !== null && $item !== ''));
        }
        if ($value === null || $value === '') {
            return [];
        }
        $parts = explode(',', (string) $value);
        return array_values(array_filter(array_map('trim', $parts), fn($item) => $item !== ''));
    }

    private function normalizeComparableValue($value, ?string $fieldType)
    {
        if (in_array($fieldType, ['date', 'datetime'], true)) {
            return $this->normalizeDateValue($value, $fieldType);
        }
        if (in_array($fieldType, ['boolean', 'checkbox'], true)) {
            return $this->normalizeBoolean($value);
        }
        return $value;
    }

    private function normalizeBoolean($value): ?int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value)) {
            return $value === 0 ? 0 : 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', '1', 'yes', 'y', 'on'], true)) {
                return 1;
            }
            if (in_array($normalized, ['false', '0', 'no', 'n', 'off'], true)) {
                return 0;
            }
        }
        return null;
    }

    private function normalizeDateValue($value, ?string $fieldType)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 1000000000000) {
                $numeric = floor($numeric / 1000);
            }
            return date($fieldType === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s', (int) $numeric);
        }
        if (is_string($value)) {
            $clean = str_replace('T', ' ', $value);
            if ($fieldType === 'datetime' && strlen($clean) === 16) {
                $clean .= ':00';
            }
            if ($fieldType === 'date') {
                return substr($clean, 0, 10);
            }
            return $clean;
        }
        return $value;
    }
}
