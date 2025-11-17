<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;

class EndpointController extends BaseController
{
    /**
     * Stores data for an endpoint.
     * @param string $name Endpoint name
     * @return void
     */
    public function store($name): void
    {
        try {
            $request = Flight::request();
            $contentType = $request->type; // e.g. "application/json" or "multipart/form-data"
            $data = [];

            // --- Detect and decode request body ---
            if (stripos($contentType, 'application/json') !== false) {
                // JSON body
                $data = json_decode($request->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Flight::jsonHalt(['error' => 'Invalid JSON payload'], 400);
            }

            } elseif (stripos($contentType, 'multipart/form-data') !== false || stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                // Form data
                $data = $request->data->getData();

                // Optional: if a field like jsonLd is a JSON string, decode it
                foreach ($data as $key => $value) {
                    if (is_string($value) && $this->isJson($value)) {
                        $data[$key] = json_decode($value, true);
                    }
                }

            } else {
                Flight::jsonHalt(['error' => 'Unsupported Content-Type: ' . $contentType], 415);
            }

            // --- Initialize entities ---

            $endpoint = $this->getEntityClass('endpoints');
            $endpointData = $endpoint->find('name', $name);

            if (!isset($endpointData['id']) || empty($endpointData['id']) || $endpointData['status'] !== 'active') {
                Flight::jsonHalt(['error' => 'Endpoint "' . $name . '" not found or inactive'], 404);
            }
            if (!empty($endpointData['token'])) {
                // Validate token from Authorization header
                $header = $request->getHeader('Authorization') ?? $request->getHeader('authorization') ?? '';
                $token = trim(preg_replace('/^Bearer\s+/i', '', $header));
                if ($token !== $endpointData['token']) {
                    Flight::jsonHalt(['error' => 'Unauthorized'], 401);
                }
            }

            $this->model = 'rawendpointdata';
            $this->entity = $this->getEntityClass($this->model);

            // --- Store in unified structure ---
            $this->entity->create([
                'endpoint_name' => $name,
                'endpoint_id' => $endpointData['id'],
                'owner' => $endpointData['owner'],
                'request_body' => $data,
                'status' => 'New',
            ]);

            Flight::json(['success' => true], 201);

        } catch (Exception $e) {
            Flight::jsonHalt(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Checks if string is valid JSON.
     * @param string $str String to check
     * @return bool True if valid JSON
     */
    private function isJson(string $str): bool
    {
        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
