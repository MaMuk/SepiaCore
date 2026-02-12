<?php
namespace SepiaCore\Entities\Files;

use SepiaCore\Entities\BaseEntity;

class Files extends BaseEntity
{
    public function __construct($table = 'files')
    {
        parent::__construct($table);
    }
}
