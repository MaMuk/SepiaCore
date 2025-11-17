<?php

namespace SepiaCore\Entities\ModuleBuilder;

use SepiaCore\Entities\BaseEntity;

class ModuleBuilder extends BaseEntity
{
    public function __construct($table = 'dashboards')
    {
        parent::__construct($table);
    }

    protected function initTwig(): void
    {
        // ModuleBuilder entity doesn't need Twig initialization
        // This is handled by EntityStudioController
    }

    protected function loadFields(): void
    {
        // ModuleBuilder entity doesn't load fields from database
        $this->fields = [];
        $this->fieldDefs = [];
    }
}