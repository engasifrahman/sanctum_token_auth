<?php

namespace App\Http\Controllers\API\v1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    /**
     * Send a password reset link to the given user.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        // Validate the request data first
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        // Define reusable log context
        $logContext = fn () => [
            'email'      => $email,
        ];

        Log::info('Forgot Password attempt.', $logContext());

        // Send the password reset link
        $status = Password::sendResetLink($request->only('email'));

        switch ($status) {
            case Password::RESET_LINK_SENT:
                Log::info('Password reset link sent (or simulated) successfully.', $logContext());

                return response()->success(
                    'If your email address exists in our system, a password reset link has been sent to it.'
                );

            case Password::INVALID_USER:
                Log::warning('Password reset attempted for a non-existent user.', $logContext());

                return response()->error(
                    'Could not send password reset link. Please try again.',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );

            case Password::RESET_THROTTLED:
                Log::warning('Password reset request throttled.', $logContext());

                return response()->error(
                    'Too many password reset attempts. Please try again later.',
                    Response::HTTP_TOO_MANY_REQUESTS
                );

            default:
                Log::error('An unexpected password reset status occurred.', array_merge($logContext(), [
                    'status' => $status,
                ]));

                return response()->error(
                    'Could not send password reset link. Please try again.',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
        }
    }

    /**
     * Reset the given user's password.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $email = $request->input('email');

        // Define reusable log context
        $logContext = fn () => [
            'email'      => $email ?? null,
        ];

        Log::info('Password reset attempt.', $logContext());

        // Perform password reset action
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Use forceFill to update the 'password' attribute even if it's not in $fillable
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        switch ($status) {
            case Password::PASSWORD_RESET:
                Log::info('Password reset successful.', $logContext());

                return response()->success('Your password has been reset successfully.');

            case Password::INVALID_TOKEN:
                Log::warning('Password reset failed due to invalid or expired token.', $logContext());

                return response()->error(
                    'The password reset token is invalid or has expired.',
                    Response::HTTP_FORBIDDEN
                );

            case Password::INVALID_USER:
                Log::warning('Password reset failed: User not found or token mismatch.', $logContext());

                return response()->error(
                    'The password reset token is invalid or has expired.',
                    Response::HTTP_FORBIDDEN
                );

            case Password::RESET_THROTTLED:
                Log::warning('Password reset attempt throttled.', $logContext());

                return response()->error(
                    'Too many password reset attempts. Please try again later.',
                    Response::HTTP_TOO_MANY_REQUESTS
                );

            default:
                Log::error('An unexpected password reset status occurred.', array_merge($logContext(), [
                    'status' => $status,
                ]));

                return response()->error(
                    'Could not reset password. Please try again.',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
        }
    }
}
