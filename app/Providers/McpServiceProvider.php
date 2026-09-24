<?php

namespace App\Providers;

use App\Mcp\ToolRegistry;
use App\Mcp\Tools\AcademicsClassTeachersTool;
use App\Mcp\Tools\AcademicsStructureTool;
use App\Mcp\Tools\AcademicsSubjectsTool;
use App\Mcp\Tools\AdmissionsConfirmTool;
use App\Mcp\Tools\AdmissionsGetEnquiryDetailsTool;
use App\Mcp\Tools\AdmissionsListEnquiriesTool;
use App\Mcp\Tools\AdmissionsTodayTool;
use App\Mcp\Tools\AdmissionsUpdateEnquiryTool;
use App\Mcp\Tools\AdmissionsValidateConfirmationTool;
use App\Mcp\Tools\AiTemplatesGenerateTool;
use App\Mcp\Tools\AiTemplatesListTool;
use App\Mcp\Tools\AiTemplatesRenderTool;
use App\Mcp\Tools\AssignmentStatusTool;
use App\Mcp\Tools\AttendanceOverviewTool;
use App\Mcp\Tools\AttendanceStudentTool;
use App\Mcp\Tools\CertificateIssuedTool;
use App\Mcp\Tools\CertificateTemplatesTool;
use App\Mcp\Tools\CircularsListTool;
use App\Mcp\Tools\CircularsTypesTool;
use App\Mcp\Tools\CommunicationChannelsTool;
use App\Mcp\Tools\CommunicationMessagesTool;
use App\Mcp\Tools\ComplaintsListTool;
use App\Mcp\Tools\ComplaintsSummaryTool;
use App\Mcp\Tools\ConsentRecordsTool;
use App\Mcp\Tools\ConsentSummaryTool;
use App\Mcp\Tools\CurriculumPlanningOutcomesTool;
use App\Mcp\Tools\CurriculumPlanningStatusTool;
use App\Mcp\Tools\DocumentTemplateVersionsTool;
use App\Mcp\Tools\DocumentTemplatesListTool;
use App\Mcp\Tools\EngagementStudentSummaryTool;
use App\Mcp\Tools\EngagementStudentsNeedingAttentionTool;
use App\Mcp\Tools\ExamsListTool;
use App\Mcp\Tools\ExamsResultsTool;
use App\Mcp\Tools\FeesArrearsTool;
use App\Mcp\Tools\FeesCollectionReportTool;
use App\Mcp\Tools\FeesGetPendingTool;
use App\Mcp\Tools\FrontDeskVisitsTool;
use App\Mcp\Tools\HomeworkListTool;
use App\Mcp\Tools\HostelAllocationsTool;
use App\Mcp\Tools\HostelAvailableRoomsTool;
use App\Mcp\Tools\HostelOccupancyTool;
use App\Mcp\Tools\HrDepartmentsTool;
use App\Mcp\Tools\InteractionsListTool;
use App\Mcp\Tools\InteractionsSummaryTool;
use App\Mcp\Tools\InventoryItemsTool;
use App\Mcp\Tools\InventoryPurchaseOrdersTool;
use App\Mcp\Tools\InventoryRequisitionsTool;
use App\Mcp\Tools\InwardRegisterTool;
use App\Mcp\Tools\InwardUnfiledTool;
use App\Mcp\Tools\LibraryCatalogueTool;
use App\Mcp\Tools\LibraryCirculationTool;
use App\Mcp\Tools\LmsActivitiesTool;
use App\Mcp\Tools\LmsCoursesTool;
use App\Mcp\Tools\MobileAppsHomescreenTool;
use App\Mcp\Tools\MobileAppsSectionsTool;
use App\Mcp\Tools\NewPalCoherenceGapsTool;
use App\Mcp\Tools\NewPalContentModelStatusTool;
use App\Mcp\Tools\NewPalGamificationSummaryTool;
use App\Mcp\Tools\OnlineExamSummaryTool;
use App\Mcp\Tools\ParentCommunicationMessagesTool;
use App\Mcp\Tools\ParentCommunicationSummaryTool;
use App\Mcp\Tools\PettyCashSummaryTool;
use App\Mcp\Tools\PettyCashTransactionsTool;
use App\Mcp\Tools\PtmBookingsTool;
use App\Mcp\Tools\PtmMeetingsTool;
use App\Mcp\Tools\SqaaCriteriaTool;
use App\Mcp\Tools\SqaaEvidenceTool;
use App\Mcp\Tools\StudentIcardCardDetailsTool;
use App\Mcp\Tools\StudentIcardRosterTool;
use App\Mcp\Tools\StudentMedicalGrowthTool;
use App\Mcp\Tools\StudentMedicalHealthRecordsTool;
use App\Mcp\Tools\StudentMedicalVaccinationsTool;
use App\Mcp\Tools\StudentMedicalVisitsTool;
use App\Mcp\Tools\StudentRequestDetailsTool;
use App\Mcp\Tools\StudentRequestTypesTool;
use App\Mcp\Tools\StudentRequestsListTool;
use App\Mcp\Tools\StudentSearchTool;
use App\Mcp\Tools\StudentsDirectoryTool;
use App\Mcp\Tools\StudentsHistoryTool;
use App\Mcp\Tools\TasksListTool;
use App\Mcp\Tools\TasksOverdueTool;
use App\Mcp\Tools\TasksProjectsTool;
use App\Mcp\Tools\TeachersDailyReportTool;
use App\Mcp\Tools\TeachersDirectoryTool;
use App\Mcp\Tools\TimetableConflictsTool;
use App\Mcp\Tools\TimetableScheduleTool;
use App\Mcp\Tools\TransportAssignmentsTool;
use App\Mcp\Tools\TransportRoutesTool;
use App\Mcp\Tools\TransportVehiclesTool;
use App\Mcp\Tools\UserAccountsDirectoryTool;
use App\Mcp\Tools\UserIcardCardDetailsTool;
use App\Mcp\Tools\UserIcardRosterTool;
use App\Mcp\Tools\UtilityCustomModulesTool;
use App\Mcp\Tools\UtilityRolloverScopeTool;
use App\Mcp\Tools\VisitorVisitsTool;
use App\Mcp\Tools\VisitorWithoutExitTool;
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
     * @var array<int, class-string<\App\Mcp\AbstractMcpTool>>
     */
    private const TOOLS = [
        // Generation
        AiTemplatesGenerateTool::class,
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
        AcademicsClassTeachersTool::class,

        // Academic records
        AttendanceOverviewTool::class,
        AttendanceStudentTool::class,
        HomeworkListTool::class,
        LmsActivitiesTool::class,
        LmsCoursesTool::class,
        ExamsListTool::class,
        ExamsResultsTool::class,

        // The five LMS + PAL modules. `LmsCoursesTool`/`LmsActivitiesTool` above already
        // serve Teach/Learn — no new tool class for that module. The nine below are new.
        CurriculumPlanningStatusTool::class,
        CurriculumPlanningOutcomesTool::class,
        EngagementStudentSummaryTool::class,
        EngagementStudentsNeedingAttentionTool::class,
        InteractionsListTool::class,
        InteractionsSummaryTool::class,
        NewPalGamificationSummaryTool::class,
        NewPalContentModelStatusTool::class,
        NewPalCoherenceGapsTool::class,

        // Exam & Assessment — online exams, homework, assignments, worksheets and
        // projects. `HomeworkListTool` above already covers Homework/Homework
        // Submission; these two are new.
        OnlineExamSummaryTool::class,
        AssignmentStatusTool::class,

        // Money
        FeesArrearsTool::class,
        FeesCollectionReportTool::class,
        FeesGetPendingTool::class,

        // Boarding, meetings, requests and notices.
        //
        // Every one of these is read-only, which is not decoration: they are reachable
        // from a module's AI Stack, from a report layout and from the conversational
        // fallback without anybody naming a tool, and `ModuleReadTools` only offers a
        // tool its own `read_only` annotation permits. None of these four families has a
        // write tool at all, so no allow-list, plan or layout can reach one.
        PtmMeetingsTool::class,
        PtmBookingsTool::class,
        HostelOccupancyTool::class,
        HostelAllocationsTool::class,
        HostelAvailableRoomsTool::class,
        StudentRequestsListTool::class,
        StudentRequestDetailsTool::class,
        StudentRequestTypesTool::class,
        CircularsListTool::class,
        CircularsTypesTool::class,

        // The apps, the cards, the certificates, the schedule and the sending.
        //
        // Read-only like the family above, and for the same reason: they are reachable
        // from a module's AI Stack, from a report layout and from the conversational
        // fallback without anybody naming a tool.
        MobileAppsHomescreenTool::class,
        MobileAppsSectionsTool::class,
        StudentIcardRosterTool::class,
        StudentIcardCardDetailsTool::class,
        CertificateIssuedTool::class,
        CertificateTemplatesTool::class,
        CommunicationMessagesTool::class,
        CommunicationChannelsTool::class,
        TimetableScheduleTool::class,
        TimetableConflictsTool::class,

        // The register, the staff card, the cash book, the consents, the gate and the buses.
        //
        // Read-only, like every family above them. Three of them exist mainly to answer a
        // question honestly that the table cannot answer at all — `inward.unfiled` because
        // the inward register records no status, `petty_cash.summary` because the cash book
        // records no approval and no balance, and `visitor.without_exit` because a missing
        // exit time is a missing exit time and not a person in the building. Each says so
        // in its own payload rather than leaving the caller to guess.
        InwardRegisterTool::class,
        InwardUnfiledTool::class,
        UserIcardRosterTool::class,
        UserIcardCardDetailsTool::class,
        PettyCashTransactionsTool::class,
        PettyCashSummaryTool::class,
        ConsentRecordsTool::class,
        ConsentSummaryTool::class,
        VisitorVisitsTool::class,
        VisitorWithoutExitTool::class,
        TransportRoutesTool::class,
        TransportVehiclesTool::class,
        TransportAssignmentsTool::class,

        // The store, the desk, the task list, the complaints book, the utilities and the
        // template library.
        //
        // Read-only, like every family above them, and three of them exist mainly to be
        // honest about a column that is not what its name says. `inventory.items` reports
        // a stock figure that is never decreased when stock is issued;
        // `complaints.list` reads a column called COMPLAINT_SOLUTION that actually holds
        // the status; and `utility.*` serves a module that is bulk data operations rather
        // than electricity and water, which this estate does not record at all. Each says
        // so in its own payload rather than leaving the caller to find out.
        InventoryItemsTool::class,
        InventoryRequisitionsTool::class,
        InventoryPurchaseOrdersTool::class,
        FrontDeskVisitsTool::class,
        TasksListTool::class,
        TasksOverdueTool::class,
        TasksProjectsTool::class,
        ComplaintsListTool::class,
        ComplaintsSummaryTool::class,
        UtilityCustomModulesTool::class,
        UtilityRolloverScopeTool::class,
        DocumentTemplatesListTool::class,
        DocumentTemplateVersionsTool::class,
        // Parent messages in, quality assurance evidence, ERP accounts and the library.
        //
        // Read-only like every family above. `parent_communication.*` is the INBOUND
        // direction and is a different module from `communication.*`, which is what the
        // school sends; `user_accounts.directory` reads the account fields of `tbluser`
        // while `user_icard.*` reads the card fields of the same table, and neither
        // reaches the payroll columns beside them.
        ParentCommunicationMessagesTool::class,
        ParentCommunicationSummaryTool::class,
        SqaaCriteriaTool::class,
        SqaaEvidenceTool::class,
        UserAccountsDirectoryTool::class,
        LibraryCatalogueTool::class,
        LibraryCirculationTool::class,

        // Student medical — the most sensitive data the platform holds.
        //
        // Every one of these is read-only and none of them is bound to any module but
        // `student_medical`, so no other module's AI Stack, report layout or agent
        // allow-list can reach a child's clinical record. `StudentMedicalService` adds a
        // second rule on top of the permission check: clinical free text is returned only
        // for a read that names one student.
        StudentMedicalVisitsTool::class,
        StudentMedicalVaccinationsTool::class,
        StudentMedicalGrowthTool::class,
        StudentMedicalHealthRecordsTool::class,

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
