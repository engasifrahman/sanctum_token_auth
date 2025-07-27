<?php
namespace App\Http\Controllers\API\v1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{
    /**
     * Mark the user's email address as verified.
     *
     * @param  EmailVerificationRequest  $request
     * @return JsonResponse
     */
    public function verifyEmailLink(EmailVerificationRequest $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        $logContext = fn () => [
            'user_id'    => $user->id ?? null,
            'email'      => $user->email ?? null,
        ];

        // Log the verification attempt
        Log::info('Email verification attempt.', $logContext());

        // Check if the email is already verified
        if ($user->hasVerifiedEmail()) {
            Log::info('Email already verified.', $logContext());

            return response()->error('Email already verified.', Response::HTTP_CONFLICT);
        }

        // Check for valid hash
        $expectedHash = sha1($user->email);
        if (!hash_equals($expectedHash, $hash)) {
            Log::warning('Invalid email verification hash provided.', array_merge($logContext(), [
                'provided_hash' => $hash,
                'expected_hash' => $expectedHash,
            ]));

            return response()->error('Invalid verification link.', Response::HTTP_FORBIDDEN);
        }

        try {
            if ($user->markEmailAsVerified()) {
                // Dispatch the Verified event, which will trigger sendEmailVerificationNotification
                event(new Verified($user));

                Log::info('Email verified successfully.', $logContext());

                return response()->success('Email verified successfully.');
            } else {
                Log::error('Email verification failed: `markEmailAsVerified()` returned false.', $logContext());

                return response()->error(
                    'Failed to verify email. Please try again.',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        } catch (Throwable $th) {
            Log::error('Unexpected error during email verification.', array_merge($logContext(), [
                'exception_message' => $th->getMessage(),
                'exception_file'    => $th->getFile(),
                'exception_line'    => $th->getLine(),
            ]));

            return response()->error(
                'An unexpected server error occurred during email verification. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Resend the email verification notification.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        // Validate the request data first
        $request->validate([
            'email' => 'required|email',
        ]);

        // Define reusable log context accepting optional $user
        $logContext = fn ($user = null) => [
            'user_id'    => $user?->id ?? null,
            'email'      => $user?->email ?? $request->input('email') ?? null,
        ];

        // Log the start of the resend attempt without user yet
        Log::info('Email verification resend attempt.', $logContext());

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::info('Resend email verification failed: User not found.', $logContext());

            return response()->error('User not found.', Response::HTTP_NOT_FOUND);
        }

        if ($user->hasVerifiedEmail()) {
            Log::info('Resend email verification skipped: Email already verified.', $logContext($user));

            return response()->error('Email already verified.', Response::HTTP_CONFLICT);
        }

        try {
            // Send the email verification notification
            $user->sendEmailVerificationNotification();

            Log::info('Email verification notification resent.', $logContext($user));

            return response()->success('Verification link sent.');
        } catch (Throwable $th) {
            Log::error('Failed to resend email verification notification.', array_merge($logContext($user), [
                'exception_message' => $th->getMessage(),
                'exception_file'    => $th->getFile(),
                'exception_line'    => $th->getLine(),
            ]));

            return response()->error(
                'Failed to send verification link. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
