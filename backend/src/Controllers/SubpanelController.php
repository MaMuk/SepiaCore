<?php

namespace SepiaCore\Controllers;

use Flight;
use SepiaCoreUtilities\Log;

class SubpanelController extends BaseController
{
    /**
     * Gets one-to-many subpanel data.
     * @param string $parentModel Parent entity model
     * @param string $parentId Parent record ID
     * @param string $relEntity Related entity model
     * @param string $relField Relationship field name
     * @return void
     */
    public function oneToMany($parentModel, $parentId, $relEntity, $relField): void
    {
        $relatedEntity = $this->getEntityClass($relEntity);
        $relatedRecords = $relatedEntity->find($relField, $parentId, false);

        // Get field definitions for the related entity
        $relEntityFieldDefs = $GLOBALS['metadata']['entities'][$relEntity]['fields'] ?? [];
        
        // Get subpanel field configuration - find by matching rel_field
        $subpanels = $GLOBALS['metadata']['entities'][$parentModel]['module_views']['subpanels'] ?? [];
        $subpanelFields = [];
        foreach ($subpanels as $subpanelDef) {
            if (isset($subpanelDef['rel_field']) && $subpanelDef['rel_field'] === $relField && $subpanelDef['entity'] === $relEntity) {
                $subpanelFields = $subpanelDef['fields'] ?? [];
                break;
            }
        }
        
        // If no specific fields configured, use defaults
        if (empty($subpanelFields)) {
            $subpanelFields = $relatedEntity->isPerson() 
                ? ['first_name', 'last_name', 'date_created', 'date_modified']
                : ['name', 'date_created', 'date_modified'];
        }

        // Get relationship data for all records
        $relationship = $this->getRelationshipDataForList($relatedRecords, $relEntityFieldDefs);

        $data = [
            'records' => $relatedRecords,
            'field_definitions' => $relEntityFieldDefs,
            'subpanel_fields' => $subpanelFields,
            'relationship' => $relationship,
            'entity' => $relEntity,
            'rel_type' => 'one_to_many',
            'rel_field' => $relField,
            'settings' => $GLOBALS['settings'],
        ];

        $this->jsonHalt($data);
    }

    /**
     * Gets many-to-many subpanel data.
     * @param string $parentModel Parent entity model
     * @param string $parentId Parent record ID
     * @param string $relEntity Related entity model
     * @param string $relName Relationship name
     * @return void
     */
    public function manyToMany($parentModel, $parentId, $relEntity, $relName): void
    {
        $parentEntity = $this->getEntityClass($parentModel);
        $relatedRecords = $parentEntity->getRelatedRecords($relName, $parentId);

        // Get field definitions for the related entity
        $relEntityFieldDefs = $GLOBALS['metadata']['entities'][$relEntity]['fields'] ?? [];
        
        // Get subpanel field configuration - find by matching rel_name
        $subpanels = $GLOBALS['metadata']['entities'][$parentModel]['module_views']['subpanels'] ?? [];
        $subpanelFields = [];
        foreach ($subpanels as $subpanelDef) {
            if (isset($subpanelDef['rel_name']) && $subpanelDef['rel_name'] === $relName && $subpanelDef['entity'] === $relEntity) {
                $subpanelFields = $subpanelDef['fields'] ?? [];
                break;
            }
        }
        
        // If no specific fields configured, use defaults
        if (empty($subpanelFields)) {
            $relEntityClass = $this->getEntityClass($relEntity);
            $subpanelFields = $relEntityClass->isPerson() 
                ? ['first_name', 'last_name', 'date_created', 'date_modified']
                : ['name', 'date_created', 'date_modified'];
        }

        // Get relationship data for all records
        $relationship = $this->getRelationshipDataForList($relatedRecords, $relEntityFieldDefs);

        $data = [
            'records' => $relatedRecords,
            'field_definitions' => $relEntityFieldDefs,
            'subpanel_fields' => $subpanelFields,
            'relationship' => $relationship,
            'entity' => $relEntity,
            'rel_type' => 'many_to_many',
            'rel_name' => $relName,
            'settings' => $GLOBALS['settings'],
        ];

        $this->jsonHalt($data);
    }
}