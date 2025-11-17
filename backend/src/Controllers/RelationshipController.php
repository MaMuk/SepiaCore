<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;

class RelationshipController extends BaseController
{
    /**
     * Searches relationship data for select dropdowns.
     * @param string $model Entity model name
     * @return void
     */
    public function search($model): void
    {
        $entity = $this->getEntityClass($model);
        $search = Flight::request()->query['search'];

        $records = $entity->read(null, 1, 10, 'date_modified', 'DESC', $search);

        $data = [
            ['id' => '', 'name' => '--'] // placeholder
        ];

        if (count($records) > 0) {
            foreach ($records as $record) {
                $data[] = ['id' => $record['id'], 'name' => $record['name']];
            }
        }

        $this->jsonHalt($data);
    }

    /**
     * Gets relationship record details.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function show($model, $id): void
    {
        $entity = $this->getEntityClass($model);
        $record = $entity->read($id);

        if ($record) {
            $data = [
                'success' => true,
                'name' => $record['name']
            ];
            $this->jsonHalt($data, 200);
        } else {
            $data = ['success' => false];
            $this->jsonHalt($data, 404);
        }
    }

    /**
     * Adds many-to-many relationship.
     * @param string $parentModel Parent entity model
     * @param string $parentId Parent record ID
     * @param string $relId Related record ID
     * @param string $relEntity Related entity model
     * @param string $relName Relationship name
     * @return void
     */
    public function addManyToMany($parentModel, $parentId, $relId, $relEntity, $relName): void
    {
        $parentEntity = $this->getEntityClass($parentModel);

        try {
            if ($parentEntity->addRelationship($relName, $parentId, $relId)) {
                $this->jsonHalt(['success' => true, 'message' => 'Relationship added']);
            }
        } catch (Exception $e) {
            $this->jsonHalt(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Adds one-to-many relationship.
     * @param string $parentModel Parent entity model
     * @param string $parentId Parent record ID
     * @param string $relId Related record ID
     * @param string $relEntity Related entity model
     * @param string $relField Relationship field name
     * @return void
     */
    public function addOneToMany($parentModel, $parentId, $relId, $relEntity, $relField): void
    {
        $parentEntity = $this->getEntityClass($parentModel);
        $parentRecord = $parentEntity->read($parentId);

        if (!$parentRecord) {
            $this->jsonHalt(['success' => false, 'message' => 'Parent record not found'], 404);
        }

        $relEntityObj = $this->getEntityClass($relEntity);
        $relRecord = $relEntityObj->read($relId);

        if (!$relRecord) {
            $this->jsonHalt(['success' => false, 'message' => 'Related record not found'], 404);
        }

        if ($relEntityObj->setRelation($relField, $relId, $parentId)) {
            $this->jsonHalt(['success' => true, 'message' => 'Relationship updated']);
        }

        $this->jsonHalt(['success' => false]);
    }

    /**
     * Removes many-to-many relationship.
     * @param string $parentModel Parent entity model
     * @param string $parentId Parent record ID
     * @param string $relId Related record ID
     * @param string $relEntity Related entity model
     * @param string $relName Relationship name
     * @return void
     */
    public function removeManyToMany($parentModel, $parentId, $relId, $relEntity, $relName): void
    {
        $parentEntity = $this->getEntityClass($parentModel);

        try {
            if ($parentEntity->removeRelationship($relName, $parentId, $relId)) {
                $this->jsonHalt(['success' => true, 'message' => 'Relationship removed']);
            } else {
                $this->jsonHalt(['success' => false, 'message' => 'Failed to remove relationship'], 500);
            }
        } catch (Exception $e) {
            $this->jsonHalt(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Removes one-to-many relationship.
     * @param string $parentModel Parent entity model
     * @param string $parentId Parent record ID
     * @param string $relId Related record ID
     * @param string $relEntity Related entity model
     * @param string $relField Relationship field name
     * @return void
     */
    public function removeOneToMany($parentModel, $parentId, $relId, $relEntity, $relField): void
    {
        $relEntityObj = $this->getEntityClass($relEntity);
        $relRecord = $relEntityObj->read($relId);

        if (!$relRecord) {
            $this->jsonHalt(['success' => false, 'message' => 'Related record not found'], 404);
        }

        // Set the relationship field to null to remove the link
        if ($relEntityObj->update($relId, [$relField => null])) {
            $this->jsonHalt(['success' => true, 'message' => 'Relationship removed']);
        } else {
            $this->jsonHalt(['success' => false, 'message' => 'Failed to remove relationship'], 500);
        }
    }
}