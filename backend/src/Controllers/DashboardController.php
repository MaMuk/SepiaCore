<?php

namespace SepiaCore\Controllers;

use Flight;
use Illuminate\Database\Capsule\Manager as Capsule;

class DashboardController extends EntityController
{
    public function default(): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $dashboards = $this->ensureDefaultDashboard($userId);
        $defaultDashboard = $this->findDefaultDashboard($dashboards);

        $this->jsonResponse($this->buildDashboardResponse($dashboards, $defaultDashboard, $defaultDashboard));
    }

    public function showDashboard($id): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $dashboard = $this->loadDashboardById($id);
        if (!$dashboard) {
            $this->jsonHalt(['error' => 'Dashboard not found'], 404);
        }

        if (!$this->canAccessDashboard($dashboard, $userId)) {
            $this->jsonHalt(['error' => 'Forbidden'], 403);
        }

        $dashboards = $this->ensureDefaultDashboard($userId);
        $defaultDashboard = $this->findDefaultDashboard($dashboards);

        $this->jsonResponse($this->buildDashboardResponse($dashboards, $defaultDashboard, $dashboard));
    }

    public function updateDashboard($id): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $dashboard = $this->loadDashboardById($id);
        if (!$dashboard) {
            $this->jsonHalt(['error' => 'Dashboard not found'], 404);
        }

        if (!$this->canAccessDashboard($dashboard, $userId)) {
            $this->jsonHalt(['error' => 'Forbidden'], 403);
        }

        $payload = json_decode(Flight::request()->getBody(), true);
        $widgets = $payload['widgets'] ?? null;

        if (!is_array($widgets)) {
            $this->jsonHalt(['error' => 'Invalid widgets payload'], 400);
        }

        $dashboardsEntity = $this->getEntityClass('Dashboards');
        $dashboardsEntity->update($id, ['widgets' => $this->encodeWidgetsForStorage($widgets)]);

        $updated = $dashboardsEntity->read($id);

        $this->jsonResponse([
            'success' => true,
            'dashboard' => $this->sanitizeDashboard($updated),
            'widgets' => $this->normalizeWidgets($updated['widgets'] ?? []),
        ]);
    }

    public function createDashboard(): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode(Flight::request()->getBody(), true);
        $name = trim($payload['name'] ?? '');
        if ($name === '') {
            $name = 'Untitled Dashboard';
        }

        $dashboards = $this->loadDashboardsForUser($userId);
        $isDefault = empty($dashboards);

        $dashboardsEntity = $this->getEntityClass('Dashboards');
        $created = $dashboardsEntity->create([
            'name' => $name,
            'owner' => $userId,
            'is_default' => $isDefault,
            'widgets' => $this->encodeWidgetsForStorage([]),
        ]);

        $createdDashboard = $dashboardsEntity->read($created['id']);

        $dashboards = $this->loadDashboardsForUser($userId);
        $defaultDashboard = $this->findDefaultDashboard($dashboards);

        $this->jsonResponse($this->buildDashboardResponse(
            $dashboards,
            $defaultDashboard,
            $this->sanitizeDashboard($createdDashboard)
        ));
    }

    public function setDefaultDashboard($id): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $dashboard = $this->loadDashboardById($id);
        if (!$dashboard) {
            $this->jsonHalt(['error' => 'Dashboard not found'], 404);
        }

        if (!$this->canAccessDashboard($dashboard, $userId)) {
            $this->jsonHalt(['error' => 'Forbidden'], 403);
        }

        $ownerId = $dashboard['owner'] ?? $userId;
        Capsule::table('dashboards')
            ->where('owner', $ownerId)
            ->update(['is_default' => 0]);

        Capsule::table('dashboards')
            ->where('id', $id)
            ->update(['is_default' => 1]);

        $dashboards = $this->loadDashboardsForUser($ownerId);
        $defaultDashboard = $this->findDefaultDashboard($dashboards);

        $this->jsonResponse($this->buildDashboardResponse(
            $dashboards,
            $defaultDashboard,
            $dashboard
        ));
    }

    public function deleteDashboard($id): void
    {
        $userId = $GLOBALS['user_id'] ?? null;
        if (empty($userId)) {
            $this->jsonHalt(['error' => 'Unauthorized'], 401);
        }

        $dashboard = $this->loadDashboardById($id);
        if (!$dashboard) {
            $this->jsonHalt(['error' => 'Dashboard not found'], 404);
        }

        if (!$this->canAccessDashboard($dashboard, $userId)) {
            $this->jsonHalt(['error' => 'Forbidden'], 403);
        }

        $ownerId = $dashboard['owner'] ?? $userId;
        $dashboardsEntity = $this->getEntityClass('Dashboards');
        $dashboardsEntity->delete($id);

        $dashboards = $this->loadDashboardsForUser($ownerId);
        $defaultDashboard = $this->findDefaultDashboard($dashboards);

        if (!$defaultDashboard && !empty($dashboards)) {
            $first = $dashboards[0];
            $dashboardsEntity->update($first['id'], ['is_default' => true]);
            $dashboards = $this->loadDashboardsForUser($ownerId);
            $defaultDashboard = $this->findDefaultDashboard($dashboards);
        }

        $this->jsonResponse($this->buildDashboardResponse(
            $dashboards,
            $defaultDashboard,
            $defaultDashboard
        ));
    }

    private function ensureDefaultDashboard(string $userId): array
    {
        $dashboards = $this->loadDashboardsForUser($userId);

        if (empty($dashboards)) {
            $dashboardsEntity = $this->getEntityClass('Dashboards');
            $dashboardsEntity->create([
                'name' => 'Main Dashboard',
                'owner' => $userId,
                'is_default' => true,
                'widgets' => $this->encodeWidgetsForStorage([]),
            ]);
            $dashboards = $this->loadDashboardsForUser($userId);
        }

        $defaultDashboard = $this->findDefaultDashboard($dashboards);
        if (!$defaultDashboard && !empty($dashboards)) {
            $first = $dashboards[0];
            $dashboardsEntity = $this->getEntityClass('Dashboards');
            $dashboardsEntity->update($first['id'], ['is_default' => true]);
            $dashboards = $this->loadDashboardsForUser($userId);
        }

        return $dashboards;
    }

    private function loadDashboardsForUser(string $userId): array
    {
        $records = Capsule::table('dashboards')
            ->where('owner', $userId)
            ->orderBy('date_modified', 'DESC')
            ->get()
            ->map(fn($item) => (array) $item)
            ->all();

        return array_map([$this, 'sanitizeDashboard'], $records);
    }

    private function loadDashboardById(string $id): ?array
    {
        $record = Capsule::table('dashboards')->where('id', $id)->first();
        if (!$record) {
            return null;
        }

        return $this->sanitizeDashboard((array) $record);
    }

    private function sanitizeDashboard(array $dashboard): array
    {
        $dashboard['widgets'] = $this->normalizeWidgets($dashboard['widgets'] ?? []);
        return $dashboard;
    }

    private function normalizeWidgets($widgets): array
    {
        if (is_string($widgets)) {
            $decoded = json_decode($widgets, true);
            return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }

        if (is_array($widgets)) {
            return $widgets;
        }

        return [];
    }

    private function encodeWidgetsForStorage(array $widgets): string
    {
        return json_encode(array_values($widgets));
    }

    private function findDefaultDashboard(array $dashboards): ?array
    {
        foreach ($dashboards as $dashboard) {
            if (!empty($dashboard['is_default'])) {
                return $dashboard;
            }
        }

        return $dashboards[0] ?? null;
    }

    private function buildDashboardResponse(array $dashboards, ?array $defaultDashboard, ?array $activeDashboard): array
    {
        $dashboardSummaries = array_map(function ($dashboard) {
            return [
                'id' => $dashboard['id'],
                'name' => $dashboard['name'] ?? 'Untitled',
            ];
        }, $dashboards);

        $defaultSummary = $defaultDashboard
            ? ['id' => $defaultDashboard['id'], 'name' => $defaultDashboard['name'] ?? 'Untitled']
            : null;

        $activeSummary = $activeDashboard
            ? ['id' => $activeDashboard['id'], 'name' => $activeDashboard['name'] ?? 'Untitled']
            : $defaultSummary;

        return [
            'dashboards' => $dashboardSummaries,
            'defaultDashboard' => $defaultSummary,
            'activeDashboard' => $activeSummary,
            'widgets' => $activeDashboard ? $this->normalizeWidgets($activeDashboard['widgets'] ?? []) : [],
        ];
    }

    private function canAccessDashboard(array $dashboard, string $userId): bool
    {
        if (isAdmin()) {
            return true;
        }

        return !empty($dashboard['owner']) && $dashboard['owner'] === $userId;
    }
}
