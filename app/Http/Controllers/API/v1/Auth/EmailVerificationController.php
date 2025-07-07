<?php
namespace App\Http\Controllers\API\v1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{
    public function verify($id, $hash)
    {
        $user = User::findOrFail($id);
        if ($user->hasVerifiedEmail()) {
            return response()->success('Email already verified!');
        }

        $expectedHash = sha1($user->email);

        if (!hash_equals($expectedHash, $hash)) {
            return response()->error('Invalid verification link!', HttpStatusCode::NotFound->value);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->success('Email verified successfully!');
    }

    public function resend(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validation->fails()) {
            return response()->error('Validation failed!', HttpStatusCode::UnprocessableEntity->value, $validation->errors());
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->error('User not found!', HttpStatusCode::NotFound->value);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->error('Email already verified!');
        }

        $user->sendEmailVerificationNotification();

        return response()->success('Verification link sent!');
    }
}
