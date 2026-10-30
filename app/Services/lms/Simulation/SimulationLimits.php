<?php

namespace App\Services\lms\Simulation;

/**
 * The limits of Simulation Learning, as DATA: what GET /api/lms/prayogshala/simulations/limits tells the authoring screen, so the
 * screen can refuse a too-big file or an over-long list before it uploads anything, and so there is one place to read what the platform holds an
 * author to.
 *
 * Nothing here is a second copy of a number: what this server itself enforces is read from the class that enforces it (SimulationStore for the
 * specification, GlbInspector for a model's contents, SimulationAssetService for the quota); only what the RUNTIME enforces and this server merely
 * reports (objects in a scene, physics bodies and steps ...) is written here, and the runtime enforces the rest.
 */
final class SimulationLimits
{
    /** Uploads one person may make in a minute (the throttle on POST /lms/prayogshala/simulations/assets). */
    public const UPLOADS_PER_MINUTE = 30;

    /** Enforced by the runtime, which plays the specification; reported here so the screen can show them. */
    public const OBJECTS = 48;
    public const BODIES = 24;
    public const JOINTS = 16;
    public const FORCES = 16;
    public const WORLD_EVENTS = 8;
    public const PHYSICS_STEPS = 60000;

    /**
     * @return array{schemas: list<string>, engines: list<string>, spec: array<string, int>, asset: array<string, mixed>, quota: array<string, int>}
     */
    public static function data(): array
    {
        return [
            'schemas' => SimulationStore::SCHEMAS,
            'engines' => SimulationStore::ENGINES,
            'spec' => [
                'bytes' => SimulationStore::MAX_SPEC_BYTES,
                'jsonDepth' => SimulationStore::JSON_DEPTH,
                'knobs' => SimulationStore::MAX_KNOBS,
                'knobsDes' => SimulationStore::MAX_KNOBS_NETWORK,
                'outputs' => SimulationStore::MAX_OUTPUTS,
                'missions' => SimulationStore::MAX_MISSIONS,
                'assets' => SimulationStore::MAX_ASSET_MODELS,
                'objects' => self::OBJECTS,
                'steps' => SimulationStore::MAX_STEPS,
                'views' => SimulationStore::MAX_VIEWS,
                'viewsDes' => SimulationStore::MAX_VIEWS_DES,
                'bodies' => self::BODIES,
                'joints' => self::JOINTS,
                'forces' => self::FORCES,
                'worldEvents' => self::WORLD_EVENTS,
                'physicsSteps' => self::PHYSICS_STEPS,
            ],
            'asset' => [
                'kinds' => SimulationStore::ASSET_KINDS,
                'modelBytes' => SimulationStore::MAX_ASSET_BYTES,
                'imageBytes' => SimulationStore::MAX_IMAGE_BYTES,
                'hdrBytes' => SimulationStore::MAX_HDR_BYTES,
                'hdrSide' => GlbInspector::MAX_HDR_SIDE,
                'triangles' => GlbInspector::MAX_TRIANGLES,
                'textureSide' => GlbInspector::MAX_TEXTURE_SIDE,
                'decodedBytes' => GlbInspector::MAX_DECODED_BYTES,
                'imageSide' => GlbInspector::MAX_IMAGE_SIDE,
                'jsonBytes' => GlbInspector::MAX_JSON_BYTES,
            ],
            'quota' => [
                'files' => SimulationAssetService::MAX_FILES,
                'bytes' => SimulationAssetService::MAX_BYTES,
                'uploadsPerMinute' => self::UPLOADS_PER_MINUTE,
            ],
        ];
    }

    /**
     * The largest request this server will take, in bytes: the smaller of PHP's `upload_max_filesize` and `post_max_size`, or null when
     * neither limits it. A file over this never reaches the controller (PHP drops it), so the screen is told in advance.
     */
    public static function serverUploadBytes(): ?int
    {
        $limits = array_filter([self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size'))], fn ($b) => $b > 0);

        return $limits === [] ? null : min($limits);
    }

    /** A php.ini size ("2M", "128K", "1G", "8388608") in bytes; 0 or -1 mean no limit. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^(-?\d+)\s*([KMG]?)B?$/i', $value, $m)) {
            return 0;
        }

        return (int) $m[1] * match (strtoupper($m[2])) {
            'K' => 1024,
            'M' => 1048576,
            'G' => 1073741824,
            default => 1,
        };
    }
}
