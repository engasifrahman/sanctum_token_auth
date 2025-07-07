<?php

namespace App\Providers;

use App\Enums\HttpStatusCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define Response macros for seamless API responses
        Response::macro('success', function (string $message = 'Operation successful!', int $statusCode = HttpStatusCode::OK->value, mixed $data = null): JsonResponse  {
            $response = [
                'status' => true,
                'message' => $message,
            ];
            $meta = null;
            $links = null;

            if ($data instanceof JsonResource) {
                $resourceData = $data->toArray(request());
                if (JsonResource::$wrap && array_key_exists(JsonResource::$wrap, $resourceData) && (array_key_exists('meta', $resourceData) || array_key_exists('links', $resourceData))) {
                    $data = $resourceData[JsonResource::$wrap];
                    $meta = $resourceData['meta'] ?? null;
                    $links = $resourceData['links'] ?? null;

                } else {
                    $data = JsonResource::$wrap && array_key_exists(JsonResource::$wrap, $resourceData) ? $resourceData[JsonResource::$wrap] : $resourceData;
                }
            }

            if (!is_null($data)) {
                $response['data'] = $data;
            }

            if (!is_null($meta)) {
                $response['meta'] = $meta;
            }

            if (!is_null($links)) {
                $response['links'] = $links;
            }

            return Response::json($response, $statusCode);
        });

        Response::macro('error', function (string $message = 'An error occurred!', $statusCode = HttpStatusCode::BadRequest->value, mixed $errors = null): JsonResponse  {
            $response = [
                'status' => false,
                'message' => $message,
            ];

            if (!is_null($errors)) {
                $response['errors'] = $errors;
            }

            return Response::json($response, $statusCode);
        });
    }
}
