<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Base controller for all Result module REST APIs.
 *
 * These APIs are thin wrappers: every controller delegates to the EXISTING
 * web controllers in app/Http/Controllers/result (with type=API merged into
 * the request), so 100% of the existing business logic - calculations,
 * grades, GPA, ranking, promotion, remarks - is reused, never duplicated.
 */
class BaseResultApiController extends Controller
{
    /* ---------------------------------------------------------------------
     | Standard JSON envelope
     * -------------------------------------------------------------------*/

    protected function success($data = null, string $message = 'Success', int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message = 'Something went wrong.', int $status = 500, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $status);
    }

    protected function validationError($errors, string $message = 'Validation Failed'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }

    /**
     * Run a callable inside a guard that converts every failure mode into the
     * standard envelope with a proper HTTP status code.
     */
    protected function run(callable $callback)
    {
        try {
            $result = $callback();

            // Callbacks may return a ready JsonResponse (e.g. success()/error())
            // or a file download / streamed response from a legacy export.
            if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
                return $result;
            }

            return $this->success($result);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (ModelNotFoundException $e) {
            return $this->error('Record not found.', 404);
        } catch (\Throwable $e) {
            Log::error('Result API error: ' . $e->getMessage(), [
                'exception' => $e,
                'url'       => request()->fullUrl(),
            ]);

            return $this->error('Something went wrong.', 500);
        }
    }

    /* ---------------------------------------------------------------------
     | Delegation to existing web controllers
     * -------------------------------------------------------------------*/

    /**
     * Call a method on an existing web controller and normalize whatever it
     * returns (JSON string from is_mobile(), JsonResponse, View data, arrays,
     * collections, redirects) into plain data ready for the envelope.
     *
     * @param string  $controller Fully qualified class of the existing web controller
     * @param string  $method     Method to invoke
     * @param mixed   ...$params  Parameters, exactly as the web route passes them
     */
    protected function delegate(string $controller, string $method, ...$params)
    {
        foreach ($params as $param) {
            if ($param instanceof Request) {
                // Force the existing controllers into their JSON/API branches.
                $param->merge(['type' => 'API']);
            }
        }

        $result = app($controller)->{$method}(...$params);

        return $this->normalize($result);
    }

    /**
     * Normalize any legacy controller return value into plain data.
     */
    protected function normalize($result)
    {
        // is_mobile() with type=API returns a json_encode()'d string.
        if (is_string($result)) {
            $decoded = json_decode($result, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $result;
        }

        if ($result instanceof JsonResponse) {
            return $result->getData(true);
        }

        // Some legacy methods return a view regardless of type; its data
        // array holds everything the Blade template would have rendered.
        if ($result instanceof \Illuminate\Contracts\View\View) {
            return $result->getData();
        }

        // Legacy redirects flash their payload as session 'data'.
        if ($result instanceof RedirectResponse) {
            return session('data');
        }

        // Legacy exports (Excel/PDF downloads, streamed files) pass through.
        if ($result instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $result instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $result;
        }

        if ($result instanceof Collection) {
            return $result->toArray();
        }

        return $result;
    }

    /* ---------------------------------------------------------------------
     | Next.js friendly helpers
     * -------------------------------------------------------------------*/

    /**
     * Paginate an array/collection returned by legacy getData() style methods
     * without touching the underlying query logic.
     *
     * Honours ?page= & ?per_page= (per_page=0 disables pagination).
     */
    protected function paginate($items, Request $request): array
    {
        $items = $items instanceof Collection ? $items->values() : collect($items)->values();

        $perPage = (int) $request->input('per_page', 25);
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $items->count();

        if ($perPage <= 0) {
            return [
                'items' => $items->all(),
                'meta'  => [
                    'current_page' => 1,
                    'per_page'     => $total,
                    'total'        => $total,
                    'last_page'    => 1,
                ],
            ];
        }

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return [
            'items' => $paginator->items(),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Case-insensitive search across the given keys of an array/collection of
     * rows (arrays or objects). Applied AFTER the legacy query so existing
     * query logic stays untouched.
     */
    protected function search($items, ?string $term, array $keys)
    {
        if ($term === null || $term === '') {
            return $items instanceof Collection ? $items : collect($items);
        }

        $term  = mb_strtolower($term);
        $items = $items instanceof Collection ? $items : collect($items);

        return $items->filter(function ($row) use ($term, $keys) {
            foreach ($keys as $key) {
                $value = is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);
                if ($value !== null && str_contains(mb_strtolower((string) $value), $term)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Sort rows by ?sort_by= / ?sort_dir= without touching legacy queries.
     */
    protected function sort($items, Request $request)
    {
        $sortBy = $request->input('sort_by');

        if (empty($sortBy)) {
            return $items instanceof Collection ? $items : collect($items);
        }

        $items      = $items instanceof Collection ? $items : collect($items);
        $descending = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc';

        return $items->sortBy(function ($row) use ($sortBy) {
            return is_array($row) ? ($row[$sortBy] ?? null) : ($row->{$sortBy} ?? null);
        }, SORT_NATURAL | SORT_FLAG_CASE, $descending)->values();
    }

    /**
     * List helper combining search + sort + paginate for legacy result sets.
     */
    protected function listResponse($items, Request $request, array $searchKeys = [], string $message = 'Success'): JsonResponse
    {
        $items = $this->search($items, $request->input('search'), $searchKeys);
        $items = $this->sort($items, $request);
        $page  = $this->paginate($items, $request);

        return $this->success($page['items'], $message, 200, ['pagination' => $page['meta']]);
    }

    /**
     * Convert a pluck(id => name) style map into [{id, name}] for dropdowns.
     *
     * (Named dropdownOptions, not dropdown, so child controllers can expose a
     * public dropdown(Request) endpoint without an incompatible override.)
     */
    protected function dropdownOptions($map): array
    {
        $out = [];
        foreach ($map as $id => $name) {
            $out[] = ['id' => $id, 'name' => $name];
        }

        return $out;
    }
}
