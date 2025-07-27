<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class LogContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
        $appTrace = [];

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;

            // Normalize backslashes to forward slashes
            $normalizedFile = str_replace('\\', '/', $file);

            // Skip frames outside /app/ or specific processor/middleware files
            if (
                $normalizedFile
                && str_contains($normalizedFile, '/app/')
                && !str_contains($normalizedFile, 'LogContextProcessor.php')
                && !str_contains($normalizedFile, 'RequestIdMiddleware.php')
                && !str_contains($normalizedFile, 'RoleMiddleware.php')
            ) {
                $appTrace[] = [
                    'file' => $file,
                    'line' => $frame['line'] ?? 'null',
                ];
            }
        }

        // Build caller string from first app frame or default nulls
        $topTrace = $appTrace[0] ?? ['file' => 'null', 'line' => 'null'];
        $caller = $topTrace['file'] . '::' . $topTrace['line'];

        $enrichedContext = array_merge(
            $record->context,
            [
                'caller' => $caller,
                'stack' => $appTrace,
                'request' => [
                    'ip' => request()->ip(),
                    'method' => request()->method(),
                    'uri' => request()->getRequestUri(),
                    'host' => request()->header('Host'),
                    'accept' => request()->header('Accept'),
                    'content_type' => request()->header('Content-Type'),
                    'referer' => request()->header('Referer'),
                    'user_agent' => request()->header('User-Agent'),
                    'x_request_id' => request()->header('X-Request-Id'),
                ],
                'system' => [
                    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                    'process_id' => getmypid(),
                ],
            ]
        );

        return $record->with(
            context: $enrichedContext,
            extra: []
        );
    }
}
