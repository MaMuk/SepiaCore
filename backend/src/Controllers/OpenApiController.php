<?php

namespace SepiaCore\Controllers;

use Exception;
use Flight;
use ReflectionClass;

class OpenApiController extends BaseController
{
    /**
     * Returns OpenAPI specification JSON.
     * @return void
     */
    public function index(): void
    {
        Flight::json($this->generateOpenApiJson());
    }

    /**
     * Generates OpenAPI specification JSON.
     * @return array OpenAPI specification
     */
    public function generateOpenApiJson(): array
    {
        // Basic OpenAPI 3.0 specification structure
        // Full implementation would require parsing FlightPHP routes dynamically
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $GLOBALS['metadata']['appTitle'] ?? 'SepiaCore API',
                'version' => '1.0.0',
                'description' => 'SepiaCore REST API'
            ],
            'servers' => [
                ['url' => 'http://localhost', 'description' => 'Local server'],
            ],
            'paths' => []
        ];

        return $openapi;
    }
}
