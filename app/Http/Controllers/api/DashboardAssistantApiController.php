<?php

namespace App\Http\Controllers\api;

use App\Domain\AI\Support\ModelClient;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\CustomWidgetCatalog;
use App\Services\Dashboard\QueryWidget;
use App\Services\Dashboard\WidgetLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * "Tell the dashboard what you want." A user types what they would like to see; the assistant turns that into a small
 * list of actions, and this controller decides which of them are allowed and carries them out.
 *
 * What the assistant can do is deliberately narrow:
 *   - show or hide a panel that is already on this dashboard (the user's own choice, as in Customize);
 *   - add a widget built from CustomWidgetCatalog, or remove one it added earlier.
 * It never writes a query and never reaches data the user's role was not given: the model only picks from the lists it
 * is shown, and every pick is checked again here against the catalog and the role before anything is saved.
 *
 * What a widget shows is read-only and worked out when the dashboard opens, so it is always the current figure.
 */
class DashboardAssistantApiController extends Controller
{
    private const MAX_ACTIONS = 6;

    /** Saved widgets with their current figures. */
    public function widgets(Request $request): JsonResponse
    {
        [$tenant, $user, $role] = $this->who();

        return response()->json([
            'status' => '1',
            'widgets' => $this->listWidgets($tenant, $user),
            'catalog' => CustomWidgetCatalog::forRole($role),
            'max' => CustomWidgetCatalog::MAX_PER_USER,
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        [$tenant, $user] = $this->who();
        $deleted = DB::table('dashboard_custom_widgets')->where(['id' => (int) $request->input('id'), 'sub_institute_id' => $tenant, 'user_id' => $user])->delete();

        return response()->json(['status' => $deleted ? '1' : '0', 'widgets' => $this->listWidgets($tenant, $user)], $deleted ? 200 : 404);
    }

    /**
     * "More like this": click a figure and see the other figures of the same subject, then the subjects that usually follow.
     * Only what the role may add to its own dashboard is offered (the same list the assistant uses), so a click can never open
     * more than the role was already allowed to see.
     */
    public function related(Request $request): JsonResponse
    {
        [, , $role] = $this->who();
        $library = $this->library($role);
        $topic = (string) $request->input('topic', '');
        $entryKey = (string) $request->input('entry', '');
        $seen = array_map('strval', (array) $request->input('seen', []));

        if ($entryKey !== '') {
            // "Because you looked at this figure": the figures people look at next, across subjects.
            if (! isset($library[$entryKey])) {
                return response()->json(['status' => '0', 'message' => 'Unknown figure.'], 422);
            }
            $keys = WidgetLibrary::RELATED[$entryKey] ?? [];
            $keys = array_values(array_filter($keys, fn ($k) => isset($library[$k])));
            if ($keys === []) {
                foreach (WidgetLibrary::TOPICS as $def) {
                    if (in_array($entryKey, $def['entries'], true)) {
                        $keys = array_values(array_diff($def['entries'], [$entryKey]));
                        break;
                    }
                }
            }
            // Leave out what the section above already shows (unless that would leave nothing).
            $keys = array_values(array_diff($keys, $seen, [$entryKey]));
            $title = 'More like '.$library[$entryKey]['label'];
            $label = $library[$entryKey]['label'];
            $next = [];
        } else {
            $def = WidgetLibrary::TOPICS[$topic] ?? null;
            if (! $def) {
                return response()->json(['status' => '0', 'message' => 'Unknown topic.'], 422);
            }
            $keys = $def['core'];
            $title = 'More on '.$def['label'];
            $label = $def['label'];
            $next = [];
            foreach ($def['next'] as $n) {
                if (array_intersect(WidgetLibrary::TOPICS[$n]['entries'], array_keys($library))) {
                    $next[] = ['id' => $n, 'label' => WidgetLibrary::TOPICS[$n]['label']];
                }
            }
        }

        $controller = app(RoleWorkspaceApiController::class);
        $items = [];
        foreach ($entryKey !== '' ? array_slice($keys, 0, 6) : $keys as $key) {
            if (! isset($library[$key])) {
                continue;
            }
            try {
                $view = WidgetLibrary::present($key, $controller->widgetValue($role, WidgetLibrary::widgetOf($key)), ['only' => null]);
            } catch (Throwable) {
                $view = null;
            }
            // A card with nothing in it is left out rather than shown as an empty box.
            if ($view !== null && ! $this->emptyView($view)) {
                $items[] = ['key' => $key, 'title' => $library[$key]['label'], 'about' => $library[$key]['about'], 'view' => $view];
            }
        }

        // A card only invites a click when something new would open under it: figures not already on screen that this role may see.
        $onScreen = array_merge($seen, array_column($items, 'key'));
        foreach ($items as $n => $item) {
            $follow = WidgetLibrary::RELATED[$item['key']] ?? [];
            $items[$n]['more'] = count(array_filter($follow, fn ($k) => $k !== $item['key'] && isset($library[$k]) && ! in_array($k, $onScreen, true))) > 0;
        }

        return response()->json(['status' => '1', 'topic' => $entryKey !== '' ? 'entry:'.$entryKey : $topic, 'label' => $label, 'title' => $title, 'items' => $items, 'next' => $next]);
    }

    /** A view with nothing to draw (and no sentence of its own to say so). */
    private function emptyView(array $view): bool
    {
        return match ($view['kind'] ?? '') {
            'number' => ($view['value'] ?? null) === null,
            'facts' => empty($view['items']),
            'bars', 'series' => empty($view['rows']),
            'list' => empty($view['items']) && empty($view['empty_note']),
            default => false,
        };
    }

    public function assistant(Request $request): JsonResponse
    {
        [$tenant, $user, $role] = $this->who();
        $message = trim(mb_substr((string) $request->input('message', ''), 0, 500));
        if ($message === '') {
            return response()->json(['status' => '0', 'message' => 'Tell me what you would like on your dashboard.'], 422);
        }

        $panels = collect((array) $request->input('panels', []))
            ->filter(fn ($p) => is_array($p) && preg_match('/^[a-z]+\.[A-Za-z0-9_.\-]+$/', (string) ($p['id'] ?? '')))
            ->map(fn ($p) => ['id' => (string) $p['id'], 'label' => mb_substr(strip_tags((string) ($p['label'] ?? $p['id'])), 0, 100), 'visible' => (bool) ($p['visible'] ?? true)])
            ->unique('id')->take(80)->values()->all();
        $mine = $this->listWidgets($tenant, $user, false);
        $catalog = CustomWidgetCatalog::forRole($role);

        $library = $this->library($role);
        $plan = $this->askModel($tenant, $message, (array) $request->input('history', []), $panels, $catalog, $mine, $library, $role);
        $viaModel = $plan !== null;
        $plan ??= $this->fallbackPlan($message, $panels, $catalog, $mine, $library);

        $applied = [];
        $problems = [];
        $ui = [];
        $panelIds = array_column($panels, 'id');
        $labelOf = array_column($panels, 'label', 'id');

        foreach (array_slice((array) ($plan['actions'] ?? []), 0, self::MAX_ACTIONS) as $a) {
            $type = (string) ($a['type'] ?? '');
            try {
                if (in_array($type, ['show', 'hide'], true)) {
                    $id = (string) ($a['panel_id'] ?? '');
                    if (! in_array($id, $panelIds, true)) {
                        throw new InvalidArgumentException('One of those panels is not on your dashboard.');
                    }
                    $ui[] = ['type' => $type, 'id' => $id];
                    $applied[] = ['type' => $type, 'text' => ($type === 'show' ? 'Showing "' : 'Hid "').($labelOf[$id] ?? $id).'"', 'panel_id' => $id];
                } elseif ($type === 'add_widget') {
                    $spec = $this->validateSpec((array) ($a['spec'] ?? $a), $role);
                    if (collect($mine)->contains(fn ($w) => $this->sameSpec($w['spec'], $spec))) {
                        throw new InvalidArgumentException("\"{$spec['title']}\" is already on your dashboard.");
                    }
                    if (count($mine) >= CustomWidgetCatalog::MAX_PER_USER) {
                        throw new InvalidArgumentException('You already have '.CustomWidgetCatalog::MAX_PER_USER.' widgets of your own. Remove one first.');
                    }
                    $id = DB::table('dashboard_custom_widgets')->insertGetId([
                        'sub_institute_id' => $tenant, 'user_id' => $user, 'role' => $role, 'spec' => json_encode($spec),
                        'sort_order' => count($mine), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $mine[] = ['id' => $id, 'spec' => $spec];
                    $applied[] = ['type' => 'add_widget', 'text' => "Added \"{$spec['title']}\"".(isset($spec['library']) ? '' : " ({$spec['chart']})").(isset($spec['query']) ? '. How it is worked out: '.$spec['how'] : ''), 'widget_id' => $id];
                } elseif ($type === 'remove_widget') {
                    $id = (int) ($a['widget_id'] ?? 0);
                    $found = collect($mine)->firstWhere('id', $id);
                    if (! $found) {
                        throw new InvalidArgumentException('I could not find that widget among yours.');
                    }
                    DB::table('dashboard_custom_widgets')->where(['id' => $id, 'sub_institute_id' => $tenant, 'user_id' => $user])->delete();
                    $mine = array_values(array_filter($mine, fn ($w) => $w['id'] !== $id));
                    $applied[] = ['type' => 'remove_widget', 'text' => "Removed \"{$found['spec']['title']}\"", 'spec' => $found['spec']];
                }
            } catch (InvalidArgumentException $e) {
                $problems[] = $e->getMessage();
            }
        }

        // What is said back comes from what actually happened. The model's own words are used only to ask or to explain a refusal.
        $words = trim(mb_substr(strip_tags((string) ($plan['message'] ?? '')), 0, 500));
        $reply = $applied
            ? 'Done. '.implode('. ', array_column($applied, 'text')).'.'.($problems ? ' But: '.implode(' ', $problems) : '')
            : ($problems ? implode(' ', $problems) : ($words !== '' ? $words : $this->help($catalog, $library)));

        return response()->json([
            'status' => '1',
            'reply' => $reply,
            'applied' => $applied,
            'ui' => $ui,
            'widgets' => $this->listWidgets($tenant, $user),
            'understood_by' => $viaModel ? 'assistant' : 'keywords',
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** What this role may build from: the ready-made figures its dashboard already has. */
    private function library(string $role): array
    {
        return WidgetLibrary::forRole(RoleWorkspaceApiController::askableFor($role));
    }

    /**
     * Check one requested widget, from either list: a ready-made figure from the library (by key) or a chart built
     * from the metric catalog. Anything else, or anything the role was not given, is refused.
     *
     * @throws InvalidArgumentException
     */
    private function validateSpec(array $spec, string $role): array
    {
        if (isset($spec['library'])) {
            $key = (string) $spec['library'];
            $entry = $this->library($role)[$key] ?? null;
            if (! $entry) {
                throw new InvalidArgumentException(WidgetLibrary::exists($key)
                    ? "\"".WidgetLibrary::entries()[$key]['label']."\" is not part of your role's dashboard, so I can't add it."
                    : "I don't have a widget called \"{$key}\".");
            }
            $only = in_array($spec['only'] ?? null, $entry['filters'] ?? [], true) ? $spec['only'] : null;
            // The title is what the widget really is. The model's own wording is never used, so a widget cannot be labelled as something it is not.
            return ['library' => $key, 'only' => $only, 'title' => mb_substr($entry['label'].($only === 'today' ? ' today' : ''), 0, 60)];
        }

        if (isset($spec['query']) && is_array($spec['query'])) {
            $clean = QueryWidget::validate($spec['query'], $role);
            $title = trim(strip_tags((string) ($spec['title'] ?? '')));
            $chart = in_array($spec['chart'] ?? null, CustomWidgetCatalog::CHARTS, true) ? $spec['chart'] : ($clean['group'] && $clean['group']['bucket'] ? 'line' : 'bar');
            if (! $clean['group']) {
                $chart = 'number';
            }

            return ['query' => QueryWidget::toStored($clean), 'chart' => $chart, 'title' => mb_substr($title !== '' ? $title : 'New figure', 0, 60), 'how' => QueryWidget::explain($clean), 'unit' => $clean['unit']];
        }

        unset($spec['title']); // same reason: a chart from the catalog is titled by the catalog

        return CustomWidgetCatalog::validate($spec, $role);
    }

    private function sameSpec(array $a, array $b): bool
    {
        if (isset($a['library']) || isset($b['library'])) {
            return ($a['library'] ?? null) === ($b['library'] ?? null) && ($a['only'] ?? null) === ($b['only'] ?? null);
        }

        if (isset($a['query']) || isset($b['query'])) {
            return json_encode($a['query'] ?? null) === json_encode($b['query'] ?? null) && ($a['chart'] ?? null) === ($b['chart'] ?? null);
        }

        return $a['metric'] === $b['metric'] && $a['dim'] === $b['dim'] && $a['chart'] === $b['chart'];
    }

    /** @return array{0:int,1:int,2:string} */
    private function who(): array
    {
        return [
            (int) session()->get('sub_institute_id'),
            (int) session()->get('user_id'),
            RoleWorkspaceApiController::roleKeyFor((string) session()->get('user_profile_name')),
        ];
    }

    /** The user's saved widgets; with figures when `$withData`. A widget whose figures fail comes back with null data, not an error. */
    private function listWidgets(int $tenant, int $user, bool $withData = true): array
    {
        $syear = session()->get('syear');
        $role = RoleWorkspaceApiController::roleKeyFor((string) session()->get('user_profile_name'));
        $out = [];
        foreach (DB::table('dashboard_custom_widgets')->where(['sub_institute_id' => $tenant, 'user_id' => $user])->orderBy('sort_order')->orderBy('id')->get() as $row) {
            $spec = json_decode((string) $row->spec, true) ?: [];
            try {
                // A saved spec is checked again every time: if the role changed since, the widget quietly stops showing.
                $spec = $this->validateSpec($spec, $role);
            } catch (InvalidArgumentException) {
                continue;
            }
            $data = null;
            if ($withData) {
                try {
                    $data = isset($spec['query']) ? $this->runQuery($spec, $role, $tenant, $syear) : (isset($spec['library'])
                        ? ['view' => WidgetLibrary::present($spec['library'], app(RoleWorkspaceApiController::class)->widgetValue($role, WidgetLibrary::widgetOf($spec['library'])), ['only' => $spec['only'] ?? null])]
                        : CustomWidgetCatalog::compute($spec, $tenant, $syear));
                } catch (Throwable) {
                    $data = null;
                }
            }
            $out[] = ['id' => (int) $row->id, 'spec' => $spec] + ($withData ? ['data' => $data] : []);
        }

        return $out;
    }

    private function askModel(int $tenant, string $message, array $history, array $panels, array $catalog, array $mine, array $library = [], string $role = ''): ?array
    {
        try {
            $client = app(ModelClient::class);
            if (! $client->isConfigured()) {
                return null;
            }
            $panelLines = collect($panels)->map(fn ($p) => "- {$p['id']} | {$p['label']} | ".($p['visible'] ? 'shown' : 'hidden'))->implode("\n");
            $metricLines = collect($catalog)->map(function ($m, $k) {
                $dims = collect($m['dims'])->map(fn ($d, $dk) => "{$dk} ({$d['label']}; charts: ".implode('/', $d['charts']).')')->implode('; ');

                return "- {$k}: {$m['label']} [{$m['unit']}] -> breakdowns: {$dims}";
            })->implode("\n") ?: '(none: this role cannot build widgets, it can only show or hide panels)';
            $libraryLines = collect($library)->map(fn ($e, $k) => "- {$k}: {$e['label']} - {$e['about']}".(! empty($e['filters']) ? ' (optional only:"today")' : ''))->implode("
") ?: '(none)';
            $mineLines = collect($mine)->map(fn ($w) => "- {$w['id']} | {$w['spec']['title']}")->implode("\n") ?: '(none)';

            $canBuild = QueryWidget::allowedFor($role);
            $needData = '';
            $newRule = '';
            $tableLines = '';

            $system = <<<TXT
You are the dashboard assistant of a school ERP. The user tells you in their own words what they want on THEIR dashboard. You turn that into actions. You never answer questions about the school's data; you only change the dashboard.

Reply with one JSON object only: {"message": string, "actions": [ ... ]}.
Action kinds, and nothing else:
  {"type":"show","panel_id":"<id from PANELS>"}
  {"type":"hide","panel_id":"<id from PANELS>"}
  {"type":"add_widget","fit":"exact|partial","spec":{"library":"<key from READY-MADE>","only":"today (optional)"}}   <- a ready-made widget
  {"type":"add_widget","fit":"exact|partial","spec":{"metric":"<key from METRICS>","dim":"<breakdown key>","chart":"number|bar|line|donut","order":"natural|asc|desc","limit":<3-20>}}
  {"type":"remove_widget","widget_id":<id from MY WIDGETS>}
{$needData}

Rules:
- Use only ids and keys listed below. Never invent one. If what the user wants is not possible with them, return "actions": [] and say in "message" what is possible instead, briefly.
{$newRule}
- "fit" says honestly whether the widget shows PRECISELY what the user asked: "exact" only when the subject (students, staff, fees ...) AND the breakdown asked for ("by department", "by gender", "per month" ...) are both what the widget gives; otherwise "partial". A plain total is "partial" when a breakdown was asked for. Partial widgets are not added.
- The SUBJECT must match exactly. Students, staff, fees, library, transport and so on are different subjects: a READY-MADE or METRICS entry about students is never an answer to a request about staff. If the closest entry is about a different subject, it is wrong; use need_data (when offered) or refuse.
- Prefer showing an existing panel when one already covers the request. Otherwise prefer a READY-MADE widget. Use METRICS only when the user asks for a chart or a breakdown that READY-MADE does not give (for example "as a pie chart", "month by month").
- "Highest / top" means order desc, "lowest / worst" means asc. Use the chart the user names; otherwise the first chart listed.
- If the request is unclear, return no actions and ask one short question in "message".
- Write "message" in the language the user wrote in (English, Gujarati, Hindi or mixed). Keep it to one or two sentences.

PANELS (id | label | state):
{$panelLines}

READY-MADE widgets you may add (key - what it shows):
{$libraryLines}

METRICS you may build charts from:
{$metricLines}

MY WIDGETS (ones this user already added):
{$mineLines}{$tableLines}
TXT;
            $messages = [['role' => 'system', 'content' => $system]];
            foreach (array_slice($history, -6) as $h) {
                if (is_array($h) && in_array($h['role'] ?? '', ['user', 'assistant'], true)) {
                    $messages[] = ['role' => $h['role'], 'content' => mb_substr(strip_tags((string) ($h['text'] ?? '')), 0, 400)];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $message];

            $ask = fn () => $client->forInstitute($tenant)->json($messages, null, 1200, 0.0);
            // One more try: the provider pool sometimes answers nothing the first time.
            $out = $ask() ?? $ask();

            // Keep what fits. A ready-made or catalog widget the model itself calls only "partial" is dropped, because a plain total
            // given for "by department" is a wrong answer, not a near one.
            if (is_array($out)) {
                $out['actions'] = array_values(array_filter((array) ($out['actions'] ?? []), function ($a) use ($canBuild) {
                    return ! ($canBuild && ($a['type'] ?? '') === 'add_widget' && ($a['fit'] ?? 'exact') === 'partial');
                }));
            }
            $settled = is_array($out) && count((array) ($out['actions'] ?? [])) > 0;

            // Nothing fits: this is a NEW figure. The server decides that, not the model: pick the tables, show their real columns,
            // then have the figure described in the fixed form. Requests for personal details end at the table step.
            if ($canBuild && ! $settled) {
                $built = $this->newFigure($client, $tenant, $role, $message);
                if ($built !== null) {
                    return $built;
                }
            }
            return is_array($out) && (isset($out['actions']) || isset($out['message'])) ? $out : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * When no model is available, understand the plainest requests from keywords: show/hide a panel by its name,
     * add a catalog widget by its subject. Anything cleverer is left to the model, and it says so rather than guess.
     */
    private function fallbackPlan(string $message, array $panels, array $catalog, array $mine, array $library = []): array
    {
        $text = strtolower($message);
        $has = fn (array $words) => (bool) array_filter($words, fn ($w) => str_contains($text, $w));
        $hide = $has(['hide', 'remove', 'delete', 'hatao', 'kadhi', 'band kar', 'not need']);
        $stop = ['the', 'and', 'for', 'show', 'add', 'dashboard', 'panel', 'my', 'me', 'with', 'from', 'on', 'in', 'of', 'a', 'to'];
        $tokens = fn (string $s) => array_values(array_diff(preg_split('/[^a-z0-9]+/', strtolower($s), -1, PREG_SPLIT_NO_EMPTY), $stop));

        // Something this user built themselves, named in the request.
        if ($hide) {
            foreach ($mine as $w) {
                if (array_intersect($tokens($w['spec']['title']), $tokens($text)) && count(array_intersect($tokens($w['spec']['title']), $tokens($text))) >= 2) {
                    return ['actions' => [['type' => 'remove_widget', 'widget_id' => $w['id']]]];
                }
            }
        }

        $best = null;
        $bestScore = 0;
        foreach ($panels as $p) {
            $score = count(array_intersect($tokens($p['label']), $tokens($text)));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$p, $score];
            }
        }

        if (! $hide) {
            // A ready-made widget named by its words ("birthday", "library overdue"); "today" narrows the ones that can be narrowed.
            $bestLib = null;
            $bestLibScore = 0;
            foreach ($library as $key => $e) {
                $score = 0;
                foreach ($e['keywords'] as $kw) {
                    if (str_contains($text, $kw)) {
                        $score = max($score, strlen($kw));
                    }
                }
                if ($score > $bestLibScore) {
                    [$bestLib, $bestLibScore] = [$key, $score];
                }
            }
            if ($bestLib && $bestLibScore >= 5 && ! $has(['chart', 'graph', 'pie', 'bar', 'month by month'])) {
                return ['actions' => [['type' => 'add_widget', 'spec' => ['library' => $bestLib] + ($has(['today', 'aaj']) ? ['only' => 'today'] : [])]]];
            }

            foreach ($catalog as $key => $m) {
                $kw = CustomWidgetCatalog::METRICS[$key]['keywords'];
                if (! $has($kw)) {
                    continue;
                }
                $dim = null;
                foreach (CustomWidgetCatalog::METRICS[$key]['dims'] as $dk => $d) {
                    if ($has($d['keywords'])) {
                        $dim = $dk;
                        break;
                    }
                }
                $dim ??= array_key_first($m['dims']);
                $chart = $has(['pie', 'donut']) ? 'donut' : ($has(['line', 'trend']) ? 'line' : ($has(['bar']) ? 'bar' : ($has(['number', 'total']) ? 'number' : null)));
                $spec = ['metric' => $key, 'dim' => $dim, 'order' => $has(['lowest', 'worst', 'least']) ? 'asc' : ($has(['highest', 'top', 'best', 'most']) ? 'desc' : 'natural')] + ($chart ? ['chart' => $chart] : []);

                return ['actions' => [['type' => 'add_widget', 'spec' => $spec]]];
            }
        }

        if ($best && $bestScore > 0) {
            return ['actions' => [['type' => $hide ? 'hide' : 'show', 'panel_id' => $best['id']]]];
        }

        return ['actions' => [], 'message' => 'The assistant is not available right now, so I could only follow simple requests, like "hide transport" or "add fees month by month". '.$this->help($catalog, $library)];
    }

    /** Figures for a widget built from the school data. A figure that cannot be worked out comes back empty with the reason. */
    private function runQuery(array $spec, string $role, int $tenant, $syear): array
    {
        try {
            $r = QueryWidget::run(QueryWidget::validate($spec['query'], $role), $tenant, $syear);
            $r['unit'] = $spec['unit'] ?? $r['unit'];

            return $r;
        } catch (InvalidArgumentException $e) {
            return ['rows' => [], 'unit' => $spec['unit'] ?? 'count', 'number' => null, 'as_of' => null, 'note' => $e->getMessage()];
        }
    }

    /**
     * A figure nothing ready-made gives. Step 1: which tables could hold it (names only). Step 2: their real columns, and the
     * figure described in the fixed form. Returns a plan, or null when the model could not help (the caller then says so).
     */
    private function newFigure(ModelClient $client, int $tenant, string $role, string $message): ?array
    {
        $pick = <<<TXT
You help build a new widget for a school dashboard from the school's own database. Choose which tables could answer the user's request. Names only, up to 3, from the list. Include a lookup table when a breakdown needs readable names (for example a department master or a class master).
A COUNT or BREAKDOWN of people (how many staff or students per department, class, gender, role, month ...) is not personal and is always allowed: the words "details" or "information" in such a request just mean a summary. Refuse only when the user asks to LIST or SHOW individual people's phone numbers, addresses, emails, names, salaries or passwords. If nothing in the list could hold the request, or the breakdown makes no sense for that subject (for example staff by class), return no tables and say why in one short sentence.
Reply with one JSON object only: {"tables": ["..."], "message": "<one sentence in the user's language, only when tables is empty>"}

TABLES:
TXT;
        $messages = [['role' => 'system', 'content' => $pick."\n".implode(', ', QueryWidget::tableNames($role))], ['role' => 'user', 'content' => $message]];
        $ask = fn () => $client->forInstitute($tenant)->json($messages, null, 400, 0.0);
        $chosen = $ask() ?? $ask();
        if (! is_array($chosen)) {
            return null;
        }
        $tables = array_values(array_filter(array_map('strval', (array) ($chosen['tables'] ?? []))));
        $schemaText = $tables ? QueryWidget::describe($tables, $role, $tenant) : '';
        if ($schemaText === '') {
            return ['actions' => [], 'message' => trim((string) ($chosen['message'] ?? '')) ?: "I couldn't find data for that in the school's records."];
        }

        $messages2 = [['role' => 'system', 'content' => $this->querySystemPrompt($schemaText)], ['role' => 'user', 'content' => $message]];
        $ask2 = fn () => $client->forInstitute($tenant)->json($messages2, null, 1500, 0.0);
        $out = $ask2() ?? $ask2();

        return is_array($out) && (isset($out['actions']) || isset($out['message'])) ? $out : null;
    }

    /** Second step for a NEW figure: the model is shown the real columns and describes the figure in the fixed form. */
    private function querySystemPrompt(string $schema): string
    {
        return <<<TXT
You build ONE new widget for a school dashboard from the school's own data. You never write SQL. Describe the figure in this fixed JSON form, using only the tables and columns listed below.

Reply with one JSON object only: {"message": string, "actions": [ {"type":"add_widget","spec":{"query":{...},"chart":"number|bar|line|donut","title":"<short title>","unit":"count|rupees|percent"}} ]}

"query" form:
{
 "table": "<table>",
 "join": {"table":"<other table>","left":"<column of table>","right":"<column of the other table>"},     (optional, only for a lookup such as a class name)
 "measure": {"fn":"count|count_distinct|sum|avg|min|max","column":"<column; omit for count>"},
 "group": {"column":"<column, or join.<column> for the other table>","bucket":"day|month|year"},         (optional; bucket only for date columns)
 "filters": [{"column":"<column>","op":"=|!=|>|>=|<|<=|in|like|is_null|not_null|between","value":"<value, or a list for in/between>"}],   (optional)
 "period": {"column":"<date column>","kind":"last_days|this_month|this_year|today","days":30},             (optional)
 "all_years": false, "order":"desc|asc|label", "limit": 12
}

Rules:
- Columns of the other table are written "join.<column>". Never use a column that is not listed. Use values exactly as listed for filters.
- Group by something broad (a class, a type, a status, a month), never by something that is different for every person.
- If the tables cannot answer it, return "actions": [] and say so in "message" in one sentence. Do not guess a column.
- Choose a chart that fits: bar for categories, line for months or days, donut for a few shares, number when there is no grouping.
- "message" in the user's language, one sentence.

{$schema}
TXT;
    }

    private function help(array $catalog, array $library = []): string
    {
        $can = $library ? 'I can add ready-made widgets such as: '.implode(', ', array_slice(array_column($library, 'label'), 0, 8)).', and more. ' : '';
        $can .= $catalog ? 'I can also draw charts of: '.implode(', ', array_column($catalog, 'label')).'. ' : '';

        return $can.'I can also show or hide any panel already on your dashboard.';
    }
}
