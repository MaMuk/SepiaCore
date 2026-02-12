<?php

namespace SepiaCore\Reports;

use RuntimeException;

class ReportValidationException extends RuntimeException
{
    private array $errors;
    private array $warnings;
    private array $definition;

    public function __construct(array $errors, array $warnings = [], array $definition = [], int $code = 400)
    {
        parent::__construct('Invalid report definition', $code);
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->definition = $definition;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }
}
