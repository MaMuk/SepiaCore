<?php

namespace SepiaCore\Reports;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

class SqlReportEngine implements ReportEngine
{
    private const ALLOWED_CHARTS = ['pie', 'bar', 'line', 'funnel'];
    private const ALLOWED_METRICS = ['count', 'sum', 'avg', 'min', 'max'];
    private const ALLOWED_BUCKETS = ['day', 'week', 'month', 'quarter', 'year', 'none'];
    private const ALLOWED_ORDER_BY = ['value', 'label'];
    private const ALLOWED_ORDER_DIR = ['asc', 'desc'];

    private const MAX_LIMIT = 200;

    public function run(array $definition, array $context): array
    {
        $validation = $this->validate($definition, $context);
        if (empty($validation['valid'])) {
            throw new ReportValidationException($validation['errors'] ?? [], $validation['warnings'] ?? [], $validation['definition'] ?? []);
        }

        $normalized = $validation['definition'] ?? $definition;
        $warnings = $validation['warnings'] ?? [];

        $entity = $normalized['entity'];
        $table = strtolower($entity);
        $driver = Capsule::connection()->getDriverName();
        $fieldDefs = $GLOBALS['metadata']['entities'][$entity]['fields'] ?? [];

        $filterTranslator = new FilterTranslator($fieldDefs, $table);
        $baseFilters = $this->resolveFilters($normalized, $context, $filterTranslator);

        if ($normalized['chartType'] === 'funnel') {
            return $this->runFunnel($normalized, $context, $filterTranslator, $baseFilters, $warnings, $fieldDefs, $table, $driver);
        }

        $result = $this->runAggregate($normalized, $filterTranslator, $baseFilters, $warnings, $fieldDefs, $table, $driver);
        return $result;
    }

    public function validate(array $definition, array $context): array
    {
        $normalized = $this->normalizeDefinition($definition);
        $errors = [];
        $warnings = [];

        $entity = $normalized['entity'] ?? '';
        if ($entity === '') {
            $errors[] = 'Entity is required';
        }

        $entities = $GLOBALS['metadata']['entities'] ?? [];
        if ($entity !== '' && empty($entities[$entity])) {
            $errors[] = "Entity '{$entity}' not found";
        }

        if ($entity !== '' && $this->isProtectedEntity($entity)) {
            $errors[] = "Entity '{$entity}' is not available for reporting";
        }

        $chartType = $normalized['chartType'] ?? '';
        if ($chartType === '' || !in_array($chartType, self::ALLOWED_CHARTS, true)) {
            $errors[] = 'Chart type is required';
        }

        $metric = $normalized['metric'] ?? [];
        $metricType = $metric['type'] ?? 'count';
        if (!in_array($metricType, self::ALLOWED_METRICS, true)) {
            $errors[] = "Metric '{$metricType}' is not supported";
        }

        $metricField = $metric['field'] ?? null;
        $fieldDefs = ($entity !== '' && !empty($entities[$entity])) ? ($entities[$entity]['fields'] ?? []) : [];
        if ($metricType !== 'count') {
            if (!$metricField) {
                $errors[] = 'Metric field is required for this metric type';
            } elseif (empty($fieldDefs[$metricField])) {
                $errors[] = "Metric field '{$metricField}' not found";
            } elseif (!$this->isNumericField($fieldDefs[$metricField])) {
                $errors[] = "Metric field '{$metricField}' must be numeric";
            }
        }

        $groupBy = $normalized['groupBy'] ?? [];
        $groupField = $groupBy['field'] ?? null;
        $bucket = $groupBy['bucket'] ?? 'none';
        if (!in_array($bucket, self::ALLOWED_BUCKETS, true)) {
            $errors[] = "Bucket '{$bucket}' is not supported";
        }

        if ($groupField) {
            if (empty($fieldDefs[$groupField])) {
                $errors[] = "Group field '{$groupField}' not found";
            } else {
                $fieldType = $fieldDefs[$groupField]['type'] ?? null;
                if ($bucket !== 'none' && !in_array($fieldType, ['date', 'datetime'], true)) {
                    $errors[] = "Bucket '{$bucket}' requires a date/datetime field";
                }
            }
        } elseif ($bucket !== 'none') {
            $warnings[] = 'Bucket ignored because no group field was selected';
            $normalized['groupBy']['bucket'] = 'none';
        }

        $order = $normalized['order'] ?? [];
        $orderBy = $order['by'] ?? 'value';
        $orderDir = $order['dir'] ?? 'desc';
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $warnings[] = "Order by '{$orderBy}' is not supported; defaulting to value";
            $normalized['order']['by'] = 'value';
        }
        if (!in_array($orderDir, self::ALLOWED_ORDER_DIR, true)) {
            $warnings[] = "Order direction '{$orderDir}' is not supported; defaulting to desc";
            $normalized['order']['dir'] = 'desc';
        }

        $limit = $normalized['limit'] ?? null;
        if ($limit !== null) {
            $limit = (int) $limit;
            if ($limit <= 0) {
                $warnings[] = 'Limit must be greater than zero; ignoring';
                $limit = null;
            } elseif ($limit > self::MAX_LIMIT) {
                $warnings[] = "Limit capped at " . self::MAX_LIMIT;
                $limit = self::MAX_LIMIT;
            }
            $normalized['limit'] = $limit;
        }

        if ($groupField === null && $limit !== null) {
            $warnings[] = 'Limit ignored because no group field is selected';
        }

        if ($groupField === null && ($normalized['order']['by'] ?? null)) {
            $warnings[] = 'Ordering ignored because no group field is selected';
        }

        if ($chartType === 'funnel') {
            $normalized['groupBy']['field'] = null;
            $normalized['groupBy']['bucket'] = 'none';
            $stages = $normalized['funnelStages'] ?? [];
            if (!is_array($stages) || empty($stages)) {
                $errors[] = 'Funnel charts require at least one stage';
            }
        }

        if ($entity !== '' && !empty($fieldDefs)) {
            $table = strtolower($entity);
            $filterTranslator = new FilterTranslator($fieldDefs, $table);
            $resolvedFilters = $this->resolveFilters($normalized, $context, $filterTranslator);
            if (!empty($normalized['filters']) && !$resolvedFilters) {
                $errors[] = 'Filters could not be parsed';
            }
            if (!empty($normalized['filterId']) && !$resolvedFilters) {
                $errors[] = "Stored filter '{$normalized['filterId']}' not found";
            }
            if ($resolvedFilters) {
                $filterErrors = $filterTranslator->validateFilterExpression($resolvedFilters);
                $errors = array_merge($errors, $filterErrors);
            }

            if ($chartType === 'funnel') {
                $stages = $normalized['funnelStages'] ?? [];
                if (is_array($stages)) {
                    foreach ($stages as $index => $stage) {
                        $stageLabel = trim((string) ($stage['label'] ?? ''));
                        if ($stageLabel === '') {
                            $errors[] = "Funnel stage " . ($index + 1) . " requires a label";
                        }
                        $stageFilters = $this->resolveStageFilters($stage, $context, $filterTranslator, $entity);
                        if (!empty($stage['filters']) && !$stageFilters) {
                            $errors[] = "Stage " . ($index + 1) . ": filters could not be parsed";
                        }
                        $stageFilterId = $stage['filterId'] ?? ($stage['filter_id'] ?? null);
                        if (!empty($stageFilterId) && !$stageFilters) {
                            $errors[] = "Stage " . ($index + 1) . ": stored filter not found";
                        }
                        if ($stageFilters) {
                            $stageErrors = $filterTranslator->validateFilterExpression($stageFilters);
                            foreach ($stageErrors as $error) {
                                $errors[] = "Stage " . ($index + 1) . ": {$error}";
                            }
                        }
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'definition' => $normalized,
        ];
    }

    private function normalizeDefinition(array $definition): array
    {
        $normalized = $definition;

        $normalized['chartType'] = strtolower((string) ($definition['chartType'] ?? ''));
        $normalized['entity'] = (string) ($definition['entity'] ?? '');
        $normalized['filterId'] = $definition['filterId'] ?? ($definition['filter_id'] ?? null);
        $normalized['filters'] = $definition['filters'] ?? null;
        $normalized['metric'] = is_array($definition['metric'] ?? null)
            ? $definition['metric']
            : ['type' => $definition['metric'] ?? 'count', 'field' => null];

        $normalized['metric']['type'] = strtolower((string) ($normalized['metric']['type'] ?? 'count'));
        $normalized['metric']['field'] = $normalized['metric']['field'] ?? null;

        $normalized['groupBy'] = is_array($definition['groupBy'] ?? null)
            ? $definition['groupBy']
            : ['field' => null, 'bucket' => 'none'];

        $normalized['groupBy']['field'] = $normalized['groupBy']['field'] ?? null;
        $normalized['groupBy']['bucket'] = strtolower((string) ($normalized['groupBy']['bucket'] ?? 'none'));

        $normalized['order'] = is_array($definition['order'] ?? null)
            ? $definition['order']
            : ['by' => 'value', 'dir' => 'desc'];

        $normalized['order']['by'] = strtolower((string) ($normalized['order']['by'] ?? 'value'));
        $normalized['order']['dir'] = strtolower((string) ($normalized['order']['dir'] ?? 'desc'));

        $normalized['limit'] = array_key_exists('limit', $definition) ? $definition['limit'] : null;
        $normalized['title'] = $definition['title'] ?? null;
        $normalized['funnelStages'] = $definition['funnelStages'] ?? $definition['funnel_stages'] ?? [];

        return $normalized;
    }

    private function runAggregate(
        array $definition,
        FilterTranslator $filterTranslator,
        ?array $filters,
        array $warnings,
        array $fieldDefs,
        string $table,
        string $driver
    ): array {
        $query = Capsule::table($table);
        if ($filters) {
            $filterTranslator->applyToQuery($query, $filters);
        }

        $metricExpr = $this->buildMetricExpression($definition, $table);
        $query->selectRaw("{$metricExpr} as value");

        $groupField = $definition['groupBy']['field'] ?? null;
        $groupFieldDef = null;
        if ($groupField) {
            $groupFieldDef = $fieldDefs[$groupField] ?? null;
            $labelExpr = $this->buildGroupLabelExpression($query, $groupField, $groupFieldDef, $definition['groupBy']['bucket'] ?? 'none', $table, $driver, $warnings);
            $query->selectRaw("{$labelExpr} as label");
            $query->groupByRaw($labelExpr);

            $orderBy = $definition['order']['by'] ?? 'value';
            $orderDir = strtoupper($definition['order']['dir'] ?? 'DESC');
            if ($orderBy === 'label') {
                $query->orderByRaw("{$labelExpr} {$orderDir}");
            } else {
                $query->orderBy('value', $orderDir);
            }

            if (!empty($definition['limit'])) {
                $query->limit((int) $definition['limit']);
            }
        }

        $rows = $query->get()->map(fn($row) => (array) $row)->all();

        $labels = [];
        $data = [];
        foreach ($rows as $row) {
            if ($groupField) {
                $rawLabel = $row['label'] ?? null;
                $labels[] = $this->formatGroupLabel($rawLabel, $groupFieldDef);
            } else {
                $labels[] = $definition['title'] ?: 'Total';
            }
            $data[] = $this->normalizeMetricValue($row['value'] ?? 0);
        }

        if (empty($rows) && !$groupField) {
            $labels = [$definition['title'] ?: 'Total'];
            $data = [0];
        }

        $seriesLabel = $this->buildSeriesLabel($definition);
        $total = array_sum($data);

        return [
            'labels' => $labels,
            'series' => [
                [
                    'label' => $seriesLabel,
                    'data' => $data,
                ]
            ],
            'meta' => [
                'total' => $total,
                'definition' => $definition,
                'warnings' => $warnings,
            ]
        ];
    }

    private function runFunnel(
        array $definition,
        array $context,
        FilterTranslator $filterTranslator,
        ?array $baseFilters,
        array $warnings,
        array $fieldDefs,
        string $table,
        string $driver
    ): array {
        $stages = $definition['funnelStages'] ?? [];
        $labels = [];
        $data = [];
        $index = 0;

        foreach ($stages as $stage) {
            $index++;
            $label = trim((string) ($stage['label'] ?? ''));
            if ($label === '') {
                $label = "Stage {$index}";
            }

            $stageFilters = $this->resolveStageFilters($stage, $context, $filterTranslator, $definition['entity']);
            $filters = $this->mergeFilters($baseFilters, $stageFilters);

            $query = Capsule::table($table);
            if ($filters) {
                $filterTranslator->applyToQuery($query, $filters);
            }

            $metricExpr = $this->buildMetricExpression($definition, $table);
            $query->selectRaw("{$metricExpr} as value");
            $row = $query->first();
            $value = 0;
            if ($row) {
                $rowArray = (array) $row;
                $value = $this->normalizeMetricValue($rowArray['value'] ?? 0);
            }

            $labels[] = $label;
            $data[] = $value;
        }

        $seriesLabel = $this->buildSeriesLabel($definition);
        $total = array_sum($data);

        return [
            'labels' => $labels,
            'series' => [
                [
                    'label' => $seriesLabel,
                    'data' => $data,
                ]
            ],
            'meta' => [
                'total' => $total,
                'definition' => $definition,
                'warnings' => $warnings,
            ]
        ];
    }

    private function buildMetricExpression(array $definition, string $table): string
    {
        $metricType = strtolower($definition['metric']['type'] ?? 'count');
        if ($metricType === 'count') {
            return 'count(*)';
        }

        $field = $definition['metric']['field'] ?? null;
        $field = $field ? $table . '.' . $field : '*';
        return "{$metricType}({$field})";
    }

    private function buildGroupLabelExpression(
        Builder $query,
        string $field,
        ?array $fieldDef,
        string $bucket,
        string $table,
        string $driver,
        array &$warnings
    ): string {
        $column = $table . '.' . $field;
        $fieldType = $fieldDef['type'] ?? null;

        if ($fieldType === 'relationship' && !empty($fieldDef['entity'])) {
            $relatedEntity = strtolower((string) $fieldDef['entity']);
            if ($this->isProtectedEntity($relatedEntity)) {
                $warnings[] = "Relationship '{$field}' points to a protected entity; using id for labels";
                return $column;
            }

            $relatedFields = $GLOBALS['metadata']['entities'][$relatedEntity]['fields'] ?? [];
            if (!array_key_exists('name', $relatedFields)) {
                $warnings[] = "Relationship '{$field}' lacks a name field; using id for labels";
                return $column;
            }

            $alias = $relatedEntity . '_rel';
            $query->leftJoin("{$relatedEntity} as {$alias}", $column, '=', "{$alias}.id");
            return "{$alias}.name";
        }

        if (in_array($fieldType, ['date', 'datetime'], true) && $bucket !== 'none') {
            return SqlDialect::labelExpr($driver, $column, $bucket);
        }

        return $column;
    }

    private function formatGroupLabel($value, ?array $fieldDef): string
    {
        if ($value === null || $value === '') {
            return 'Unknown';
        }

        if (!$fieldDef) {
            return (string) $value;
        }

        $fieldType = $fieldDef['type'] ?? null;
        if ($fieldType === 'select') {
            $options = $fieldDef['options'] ?? null;
            if (is_array($options)) {
                if (array_key_exists((string) $value, $options)) {
                    return (string) $options[(string) $value];
                }
                if (in_array($value, $options, true)) {
                    return (string) $value;
                }
            }
        }

        if (in_array($fieldType, ['boolean', 'checkbox'], true)) {
            $normalized = strtolower((string) $value);
            if ($normalized === '1' || $normalized === 'true') {
                return 'True';
            }
            if ($normalized === '0' || $normalized === 'false') {
                return 'False';
            }
        }

        return (string) $value;
    }

    private function normalizeMetricValue($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        return 0.0;
    }

    private function buildSeriesLabel(array $definition): string
    {
        $metricType = strtoupper((string) ($definition['metric']['type'] ?? 'COUNT'));
        if ($metricType === 'COUNT') {
            return 'Count';
        }

        $field = $definition['metric']['field'] ?? 'value';
        return $metricType . ' of ' . $field;
    }

    private function resolveFilters(array $definition, array $context, FilterTranslator $translator): ?array
    {
        $filters = null;
        if (!empty($definition['filters'])) {
            $filters = $translator->normalizeFilterExpression($definition['filters']);
        }

        if (!$filters && !empty($definition['filterId'])) {
            $filters = $this->loadStoredFilterDefinition(
                $definition['entity'],
                $definition['filterId'],
                $context['user_id'] ?? null,
                $translator
            );
        }

        return $filters;
    }

    private function resolveStageFilters(array $stage, array $context, FilterTranslator $translator, string $entity): ?array
    {
        $filters = null;
        if (!empty($stage['filters'])) {
            $filters = $translator->normalizeFilterExpression($stage['filters']);
        }

        $filterId = $stage['filterId'] ?? ($stage['filter_id'] ?? null);
        if (!$filters && $filterId) {
            $filters = $this->loadStoredFilterDefinition($entity, $filterId, $context['user_id'] ?? null, $translator);
        }

        return $filters;
    }

    private function loadStoredFilterDefinition(string $entity, $filterId, ?string $userId, FilterTranslator $translator): ?array
    {
        if (!$filterId || !$userId) {
            return null;
        }

        $record = Capsule::table('saved_filters')->where('id', $filterId)->first();
        if (!$record) {
            return null;
        }

        $recordArray = (array) $record;
        if (($recordArray['owner'] ?? null) !== $userId) {
            return null;
        }
        if (($recordArray['entity'] ?? null) !== $entity) {
            return null;
        }

        $definition = $recordArray['definition'] ?? null;
        if (is_string($definition)) {
            $decoded = json_decode($definition, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $definition = $decoded;
            }
        }

        if (!is_array($definition)) {
            return null;
        }

        return $translator->normalizeFilterExpression($definition);
    }

    private function mergeFilters(?array $base, ?array $extra): ?array
    {
        if (!$base) {
            return $extra;
        }
        if (!$extra) {
            return $base;
        }

        return [
            'group' => 'AND',
            'filters' => [$base, $extra],
        ];
    }

    private function isProtectedEntity(string $entity): bool
    {
        $protected = $GLOBALS['metadata']['protected_entities'] ?? [];
        if (!is_array($protected)) {
            return false;
        }
        $protectedList = array_map('strtolower', array_values($protected));
        return in_array(strtolower($entity), $protectedList, true);
    }

    private function isNumericField(array $fieldDef): bool
    {
        $type = strtolower((string) ($fieldDef['type'] ?? ''));
        return in_array($type, ['integer', 'number', 'float', 'decimal', 'currency'], true);
    }
}
