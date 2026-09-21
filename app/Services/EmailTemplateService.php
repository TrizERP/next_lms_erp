<?php

namespace App\Services;

use App\Models\communication\EmailTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use PDF;

/**
 * Resolves and renders frontend-managed email templates.
 *
 * Sending code asks for an event (e.g. admission_confirmed) and hands over the
 * merge values. If the institute has saved a template for that event the stored
 * HTML is used; otherwise the legacy blade declared in config/email_templates.php
 * is rendered, so nothing breaks before a template has been created.
 *
 * Stored HTML is NEVER run through Blade - placeholders are plain string
 * substitution, so an admin editing a template cannot execute PHP.
 */
class EmailTemplateService
{
    /** Attachment name used when a template does not set its own. */
    public const DEFAULT_PDF_NAME = 'Admission Confirmation.pdf';

    /**
     * Sentinel values used when importing a legacy blade into an editable
     * template: the blade renders real-looking data, which is then swapped back
     * to placeholders.
     */
    private const IMPORT_SENTINELS = [
        'parent_date' => '2099-12-31',
        'conf_date'   => '2098-11-30',
        'parent_time' => '23:59',
    ];

    public static function events(): array
    {
        return config('email_templates.events', []);
    }

    public static function event(string $eventKey): ?array
    {
        return Arr::get(self::events(), $eventKey);
    }

    /**
     * Best matching saved template for an event.
     *
     * More specific rows win: standard + status > standard > status > generic.
     */
    public static function resolve(int $subInstituteId, string $eventKey, $standardId = null, $statusCode = null): ?EmailTemplate
    {
        // Templates that exist only to be attached as the PDF letter never act
        // as the mail body, or a letter scoped to a standard would outrank the
        // covering note and go out inline with no attachment.
        $letterIds = EmailTemplate::where('sub_institute_id', $subInstituteId)
            ->whereNotNull('pdf_template_id')
            ->pluck('pdf_template_id')
            ->filter()
            ->unique()
            ->all();

        $candidates = EmailTemplate::where('sub_institute_id', $subInstituteId)
            ->where('event_key', $eventKey)
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('is_letter', 0)->orWhereNull('is_letter');
            })
            ->when(!empty($letterIds), function ($q) use ($letterIds) {
                $q->whereNotIn('id', $letterIds);
            })
            ->get();

        return self::pickBest($candidates, $standardId, $statusCode);
    }

    /**
     * Most specific match wins: standard + status > standard > status > generic.
     * On a tie the newest row wins, so a freshly edited template takes effect
     * rather than an older duplicate quietly continuing to be used.
     *
     * @param \Illuminate\Support\Collection<int,EmailTemplate> $candidates
     */
    private static function pickBest($candidates, $standardId, $statusCode): ?EmailTemplate
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = -1;

        foreach ($candidates as $template) {
            $standards = $template->standardIdList();
            $hasStandard = !empty($standards);
            $hasStatus = $template->status_code !== null && $template->status_code !== '';

            if ($hasStandard && (!$standardId || !in_array((int) $standardId, $standards, true))) {
                continue;
            }

            if ($hasStatus && trim((string) $template->status_code) !== trim((string) $statusCode)) {
                continue;
            }

            $score = ($hasStandard ? 2 : 0) + ($hasStatus ? 1 : 0);

            if ($score > $bestScore || ($score === $bestScore && $best && $template->id > $best->id)) {
                $best = $template;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Build the mail body + subject for an event.
     *
     * When the resolved template has attach_as_pdf set, 'attachment' holds the
     * path to a generated PDF of the letter and the body is only the covering
     * note. The caller is responsible for deleting the file after sending.
     *
     * @return array{subject:string,body:string,source:string,attachment:?string}|null
     *         null when neither a saved template nor a legacy blade is available.
     */
    public static function render(int $subInstituteId, string $eventKey, array $vars = [], $standardId = null, $statusCode = null): ?array
    {
        $event = self::event($eventKey);
        $template = self::resolve($subInstituteId, $eventKey, $standardId, $statusCode);

        if ($template) {
            // Logged so a wrong-template report can be traced to a row id.
            Log::info('Email body template resolved', [
                'template' => $template->id,
                'name'     => $template->name,
                'event'    => $eventKey,
                'standard' => $standardId,
                'status'   => $statusCode,
            ]);

            $attachment = empty($template->attach_as_pdf)
                ? null
                : self::buildPdfAttachment($template, $subInstituteId, $eventKey, $vars, $standardId, $statusCode);

            return [
                'subject'         => self::replace($template->subject ?: ($event['default_subject'] ?? ''), $vars),
                'body'            => self::replace($template->html_content, $vars),
                'source'          => 'database',
                'attachment'      => $attachment,
                // The name the recipient sees, independent of the temp path.
                'attachment_name' => $attachment ? self::attachmentName($template, $vars) : null,
            ];
        }

        $body = self::renderLegacy($eventKey, $vars, $standardId);

        if ($body === null) {
            return null;
        }

        return [
            'subject'         => $event['default_subject'] ?? '',
            'body'            => $body,
            'source'          => 'blade',
            'attachment'      => null,
            'attachment_name' => null,
        ];
    }

    /**
     * The file name shown on the mail, with placeholders resolved.
     */
    public static function attachmentName(EmailTemplate $template, array $vars = []): string
    {
        $name = self::replace($template->pdf_filename ?: self::DEFAULT_PDF_NAME, $vars);
        // Spaces are kept - this is a display name, not a filesystem temp name.
        $name = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 _\-\.]/', '', $name)));

        if ($name === '' || !Str::endsWith(strtolower($name), '.pdf')) {
            $name = ($name ?: 'Admission Confirmation') . '.pdf';
        }

        return $name;
    }

    /**
     * The letter that goes out as a PDF: an explicitly chosen template, or the
     * blade layout already registered for this event + standard.
     */
    public static function letterHtml(int $subInstituteId, string $eventKey, array $vars, $standardId = null, $pdfTemplateId = null, $statusCode = null): ?string
    {
        // 1. A letter the body template names explicitly.
        if ($pdfTemplateId) {
            $letter = EmailTemplate::where('sub_institute_id', $subInstituteId)->find($pdfTemplateId);

            if ($letter) {
                Log::info('Email letter resolved', ['source' => 'explicit', 'template' => $letter->id]);

                return self::replace($letter->html_content, $vars);
            }
        }

        // 2. A saved letter scoped to this standard/status. Without this step an
        //    edited Letter template was silently ignored and the blade below won,
        //    so wording changes never reached the PDF.
        $letter = self::resolveLetter($subInstituteId, $eventKey, $standardId, $statusCode);

        if ($letter) {
            Log::info('Email letter resolved', [
                'source'   => 'database',
                'template' => $letter->id,
                'standard' => $standardId,
                'status'   => $statusCode,
            ]);

            return self::replace($letter->html_content, $vars);
        }

        // 3. Nothing saved yet - fall back to the layout still in the blade file.
        Log::info('Email letter resolved', [
            'source'   => 'blade',
            'view'     => self::legacyView($eventKey, $standardId),
            'standard' => $standardId,
        ]);

        return self::renderLegacy($eventKey, $vars, $standardId);
    }

    /**
     * Best matching saved letter (is_letter = 1) for an event.
     */
    public static function resolveLetter(int $subInstituteId, string $eventKey, $standardId = null, $statusCode = null): ?EmailTemplate
    {
        $candidates = EmailTemplate::where('sub_institute_id', $subInstituteId)
            ->where('event_key', $eventKey)
            ->where('status', 1)
            ->where('is_letter', 1)
            ->get();

        return self::pickBest($candidates, $standardId, $statusCode);
    }

    /**
     * Render the letter to a PDF on disk and return its path, or null on failure.
     */
    public static function buildPdfAttachment(EmailTemplate $template, int $subInstituteId, string $eventKey, array $vars, $standardId = null, $statusCode = null): ?string
    {
        $html = self::letterHtml($subInstituteId, $eventKey, $vars, $standardId, $template->pdf_template_id, $statusCode);

        if (empty($html)) {
            Log::warning('Email template marked attach_as_pdf but no letter layout was found', [
                'template' => $template->id,
                'event'    => $eventKey,
                'standard' => $standardId,
                'status'   => $statusCode,
            ]);

            return null;
        }

        $name = self::attachmentName($template, $vars);

        try {
            // The unique id goes in the directory name, never the file name:
            // PHPMailer uses the file's basename as the attachment name, so a
            // uniqid prefix here would show up in the recipient's inbox.
            $directory = storage_path('app/email_attachments') . DIRECTORY_SEPARATOR . uniqid('mail_', true);

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $path = $directory . DIRECTORY_SEPARATOR . $name;

            $pdf = PDF::loadHTML(self::wrapForPdf($html))->setPaper('a4');
            $pdf->setOptions([
                // The letters pull the school logo from an absolute URL.
                'isRemoteEnabled'      => true,
                'isHtml5ParserEnabled' => true,
                // DejaVu Sans carries the rupee sign; dompdf's default serif does not.
                'defaultFont'          => 'DejaVu Sans',
            ]);

            file_put_contents($path, $pdf->output());

            return $path;
        } catch (\Throwable $e) {
            Log::error('Failed to generate email attachment PDF', [
                'template' => $template->id,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Make the letter safe to render as a PDF.
     *
     * The letters declare "font-family: Arial, Helvetica", which dompdf maps to
     * a core PDF font that has no rupee glyph - the fee table then prints "?".
     * Render just the symbol in DejaVu Sans (which carries U+20B9) so the rest
     * of the letter keeps its own typeface.
     */
    private static function wrapForPdf(string $html): string
    {
        $rupee = '<span style="font-family: \'DejaVu Sans\', sans-serif;">&#x20B9;</span>';

        $html = str_ireplace(['&#x20B9;', '&#8377;', '&#X20B9;'], $rupee, $html);
        $html = str_replace("\u{20B9}", $rupee, $html);

        if (stripos($html, '<html') !== false) {
            if (stripos($html, 'charset') === false) {
                $html = preg_replace('/<head([^>]*)>/i', '<head$1><meta charset="UTF-8">', $html, 1);
            }

            return $html;
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<style>body{font-family:"DejaVu Sans",sans-serif;font-size:12px;}</style>'
            . '</head><body>' . $html . '</body></html>';
    }

    /**
     * Legacy blade for an event/standard, or null when the standard is not mapped.
     */
    public static function legacyView(string $eventKey, $standardId = null): ?string
    {
        $views = Arr::get(self::event($eventKey) ?? [], 'legacy.views', []);
        $fallback = null;

        foreach ($views as $row) {
            $standards = $row['standards'] ?? [];

            if (empty($standards)) {
                $fallback = $fallback ?? ($row['view'] ?? null);
                continue;
            }

            if ($standardId && in_array((int) $standardId, $standards, true)) {
                return $row['view'] ?? null;
            }
        }

        return $fallback;
    }

    /**
     * Every layout that still lives in a blade file, one row per event/variant.
     *
     * Used by the template list so an admin can see the hardcoded layouts that
     * are in use and import any of them into an editable template.
     */
    public static function legacyCatalog(): array
    {
        $catalog = [];

        foreach (self::events() as $eventKey => $event) {
            foreach (Arr::get($event, 'legacy.views', []) as $row) {
                $view = $row['view'] ?? null;

                if (!$view) {
                    continue;
                }

                $standards = $row['standards'] ?? [];

                $catalog[] = [
                    'event_key'       => $eventKey,
                    'event_label'     => $event['label'] ?? $eventKey,
                    'module'          => $event['module'] ?? '',
                    'default_subject' => $event['default_subject'] ?? '',
                    'status_codes'    => $event['status_codes'] ?? [],
                    'standard_ids'    => implode(',', $standards),
                    'view'            => $view,
                    'file'            => 'resources/views/' . str_replace('.', '/', $view) . '.blade.php',
                    'exists'          => View::exists($view),
                ];
            }
        }

        return $catalog;
    }

    public static function renderLegacy(string $eventKey, array $vars = [], $standardId = null): ?string
    {
        $view = self::legacyView($eventKey, $standardId);

        if (!$view || !View::exists($view)) {
            return null;
        }

        $context = array_merge(Arr::get(self::event($eventKey) ?? [], 'legacy.context', []), $vars);

        try {
            return view($view, $context)->render();
        } catch (\Throwable $e) {
            Log::error('Email template blade fallback failed', [
                'event' => $eventKey,
                'view'  => $view,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Render the legacy blade with sentinel data and swap that data back to
     * placeholders, producing an editable starting point for an admin.
     */
    public static function importLegacy(string $eventKey, $standardId = null, $statusCode = null): ?string
    {
        $event = self::event($eventKey);

        if (!$event) {
            return null;
        }

        $vars = [];

        foreach (array_keys($event['placeholders'] ?? []) as $key) {
            $vars[$key] = self::IMPORT_SENTINELS[$key] ?? self::wrap($key);
        }

        // Status drives the @if branches inside the legacy blades.
        if ($statusCode) {
            foreach (['pint', 'conf'] as $statusVar) {
                if (array_key_exists($statusVar, $vars)) {
                    $vars[$statusVar] = $statusCode;
                }
            }
        }

        $vars['student_data'] = [];

        $html = self::renderLegacy($eventKey, $vars, $standardId);

        if ($html === null) {
            return null;
        }

        // Swap every rendering of a sentinel date/time back to its placeholder.
        foreach (self::IMPORT_SENTINELS as $key => $sentinel) {
            $formats = $key === 'parent_time'
                ? ['H:i', 'h:i a', 'h:i A', 'g:i a']
                : ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd M Y'];

            foreach ($formats as $format) {
                $html = str_replace(date($format, strtotime($sentinel)), self::wrap($key), $html);
            }
        }

        return $html;
    }

    /**
     * Replace << placeholder >> tokens. Supports an optional date filter:
     * << conf_date | date:d-m-Y >>
     */
    public static function replace(?string $content, array $vars): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        $open = preg_quote(config('email_templates.placeholder_open', '<<'), '/');
        $close = preg_quote(config('email_templates.placeholder_close', '>>'), '/');

        return preg_replace_callback(
            '/' . $open . '\s*([a-zA-Z0-9_\.]+)\s*(?:\|\s*date\s*:\s*([^>|]+?)\s*)?' . $close . '/',
            static function ($matches) use ($vars) {
                $value = Arr::get($vars, $matches[1]);

                if ($value === null || $value === '') {
                    return '';
                }

                if (!empty($matches[2])) {
                    $timestamp = strtotime((string) $value);

                    return $timestamp ? date(trim($matches[2]), $timestamp) : (string) $value;
                }

                return is_scalar($value) ? (string) $value : '';
            },
            $content
        );
    }

    /**
     * Delete a generated attachment and the unique directory holding it.
     */
    public static function cleanupAttachment(?string $path): void
    {
        if (empty($path) || !is_file($path)) {
            return;
        }

        $directory = dirname($path);
        @unlink($path);

        // Only remove the per-mail directory we created, never a shared folder.
        if (Str::startsWith(basename($directory), 'mail_')) {
            @rmdir($directory);
        }
    }

    public static function wrap(string $key): string
    {
        return config('email_templates.placeholder_open', '<<') . ' ' . $key . ' ' . config('email_templates.placeholder_close', '>>');
    }
}
