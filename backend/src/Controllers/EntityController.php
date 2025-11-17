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