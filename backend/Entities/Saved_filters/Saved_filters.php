<?php
namespace SepiaCore\Entities\Saved_filters;

use SepiaCore\Entities\BaseEntity;

class Saved_filters extends BaseEntity
{
    public function __construct($table = 'saved_filters')
    {
        parent::__construct($table);
    }
}
