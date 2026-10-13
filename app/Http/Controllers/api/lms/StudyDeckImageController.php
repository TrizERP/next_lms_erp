<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves one stored study-deck picture: GET /api/study-deck/images/{id}?t=<school>&expires=...&signature=...
 *
 * Authorization is the signature (route middleware `signed:relative`): it is issued by StudyDeckImageUrls to a
 * caller that already passed the deck's school check, and covers the picture id and the school `t`. This
 * controller then enforces tenant ownership itself, because a signature only proves who the address was issued
 * to, not that the picture still belongs to them:
 *   404  there is no such picture
 *   403  the picture exists but is not this school's (nor the platform library's, for a school that has the LMS)
 *
 * The response is the picture's own bytes (never Base64 in JSON) with its stored type, an ETag that is the picture's
 * SHA-256 (so a repeat is a 304 with no body and no database read of the picture), and a private cache lifetime.
 * Large pictures are read from the database in slices, so memory stays at one slice.
 */
class StudyDeckImageController extends Controller
{
    private const CACHE_SECONDS = 86400;

    public function show(Request $request, int $id): Response
    {
        $images = new StudyDeckImages();
        $tenant = (int) $request->query('t', 0);

        $meta = $images->meta($id);
        if ($meta === null) {
            abort(404, 'This picture does not exist.');
        }
        if ($tenant < 1 || !in_array($meta['tenant'], $images->visibleTenants($tenant), true)) {
            abort(403, 'This picture is not available to your school.');
        }

        $etag = '"' . $meta['sha256'] . '"';
        $headers = [
            'Content-Type' => $meta['mime'],
            'ETag' => $etag,
            'Cache-Control' => 'private, max-age=' . self::CACHE_SECONDS,
            'Content-Disposition' => 'inline; filename="study-deck-image-' . $meta['id'] . '.' . ($meta['format'] === 'jpeg' ? 'jpg' : $meta['format']) . '"',
            'X-Content-Type-Options' => 'nosniff',
            // The player is on another origin and shows the picture in an <img>.
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ];

        $sent = array_map(fn ($v) => preg_replace('/^W\//', '', $v), $request->getETags());
        if (in_array($etag, $sent, true) || in_array('*', $sent, true)) {
            return response('', 304, $headers);
        }

        return response()->stream(function () use ($images, $meta): void {
            foreach ($images->chunks($meta['id'], $meta['bytes']) as $piece) {
                echo $piece;
            }
        }, 200, $headers + ['Content-Length' => (string) $meta['bytes']]);
    }
}
