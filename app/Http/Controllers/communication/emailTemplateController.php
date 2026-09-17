<?php

namespace App\Http\Controllers\communication;

use App\Http\Controllers\Controller;
use App\Http\Controllers\easy_com\send_email_parents\send_email_parents_controller;
use App\Models\AuditLog;
use App\Models\communication\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;

/**
 * Frontend management of transactional email templates.
 *
 * Replaces editing resources/views/admission/registrationHills/*.blade.php by
 * hand: the layout is stored in email_templates and merged with << placeholder >>
 * values at send time by EmailTemplateService.
 */
class emailTemplateController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $this->subInstituteId($request);

        $templates = EmailTemplate::where('sub_institute_id', $sub_institute_id)
            ->when($request->filled('event_key'), function ($q) use ($request) {
                $q->where('event_key', $request->input('event_key'));
            })
            ->orderBy('event_key')
            ->orderBy('id', 'DESC')
            ->get();

        $events = EmailTemplateService::events();

        $res['status_code'] = 1;
        $res['message'] = 'SUCCESS';
        $res['data'] = $templates->map(function ($template) use ($events) {
            $row = $template->toArray();
            $row['event_label'] = $events[$template->event_key]['label'] ?? $template->event_key;

            return $row;
        })->toArray();

        // The layouts still served from blade files. They are listed alongside
        // the saved ones so nothing that can go out by email is invisible here,
        // and each can be imported into an editable template in one click.
        $res['legacy'] = collect(EmailTemplateService::legacyCatalog())
            ->when($request->filled('event_key'), function ($rows) use ($request) {
                return $rows->where('event_key', $request->input('event_key'));
            })
            ->map(function ($row) use ($templates) {
                $row['overridden'] = $templates
                    ->where('event_key', $row['event_key'])
                    ->where('status', 1)
                    ->isNotEmpty();

                return $row;
            })
            ->values()
            ->toArray();

        $res['events'] = $events;

        return is_mobile($type, 'communication/email_template/show', $res, 'view');
    }

    public function create(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = 'SUCCESS';
        $res['events'] = EmailTemplateService::events();
        $res['standards'] = $this->standards($request);

        // Set when arriving from "Import & Edit" on a blade-backed row: the form
        // preselects the event and pulls the existing layout into the editor.
        $res['prefill'] = [
            'event_key'    => $request->input('event_key', ''),
            'standard_ids' => $request->input('standard_ids', ''),
            'status_code'  => $request->input('status_code', ''),
            'name'         => $request->input('name', ''),
            'import'       => (bool) $request->input('import'),
        ];

        return is_mobile($type, 'communication/email_template/add', $res, 'view');
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $this->subInstituteId($request);
        $user_id = $request->session()->get('user_id');

        $validator = Validator::make($request->all(), [
            'event_key'    => 'required',
            'name'         => 'required',
            'subject'      => 'required',
            'html_content' => 'required',
        ]);

        if ($validator->fails()) {
            $request->flash();

            $res['status_code'] = 0;
            $res['message'] = $validator->messages()->first();

            return is_mobile($type, 'email_template.create', $res, 'redirect');
        }

        if (!EmailTemplateService::event($request->get('event_key'))) {
            $res['status_code'] = 0;
            $res['message'] = 'Unknown email event.';

            return is_mobile($type, 'email_template.create', $res, 'redirect');
        }

        $template = EmailTemplate::create([
            'sub_institute_id' => $sub_institute_id,
            'module'           => EmailTemplateService::event($request->get('event_key'))['module'] ?? 'admission',
            'event_key'        => $request->get('event_key'),
            'name'             => $request->get('name'),
            'subject'          => $request->get('subject'),
            'html_content'     => $request->get('html_content'),
            'standard_ids'     => $this->normalizeStandardIds($request->get('standard_ids')),
            'status_code'      => $request->get('status_code') ?: null,
            'remarks'          => $request->get('remarks'),
            'status'           => $request->get('status', 1),
            'created_by'       => $user_id,
            'updated_by'       => $user_id,
        ]);

        AuditLog::record([
            'module'      => 'communication',
            'action'      => 'email_template_store',
            'entity_type' => 'email_templates',
            'entity_id'   => $template->id,
            'new_values'  => $template->toArray(),
        ]);

        $res['status_code'] = 1;
        $res['message'] = 'Email Template Added Successfully';
        $res['data'] = $template->toArray();

        return is_mobile($type, 'email_template.index', $res, 'redirect');
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $this->subInstituteId($request);

        $template = EmailTemplate::where('sub_institute_id', $sub_institute_id)->find($id);

        if (!$template) {
            $res['status_code'] = 0;
            $res['message'] = 'Email Template Not Found';

            return is_mobile($type, 'email_template.index', $res, 'redirect');
        }

        $res['status_code'] = 1;
        $res['message'] = 'SUCCESS';
        $res['data'] = $template->toArray();
        $res['events'] = EmailTemplateService::events();
        $res['standards'] = $this->standards($request);

        return is_mobile($type, 'communication/email_template/edit', $res, 'view');
    }

    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $this->subInstituteId($request);
        $user_id = $request->session()->get('user_id');

        $template = EmailTemplate::where('sub_institute_id', $sub_institute_id)->find($id);

        if (!$template) {
            $res['status_code'] = 0;
            $res['message'] = 'Email Template Not Found';

            return is_mobile($type, 'email_template.index', $res, 'redirect');
        }

        $validator = Validator::make($request->all(), [
            'name'         => 'required',
            'subject'      => 'required',
            'html_content' => 'required',
        ]);

        if ($validator->fails()) {
            $request->flash();

            $res['status_code'] = 0;
            $res['message'] = $validator->messages()->first();

            if (in_array($type, ['API', 'JSON'], true)) {
                return is_mobile($type, 'email_template.index', $res, 'redirect');
            }

            // Back to the same template, not the list, so the edit is not lost.
            return redirect()->route('email_template.edit', $id)->with(['data' => $res]);
        }

        $old = $template->toArray();

        $template->update([
            'name'         => $request->get('name'),
            'subject'      => $request->get('subject'),
            'html_content' => $request->get('html_content'),
            'standard_ids' => $this->normalizeStandardIds($request->get('standard_ids')),
            'status_code'  => $request->get('status_code') ?: null,
            'remarks'      => $request->get('remarks'),
            'status'       => $request->get('status', $template->status),
            'updated_by'   => $user_id,
        ]);

        AuditLog::record([
            'module'      => 'communication',
            'action'      => 'email_template_update',
            'entity_type' => 'email_templates',
            'entity_id'   => $template->id,
            'old_values'  => $old,
            'new_values'  => $template->toArray(),
        ]);

        $res['status_code'] = 1;
        $res['message'] = 'Email Template Updated Successfully';
        $res['data'] = $template->toArray();

        return is_mobile($type, 'email_template.index', $res, 'redirect');
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $this->subInstituteId($request);

        $template = EmailTemplate::where('sub_institute_id', $sub_institute_id)->find($id);

        if (!$template) {
            $res['status_code'] = 0;
            $res['message'] = 'Email Template Not Found';

            return is_mobile($type, 'email_template.index', $res, 'redirect');
        }

        $old = $template->toArray();
        $template->delete();

        AuditLog::record([
            'module'      => 'communication',
            'action'      => 'email_template_destroy',
            'entity_type' => 'email_templates',
            'entity_id'   => $id,
            'old_values'  => $old,
        ]);

        $res['status_code'] = 1;
        $res['message'] = 'Email Template Deleted Successfully';

        return is_mobile($type, 'email_template.index', $res, 'redirect');
    }

    /**
     * Seed the editor with the layout that is currently hardcoded in the blade
     * file, converted to << placeholder >> tokens.
     */
    public function importLegacy(Request $request)
    {
        $eventKey = $request->input('event_key');
        $html = EmailTemplateService::importLegacy(
            $eventKey,
            $request->input('standard_id'),
            $request->input('status_code')
        );

        if ($html === null) {
            return response()->json([
                'status'  => 0,
                'message' => 'No existing layout found for this event.',
            ]);
        }

        $event = EmailTemplateService::event($eventKey);

        return response()->json([
            'status'       => 1,
            'message'      => 'SUCCESS',
            'subject'      => $event['default_subject'] ?? '',
            'html_content' => $html,
        ]);
    }

    /**
     * Show the layout a blade file currently produces, with sample values.
     */
    public function previewLegacy(Request $request)
    {
        $eventKey = $request->input('event_key');
        $standardIds = $request->input('standard_ids');
        $standardId = $standardIds ? (int) explode(',', $standardIds)[0] : null;

        $vars = $this->sampleVars(EmailTemplateService::event($eventKey));
        $vars['student_data'] = [];

        $html = EmailTemplateService::renderLegacy($eventKey, $vars, $standardId);

        if ($html === null) {
            return response('No layout found for this event.', 404);
        }

        return response($html);
    }

    /**
     * Render a template with sample values so the admin can see the result
     * before saving.
     */
    public function preview(Request $request)
    {
        $eventKey = $request->input('event_key');
        $event = EmailTemplateService::event($eventKey);
        $vars = $this->sampleVars($event);

        $html = EmailTemplateService::replace($request->input('html_content'), $vars);
        $subject = EmailTemplateService::replace($request->input('subject'), $vars);

        if ($request->input('type') === 'API' || $request->input('type') === 'JSON') {
            return response()->json([
                'status'  => 1,
                'subject' => $subject,
                'html'    => $html,
            ]);
        }

        return response($html);
    }

    /**
     * Send the template being edited to one address using the institute SMTP.
     */
    public function sendTest(Request $request)
    {
        $type = $request->input('type', 'JSON');

        $validator = Validator::make($request->all(), [
            'to'           => 'required|email',
            'subject'      => 'required',
            'html_content' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->messages()->first()]);
        }

        $event = EmailTemplateService::event($request->input('event_key'));
        $vars = $this->sampleVars($event);

        $mailRequest = new Request([
            'type'             => 'JSON',
            'all_email'        => $request->input('to'),
            'subject'          => EmailTemplateService::replace($request->input('subject'), $vars),
            'example_subject'  => EmailTemplateService::replace($request->input('subject'), $vars),
            'content'          => EmailTemplateService::replace($request->input('html_content'), $vars),
            'sub_institute_id' => $this->subInstituteId($request),
            'syear'            => $request->session()->get('syear'),
            'teacher_id'       => $request->session()->get('user_id'),
        ]);
        $mailRequest->setLaravelSession($request->session());

        (new send_email_parents_controller)->sendEmail($mailRequest);

        return response()->json([
            'status'  => 1,
            'message' => 'Test email queued to ' . $request->input('to'),
        ]);
    }

    private function sampleVars(?array $event): array
    {
        $samples = [
            'aca_year'      => date('Y') . '-' . (((int) date('y')) + 1),
            'conf_date'     => date('Y-m-d'),
            'parent_date'   => date('Y-m-d'),
            'parent_time'   => '10:30',
            'conf'          => 'C',
            'pint'          => 'I',
            'paid_status'   => 'Yes',
            'enquiry_no'    => 'ENQ-0001',
            'student_name'  => 'Sample Student',
            'admission_std' => 'Std 1',
            'medium'        => 'English',
            'mobile'        => '9999999999',
            'email'         => 'parent@example.com',
        ];

        $vars = [];

        foreach (array_keys($event['placeholders'] ?? $samples) as $key) {
            $vars[$key] = $samples[$key] ?? strtoupper(str_replace('_', ' ', $key));
        }

        return $vars;
    }

    private function normalizeStandardIds($value): ?string
    {
        if (is_array($value)) {
            $ids = $value;
        } elseif (is_string($value) && $value !== '') {
            $ids = explode(',', $value);
        } else {
            return null;
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));

        return empty($ids) ? null : implode(',', $ids);
    }

    private function standards(Request $request): array
    {
        $sub_institute_id = $this->subInstituteId($request);

        return DB::table('standard')
            ->select('id', 'name', 'medium')
            ->where('sub_institute_id', $sub_institute_id)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function subInstituteId(Request $request)
    {
        return $request->session()->get('sub_institute_id') ?: $request->input('sub_institute_id');
    }
}
