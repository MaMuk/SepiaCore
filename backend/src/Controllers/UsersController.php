<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;

class UsersController extends BaseController
{
    /**
     * Lists all users (admin only).
     * @return void
     */
    public function index(): void
    {
        if (!$GLOBALS['isAdmin']) {
            $this->jsonHalt(['error' => 'Unauthorized. Admin access required.'], 403);
            return;
        }

        $this->model = 'Users';
        $this->entity = $this->getEntityClass('Users');

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

        // Remove password_hash from response
        $records = array_map(function($record) {
            unset($record['password_hash']);
            return $record;
        }, $records);

        $this->jsonResponse([
            'records' => $records,
            'total' => $this->entity->count()
        ]);
    }

    /**
     * Shows a specific user (admin only).
     * @param string $id User ID
     * @return void
     */
    public function show($id): void
    {
        if (!$GLOBALS['isAdmin']) {
            $this->jsonHalt(['error' => 'Unauthorized. Admin access required.'], 403);
            return;
        }

        $this->model = 'Users';
        $this->entity = $this->getEntityClass('Users');

        $record = $this->entity->read($id);

        if (!$record) {
            $this->jsonHalt(['error' => 'User not found'], 404);
            return;
        }

        // Remove password_hash from response
        unset($record['password_hash']);

        $this->jsonResponse($record);
    }

    /**
     * Creates a new user (admin only).
     * @return void
     */
    public function store(): void
    {
        if (!$GLOBALS['isAdmin']) {
            $this->jsonHalt(['error' => 'Unauthorized. Admin access required.'], 403);
            return;
        }

        $this->model = 'Users';
        $this->entity = $this->getEntityClass('Users');

        $data = Flight::request()->data->getData();

        // Validate required fields
        if (empty($data['name'])) {
            $this->jsonHalt(['error' => 'Username is required'], 400);
            return;
        }

        // Check if user already exists
        $existingUser = $this->entity->find('name', $data['name']);
        if ($existingUser) {
            $this->jsonHalt(['error' => 'User with this name already exists'], 400);
            return;
        }

        // Hash password if provided
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        } else {
            $this->jsonHalt(['error' => 'Password is required'], 400);
            return;
        }

        // Set default values
        if (!isset($data['isadmin'])) {
            $data['isadmin'] = false;
        }

        // Remove id if present
        if (isset($data['id'])) {
            unset($data['id']);
        }

        try {
            $storedEntity = $this->entity->create($data);
            $record = $this->entity->read($storedEntity['id']);

            // Remove password_hash from response
            unset($record['password_hash']);

            $this->jsonResponse($record, 201);
        } catch (Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Updates a user (admin only, or user updating their own profile).
     * @param string $id User ID
     * @return void
     */
    public function update($id): void
    {
        $this->model = 'Users';
        $this->entity = $this->getEntityClass('Users');

        $data = Flight::request()->data->getData();

        // Check if user exists
        $existingUser = $this->entity->read($id);
        if (!$existingUser) {
            $this->jsonHalt(['error' => 'User not found'], 404);
            return;
        }

        // Check permissions: user can only update their own profile, unless they're admin
        $currentUserId = $GLOBALS['user_id'];
        $isAdmin = $GLOBALS['isAdmin'];

        if (!$isAdmin && $currentUserId !== $id) {
            $this->jsonHalt(['error' => 'Unauthorized. You can only update your own profile.'], 403);
            return;
        }

        // If admin is updating another user, allow all fields
        // If user is updating their own profile, only allow password update
        if (!$isAdmin) {
            // Non-admin users can only update their password
            $allowedFields = ['password'];
            $data = array_intersect_key($data, array_flip($allowedFields));
        }

        // Hash password if provided
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        // Remove id if present
        if (isset($data['id'])) {
            unset($data['id']);
        }

        // Don't allow changing isadmin unless current user is admin
        if (!$isAdmin && isset($data['isadmin'])) {
            unset($data['isadmin']);
        }

        // Prevent users from removing their own admin rights
        if ($isAdmin && isset($data['isadmin']) && $currentUserId === $id) {
            $currentIsAdmin = (bool) $existingUser['isadmin'];
            $newIsAdmin = (bool) $data['isadmin'];
            
            // If trying to remove own admin rights, prevent it
            if ($currentIsAdmin && !$newIsAdmin) {
                $this->jsonHalt(['error' => 'You cannot remove your own administrator privileges.'], 400);
                return;
            }
        }

        try {
            $this->entity->update($id, $data);
            $record = $this->entity->read($id);

            // Remove password_hash from response
            unset($record['password_hash']);

            $this->jsonResponse($record);
        } catch (Exception $e) {
            $this->jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Deletes a user (admin only).
     * @param string $id User ID
     * @return void
     */
    public function destroy($id): void
    {
        if (!$GLOBALS['isAdmin']) {
            $this->jsonHalt(['error' => 'Unauthorized. Admin access required.'], 403);
            return;
        }

        $this->model = 'Users';
        $this->entity = $this->getEntityClass('Users');

        // Prevent deleting yourself
        $currentUserId = $GLOBALS['user_id'];
        if ($currentUserId === $id) {
            $this->jsonHalt(['error' => 'You cannot delete your own account'], 400);
            return;
        }

        if ($this->entity->delete($id)) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonHalt(['error' => 'Failed to delete user'], 400);
        }
    }

    /**
     * Gets current user profile.
     * @return void
     */
    public function profile(): void
    {
        $this->model = 'Users';
        $this->entity = $this->getEntityClass('Users');

        $currentUserId = $GLOBALS['user_id'];
        $record = $this->entity->read($currentUserId);

        if (!$record) {
            $this->jsonHalt(['error' => 'User not found'], 404);
            return;
        }

        // Remove password_hash from response
        unset($record['password_hash']);

        $this->jsonResponse($record);
    }

    /**
     * Gets owned records count for a user.
     * @param string $id User ID
     * @return void
     */
    public function ownedRecords($id): void
    {
        if (!$GLOBALS['isAdmin']) {
            $this->jsonHalt(['error' => 'Unauthorized. Admin access required.'], 403);
            return;
        }

        $this->model = 'Users';
        $this->entity = $this->getEntityClass('Users');

        $user = $this->entity->read($id);
        if (!$user) {
            $this->jsonHalt(['error' => 'User not found'], 404);
            return;
        }

        $ownedRecords = [];
        $entities = $GLOBALS['metadata']['entities'] ?? [];

        foreach ($entities as $entityName => $entityMeta) {
            // Skip system entities
            if (in_array(strtolower($entityName), ['users', 'tokens', 'modulebuilder', 'dashboards', 'rawendpointdata', 'endpoints'])) {
                continue;
            }

            $fields = $entityMeta['fields'] ?? [];
            // Check if entity has owner field
            if (isset($fields['owner']) && $fields['owner']['type'] === 'relationship') {
                try {
                    $entity = $this->getEntityClass($entityName);
                    $table = $entity->getTableName();
                    $count = \Illuminate\Database\Capsule\Manager::table($table)
                        ->where('owner', $id)
                        ->count();
                    
                    if ($count > 0) {
                        $ownedRecords[$entityName] = [
                            'entity' => $entityName,
                            'count' => $count,
                            'displayName' => $this->formatEntityName($entityName)
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip entities that can't be loaded
                    continue;
                }
            }
        }

        $this->jsonResponse([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'] ?? ''
            ],
            'ownedRecords' => $ownedRecords
        ]);
    }

    /**
     * Formats entity name for display.
     * @param string $entityName Entity name
     * @return string Formatted name
     */
    private function formatEntityName($entityName): string
    {
        return ucwords(str_replace('_', ' ', $entityName));
    }
}

