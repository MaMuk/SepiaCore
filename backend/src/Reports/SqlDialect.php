<?php

namespace SepiaCore\Reports;

class SqlDialect
{
    public static function bucketExpr(string $driver, string $field, string $bucket): string
    {
        $bucket = strtolower($bucket);
        if ($bucket === 'none' || $bucket === '') {
            return $field;
        }

        switch (strtolower($driver)) {
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return self::postgresBucketExpr($field, $bucket);
            case 'mysql':
            case 'mariadb':
                return self::mysqlBucketExpr($field, $bucket);
            case 'sqlite':
            default:
                return self::sqliteBucketExpr($field, $bucket);
        }
    }

    public static function labelExpr(string $driver, string $field, string $bucket): string
    {
        return self::bucketExpr($driver, $field, $bucket);
    }

    private static function sqliteBucketExpr(string $field, string $bucket): string
    {
        switch ($bucket) {
            case 'day':
                return "strftime('%Y-%m-%d', {$field})";
            case 'week':
                return "strftime('%Y-W%W', {$field})";
            case 'month':
                return "strftime('%Y-%m', {$field})";
            case 'quarter':
                return "printf('%04d-Q%d', strftime('%Y', {$field}), ((cast(strftime('%m', {$field}) as integer) - 1) / 3) + 1)";
            case 'year':
                return "strftime('%Y', {$field})";
            default:
                return $field;
        }
    }

    private static function postgresBucketExpr(string $field, string $bucket): string
    {
        switch ($bucket) {
            case 'day':
                return "to_char(date_trunc('day', {$field}), 'YYYY-MM-DD')";
            case 'week':
                return "to_char(date_trunc('week', {$field}), 'IYYY-\"W\"IW')";
            case 'month':
                return "to_char(date_trunc('month', {$field}), 'YYYY-MM')";
            case 'quarter':
                return "to_char(date_trunc('quarter', {$field}), 'YYYY-\"Q\"Q')";
            case 'year':
                return "to_char(date_trunc('year', {$field}), 'YYYY')";
            default:
                return $field;
        }
    }

    private static function mysqlBucketExpr(string $field, string $bucket): string
    {
        switch ($bucket) {
            case 'day':
                return "date_format({$field}, '%Y-%m-%d')";
            case 'week':
                return "date_format({$field}, '%x-W%v')";
            case 'month':
                return "date_format({$field}, '%Y-%m')";
            case 'quarter':
                return "concat(year({$field}), '-Q', quarter({$field}))";
            case 'year':
                return "date_format({$field}, '%Y')";
            default:
                return $field;
        }
    }
}
