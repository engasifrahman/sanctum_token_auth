<?php

namespace Tests\Unit\Logging;

use Mockery;
use Tests\TestCase;
use Illuminate\Log\Logger;
use App\Logging\LogContextProcessor;
use App\Logging\AddLogContextProcessor;

class AddLogContextProcessorTest extends TestCase
{
    /**
     * Test that invoking AddLogContextProcessor pushes a LogContextProcessor to the logger.
     *
     * This ensures that when the invokable class is called with a Logger instance,
     * it correctly registers a LogContextProcessor as a log processor.
     *
     * @return void
     */
    public function testInvokePushesLogContextProcessor(): void
    {
        // Arrange
        $loggerMock = Mockery::mock(Logger::class);
        $loggerMock->shouldReceive('pushProcessor')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg instanceof LogContextProcessor;
            }));

        // Act
        $invoker = new AddLogContextProcessor();
        $invoker($loggerMock);

        // Assert
        // No explicit assertions are required; Mockery verifies expectations.
        $this->assertTrue(true);
    }

    /**
     * Clean up Mockery after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
