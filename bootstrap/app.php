<?php

use App\Http\Middleware\VerifyWhatsappBridgeSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Stateless machine-to-machine surface for the VYA WhatsApp
        // bridge (Fase 1) — Laravel's default "api" group has no
        // session/CSRF, exactly what a signed, non-browser caller needs.
        // Nothing else in the app uses it yet.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'whatsapp.bridge.signature' => VerifyWhatsappBridgeSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
