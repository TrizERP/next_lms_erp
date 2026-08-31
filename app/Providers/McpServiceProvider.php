<?php

namespace App\Providers;

use App\Mcp\ToolRegistry;
use App\Mcp\Tools\AcademicsStructureTool;
use App\Mcp\Tools\AcademicsSubjectsTool;
use App\Mcp\Tools\AdmissionsConfirmTool;
use App\Mcp\Tools\AdmissionsGetEnquiryDetailsTool;
use App\Mcp\Tools\AdmissionsListEnquiriesTool;
use App\Mcp\Tools\AdmissionsValidateConfirmationTool;
use App\Mcp\Tools\AdmissionsTodayTool;
use App\Mcp\Tools\AdmissionsUpdateEnquiryTool;
use App\Mcp\Tools\AiTemplatesListTool;
use App\Mcp\Tools\AiTemplatesRenderTool;
use App\Mcp\Tools\AttendanceOverviewTool;
use App\Mcp\Tools\AttendanceStudentTool;
use App\Mcp\Tools\ExamsListTool;
use App\Mcp\Tools\ExamsResultsTool;
use App\Mcp\Tools\FeesArrearsTool;
use App\Mcp\Tools\FeesCollectionReportTool;
use App\Mcp\Tools\FeesGetPendingTool;
use App\Mcp\Tools\HomeworkListTool;
use App\Mcp\Tools\HrDepartmentsTool;
use App\Mcp\Tools\LmsActivitiesTool;
use App\Mcp\Tools\StudentSearchTool;
use App\Mcp\Tools\StudentsDirectoryTool;
use App\Mcp\Tools\StudentsHistoryTool;
use App\Mcp\Tools\TeachersDailyReportTool;
use App\Mcp\Tools\TeachersDirectoryTool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Every tool the platform exposes.
     *
     * This list is the answer to "what can the assistant actually reach?", so it is kept
     * as one readable array rather than scattered across the method below. Anything not
     * here is unreachable, however a question is worded — which is the property that
     * makes the module tool bindings in config/ai.php meaningful.
     *
     * @var array<int, class-string<\App\Mcp\McpToolInterface>>
     */
    private const TOOLS = [
        // Generation
        AiTemplatesListTool::class,
        AiTemplatesRenderTool::class,

        // People
        StudentSearchTool::class,
        StudentsDirectoryTool::class,
        StudentsHistoryTool::class,
        TeachersDirectoryTool::class,
        TeachersDailyReportTool::class,
        HrDepartmentsTool::class,

        // The shape of the school
        AcademicsStructureTool::class,
        AcademicsSubjectsTool::class,

        // Academic records
        AttendanceOverviewTool::class,
        AttendanceStudentTool::class,
        HomeworkListTool::class,
        LmsActivitiesTool::class,
        ExamsListTool::class,
        ExamsResultsTool::class,

        // Money
        FeesArrearsTool::class,
        FeesCollectionReportTool::class,
        FeesGetPendingTool::class,

        // Admissions — the only family with a write path, gated by its own confirmation.
        AdmissionsTodayTool::class,
        AdmissionsListEnquiriesTool::class,
        AdmissionsGetEnquiryDetailsTool::class,
        AdmissionsValidateConfirmationTool::class,
        AdmissionsUpdateEnquiryTool::class,
        AdmissionsConfirmTool::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(config_path('mcp.php'), 'mcp');

        foreach (self::TOOLS as $tool) {
            $this->app->singleton($tool);
        }

        $this->app->tag(self::TOOLS, 'mcp.tools');

        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry(
                $app->tagged('mcp.tools'),
                $app->make(\App\Services\Mcp\McpConfirmationService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/mcp.php'));

        RateLimiter::for('mcp', function (Request $request) {
            $limit = (int) config('mcp.rate_limit.per_minute', 60);
            $auth = $request->attributes->get('mcp_auth', []);
            $userId = $auth['user_id'] ?? null;

            return Limit::perMinute($limit)->by($userId ?: $request->ip());
        });
    }
}
