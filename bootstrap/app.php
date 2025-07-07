<?php

use Illuminate\Http\Request;
use App\Enums\HttpStatusCode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $th, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // Default status code fallback
                $statusCode = $th->getCode();
                $statusCode = is_int($statusCode) && $statusCode >= HttpStatusCode::Continue->value && $statusCode <= HttpStatusCode::NetworkAuthenticationRequired->value
                    ? $statusCode
                    : HttpStatusCode::InternalServerError->value;

                // Determine message and debug info
                $isProd = app()->environment('production');
                $message = $isProd ? 'Something went wrong. Please try again later.' : ($th->getMessage() ?: 'An unexpected error occurred');

                // Only attach debug info if not in production
                $debug = null;
                if (!$isProd) {
                    $debug = [
                        'file'  => $th->getFile(),
                        'line'  => $th->getLine(),
                        'trace' => collect($th->getTrace())->take(5)->toArray(), // optional: limit trace depth
                    ];

                    // Check if the exception has an errors() method (e.g. ValidationException)
                    if (method_exists($th, 'errors')) {
                        $debug['errors'] = $th->errors();
                    }
                }

                return response()->error($message, $statusCode, $debug);
            }

            return null; // fallback to Laravel default
        });
    })->create();
