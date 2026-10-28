<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Lists the routes that change data but have no menu row, so the rights middleware cannot check them.
 *
 * `checkPermission` finds the rights row through `tblmenumaster.link = <route name>`. A write route
 * whose name has no menu row (or that has no name at all) passes that check for everyone. This command
 * only reads: it shows where those gaps are so each one can be given a menu row, or an explicit guard,
 * before the middleware is made to fail closed.
 *
 *   php artisan routes:unmapped-writes             a readable list grouped by risk
 *   php artisan routes:unmapped-writes --json      the same as JSON
 *   php artisan routes:unmapped-writes --only=web  one route file group: web or api
 *
 * (Named "routes:" and not "route:list" because that command is broken in this project.)
 */
class RoutesUnmappedWritesCommand extends Command
{
    protected $signature = 'routes:unmapped-writes {--json : Output JSON} {--only= : web or api}';

    protected $description = 'List write routes that no menu row covers (so their rights are not checked)';

    public function handle(): int
    {
        $links = DB::table('tblmenumaster')->where('status', 1)->pluck('link')->filter()->flip();

        $rows = [];
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            $methods = array_values(array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']));
            if ($methods === []) {
                continue;
            }

            $uri = $route->uri();
            $isApi = str_starts_with($uri, 'api/');
            if (($only = $this->option('only')) && ($only === 'api') !== $isApi) {
                continue;
            }

            $name = $route->getName();
            if ($name !== null && $links->has($name)) {
                continue; // covered by a menu row
            }

            // middleware() is what the route declares (groups and aliases as written). gatherMiddleware() would
            // build every controller to read its own middleware, and some controllers here fail to load.
            $middleware = array_map(fn ($m) => is_string($m) ? $m : 'closure', (array) $route->middleware());
            $has = fn (string $needle) => (bool) array_filter($middleware, fn ($m) => str_contains($m, $needle));

            $rows[] = [
                'risk' => $has('checkPermission') || $has('check_permissions')
                    ? 'rights middleware present, no menu row'
                    : ($has('api.session') || $has('api.jwt') || $has('lms.auth') || $has('pal.auth') || $has('brain.auth') || $has('staff.only') || $has('mobile.') || in_array('session', $middleware, true)
                        ? 'login only'
                        : 'no login and no rights check'),
                'methods' => implode('|', $methods),
                'uri' => $uri,
                'name' => $name ?? '(unnamed)',
                'action' => $route->getActionName(),
            ];
        }

        usort($rows, fn ($a, $b) => [$a['risk'], $a['uri']] <=> [$b['risk'], $b['uri']]);

        if ($this->option('json')) {
            $this->line(json_encode(['count' => count($rows), 'routes' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['risk']][] = $r;
        }
        foreach (['no login and no rights check', 'rights middleware present, no menu row', 'login only'] as $risk) {
            $list = $grouped[$risk] ?? [];
            $this->newLine();
            $this->info(sprintf('%s: %d', $risk, count($list)));
            if ($list) {
                $this->table(['Methods', 'URI', 'Name', 'Action'], array_map(fn ($r) => [$r['methods'], $r['uri'], $r['name'], class_basename(explode('@', $r['action'])[0]) . '@' . (explode('@', $r['action'])[1] ?? '')], $list));
            }
        }
        $this->newLine();
        $this->line(sprintf('%d write route(s) are not covered by a menu row.', count($rows)));

        return self::SUCCESS;
    }
}
