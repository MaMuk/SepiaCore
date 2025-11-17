<?php
namespace SepiaCore\Entities\Dashboards;

use SepiaCore\Entities\BaseEntity;

class Dashboards extends BaseEntity {

    public function __construct($table = 'dashboards')
    {
        parent::__construct($table);
    }

}