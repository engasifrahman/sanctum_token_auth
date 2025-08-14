<?php

namespace Tests\Unit\Mixins;

use Tests\TestCase;
use Illuminate\Http\Response;
use App\Mixins\ApiResponseMacros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponseMacrosTest extends TestCase
{
    /**
     * Test that the success macro returns the default JSON response structure.
     *
     * @return void
     */
    public function testSuccessMacroReturnsDefaultResponse(): void
    {
        // Arrange
        $macros = new ApiResponseMacros();
        $macro = $macros->success();

        // Act
        /** @var JsonResponse $response */
        $response = $macro();
        $data = $response->getData(true);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertTrue($data['status']);
        $this->assertSame('Operation successful!', $data['message']);
        $this->assertArrayNotHasKey('data', $data);
        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('links', $data);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test that the success macro returns a custom message, data, and status code.
     *
     * @return void
     */
    public function testSuccessMacroWithCustomMessageDataAndStatus(): void
    {
        // Arrange
        $macros = new ApiResponseMacros();
        $macro = $macros->success();

        // Act
        $response = $macro('Custom message', 201, ['foo' => 'bar']);
        $data = $response->getData(true);

        // Assert
        $this->assertTrue($data['status']);
        $this->assertSame('Custom message', $data['message']);
        $this->assertSame(['foo' => 'bar'], $data['data']);
        $this->assertSame(201, $response->getStatusCode());
    }

    /**
     * Test that the success macro correctly processes JsonResource with wrap, meta, and links.
     *
     * @return void
     */
    public function testSuccessMacroWithJsonResourceWrapMetaAndLinks(): void
    {
        // Arrange
        $macros = new ApiResponseMacros();
        $macro = $macros->success();

        $resource = new class ([
            'data' => ['id' => 1, 'name' => 'Test'],
            'meta' => ['page' => 1],
            'links' => ['self' => '/api/test']
        ]) extends JsonResource {
            public static $wrap = 'data';
        };

        // Act
        $response = $macro('Wrapped resource', 200, $resource);
        $data = $response->getData(true);

        // Assert
        $this->assertTrue($data['status']);
        $this->assertSame('Wrapped resource', $data['message']);
        $this->assertSame(['id' => 1, 'name' => 'Test'], $data['data']);
        $this->assertSame(['page' => 1], $data['meta']);
        $this->assertSame(['self' => '/api/test'], $data['links']);
    }

    /**
     * Test that the success macro processes JsonResource without wrapping.
     *
     * @return void
     */
    public function testSuccessMacroWithJsonResource(): void
    {
        // Arrange
        $macros = new ApiResponseMacros();
        $macro = $macros->success();

        $resource = new class (['id' => 1, 'name' => 'Test']) extends JsonResource {
            public static $wrap = null;
        };

        // Act
        $response = $macro('Resource test', 200, $resource);
        $data = $response->getData(true);

        // Assert
        $this->assertTrue($data['status']);
        $this->assertSame('Resource test', $data['message']);
        $this->assertSame(['id' => 1, 'name' => 'Test'], $data['data']);
    }

    /**
     * Test that the error macro returns the default JSON error response.
     *
     * @return void
     */
    public function testErrorMacroReturnsDefaultResponse(): void
    {
        // Arrange
        $macros = new ApiResponseMacros();
        $macro = $macros->error();

        // Act
        $response = $macro();
        $data = $response->getData(true);

        // Assert
        $this->assertFalse($data['status']);
        $this->assertSame('Bad request!', $data['message']);
        $this->assertArrayNotHasKey('errors', $data);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Test that the error macro returns a custom message, status code, and errors array.
     *
     * @return void
     */
    public function testErrorMacroWithCustomMessageStatusAndErrors(): void
    {
        // Arrange
        $macros = new ApiResponseMacros();
        $macro = $macros->error();
        $errors = ['field' => ['Invalid value']];

        // Act
        $response = $macro('Custom error', 422, $errors);
        $data = $response->getData(true);

        // Assert
        $this->assertFalse($data['status']);
        $this->assertSame('Custom error', $data['message']);
        $this->assertSame($errors, $data['errors']);
        $this->assertSame(422, $response->getStatusCode());
    }
}
