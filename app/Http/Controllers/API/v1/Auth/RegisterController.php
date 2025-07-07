<?php
namespace App\Http\Controllers\API\v1\Auth;

use Throwable;
use App\Models\User;
use App\Enums\HttpStatusCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\Auth\RegistrationRequest;

class RegisterController extends Controller
{
    /**
     * User registration controller
     *
     * @param  RegistrationRequest $request
     * @return JsonResponse
     */
    public function register(RegistrationRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = User::create2([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
            ]);

            $user->sendEmailVerificationNotification();

            DB::commit();
        } catch(Throwable $th) {
            DB::rollBack();
            throw $th;

            return response()->error(HttpStatusCode::BadRequest->reasonPhrase());
        }

        return response()->success('User registered successfully. Please verify your email');
    }
}
