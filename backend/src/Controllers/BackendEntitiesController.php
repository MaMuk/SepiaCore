<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use SepiaCore\Utilities\Log;

class BackendEntitiesController extends EntityController
{
    /**
     * Boots detached backend view with rendered HTML and script.
     * @param string $model Entity model name
     * @return void
     */
    public function boot($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

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
        Log::logMessage('Twig rendering is deprecated in BackendEntitiesController::boot()', 'warning');
        $innerHtmlPath = $this->entity->twig->getLoader()->exists($p = 'backendviews/'.$this->entity->getClassName().'/list.html.twig') ? $p : 'backendviews/list.html.twig';
        $scriptPath = $this->entity->twig->getLoader()->exists($p = 'backendviews/'.$this->entity->getClassName().'/script.js.twig') ? $p : 'backendviews/script.js.twig';
        $data = ['innerHtml' => $this->entity->twig->render($innerHtmlPath, $viewData)];
        $data['script'] = $this->entity->twig->render($scriptPath, []);

        $this->jsonResponse($data);
    }

    /**
     * Renders JavaScript script for backend entity view.
     * @param string $model Entity model name
     * @return void
     */
    public function script($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        Log::logMessage('Twig rendering is deprecated in BackendEntitiesController::script()', 'warning');
        $scriptPath = $this->entity->twig->getLoader()->exists($p = 'backendviews/'.$this->entity->getClassName().'/script.js.twig') ? $p : 'backendviews/script.js.twig';
        $script = $this->entity->twig->render($scriptPath, ['entityKey' => $model]);

        Flight::response()->header('Content-Type', 'application/javascript');
        Flight::response()->write($script);
    }

    /**
     * Lists records for backend entity.
     * @param string $model Entity model name
     * @return void
     */
    public function list($model): void
    {
        $this->model = $model;
        $this->entity = $this->getEntityClass($model);

        $params = $this->getPaginationParams();

        $records = $this->entity->read(
            null,
            $params['page'],
            $params['limit'],
            $params['sortBy'],
            $params['sortOrder'],
            $params['search']
        );
        $data = ['records' => $records , 'total' => $this->entity->count()];
        $this->jsonResponse($data);
    }

    /**
     * Shows a specific record detail view.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function recordView($model, $id): void
    {
        $this->show($model, $id);
    }

    /**
     * Shows edit form for a record.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function editView($model, $id): void
    {
        $this->edit($model, $id);
    }

    /**
     * Creates a new record.
     * @param string $model Entity model name
     * @return void
     */
    public function new($model): void
    {
        $this->store($model);
    }

    /**
     * Updates an existing record.
     * @param string $model Entity model name
     * @param string $id Record ID
     * @return void
     */
    public function modify($model, $id): void
    {
        $this->update($model, $id);
    }
}