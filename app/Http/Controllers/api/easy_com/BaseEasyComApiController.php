<?php

namespace App\Http\Controllers\api\easy_com;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Base controller for all Easy Communication (easy_com) REST APIs used by the
 * Next.js frontend.
 *
 * WHY A SEPARATE CONTROLLER TREE
 * ------------------------------
 * The existing Blade controllers under app/Http/Controllers/easy_com are mounted
 * on the `web` middleware group (session, menu, logRoute, check_permissions).
 * They read tenant context straight from session()->get('sub_institute_id') and
 * frequently touch $_REQUEST without isset() guards. Called statelessly from
 * Next.js they produced NULL tenant ids (SQL syntax errors), HTML redirects
 * instead of JSON, and - worse - unscoped UPDATE/DELETE statements that could
 * touch another institute's rows.
 *
 * Rather than change those controllers (and risk the working Blade screens),
 * these API controllers re-express the SAME business logic behind the
 * `api.session` middleware, which validates the JWT and hydrates the exact
 * session keys web login sets. Shared, side-effect-free logic (SearchStudent(),
 * sendSMS(), sendWhatsappCloudApi(), mediaFound()) is REUSED from the existing
 * controllers/helpers rather than duplicated.
 *
 * Every endpoint returns the standard envelope:
 *   { success, message, data, errors }
 */
class BaseEasyComApiController extends Controller
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

    protected function validationError($errors, string $message = 'Validation failed.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], 422);
    }

    /**
     * Run a callable, converting every failure mode into the standard envelope.
     */
    protected function run(callable $callback)
    {
        try {
            $result = $callback();

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
            Log::error('EasyCom API error: '.$e->getMessage(), [
                'exception' => $e,
                'url'       => request()->fullUrl(),
            ]);

            return $this->error('Something went wrong.', 500);
        }
    }

    /* ---------------------------------------------------------------------
     | Tenant context (hydrated by the api.session middleware)
     * -------------------------------------------------------------------*/

    protected function subInstituteId()
    {
        return session()->get('sub_institute_id');
    }

    protected function syear()
    {
        return session()->get('syear');
    }

    protected function userId()
    {
        return session()->get('user_id');
    }

    /* ---------------------------------------------------------------------
     | Request helpers
     * -------------------------------------------------------------------*/

    /**
     * Read a filter that the frontend may send as "", "null" or absent.
     */
    protected function filter(Request $request, string $key, $default = '')
    {
        $value = $request->input($key, $default);

        if ($value === null || $value === 'null' || $value === 'undefined') {
            return $default;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Normalise a checkbox map posted as sendsms[<key>]=on / sendNotification[<key>]=on
     * into a plain list of keys. Also accepts a JSON array or a comma separated
     * string, so the frontend is free to post whichever is convenient.
     *
     * @return array<int, string>
     */
    protected function selectionKeys(Request $request, string $field): array
    {
        $raw = $request->input($field);

        if (is_array($raw)) {
            // sendsms[9876543210] => "on"  -> keys are the payload
            // sendsms[] => "9876543210"    -> values are the payload
            $keys = array_keys($raw);
            $isList = $keys === range(0, count($raw) - 1);

            $values = $isList ? array_values($raw) : $keys;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $values = is_array($decoded) ? $decoded : explode(',', $raw);
        } else {
            $values = [];
        }

        $values = array_map(static fn ($v) => trim((string) $v), $values);

        return array_values(array_filter(array_unique($values), static fn ($v) => $v !== ''));
    }

    /**
     * Inclusive end-of-day bound for a date filter.
     *
     * The legacy screens compare a `timestamp` column against a bare Y-m-d
     * to_date, which silently drops every row created after 00:00:00 on that
     * day. Reports here use "<= <date> 23:59:59" instead.
     */
    protected function endOfDay(string $date): string
    {
        return $date.' 23:59:59';
    }

    /* ---------------------------------------------------------------------
     | Shared validation rules
     * -------------------------------------------------------------------*/

    protected function isValidMobile(?string $mobile): bool
    {
        return (bool) preg_match('/^[6-9]\d{9}$/', (string) $mobile);
    }

    protected function isValidEmail(?string $email): bool
    {
        return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
    }
}
