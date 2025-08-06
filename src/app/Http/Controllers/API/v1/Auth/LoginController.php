<?php

namespace App\Http\Controllers\API\v1\Auth;

use Throwable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\API\v1\Auth\LoginRequest;

class LoginController extends Controller
{
    /**
     * Handle the incoming login request.
     *
     * @param  LoginRequest  $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Define reusable log context
        $logContext = fn ($user = null) => [
            'user_id'    => $user->id ?? null,
            'email'      => $user->email ?? $request->input('email') ?? null,
        ];

        Log::info('Login attempt.', $logContext());

        // Retrieve the user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password matches
        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('Failed login attempt due to invalid credentials.', $logContext());

            return response()->error('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        // Check if the user has verified their email
        if (!$user->hasVerifiedEmail()) {
            Log::warning('Failed login attempt due to unverified email.', $logContext($user));

            return response()->error('Please verify your email first.', Response::HTTP_FORBIDDEN);
        }

        try {
            // Create a new access token for the user
            $token = $user->createToken($user->email)->plainTextToken;

            Log::info('User login successful.', $logContext($user));

            return response()->success('Login successful.', Response::HTTP_OK, [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'expires_in'   => config('sanctum.expiration') * 60,
                'user'         => $user->only(['id', 'name', 'email', 'role_names']),
            ]);
        } catch (Throwable $th) {
            Log::error('Login failed unexpectedly during token creation.', array_merge($logContext($user), [
                'exception_message' => $th->getMessage(),
                'exception_file'    => $th->getFile(),
                'exception_line'    => $th->getLine(),
            ]));

            return response()->error(
                'An unexpected error occurred during login. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Handle the incoming refresh token creation request.
     *
     * @param  request  $request
     * @return JsonResponse
     */
    public function refreshToken(Request $request): JsonResponse
    {
        // Retrieve the authenticated user from the request
        $user = $request->user();

        // Define reusable log context
        $logContext = fn () => [
            'user_id'    => $user->id ?? null,
            'email'      => $user->email ?? null,
        ];

        Log::info('Refresh token creation attempt.', $logContext());

        try {
            // Revoke the current access token to ensure a new one is created
            $user->currentAccessToken()->delete();

            Log::info(
                'Current access token revoked successfully to create a new refresh token.',
                $logContext()
            );
        } catch (Throwable $th) {
            Log::error('Failed to revoke current access token during refresh token creation.', array_merge($logContext(), [
                    'exception_message' => $th->getMessage(),
                    'exception_file'    => $th->getFile(),
                    'exception_line'    => $th->getLine(),
            ]));

            return response()->error(
                'An unexpected error occurred during refresh token generation. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        try {
            // Create a new access token for the user
            $token = $user->createToken($user->email)->plainTextToken;

            Log::info('Refresh token created successfully.', $logContext());

            return response()->success('Refresh token created successfully.', Response::HTTP_OK, [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'expires_in'   => config('sanctum.expiration') * 60,
            ]);
        } catch (Throwable $th) {
            Log::error('Refresh token creation failed unexpectedly during token creation.', array_merge(
                $logContext(),
                [
                    'exception_message' => $th->getMessage(),
                    'exception_file'    => $th->getFile(),
                    'exception_line'    => $th->getLine(),
                ]
            ));

            return response()->error(
                'An unexpected error occurred during refresh token generation. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
