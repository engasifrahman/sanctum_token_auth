<?php
namespace App\Http\Controllers\API\v1\Auth;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class LogoutController extends Controller
{
    /**
     * Log out the authenticated user by revoking their current access token.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Retrieve the authenticated user from the request
        $user = $request->user();

        // Define reusable log context
        $logContext = fn () => [
            'user_id'    => $user?->id ?? null,
            'email'      => $user?->email ?? null,
        ];

        Log::info('User logout attempt.', $logContext());

        try {
            // Delete the current access token associated with the authenticated user
            $user->currentAccessToken()?->delete();

            Log::info('User logged out successfully.', $logContext());

            return response()->success('Logged out successfully.');
        } catch (Throwable $th) {
            Log::error('Failed to revoke current access token during logout.', array_merge($logContext(), [
                    'exception_message' => $th->getMessage(),
                    'exception_file'    => $th->getFile(),
                    'exception_line'    => $th->getLine(),
            ]));

            return response()->error(
                'Failed to log out. An internal server error occurred.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

