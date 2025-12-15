<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'staff' => \App\Http\Middleware\EnsureUserIsStaff::class,
            'customer' => \App\Http\Middleware\EnsureUserIsCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle 404 errors for storage files
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if (str_starts_with($request->path(), 'storage/')) {
                $filePath = str_replace('storage/', '', $request->path());
                return app(\App\Http\Controllers\StorageController::class)->serve($filePath);
            }
            return null;
        });
    })->create();
