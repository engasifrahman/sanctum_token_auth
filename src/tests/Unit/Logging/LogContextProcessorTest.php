<?php

/**
 * tests/Unit/Logging/LogContextProcessorTest.php
 */

declare(strict_types=1);

// 1) Shadow debug_backtrace() inside the App\Logging namespace

namespace App\Logging {
    /**
     * Fake debug_backtrace for deterministic test results.
     *
     * @param int $options Backtrace options.
     * @param int $limit   Number of frames to return.
     * @return array<int, array<string, mixed>>
     */
    function debug_backtrace($options = DEBUG_BACKTRACE_IGNORE_ARGS, $limit = 0): array
    {
        // Two /app/ frames (should be kept), one vendor frame (ignored),
        // and one LogContextProcessor frame (should be filtered out).
        return [
            ['file' => '/var/www/html/app/Services/MyService.php', 'line' => 42],
            ['file' => '/var/www/html/app/Console/Kernel.php', 'line' => 88],
            ['file' => '/var/www/html/vendor/laravel/framework/src/Illuminate/Support/Arr.php', 'line' => 12],
            ['file' => '/var/www/html/app/Logging/LogContextProcessor.php', 'line' => 999], // filtered
            ['file' => null], // ignored
        ];
    }
}

// 2) Actual test lives in your Tests namespace

namespace Tests\Unit\Logging {

    use App\Logging\LogContextProcessor;
    use Illuminate\Http\Request;
    use Monolog\Level;
    use Monolog\LogRecord;
    use Tests\TestCase;

    class LogContextProcessorTest extends TestCase
    {
        /**
         * Ensures that the LogContextProcessor enriches log records
         * with request, system, and caller information.
         *
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         *
         * @return void
         */
        public function testInvokeEnrichesLogRecord(): void
        {
            // Arrange
            $request = Request::create(
                '/test-uri',
                'POST',
                [],
                [],
                [],
                [
                    'REMOTE_ADDR'       => '127.0.0.1',
                    'HTTP_HOST'         => 'example.com',
                    'HTTP_ACCEPT'       => 'application/json',
                    'CONTENT_TYPE'      => 'application/json',
                    'HTTP_REFERER'      => 'https://referrer.test',
                    'HTTP_USER_AGENT'   => 'UnitTestAgent',
                    'HTTP_X_REQUEST_ID' => 'req-123',
                ]
            );
            $request->server->set('REMOTE_ADDR', '127.0.0.1');
            app()->instance('request', $request);

            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: Level::Info,
                message: 'Test message',
                context: ['foo' => 'bar'],
                extra: []
            );

            // Act
            $processor = new LogContextProcessor();
            $processed = $processor($record);
            $ctx = $processed->context;

            // Assert: caller information
            $this->assertSame('/var/www/html/app/Services/MyService.php::42', $ctx['caller']);

            // Stack frames should contain only filtered /app/ paths
            $this->assertCount(2, $ctx['stack']);
            $this->assertSame('/var/www/html/app/Services/MyService.php', $ctx['stack'][0]['file']);
            $this->assertSame(42, $ctx['stack'][0]['line']);
            $this->assertSame('/var/www/html/app/Console/Kernel.php', $ctx['stack'][1]['file']);
            $this->assertSame(88, $ctx['stack'][1]['line']);

            // Original context preserved
            $this->assertSame('bar', $ctx['foo']);

            // Request context assertions
            $this->assertSame('127.0.0.1', $ctx['request']['ip']);
            $this->assertSame('POST', $ctx['request']['method']);
            $this->assertSame('/test-uri', $ctx['request']['uri']);
            $this->assertSame('example.com', $ctx['request']['host']);
            $this->assertSame('application/json', $ctx['request']['accept']);
            $this->assertSame('application/json', $ctx['request']['content_type']);
            $this->assertSame('https://referrer.test', $ctx['request']['referer']);
            $this->assertSame('UnitTestAgent', $ctx['request']['user_agent']);
            $this->assertSame('req-123', $ctx['request']['x_request_id']);

            // System context assertions
            $this->assertArrayHasKey('memory_usage', $ctx['system']);
            $this->assertArrayHasKey('process_id', $ctx['system']);
        }
    }
}
