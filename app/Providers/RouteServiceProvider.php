<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
//        $this->configureRateLimiting();
//
//        $this->routes(function () {
//            Route::prefix('api')
//                ->middleware('api')
//                ->namespace($this->namespace)
//                ->group(base_path('routes/api.php'));
//
//            Route::middleware('web')
//                ->namespace($this->namespace)
//                ->group(base_path('routes/web.php'));
//        });
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapUserRoutes();

        $this->mapResultRoutes();

        $this->mapSettingsRoutes();

        $this->mapStudentRoutes();

        $this->mapHostelRoutes();

        $this->mapVisitorRoutes();

        $this->mapInwardOutwardRoutes();

        $this->mapInventoryRoutes();

        $this->mapConsentRoutes();

        $this->mapPTMRoutes();

        $this->mapFeesRoutes();

        $this->mapCalRoutes();

        $this->mapTrancepotRoutes();

        $this->mapFrontdeskRoutes();

        $this->mapImplementationRoutes();

        $this->mapAdmissionRoutes();

        $this->maplmsRoutes();

        $this->mapTeacherApiRoutes();

        $this->mapAdminApiRoutes();

        $this->mapHrmsRoutes();

        $this->mapSkillRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }

    protected function mapUserRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/user.php'));
    }

    protected function mapSettingsRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/settings.php'));
    }

    protected function mapResultRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/result.php'));
    }

    protected function mapStudentRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/student.php'));
    }

    protected function mapHostelRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/hostel_management.php'));
    }

    protected function mapVisitorRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/visitor_management.php'));
    }

    protected function mapInwardOutwardRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/inward_outward.php'));
    }

    protected function mapInventoryRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/inventory.php'));
    }

    protected function mapConsentRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/consent.php'));
    }

    protected function mapPTMRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/ptm.php'));
    }

    protected function mapCalRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/cal.php'));
    }

    protected function mapTrancepotRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/tranceport.php'));
    }

    protected function mapFeesRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/fees.php'));
    }

    protected function mapFrontdeskRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/frontdesk.php'));
    }

    protected function mapImplementationRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/implementation.php'));
    }

    protected function mapAdmissionRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/admission.php'));
    }

    protected function maplmsRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/lms.php'));
    }

    protected function mapTeacherApiRoutes()
    {
        Route::namespace($this->namespace)
            ->group(base_path('routes/teacherapi.php'));
    }

    protected function mapAdminApiRoutes()
    {
        Route::namespace($this->namespace)
            ->group(base_path('routes/adminapi.php'));
    }

    protected function mapHrmsRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/hrms.php'));
    }

    protected function mapSkillRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('web')
            ->group(base_path('routes/skill.php'));
    }
    
    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1000)->by($request->user()?->id ?: $request->ip());
        });
    }
}
