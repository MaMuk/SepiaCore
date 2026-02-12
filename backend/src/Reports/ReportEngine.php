<?php

namespace SepiaCore\Reports;

interface ReportEngine
{
    /**
     * Execute a report definition and return a ReportResult payload.
     * @param array $definition
     * @param array $context
     * @return array
     */
    public function run(array $definition, array $context): array;

    /**
     * Validate a report definition without executing it.
     * @param array $definition
     * @param array $context
     * @return array
     */
    public function validate(array $definition, array $context): array;
}
