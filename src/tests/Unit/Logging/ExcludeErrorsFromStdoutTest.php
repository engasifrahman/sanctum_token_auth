<?php

namespace Tests\Unit\Logging;

use Monolog\Level;
use Monolog\Logger;
use ReflectionObject;
use PHPUnit\Framework\TestCase;
use Monolog\Handler\TestHandler;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use App\Logging\ExcludeErrorsFromStdout;

class ExcludeErrorsFromStdoutTest extends TestCase
{
    /**
     * Ensure that the ExcludeErrorsFromStdout invokable replaces the StreamHandler
     * with a FilterHandler while preserving other handlers.
     *
     * @return void
     */
    public function testInvokeReplacesStreamHandlerWithFilterHandler(): void
    {
        // Arrange
        $streamHandler = new StreamHandler('php://stdout', Level::Debug);
        $testHandler = new TestHandler();
        $logger = new Logger('test', [$streamHandler, $testHandler]);

        // Act
        $invoker = new ExcludeErrorsFromStdout();
        $returnedLogger = $invoker($logger);

        // Assert
        $this->assertSame($logger, $returnedLogger);

        $handlers = $returnedLogger->getHandlers();
        $this->assertInstanceOf(FilterHandler::class, $handlers[0]);
        $this->assertAttributeInstanceOf(StreamHandler::class, 'handler', $handlers[0]);
        $this->assertSame($testHandler, $handlers[1]);
    }

    /**
     * Assert that a given property in an object is an instance of the expected class.
     *
     * @param string $expected  Expected class name
     * @param string $property  Property name
     * @param object $object    Object instance
     * @return void
     */
    private function assertAttributeInstanceOf(string $expected, string $property, object $object): void
    {
        $reflection = new ReflectionObject($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $value = $prop->getValue($object);

        $this->assertInstanceOf($expected, $value, "Failed asserting that {$property} is instance of {$expected}");
    }
}
