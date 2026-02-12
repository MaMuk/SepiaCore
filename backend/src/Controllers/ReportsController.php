<?php

namespace SepiaCore\Controllers;

use Flight;
use SepiaCore\Reports\ReportEngineFactory;
use SepiaCore\Reports\ReportValidationException;

class ReportsController extends BaseController
{
    public function run(): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $definition = $this->getDefinitionPayload();
        if (!is_array($definition)) {
            $this->jsonHalt(['error' => 'Invalid report definition'], 400);
        }
        $entity = $definition['entity'] ?? null;
        if (is_string($entity) && !$this->isEntityAllowedForCapability($entity, 'graph-widget')) {
            $this->jsonHalt(['error' => 'Not authorized for this entity'], 403);
        }

        $engine = ReportEngineFactory::make();

        try {
            $result = $engine->run($definition, ['user_id' => $userId]);
            $this->jsonResponse($result);
        } catch (ReportValidationException $e) {
            $this->jsonHalt([
                'error' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'warnings' => $e->getWarnings(),
                'definition' => $e->getDefinition(),
            ], 400);
        } catch (\Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    public function validate(): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $definition = $this->getDefinitionPayload();
        if (!is_array($definition)) {
            $this->jsonHalt(['error' => 'Invalid report definition'], 400);
        }
        $entity = $definition['entity'] ?? null;
        if (is_string($entity) && !$this->isEntityAllowedForCapability($entity, 'graph-widget')) {
            $this->jsonHalt(['error' => 'Not authorized for this entity'], 403);
        }

        $engine = ReportEngineFactory::make();
        $result = $engine->validate($definition, ['user_id' => $userId]);
        $this->jsonResponse($result);
    }

    public function options(): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $entities = $GLOBALS['metadata']['entities'] ?? [];
        $protected = $this->getProtectedEntities();

        $response = [];
        foreach ($entities as $name => $meta) {
            if (in_array(strtolower($name), $protected, true)) {
                continue;
            }
            if (!$this->isEntityAllowedForCapability($name, 'graph-widget', $meta)) {
                continue;
            }

            $fields = $meta['fields'] ?? [];
            $fieldList = [];
            foreach ($fields as $fieldName => $def) {
                if ($fieldName === '') {
                    continue;
                }
                $fieldList[] = [
                    'name' => $fieldName,
                    'label' => $this->formatLabel($fieldName),
                    'type' => $def['type'] ?? 'string',
                    'options' => $def['options'] ?? null,
                    'entity' => $def['entity'] ?? null,
                ];
            }

            $response[] = [
                'name' => $name,
                'label' => $this->formatLabel($name),
                'fields' => $fieldList,
            ];
        }

        $this->jsonResponse([
            'entities' => $response,
        ]);
    }

    private function isEntityAllowedForCapability(string $entityName, string $capabilityKey, ?array $meta = null): bool
    {
        $entityKey = strtolower($entityName);
        $protected = $this->getProtectedEntities();
        if (in_array($entityKey, $protected, true)) {
            return false;
        }

        if ($meta === null) {
            $meta = $GLOBALS['metadata']['entities'][$entityKey] ?? null;
        }
        if (!$meta || !is_array($meta)) {
            return false;
        }

        $capability = $meta['capabilities'][$capabilityKey] ?? null;
        $active = true;
        $requiresAdmin = false;

        if ($capability !== null) {
            if (is_bool($capability) || is_string($capability) || is_int($capability)) {
                $active = $this->toBoolean($capability);
            } elseif (is_array($capability)) {
                if (array_key_exists('active', $capability)) {
                    $active = $this->toBoolean($capability['active']);
                }
                if (array_key_exists('requires_admin', $capability)) {
                    $requiresAdmin = $this->toBoolean($capability['requires_admin']);
                }
            }
        }

        if ($requiresAdmin && !isAdmin()) {
            return false;
        }

        return $active;
    }

    private function toBoolean($value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    private function getDefinitionPayload(): ?array
    {
        $body = Flight::request()->getBody();
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = Flight::request()->data->getData();
        }
        if (!is_array($decoded)) {
            return null;
        }

        if (array_key_exists('definition', $decoded) && is_array($decoded['definition'])) {
            return $decoded['definition'];
        }
        if (array_key_exists('reportDefinition', $decoded) && is_array($decoded['reportDefinition'])) {
            return $decoded['reportDefinition'];
        }

        return $decoded;
    }

    private function getProtectedEntities(): array
    {
        $protected = $GLOBALS['metadata']['protected_entities'] ?? [];
        if (!is_array($protected)) {
            return [];
        }
        return array_values(array_filter(array_map('strtolower', $protected)));
    }

    private function formatLabel(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
        $value = strtolower($value ?? '');
        return ucwords(trim($value));
    }
}
