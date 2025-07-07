<?php
namespace App\Http\Controllers\API\v1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\API\v1\Auth\LoginRequest;

class LoginController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->error('Invalid credentials', HttpStatusCode::Unauthorized->value);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->error('Please verify your email first.', HttpStatusCode::Forbidden->value);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $data = [
            'token' => $token,
            'user' => $user,
        ];

        return response()->success('Login Successsful!', $data);
    }
}
