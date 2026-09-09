<?php
namespace App\Http\Controllers;

use App\Domain\AI\Conversation\AskPipeline;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function __construct(private readonly AskPipeline $pipeline)
    {
    }

    public function handle(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer|min:1',
            'payload' => 'nullable|array',
            'payload.case_id' => 'nullable|integer|min:1',
            'payload.student_id' => 'nullable|integer|min:1',
            'payload.recommendation_id' => 'nullable|integer|min:1',
            'payload.workflow_approval_id' => 'nullable|integer|min:1',
        ]);

        $scope = $this->scope($request);
        $result = $this->pipeline->ask(
            $validated['message'],
            $scope,
            $validated['conversation_id'] ?? null,
            ['payload' => $validated['payload'] ?? [], 'route' => '/chatbot']
        );

        // Keep `message` for older panel integrations while exposing the complete
        // lifecycle payload to the current Chatbot Panel.
        return response()->json([
            'success' => true,
            'message' => $result['answer']['headline'],
            'data' => $result,
        ]);
    }

    /**
     * The panel is authenticated by the ERP web session rather than an MCP JWT. Scope
     * is still server-derived, so a browser cannot choose another institute or user.
     */
    private function scope(Request $request): McpRequestContext
    {
        $userId = (int) $request->session()->get('user_id', 0);
        $instituteId = (int) $request->session()->get('sub_institute_id', 0);

        abort_unless($userId > 0 && $instituteId > 0, 403, 'Sign in with an institute selected to use Conversational AI.');

        $isAdmin = (int) $request->session()->get('is_admin', 0) >= 1;

        return new McpRequestContext(
            userId: $userId,
            role: $isAdmin ? 'admin' : 'staff',
            selectedInstituteId: $instituteId,
            allowedInstituteIds: [$instituteId],
            userProfileId: $request->session()->get('user_profile_id') !== null ? (int) $request->session()->get('user_profile_id') : null,
            clientId: $request->session()->get('client_id') !== null ? (int) $request->session()->get('client_id') : null,
            academicYear: $request->session()->get('syear') !== null ? (int) $request->session()->get('syear') : null,
            termId: $request->session()->get('term_id') !== null ? (int) $request->session()->get('term_id') : null,
            isAdmin: $isAdmin,
            isStudent: false,
        );
    }
}
