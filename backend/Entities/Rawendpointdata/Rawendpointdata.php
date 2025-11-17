<?php
namespace SepiaCore\Entities\Rawendpointdata;

use SepiaCore\Entities\BaseEntity;

class Rawendpointdata extends BaseEntity
{
    public function __construct($table = 'rawendpointdata')
    {
        parent::__construct($table);
    }

    /**
     * Create a new raw endpoint data record.
     * Automatically JSON-encodes the request_body if it's an array or object.
     */
    public function create($data)
    {
        if (isset($data['request_body']) && (is_array($data['request_body']) || is_object($data['request_body']))) {
            $data['request_body'] = json_encode($data['request_body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return parent::create($data);
    }

    /**
     * Get and decode the request body of a record.
     */
    public function getDecodedBody($record)
    {
        if (!isset($record['request_body'])) {
            return null;
        }

        $decoded = json_decode($record['request_body'], true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $record['request_body'];
    }
}
