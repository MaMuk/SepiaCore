<?php

use SepiaCore\Controllers\AuthController;
use SepiaCore\Controllers\EntityController;
use SepiaCore\Controllers\BackendEntitiesController;
use SepiaCore\Controllers\InstallController;
use SepiaCore\Controllers\EntityStudioController;
use SepiaCore\Controllers\RelationshipController;
use SepiaCore\Controllers\SubpanelController;
use SepiaCore\Controllers\SystemController;
use SepiaCore\Controllers\EndpointController;
use SepiaCore\Controllers\OpenApiController;
use SepiaCore\Controllers\UsersController;
use SepiaCore\Controllers\DashboardController;
use SepiaCore\Controllers\FiltersController;

// ==========================================
// Public Routes (No Authentication)
// ==========================================

Flight::route("GET /", function(){
    Flight::redirect('/openapi', 303);
});


Flight::route("GET /openapi", function(){
    $controller = new OpenApiController();
    $controller->index();

});

Flight::route('GET /ping', function() {
    $controller = new InstallController();
    $controller->ping();
});

Flight::route('GET /install/requirements', function() {
    $controller = new InstallController();
    $controller->checkRequirements();
});

Flight::route('POST /install', function() {
    $controller = new InstallController();
    $controller->install();
});

Flight::route('POST /login', function() {
    $controller = new AuthController();
    $controller->login();
});

Flight::route('POST /logout', function() {
    $controller = new AuthController();
    $controller->logout();
});

// ==========================================
// Metadata & Settings
// ==========================================

Flight::route('GET /metadata', function() {
    $controller = new SystemController();
    $controller->metadata();
});

Flight::route('GET /settings', function() {
    $controller = new SystemController();
    $controller->getSettings();
});

Flight::route('PUT /settings', function() {
    $controller = new SystemController();
    $controller->updateSettings();
});

Flight::route('GET /system/components/@component', function($component) {
    $controller = new SystemController();
    $controller->component($component);
});

// ==========================================
// Entity Studio Routes (formerly Module Builder)
// ==========================================

Flight::route('GET /modulebuilder', function() {
    $controller = new EntityStudioController();
    $controller->index();
});

Flight::route('POST /modulebuilder/@action', function($action) {
    $controller = new EntityStudioController();
    $controller->action($action);
});

// ==========================================
// Endpoint Routes
// ==========================================

Flight::route('POST /endpoint/@name', function($name) {
    $controller = new EndpointController();
    $controller->store($name);
});

// ==========================================
// Backend handled Views Routes
// ==========================================

Flight::route('GET /backendviews/@entity', function($entity) {
    //Some Entities are tightly coupled with the System the instead of a Generic View we handle that now from the backend
    $controller = new BackendEntitiesController();
    $controller->boot($entity);
});

Flight::route('GET /backendviews/@entity/script', function($entity) {
    //Some Entities are tightly coupled with the System the instead of a Generic View we handle that now from the backend
    $controller = new BackendEntitiesController();
    $controller->script($entity);
});

Flight::route('GET /backendviews/@entity/list', function($entity) {
    //Some Entities are tightly coupled with the System the instead of a Generic View we handle that now from the backend
    $controller = new BackendEntitiesController();
    $controller->list($entity);
});
Flight::route('GET /backendviews/@entity/@id', function($entity,$id) {
    //Some Entities are tightly coupled with the System the instead of a Generic View we handle that now from the backend
    $controller = new BackendEntitiesController();
    $controller->recordView($entity,$id);
});
Flight::route('GET /backendviews/@entity/@id/edit', function($entity,$id) {
    //Some Entities are tightly coupled with the System the instead of a Generic View we handle that now from the backend
    $controller = new BackendEntitiesController();
    $controller->editView($entity,$id);
});
Flight::route('POST /backendviews/@entity', function($entity) {
    //Some Entities are tightly coupled with the System the instead of a Generic View we handle that now from the backend
    $controller = new BackendEntitiesController();
    $controller->new($entity);
});
Flight::route('PUT /backendviews/@entity/@id', function($entity,$id) {
    //Some Entities are tightly coupled with the System the instead of a Generic View we handle that now from the backend
    $controller = new BackendEntitiesController();
    $controller->modify($entity,$id);
});
// ==========================================
// User Management Routes
// ==========================================

Flight::route('GET /users', function() {
    $controller = new UsersController();
    $controller->index();
});

Flight::route('GET /users/@id', function($id) {
    $controller = new UsersController();
    $controller->show($id);
});

Flight::route('POST /users', function() {
    $controller = new UsersController();
    $controller->store();
});

Flight::route('PUT /users/@id', function($id) {
    $controller = new UsersController();
    $controller->update($id);
});

Flight::route('DELETE /users/@id', function($id) {
    $controller = new UsersController();
    $controller->destroy($id);
});

Flight::route('GET /users/profile/me', function() {
    $controller = new UsersController();
    $controller->profile();
});

Flight::route('GET /users/@id/owned-records', function($id) {
    $controller = new UsersController();
    $controller->ownedRecords($id);
});

// ==========================================
// Dashboard Routes
// ==========================================

Flight::route('GET /dashboard', function() {
    $controller = new DashboardController();
    $controller->default();
});

Flight::route('GET /dashboard/@id', function($id) {
    $controller = new DashboardController();
    $controller->showDashboard($id);
});

Flight::route('PUT /dashboard/@id', function($id) {
    $controller = new DashboardController();
    $controller->updateDashboard($id);
});

Flight::route('POST /dashboard', function() {
    $controller = new DashboardController();
    $controller->createDashboard();
});

Flight::route('PUT /dashboard/@id/default', function($id) {
    $controller = new DashboardController();
    $controller->setDefaultDashboard($id);
});

Flight::route('DELETE /dashboard/@id', function($id) {
    $controller = new DashboardController();
    $controller->deleteDashboard($id);
});

// ==========================================
// Saved Filters Routes
// ==========================================

Flight::route('GET /filters', function() {
    $controller = new FiltersController();
    $controller->index();
});

Flight::route('GET /filters/@id', function($id) {
    $controller = new FiltersController();
    $controller->show($id);
});

Flight::route('POST /filters', function() {
    $controller = new FiltersController();
    $controller->store();
});

Flight::route('PUT /filters/@id', function($id) {
    $controller = new FiltersController();
    $controller->update($id);
});

Flight::route('DELETE /filters/@id', function($id) {
    $controller = new FiltersController();
    $controller->destroy($id);
});

// ==========================================
// Reserved Routes (Security)
// ==========================================

Flight::route('GET /tokens(/@id)', function($id = null) {
    Flight::jsonHalt(['error' => 'Tokens endpoint is reserved. This endpoint requires additional security checks before it can be safely exposed to authorized users.'], 403);
});

// ==========================================
// Relationship Routes
// ==========================================

Flight::route('GET /relationship/@model', function($model) {
    $controller = new RelationshipController();
    $controller->search($model);
});

Flight::route('GET /relationship/@model/@id', function($model, $id) {
    $controller = new RelationshipController();
    $controller->show($model, $id);
});

Flight::route('POST /relationship/many_to_many/@parentModel/@parentId/@relId/@relEntity/@relName',
    function($parentModel, $parentId, $relId, $relEntity, $relName) {
        $controller = new RelationshipController();
        $controller->addManyToMany($parentModel, $parentId, $relId, $relEntity, $relName);
    }
);

Flight::route('POST /relationship/one_to_many/@parentModel/@parentId/@relId/@relEntity/@relField',
    function($parentModel, $parentId, $relId, $relEntity, $relField) {
        $controller = new RelationshipController();
        $controller->addOneToMany($parentModel, $parentId, $relId, $relEntity, $relField);
    }
);

Flight::route('DELETE /relationship/many_to_many/@parentModel/@parentId/@relId/@relEntity/@relName',
    function($parentModel, $parentId, $relId, $relEntity, $relName) {
        $controller = new RelationshipController();
        $controller->removeManyToMany($parentModel, $parentId, $relId, $relEntity, $relName);
    }
);

Flight::route('DELETE /relationship/one_to_many/@parentModel/@parentId/@relId/@relEntity/@relField',
    function($parentModel, $parentId, $relId, $relEntity, $relField) {
        $controller = new RelationshipController();
        $controller->removeOneToMany($parentModel, $parentId, $relId, $relEntity, $relField);
    }
);

// ==========================================
// Subpanel Routes
// ==========================================

Flight::route('GET /subpanel/one_to_many/@parentModel/@parentId/@relEntity/@relField',
    function($parentModel, $parentId, $relEntity, $relField) {
        $controller = new SubpanelController();
        $controller->oneToMany($parentModel, $parentId, $relEntity, $relField);
    }
);

Flight::route('GET /subpanel/many_to_many/@parentModel/@parentId/@relEntity/@relName',
    function($parentModel, $parentId, $relEntity, $relName) {
        $controller = new SubpanelController();
        $controller->manyToMany($parentModel, $parentId, $relEntity, $relName);
    }
);

// ==========================================
// Entity CRUD Routes
// ==========================================


// Create routes
Flight::route('GET /create/@model', function($model) {
    $controller = new EntityController();
    $controller->create($model);
});

Flight::route('POST /create/@model', function($model) {
    $controller = new EntityController();
    $controller->store($model);
});

// Delete route
Flight::route('DELETE /delete/@model/@id', function($model, $id) {
    $controller = new EntityController();
    $controller->destroy($model, $id);
});

// Count route
Flight::route('GET /@model/count', function($model) {
    $controller = new EntityController();
    $controller->count($model);
});

// Filter route
Flight::route('POST /@model/filter', function($model) {
    $controller = new EntityController();
    $controller->filter($model);
});

// Generic entity routes (must be last to avoid conflicts)
Flight::route('GET /@model(/@id(/@action))', function($model, $id = null, $action = null) {
    $controller = new EntityController();

    if ($id === null) {
        // List view
        $controller->index($model);
    } elseif (preg_match(UUID_REGEX, $id) && $action === null) {
        // Detail view
        $controller->show($model, $id);
    } elseif (preg_match(UUID_REGEX, $id) && $action !== null) {
        // Edit view
        $controller->edit($model, $id);
    } else {
        Flight::json(["error" => "Invalid request"], 400);
    }
});

Flight::route('PUT /@model/@id', function($model, $id) {
    $controller = new EntityController();
    $controller->update($model, $id);
});
