<?php

namespace Tests\Unit\Requests\API\v1\Auth;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\API\v1\Auth\LoginRequest;

class LoginRequestTest extends TestCase
{
    public function testValidationMustFailedIfEmailAndPasswordIsMissing()
    {
        // Arrange
        $request = new LoginRequest();
        $request->authorize();
        $rules = $request->rules();

        // Test case 1: Missing email and password
        $data = [];

        // Act
        $validator = Validator::make($data, $rules);

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertCount(2, $validator->errors()->all());
        $this->assertEquals('The email field is required.', $validator->errors()->first('email'));
        $this->assertEquals('The password field is required.', $validator->errors()->first('password'));
    }

    public function testValidationMustPassedIfEmailAndPasswordIsPresent()
    {
        // Arrange
        $request = new LoginRequest();
        $request->authorize();
        $rules = $request->rules();

        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // Act
        $validator = Validator::make($data, $rules);

        // Assert
        $this->assertFalse($validator->fails());

    }
}
