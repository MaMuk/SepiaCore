<?php

namespace SepiaCore\Utilities;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

class Log
{
    public static function getLogger(): Logger {
        static $logger = null;

        if ($logger === null) {
            $logFile = ROOT_DIR . '/logs/application.log';
            $maxFiles = 2;
            $logger = new Logger('app');
            $logger->pushHandler(new RotatingFileHandler($logFile, $maxFiles, Logger::DEBUG, true, 0664));
        }

        return $logger;
    }

    public static function logMessage(string $message, string $level = 'info') {
        $logger = self::getLogger();
        $logger->{$level}($message);
    }
}
