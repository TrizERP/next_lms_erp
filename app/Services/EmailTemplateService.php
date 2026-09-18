<?php

namespace App\Services;

use App\Models\communication\EmailTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

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
        $candidates = EmailTemplate::where('sub_institute_id', $subInstituteId)
            ->where('event_key', $eventKey)
            ->where('status', 1)
            ->get();

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

            if ($hasStatus && (string) $template->status_code !== (string) $statusCode) {
                continue;
            }

            $score = ($hasStandard ? 2 : 0) + ($hasStatus ? 1 : 0);

            if ($score > $bestScore) {
                $best = $template;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Build the mail body + subject for an event.
     *
     * @return array{subject:string,body:string,source:string}|null null when
     *         neither a saved template nor a legacy blade is available.
     */
    public static function render(int $subInstituteId, string $eventKey, array $vars = [], $standardId = null, $statusCode = null): ?array
    {
        $event = self::event($eventKey);
        $template = self::resolve($subInstituteId, $eventKey, $standardId, $statusCode);

        if ($template) {
            return [
                'subject' => self::replace($template->subject ?: ($event['default_subject'] ?? ''), $vars),
                'body'    => self::replace($template->html_content, $vars),
                'source'  => 'database',
            ];
        }

        $body = self::renderLegacy($eventKey, $vars, $standardId);

        if ($body === null) {
            return null;
        }

        return [
            'subject' => $event['default_subject'] ?? '',
            'body'    => $body,
            'source'  => 'blade',
        ];
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

        // Swap every rendering of a sentinel date/time back to its placeholder,
        // carrying the format the blade used so the imported template still
        // prints "08-04-2026" rather than the raw "2026-04-08".
        foreach (self::IMPORT_SENTINELS as $key => $sentinel) {
            $formats = $key === 'parent_time'
                ? ['h:i a', 'h:i A', 'g:i a', 'H:i']
                : ['d-m-Y', 'd/m/Y', 'd M Y', 'Y-m-d'];

            foreach ($formats as $format) {
                $html = str_replace(
                    date($format, strtotime($sentinel)),
                    self::wrap($key . ' | date:' . $format),
                    $html
                );
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

        $openRaw = config('email_templates.placeholder_open', '<<');
        $closeRaw = config('email_templates.placeholder_close', '>>');

        // A rich-text editor serialises "<<" as "&lt;&lt;", so a template typed
        // in the WYSIWYG stores escaped delimiters. Match either form, otherwise
        // the token would go out to parents as literal text.
        $open = '(?:' . preg_quote($openRaw, '/') . '|' . preg_quote(htmlspecialchars($openRaw), '/') . ')';
        $close = '(?:' . preg_quote($closeRaw, '/') . '|' . preg_quote(htmlspecialchars($closeRaw), '/') . ')';

        return preg_replace_callback(
            '/' . $open . '\s*([a-zA-Z0-9_\.]+)\s*(?:\|\s*date\s*:\s*([^>|&]+?)\s*)?' . $close . '/',
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

    public static function wrap(string $key): string
    {
        return config('email_templates.placeholder_open', '<<') . ' ' . $key . ' ' . config('email_templates.placeholder_close', '>>');
    }
}
