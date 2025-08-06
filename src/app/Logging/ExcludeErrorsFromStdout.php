<?php

namespace App\Logging;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;

/**
 * Customize the stdout channel to exclude error and higher level logs.
 */
class ExcludeErrorsFromStdout
{
    /**
     * Invoked by Laravel logging configuration.
     *
     * This replaces StreamHandlers with a FilterHandler to only allow logs
     * below the error level (e.g., debug, info, notice, warning).
     *
     * @param Logger $logger
     * @return Logger
     */
    public function __invoke(Logger $logger): Logger
    {
        $handlers = $logger->getHandlers();
        $newHandlers = [];

        foreach ($handlers as $handler) {
            if ($handler instanceof StreamHandler) {
                $filteredHandler = new FilterHandler(
                    $handler,
                    Level::Debug,   // Minimum log level
                    Level::Warning  // Maximum log level (excludes Error and above)
                );
                $newHandlers[] = $filteredHandler;
            } else {
                $newHandlers[] = $handler;
            }
        }

        $logger->setHandlers($newHandlers);

        return $logger;
    }
}
