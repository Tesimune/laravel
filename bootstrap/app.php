<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                $response = [
                    "status" => 401,
                    "success" => false,
                    "message" => "Unauthenticated",
                    "data" => [
                        "message" => "Unauthenticated"
                    ]
                ];
                return response()->json($response, 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            $errors = [];
            $firstErrorMessage = null;

            foreach ($e->errors() as $field => $messages) {
                if ($firstErrorMessage === null) {
                    // Set the first error message
                    $firstErrorMessage = reset($messages);
                }

                // Add error message for each field
                $errors[$field] = [
                    'code' => $field,
                    'message' => $messages[0], // Pick the first error message for the field
                ];
            }

            // Include the first error message separately under "message" key
            $data = ['message' => $firstErrorMessage] + $errors;

            return response()->json([
                'status' => 422,
                'success' => false,
                'data' => $data,
            ], $e->status);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }
        });
    })->create();
