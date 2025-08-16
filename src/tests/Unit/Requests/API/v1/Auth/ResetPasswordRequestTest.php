<?php

namespace Tests\Unit\Requests\API\v1\Auth;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\API\v1\Auth\ResetPasswordRequest;

class ResetPasswordRequestTest extends TestCase
{
    /**
     * Helper method to create a validator instance for a given set of data.
     *
     * @param array $data The data to validate.
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function createValidator(array $data)
    {
        $request = new ResetPasswordRequest();
        $request->authorize();
        $rules = $request->rules();
        return Validator::make($data, $rules);
    }

    /**
     * Tests that validation fails when all required fields are missing.
     *
     * @return void
     */
    public function testValidationFailsIfAllFieldsAreMissing()
    {
        // Arrange
        $data = [];

        // Act
        $validator = $this->createValidator($data);

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertCount(3, $validator->errors()->all());
        $this->assertEquals('The token field is required.', $validator->errors()->first('token'));
        $this->assertEquals('The email field is required.', $validator->errors()->first('email'));
        $this->assertEquals('The password field is required.', $validator->errors()->first('password'));
    }

    /**
     * Tests that validation fails with invalid email format.
     *
     * @return void
     */
    public function testValidationFailsWithInvalidEmailFormat()
    {
        // Arrange
        $data = [
            'token' => 'some-valid-token',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act
        $validator = $this->createValidator($data);

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertCount(1, $validator->errors()->all());
        $this->assertEquals('The email field must be a valid email address.', $validator->errors()->first('email'));
    }

    /**
     * Tests that validation fails when password is too short.
     *
     * @return void
     */
    public function testValidationFailsIfPasswordIsTooShort()
    {
        // Arrange
        $data = [
            'token' => 'some-valid-token',
            'email' => 'test@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ];

        // Act
        $validator = $this->createValidator($data);

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertCount(1, $validator->errors()->all());
        $this->assertEquals('The password field must be at least 6 characters.', $validator->errors()->first('password'));
    }

    /**
     * Tests that validation fails when passwords do not match.
     *
     * @return void
     */
    public function testValidationFailsIfPasswordsDoNotMatch()
    {
        // Arrange
        $data = [
            'token' => 'some-valid-token',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password-mismatch',
        ];

        // Act
        $validator = $this->createValidator($data);

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertCount(1, $validator->errors()->all());
        $this->assertEquals('The password field confirmation does not match.', $validator->errors()->first('password'));
    }

    /**
     * Tests that validation passes when all fields are present and valid.
     *
     * @return void
     */
    public function testValidationPassesIfAllFieldsArePresentAndValid()
    {
        // Arrange
        $data = [
            'token' => 'some-valid-token',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act
        $validator = $this->createValidator($data);

        // Assert
        $this->assertFalse($validator->fails());
    }
}
