<?php
namespace App\Http\Controllers\API\v1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
        } catch (Throwable $th) {
            return response()->error('Failed to log out', HttpStatusCode::InternalServerError->value);
        }

        return response()->success('Logged out successfully');
    }
}

