<?php
namespace App\Http\Controllers\API\v1\Auth;

use Illuminate\Http\Request;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;

class ForgotPasswordController extends Controller
{
    public function sendPasswordResetLinkEmail(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validation->fails()) {
            return response()->error('Validation failed!', HttpStatusCode::UnprocessableEntity->value, $validation->errors());
        }

        $user = Password::getUser($request->only('email'));
        if (!$user) {
            return response()->error('User not found', HttpStatusCode::NotFound->value);
        }

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT ? response()->success(__($status)) : response()->error(__($status));
    }
}
