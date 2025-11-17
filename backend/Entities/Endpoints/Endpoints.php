<?php
namespace SepiaCore\Entities\Endpoints;

use SepiaCore\Entities\BaseEntity;

class Endpoints extends BaseEntity
{
    public function __construct($table = 'endpoints')
    {
        parent::__construct($table);
    }
}