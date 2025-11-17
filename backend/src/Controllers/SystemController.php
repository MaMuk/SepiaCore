<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use SepiaCore\Utilities\Log;

class SystemController extends BaseController
{
    /**
     * Returns application metadata.
     * @return void
     */
    public function metadata(): void
    {
        global $metadata;
        $this->jsonResponse($metadata);
    }

    /**
     * Returns current system settings.
     * @return void
     */
    public function getSettings(): void
    {
        $this->jsonResponse($GLOBALS['settings']);
    }

    /**
     * Updates a system setting. Requires admin privileges.
     * @return void
     */
    public function updateSettings(): void
    {
        $data = json_decode(Flight::request()->getBody(), true);

        if (!isAdmin()) {
            $this->jsonHalt([
                'status' => 'error',
                'message' => 'Unauthorized. Only Admin user can set System Settings'
            ], 401);
        }

        if (!isset($data['key']) || !isset($data['value'])) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Invalid input. Key and value are required.'
            ], 400);
            return;
        }

        try {
            $this->saveSettings($data['key'], $data['value']);
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Settings saved successfully.'
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renders a system component template.
     * @param string $component Component name
     * @return void
     */
    public function component($component): void
    {
        $entityName = Flight::request()->query['entity'];

        if ($entityName) {
            $entity = $this->getEntityClass($entityName);
            Log::logMessage('Twig rendering is deprecated in SystemController::component()', 'warning');
            Flight::response()->write(
                $entity->twig->render('components/' . $component . '.html.twig', ['entity' => $entityName])
            );
        } else {
            $entity = new \SepiaCore\Entities\BaseEntity('foo');
            Log::logMessage('Twig rendering is deprecated in SystemController::component()', 'warning');
            Flight::response()->write(
                $entity->twig->render('components/' . $component . '.html.twig')
            );
        }
    }

    /**
     * Saves a setting to the settings config file.
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return void
     * @throws Exception If settings are not initialized or file write fails
     */
    private function saveSettings($key, $value): void
    {
        $settingsFile = ROOT_DIR . '/config/settings.php';

        if (!isset($GLOBALS['settings']) || !is_array($GLOBALS['settings'])) {
            throw new Exception("Settings are not initialized properly.");
        }

        $GLOBALS['settings'][$key] = $value;

        if (!file_exists($settingsFile)) {
            if (!is_dir(ROOT_DIR . '/config')) {
                if (mkdir(ROOT_DIR . '/config', 0777, true) === false) {
                    throw new Exception("/config directory does not exist and could not be created");
                }
            }
        }

        $content = "<?php\nreturn " . var_export($GLOBALS['settings'], true) . ";\n";

        if (file_put_contents($settingsFile, $content) === false) {
            throw new Exception("Failed to save settings to file.");
        }
    }
}