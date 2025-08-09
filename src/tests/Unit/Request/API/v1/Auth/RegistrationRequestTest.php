<?php

namespace Tests\Unit\Requests\API\v1\Auth;

use Mockery;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use App\Http\Requests\API\v1\Auth\RegistrationRequest;

class RegistrationRequestTest extends TestCase
{
    /**
     * Validator factory instance used to create validators.
     *
     * @var Factory
     */
    protected Factory $validatorFactory;

    /**
     * Default valid request data used across tests.
     *
     * @var array<string, mixed>
     */
    protected array $requestData;

    /**
     * Sample emails that already exist in the database.
     *
     * @var string[]
     */
    protected array $existingEmails = ['existing@example.com'];

    /**
     * Sample roles that already exist in the database.
     *
     * @var string[]
     */
    protected array $existingRoles = ['User', 'Subscriber', 'Admin', 'Super Admin'];

    /**
     * Setup test environment and validator factory with mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $filesystem = new Filesystem();
        $langPath = base_path('lang');

        $loader = new FileLoader($filesystem, $langPath);
        $translator = new Translator($loader, 'en');

        $this->validatorFactory = new Factory($translator);

        $mockPresenceVerifier = Mockery::mock(DatabasePresenceVerifier::class);

        $mockPresenceVerifier
            ->shouldReceive('setConnection')
            ->andReturnSelf();

        $mockPresenceVerifier
            ->shouldReceive('getCount')
            ->andReturnUsing(function ($table, $column, $value) {
                if ($table === 'users' && $column === 'email' && in_array($value, $this->existingEmails)) {
                    return 1;
                }
                if ($table === 'roles' && $column === 'name') {
                    return in_array($value, $this->existingRoles) ? 1 : 0;
                }
                return 0;
            });

        $mockPresenceVerifier
            ->shouldReceive('getMultiCount')
            ->andReturnUsing(function ($table, $column, $values) {
                if ($table === 'roles' && $column === 'name') {
                    return count(array_intersect($values, $this->existingRoles));
                }
                return 0;
            });

        $this->validatorFactory->setPresenceVerifier($mockPresenceVerifier);

        $this->requestData = [
            'name' => 'John Doe',
            'email' => 'unique@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['User', 'Subscriber'],
        ];
    }

    /**
     * Creates a Validator for the given request data.
     *
     * @param array<string, mixed> $data
     * @return Validator
     */
    protected function getValidatorForRequestData(array $data): Validator
    {
        $headers = [
            'HTTP_Authorization' => 'Bearer your-token-here',
        ];

        $request = Request::create('/some-url', 'POST', $data, [], [], $headers);

        $registrationRequest = new RegistrationRequest();
        $registrationRequest->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent()
        );

        $closure = function () {
            $this->prepareForValidation();
        };
        $closure->call($registrationRequest);
        
        $registrationRequest->authorize();

        $validator = $this->validatorFactory->make($registrationRequest->all(), $registrationRequest->rules());

        $registrationRequest->withValidator($validator);

        return $validator;
    }

    /**
     * Helper to mock an authenticated user with given administrator status.
     *
     * @param bool $isAdmin Whether the mocked user is admin or not
     * @return void
     */
    protected function mockAuthenticatedUser(bool $isAdmin): void
    {
        $mockUser = Mockery::mock();
        $mockUser->shouldReceive('isAdministrator')->andReturn($isAdmin);

        Auth::shouldReceive('guard')->with('sanctum')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($mockUser);
    }

    /**
     * Test prepareForValidation normalizes email to lowercase and roles to title case.
     *
     * @return void
     */
    public function testPrepareForValidationLowercasesEmailAndTitleCasesRoles(): void
    {
        // Arrange
        $data = $this->requestData;
        $data['roles'] = ['uSeR ', ' SuBsCribER'];

        $request = new RegistrationRequest();
        $request->replace($data);

        $closure = function () {
            $this->prepareForValidation();
        };

        // Act
        $closure->call($request);

        // Assert
        $this->assertEquals('unique@example.com', $request->input('email'));
        $this->assertEquals(['User', 'Subscriber'], $request->input('roles'));
    }

    /**
     * Test validation fails with invalid input data.
     *
     * @return void
     */
    public function testValidationMustBeFailedIfInvalidDataProvided(): void
    {
        // Arrange
        $data = $this->requestData;
        $data['name'] = '';
        $data['email'] = $this->existingEmails[0];
        $data['password'] = ['password'];
        $data['roles'] = ['editor'];

        // Act
        $validator = $this->getValidatorForRequestData($data);
        $errors = $validator->errors()->toArray();

        // Assert
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertArrayHasKey('roles.0', $errors);
        $this->assertEquals('The email has already been taken.', $errors['email'][0]);
        $this->assertEquals('The selected roles.0 is invalid.', $errors['roles.0'][0]);
    }

    /**
     * Test validation fails if assigning admin role without authentication.
     *
     * @return void
     */
    public function testValidationFailsWithoutAuthWhenAssigningAdminRole(): void
    {
        // Arrange
        Auth::shouldReceive('guard')->with('sanctum')->andReturnSelf();
        Auth::shouldReceive('user')->andReturnNull();

        $data = $this->requestData;
        $data['roles'] = ['AdMin'];

        // Act
        $validator = $this->getValidatorForRequestData($data);
        $passes = $validator->passes();

        // Assert
        $this->assertFalse($passes);
        $this->assertEquals('Authentication is required to assign admin roles.', $validator->errors()->first('roles'));
    }

    /**
     * Test validation fails if non-admin user tries to assign admin role.
     *
     * @return void
     */
    public function testValidationFailsIfNonAdminUserAssignsAdminRole(): void
    {
        // Arrange
        $this->mockAuthenticatedUser(false);

        $data = $this->requestData;
        $data['roles'] = ['AdMin'];

        // Act
        $validator = $this->getValidatorForRequestData($data);
        $passes = $validator->passes();

        // Assert
        $this->assertFalse($passes);
        $this->assertEquals('Only existing administrators can assign Admin or Super Admin roles.', $validator->errors()->first('roles'));
    }

    /**
     * Test validation fails if admin roles mixed with other roles.
     *
     * @return void
     */
    public function testValidationFailsIfAdminRoleMixedWithOtherRoles(): void
    {
        // Arrange
        $this->mockAuthenticatedUser(true);

        $data = $this->requestData;
        $data['roles'] = ['AdMin', 'user'];

        // Act
        $validator = $this->getValidatorForRequestData($data);
        $passes = $validator->passes();

        // Assert
        $this->assertFalse($passes);
        $this->assertEquals('If Admin or Super Admin is selected, no other roles are allowed.', $validator->errors()->first('roles'));
    }

    /**
     * Test validation passes when an admin user assigns admin roles.
     *
     * @return void
     */
    public function testValidationPassedIfAnAdminUserAssignsAdminRole(): void
    {
        // Arrange
        $this->mockAuthenticatedUser(true);

        $data = $this->requestData;
        $data['roles'] = ['AdMin', 'super Admin'];

        // Act
        $validator = $this->getValidatorForRequestData($data);
        $passes = $validator->passes();

        // Assert
        $this->assertTrue($passes);
    }

    /**
     * Test validation fails if subscriber role assigned without user role.
     *
     * @return void
     */
    public function testValidationFailsIfSubscriberWithoutUserRole(): void
    {
        // Arrange
        $data = $this->requestData;
        $data['roles'] = ['subscriber'];

        // Act
        $validator = $this->getValidatorForRequestData($data);
        $passes = $validator->passes();

        // Assert
        $this->assertFalse($passes);
        $this->assertEquals('Subscriber role cannot be registered without the User role.', $validator->errors()->first('roles'));
    }

    /**
     * Test validation passes with valid data.
     *
     * @return void
     */
    public function testValidationMustBePassedIfValidDataProvided(): void
    {
        // Arrange
        $data = $this->requestData;

        // Act
        $validator = $this->getValidatorForRequestData($data);
        $passes = $validator->passes();
        $errors = $validator->errors()->toArray();

        // Assert
        $this->assertTrue($passes);
        $this->assertEmpty($errors);
    }

    /**
     * Clean up mockery after tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
