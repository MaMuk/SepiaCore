<?php

namespace SepiaCore\Reports;

class ReportEngineFactory
{
    public static function make(): ReportEngine
    {
        $configEngine = $GLOBALS['config']['report_engine'] ?? null;
        $envEngine = getenv('REPORT_ENGINE') ?: null;
        $engine = strtolower(trim((string) ($configEngine ?: $envEngine ?: 'sql')));

        switch ($engine) {
            case 'sql':
            default:
                return new SqlReportEngine();
        }
    }
}
