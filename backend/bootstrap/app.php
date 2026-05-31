<?php

use App\Http\Middleware\AnomalyDetection;
use App\Http\Middleware\CheckPlanFeature;
use App\Http\Middleware\CheckUserIsActive;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\InitializeTenancyBySession;
use App\Http\Middleware\IpWhitelist;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionSecurity;
use App\Http\Middleware\SetLocale;
use App\Providers\TenancyServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        TenancyServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            InitializeTenancyBySession::class,
            SetLocale::class,
            SecurityHeaders::class,
            SessionSecurity::class,
            AnomalyDetection::class,
            CheckUserIsActive::class,
        ]);

        // API routes share session-based auth — prepend cookie+session+tenancy
        // so they run BEFORE the default throttle:api and SubstituteBindings.
        $middleware->api(
            prepend: [
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                InitializeTenancyBySession::class,
                CheckUserIsActive::class,
            ],
        );

        $middleware->alias([
            'tenancy' => InitializeTenancyBySession::class,
            '2fa' => EnforceTwoFactor::class,
            'ip.whitelist' => IpWhitelist::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'planFeature' => CheckPlanFeature::class,
        ]);

        $middleware->priority([
            StartSession::class,
            ShareErrorsFromSession::class,
            InitializeTenancyBySession::class,
            Authenticate::class,
            EnforceTwoFactor::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // #48 ردود JSON موحدة — بدون Stack Trace في الإنتاج
        $exceptions->render(function (AuthenticationException $_, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'غير مصرح.'], 401);
            }
        });
        $exceptions->render(function (AuthorizationException $_, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية لهذه العملية.'], 403);
            }
        });
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'بيانات غير صالحة.', 'errors' => $e->errors()], 422);
            }
        });
        $exceptions->render(function (ModelNotFoundException $_, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'العنصر غير موجود.'], 404);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $_, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'طلبات كثيرة جداً. حاول بعد دقيقة.'], 429);
            }
        });

        // Catch-all: any unhandled exception on an AJAX/API request returns JSON
        // instead of an HTML error page. Never expose the real message in production.
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = app()->hasDebugModeEnabled()
                    ? $e->getMessage()
                    : (__('pos.server_error', [], 'en') ?: 'An unexpected error occurred.');

                return response()->json(['success' => false, 'message' => $message], $status);
            }
        });
    })->create();
