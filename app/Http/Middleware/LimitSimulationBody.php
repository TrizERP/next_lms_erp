<?php

namespace App\Http\Middleware;

use App\Services\lms\Simulation\SimulationStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request body far over what a simulation can be BEFORE anything decodes it.
 *
 * The framework's own middleware (TrimStrings, ConvertEmptyStringsToNull) decodes a JSON body into PHP arrays, which takes about a hundred times the
 * size of the body in memory, so this stands in front of them in the GLOBAL stack: a specification is at most SimulationStore::MAX_SPEC_BYTES, and
 * the other fields of the call are small. Only the calls that carry a specification are looked at (create, and a new version). An asset upload is
 * a multipart file with limits of its own, and the other calls under this prefix have tiny bodies.
 */
class LimitSimulationBody
{
    /** The room the fields around the specification may take. */
    private const SLACK = 100000;

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT'], true)
            && $request->is('api/lms/prayogshala/simulations', 'api/lms/prayogshala/simulations/*')
            && ! $request->is('api/lms/prayogshala/simulations/assets')) {
            $limit = SimulationStore::MAX_SPEC_BYTES + self::SLACK;
            $length = (int) $request->server('CONTENT_LENGTH');   // 0 when the body comes in chunks: then the body itself is measured (it is not decoded)
            if ($length > $limit || ($length === 0 && strlen($request->getContent()) > $limit)) {
                $problems = ['The request is larger than a simulation may be: the specification is at most 1 MB.'];

                return response()->json(['success' => false, 'data' => null, 'message' => $problems[0], 'errors' => ['spec' => $problems], 'problems' => $problems], 413);
            }
        }

        return $next($request);
    }
}
