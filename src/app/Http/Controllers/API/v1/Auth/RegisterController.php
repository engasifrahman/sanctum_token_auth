<?php
namespace App\Http\Controllers\API\v1\Auth;

use Throwable;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use App\Http\Requests\API\v1\Auth\RegistrationRequest;

class RegisterController extends Controller
{
    /**
     * Handle the user registration request.
     *
     * @param  RegistrationRequest $request
     * @return JsonResponse
     */
    public function __invoke(RegistrationRequest $request): JsonResponse
    {
        // Define reusable log context
        $logContext = fn ($user = null) => [
            'user_id'    => $user?->id ?? null,
            'email'      => $user?->email ?? $request->input('email') ?? null,
        ];

        Log::info('User registration attempt.', $logContext());

        DB::beginTransaction();

        try {
            // Create a new user instance
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password,
            ]);

            Log::info('User created successfully.', $logContext($user));

            if ($user) {
                // Dispatch the Verified event, which will trigger sendEmailVerificationNotification
                event(new Registered($user));
                Log::info('Email verification notification sent.', $logContext($user));
            }

            DB::commit();

            Log::info('User registration transaction committed.', $logContext($user));

            return response()->success('User registered successfully. Please verify your email.');
        } catch (Throwable $th) {
            DB::rollBack();

            Log::error('User registration failed.', array_merge($logContext(), [
                    'exception_message' => $th->getMessage(),
                    'exception_file'    => $th->getFile(),
                    'exception_line'    => $th->getLine(),
            ]));

            return response()->error(
                'Registration failed. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
