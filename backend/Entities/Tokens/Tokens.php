<?php
namespace SepiaCore\Entities\Tokens;

use SepiaCore\Entities\BaseEntity;

class Tokens extends BaseEntity
{
    public function __construct($table = 'tokens')
    {
        parent::__construct($table); // tokens table
    }
}