<?php

namespace App\Http\Controllers\api\Platform;

use App\Models\Platform\PlatformNotificationChannel;
use App\Models\Platform\PlatformNotificationPreference;
use App\Services\Platform\PlatformRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Communication service — which notification each component raises, and on
 * which channels.
 *
 * TWO LEVELS, AND THEY ARE NOT THE SAME QUESTION.
 *
 *   1. The channel switches are institute-wide plumbing. WhatsApp off means no
 *      module sends WhatsApp, whatever any component's row says. This is the
 *      switch a school reaches for when the SMS credit runs out.
 *   2. The matrix is per component: for each notification, whether it is raised
 *      at all, and per channel whether it is on and whether the recipient may
 *      change it.
 *
 * `locked` is what makes this a central service rather than a defaults screen.
 * Enabled decides what a parent gets by default; locked decides whether they may
 * opt out. A fee receipt is locked on for email because a school cannot allow
 * somebody to opt out of the record of money they paid.
 *
 * DEFAULTS ARE MERGED, NOT SEEDED. A notification a school has never touched has
 * no row in the database; the registry answers for it and `customised` says so.
 * That is what lets a better default reach every school that never disagreed.
 */
class NotificationController extends PlatformController
{
    /**
     * GET /api/platform/notifications?module=&component=
     *
     * Every notification in scope, each joined to what this institute saved, plus
     * the channel switches and a summary of what is actually reachable.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        [$module, $component] = $this->readScope($request);

        $channels = $this->channelsFor($tenantId);
        $events = $this->eventsFor($tenantId, $module, $component);

        return $this->ok([
            'channels' => array_values($channels),
            'events' => $events,
            'summary' => $this->summarise($events, $channels),
        ]);
    }

    /**
     * PUT /api/platform/notifications
     *
     * Body: {"changes":[{"event_key":"fees.receipt.issued","enabled":true,
     *        "channels":{"sms":{"enabled":true,"locked":false}}}]}
     *
     * TAKES A LIST because the screen has one Save changes button over a whole
     * module — an operator toggles fifteen things and presses save once. Every
     * change is validated before any is written and the lot goes in one
     * transaction, so a typo in the fifteenth cannot leave the first fourteen
     * saved and the operator guessing which took effect.
     *
     * A PARTIAL CHANGE IS PARTIAL. A body naming only `sms` leaves the other four
     * channels exactly as they were; anything else would silently undo whatever
     * somebody changed between two page loads.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $changes = $request->input('changes');
        if (! is_array($changes) || $changes === []) {
            return $this->fail('Send at least one notification to change.');
        }
        if (count($changes) > 500) {
            return $this->fail('Change at most 500 notifications at a time.');
        }

        $existing = PlatformNotificationPreference::forTenant($tenantId)->get()->keyBy('event_key');
        $actor = $this->actorLabel($request);
        $channelKeys = $this->registry->channelKeys();

        // Validate everything first — see the docblock.
        $prepared = [];
        foreach ($changes as $index => $change) {
            if (! is_array($change)) {
                return $this->fail('Change '.($index + 1).' is not an object.');
            }

            $eventKey = trim((string) ($change['event_key'] ?? ''));
            $definition = $this->registry->notification($eventKey);
            if ($definition === null) {
                return $this->fail('"'.($eventKey ?: '(none)').'" is not a known notification.', 404);
            }

            $current = $existing->get($eventKey);
            $enabled = $current ? (bool) $current->enabled : true;
            $currentChannels = $current
                ? $this->normaliseChannels((array) $current->channels, $channelKeys)
                : $this->registry->defaultChannelsFor($eventKey);

            if (array_key_exists('enabled', $change)) {
                if (! is_bool($change['enabled'])) {
                    return $this->fail("{$eventKey}: enabled must be true or false.");
                }
                if ($change['enabled'] === false && ($definition['mandatory'] ?? false)) {
                    return $this->fail(
                        '"'.$definition['label'].'" is required and cannot be switched off. You can still choose its channels.'
                    );
                }
                $enabled = $change['enabled'];
            }

            foreach ((array) ($change['channels'] ?? []) as $channel => $patch) {
                if (! $this->registry->hasChannel((string) $channel)) {
                    return $this->fail("\"{$channel}\" is not a delivery channel.");
                }
                if (! is_array($patch)) {
                    return $this->fail("{$eventKey}: the setting for {$channel} is not an object.");
                }
                foreach (['enabled', 'locked'] as $flag) {
                    if (! array_key_exists($flag, $patch)) {
                        continue;
                    }
                    if (! is_bool($patch[$flag])) {
                        return $this->fail("{$eventKey}: {$channel} {$flag} must be true or false.");
                    }
                    $currentChannels[$channel][$flag] = $patch[$flag];
                }
            }

            $prepared[] = [
                'event_key' => $eventKey,
                'module' => PlatformRegistry::moduleOf($eventKey),
                'component' => PlatformRegistry::componentOf($eventKey),
                'enabled' => $enabled,
                'channels' => $currentChannels,
                'updated_by' => $actor,
            ];
        }

        DB::transaction(function () use ($prepared, $tenantId) {
            foreach ($prepared as $row) {
                PlatformNotificationPreference::updateOrCreate(
                    ['sub_institute_id' => $tenantId, 'event_key' => $row['event_key']],
                    $row
                );
            }
        });

        // Return the whole scope the caller was looking at, not just what
        // changed: the summary at the top of the screen depends on rows the
        // operator did not touch, and a screen that recomputes it from a partial
        // reply shows a number that is quietly wrong.
        [$module, $component] = $this->readScope($request);
        $channels = $this->channelsFor($tenantId);
        $events = $this->eventsFor($tenantId, $module, $component);

        return $this->ok([
            'channels' => array_values($channels),
            'events' => $events,
            'summary' => $this->summarise($events, $channels),
            'saved' => count($prepared),
        ]);
    }

    /**
     * PUT /api/platform/notifications/channels
     *
     * Body: {"channel":"sms","enabled":false}
     *
     * One switch at a time, because this is the highest-blast-radius control on
     * the screen and a bulk endpoint invites a body that turns three channels off
     * by accident.
     */
    public function updateChannel(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $channel = trim((string) $request->input('channel', ''));
        if (! $this->registry->hasChannel($channel)) {
            return $this->fail('"'.($channel ?: '(none)').'" is not a delivery channel.', 404);
        }

        $enabled = $request->input('enabled');
        if (! is_bool($enabled)) {
            return $this->fail('enabled must be true or false.');
        }

        PlatformNotificationChannel::updateOrCreate(
            ['sub_institute_id' => $tenantId, 'channel' => $channel],
            ['enabled' => $enabled, 'updated_by' => $this->actorLabel($request)]
        );

        return $this->ok(['channels' => array_values($this->channelsFor($tenantId))]);
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    /**
     * The institute's channel switches: registry defaults with its overrides on
     * top.
     *
     * Keyed by channel so summarise() can ask about one without a search.
     *
     * @return array<string,array<string,mixed>>
     */
    private function channelsFor(int $tenantId): array
    {
        $overrides = PlatformNotificationChannel::forTenant($tenantId)
            ->pluck('enabled', 'channel');

        $rows = [];
        foreach ($this->registry->channels() as $key => $definition) {
            $rows[$key] = [
                'key' => $key,
                'label' => $definition['label'] ?? $key,
                'description' => $definition['description'] ?? '',
                'needs_credentials' => (bool) ($definition['needs_credentials'] ?? false),
                'enabled' => $overrides->has($key)
                    ? (bool) $overrides->get($key)
                    : (bool) ($definition['enabled'] ?? false),
                'customised' => $overrides->has($key),
            ];
        }

        return $rows;
    }

    /**
     * Every notification in scope, joined to what this institute saved.
     *
     * @return list<array<string,mixed>>
     */
    private function eventsFor(int $tenantId, ?string $module, ?string $component): array
    {
        $saved = PlatformNotificationPreference::forTenant($tenantId)
            ->forModule($module)
            ->forComponent($component)
            ->get()
            ->keyBy('event_key');

        $channelKeys = $this->registry->channelKeys();
        $definitions = $this->registry->scope($this->registry->notifications(), $module, $component);

        $rows = [];
        foreach ($definitions as $key => $definition) {
            $row = $saved->get($key);
            $componentKey = PlatformRegistry::componentOf($key);

            $rows[] = [
                'key' => $key,
                'module' => PlatformRegistry::moduleOf($key),
                'component' => $componentKey,
                'component_label' => $this->registry->components()[$componentKey]['label'] ?? $componentKey,
                'label' => $definition['label'] ?? $key,
                'description' => $definition['description'] ?? '',
                'audience' => array_values((array) ($definition['audience'] ?? [])),
                'mandatory' => (bool) ($definition['mandatory'] ?? false),
                'enabled' => $row ? (bool) $row->enabled : true,
                'channels' => $row
                    ? $this->normaliseChannels((array) $row->channels, $channelKeys)
                    : $this->registry->defaultChannelsFor($key),
                'customised' => (bool) $row,
                'updated_at' => $row?->updated_at?->toIso8601String(),
                'updated_by' => $row?->updated_by,
            ];
        }

        return $rows;
    }

    /**
     * Fill in any channel a stored row predates.
     *
     * A row saved before a channel was added to the registry has no entry for it,
     * and a screen reading `channels.telegram.enabled` off that row would break.
     * Missing means off and unlocked — the safe reading for a channel nobody has
     * ever made a decision about.
     *
     * @param  list<string>  $channelKeys
     * @return array<string,array{enabled:bool,locked:bool}>
     */
    private function normaliseChannels(array $stored, array $channelKeys): array
    {
        $result = [];
        foreach ($channelKeys as $channel) {
            $entry = $stored[$channel] ?? [];
            $result[$channel] = [
                'enabled' => (bool) ($entry['enabled'] ?? false),
                'locked' => (bool) ($entry['locked'] ?? false),
            ];
        }

        return $result;
    }

    /**
     * How many notifications are on, and how many actually reach anybody.
     *
     * REACHABLE is the number that matters and the one nothing else on the screen
     * shows: an event that is on, with at least one channel on, where that
     * channel is also switched on for the institute. A row that is on but whose
     * only channel is a switched-off WhatsApp sends nothing, and an administrator
     * should be able to see that without opening five screens.
     *
     * @param  list<array<string,mixed>>  $events
     * @param  array<string,array<string,mixed>>  $channels
     */
    private function summarise(array $events, array $channels): array
    {
        $live = [];
        foreach ($channels as $key => $channel) {
            if ($channel['enabled']) {
                $live[$key] = true;
            }
        }

        $enabled = 0;
        $reachable = 0;
        $locked = 0;
        $customised = 0;

        foreach ($events as $event) {
            if ($event['customised']) {
                $customised++;
            }
            if (! $event['enabled']) {
                continue;
            }
            $enabled++;

            $hasLive = false;
            $hasLocked = false;
            foreach ($event['channels'] as $channel => $setting) {
                if ($setting['enabled'] && isset($live[$channel])) {
                    $hasLive = true;
                }
                if ($setting['locked']) {
                    $hasLocked = true;
                }
            }
            if ($hasLive) {
                $reachable++;
            }
            if ($hasLocked) {
                $locked++;
            }
        }

        return [
            'total' => count($events),
            'enabled' => $enabled,
            'reachable' => $reachable,
            'locked' => $locked,
            'customised' => $customised,
        ];
    }
}
