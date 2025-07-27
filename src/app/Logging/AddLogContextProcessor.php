<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use App\Logging\LogContextProcessor;

class AddLogContextProcessor
{
    /**
     * Customize the given logger instance.
     *
     * @param  Logger  $logger
     * @return void
     */
    public function __invoke(Logger $logger)
    {
        // According to Laravel documentation, Illuminate\Log\Logger proxies
        // method calls to the underlying Monolog instance.
        // Therefore, we can directly push the processor to the LaravelLogger instance,
        // and it will be forwarded to the wrapped Monolog instance.
        $logger->pushProcessor(new LogContextProcessor());
    }
}
