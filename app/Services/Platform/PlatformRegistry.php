<?php

namespace App\Services\Platform;

use InvalidArgumentException;

/**
 * Reads config/platform_services.php and answers every question the three
 * platform services ask of it.
 *
 * WHY THE CONTROLLERS DO NOT TOUCH config() DIRECTLY
 * Two reasons, both about the contract holding. First, every key that arrives
 * from a client has to be checked against the registry before it is written, and
 * a check that is spelled out in three controllers is a check that will be
 * spelled differently in three controllers. Second, the frontend renders from the
 * SAME registry this validates against — served by RegistryController — so there
 * is exactly one list of modules in the product and no screen can offer a setting
 * the API would refuse.
 *
 * THE ADDRESS IS THE STRUCTURE. `fees` is a module, `fees.collection` a
 * component, `fees.collection.payment_received` an item on that component. The
 * prefix is the whole relationship, which is why moduleOf() and componentOf() are
 * string operations and not lookups: there is no join to get wrong.
 *
 * IT VALIDATES ITSELF. A component whose key does not start with a declared
 * module, or an item whose key does not start with a declared component, is a
 * configuration mistake that would otherwise surface as a screen quietly missing
 * a row. assertConsistent() turns it into a loud failure instead, and the
 * registry endpoint runs it.
 */
class PlatformRegistry
{
    /** The three services, and the RBAC key each asks about. */
    public const SERVICES = ['notification', 'scheduler', 'workflow'];

    /**
     * The RBAC module key for a service.
     *
     * Deliberately one key per service rather than one per business module: the
     * question these screens ask is "may this person change how the whole
     * institute is notified", which is an administrator's right, not a Fees
     * clerk's. Registered in config/rbac_modules.php; an unregistered key answers
     * deny-all, which is the correct failure.
     */
    public static function rbacKey(string $service): string
    {
        if (! in_array($service, self::SERVICES, true)) {
            throw new InvalidArgumentException("Unknown platform service: {$service}");
        }

        return "platform.{$service}";
    }

    // ── Raw config ──────────────────────────────────────────────────────────

    public function channels(): array
    {
        return (array) config('platform_services.channels', []);
    }

    public function modules(): array
    {
        return (array) config('platform_services.modules', []);
    }

    public function components(): array
    {
        return (array) config('platform_services.components', []);
    }

    public function notifications(): array
    {
        return (array) config('platform_services.notifications', []);
    }

    public function tasks(): array
    {
        return (array) config('platform_services.tasks', []);
    }

    public function workflowPoints(): array
    {
        return (array) config('platform_services.workflows', []);
    }

    public function approverTypes(): array
    {
        return (array) config('platform_services.approver_types', []);
    }

    public function escalationActions(): array
    {
        return (array) config('platform_services.escalation_actions', []);
    }

    // ── Address arithmetic ──────────────────────────────────────────────────

    /** `fees.collection.payment_received` is owned by `fees`. */
    public static function moduleOf(string $key): string
    {
        return explode('.', $key)[0] ?? '';
    }

    /** `fees.collection.payment_received` sits on `fees.collection`. */
    public static function componentOf(string $itemKey): string
    {
        $parts = explode('.', $itemKey);

        return count($parts) >= 2 ? $parts[0].'.'.$parts[1] : $itemKey;
    }

    // ── Existence ───────────────────────────────────────────────────────────

    public function hasModule(?string $key): bool
    {
        return $key !== null && $key !== '' && array_key_exists($key, $this->modules());
    }

    public function hasComponent(?string $key): bool
    {
        return $key !== null && $key !== '' && array_key_exists($key, $this->components());
    }

    public function notification(string $key): ?array
    {
        return $this->notifications()[$key] ?? null;
    }

    public function task(string $key): ?array
    {
        return $this->tasks()[$key] ?? null;
    }

    public function workflowPoint(string $key): ?array
    {
        return $this->workflowPoints()[$key] ?? null;
    }

    public function hasChannel(?string $key): bool
    {
        return $key !== null && $key !== '' && array_key_exists($key, $this->channels());
    }

    /** Channel keys in the order the screen shows its columns. */
    public function channelKeys(): array
    {
        return array_keys($this->channels());
    }

    // ── Filtering ───────────────────────────────────────────────────────────

    /**
     * Filter any of the three item lists to a module or a single component.
     *
     * One method for all three because the address format is the same in each —
     * the payoff of a shared registry, not a coincidence worth writing out three
     * times.
     *
     * @param  array<string,array>  $items  keyed by item key
     * @return array<string,array>
     */
    public function scope(array $items, ?string $module = null, ?string $component = null): array
    {
        if ($module === null && $component === null) {
            return $items;
        }

        return array_filter($items, function ($ignored, string $key) use ($module, $component) {
            $itemComponent = self::componentOf($key);

            if ($component !== null && $component !== '' && $itemComponent !== $component) {
                return false;
            }

            if ($module !== null && $module !== '' && self::moduleOf($itemComponent) !== $module) {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /** Components belonging to one module, keys preserved. */
    public function componentsOfModule(string $module): array
    {
        return array_filter(
            $this->components(),
            fn ($ignored, string $key) => self::moduleOf($key) === $module,
            ARRAY_FILTER_USE_BOTH
        );
    }

    // ── Defaults ────────────────────────────────────────────────────────────

    /**
     * Expand a notification's shorthand defaults into the stored shape.
     *
     * The config writes 'on' | 'off' | 'locked' per channel because a table of
     * sixty events is unreadable when each cell is a two-key array. A channel
     * left out is off and unlocked.
     *
     * @return array<string,array{enabled:bool,locked:bool}>
     */
    public function defaultChannelsFor(string $eventKey): array
    {
        $event = $this->notification($eventKey);
        $shorthand = (array) ($event['defaults'] ?? []);

        $expanded = [];
        foreach ($this->channelKeys() as $channel) {
            $setting = $shorthand[$channel] ?? 'off';
            $expanded[$channel] = [
                'enabled' => $setting === 'on' || $setting === 'locked',
                'locked' => $setting === 'locked',
            ];
        }

        return $expanded;
    }

    /**
     * The default schedule for a task, with every field present.
     *
     * @return array{minute:string,hour:string,day:string,month:string,day_of_week:string}
     */
    public function defaultScheduleFor(string $taskKey): array
    {
        $schedule = (array) ($this->task($taskKey)['schedule'] ?? []);

        return [
            'minute' => (string) ($schedule['minute'] ?? '0'),
            'hour' => (string) ($schedule['hour'] ?? '0'),
            'day' => (string) ($schedule['day'] ?? '*'),
            'month' => (string) ($schedule['month'] ?? '*'),
            'day_of_week' => (string) ($schedule['day_of_week'] ?? '*'),
        ];
    }

    // ── The payload the frontend renders from ───────────────────────────────

    /**
     * The whole registry, shaped as lists with their keys inlined.
     *
     * Lists rather than objects because the frontend renders them in this order
     * and JSON objects have no guaranteed one; `key` is inlined so a row carries
     * its own address once it is out of the map.
     */
    public function payload(): array
    {
        $inline = static function (array $items, string $keyName = 'key'): array {
            $rows = [];
            foreach ($items as $key => $row) {
                $rows[] = array_merge([$keyName => $key], (array) $row);
            }

            return $rows;
        };

        $modules = [];
        foreach ($this->modules() as $key => $row) {
            $modules[] = array_merge(['key' => $key], (array) $row, [
                // What the module picker shows under each name, so an
                // administrator can see where the configuration actually is.
                'counts' => [
                    'components' => count($this->componentsOfModule($key)),
                    'notifications' => count($this->scope($this->notifications(), $key)),
                    'tasks' => count($this->scope($this->tasks(), $key)),
                    'workflows' => count($this->scope($this->workflowPoints(), $key)),
                ],
            ]);
        }

        $components = [];
        foreach ($this->components() as $key => $row) {
            $components[] = array_merge(['key' => $key, 'module' => self::moduleOf($key)], (array) $row);
        }

        $notifications = [];
        foreach ($this->notifications() as $key => $row) {
            $component = self::componentOf($key);
            $notifications[] = array_merge(['key' => $key, 'module' => self::moduleOf($key), 'component' => $component], (array) $row, [
                'mandatory' => (bool) ($row['mandatory'] ?? false),
                'default_channels' => $this->defaultChannelsFor($key),
            ]);
        }

        $tasks = [];
        foreach ($this->tasks() as $key => $row) {
            $tasks[] = array_merge(['key' => $key, 'module' => self::moduleOf($key), 'component' => self::componentOf($key)], (array) $row, [
                'schedule' => $this->defaultScheduleFor($key),
                'disabled_by_default' => (bool) ($row['disabled_by_default'] ?? false),
            ]);
        }

        $workflows = [];
        foreach ($this->workflowPoints() as $key => $row) {
            $workflows[] = array_merge(['key' => $key, 'module' => self::moduleOf($key), 'component' => self::componentOf($key)], (array) $row);
        }

        return [
            'channels' => $inline($this->channels(), 'key'),
            'modules' => $modules,
            'components' => $components,
            'notifications' => $notifications,
            'tasks' => $tasks,
            'workflows' => $workflows,
            'approver_types' => $inline($this->approverTypes(), 'key'),
            'escalation_actions' => $inline($this->escalationActions(), 'key'),
        ];
    }

    // ── Self-check ──────────────────────────────────────────────────────────

    /**
     * Every problem with the registry as configured, as readable sentences.
     *
     * An orphan would otherwise show up as a row silently missing from a screen,
     * which is the kind of bug that survives for months. Reported rather than
     * thrown so the registry endpoint can serve what is valid AND say what is
     * wrong — a single typo should not black out a working screen.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach (array_keys($this->components()) as $key) {
            $module = self::moduleOf($key);
            if (! $this->hasModule($module)) {
                $problems[] = "Component \"{$key}\" names module \"{$module}\", which is not declared.";
            }
        }

        $lists = [
            'Notification' => $this->notifications(),
            'Task' => $this->tasks(),
            'Workflow point' => $this->workflowPoints(),
        ];

        foreach ($lists as $label => $items) {
            foreach (array_keys($items) as $key) {
                $component = self::componentOf($key);
                if (! $this->hasComponent($component)) {
                    $problems[] = "{$label} \"{$key}\" names component \"{$component}\", which is not declared.";
                }
                if (substr_count($key, '.') < 2) {
                    $problems[] = "{$label} \"{$key}\" is not a module.component.item key.";
                }
            }
        }

        foreach ($this->notifications() as $key => $row) {
            foreach (array_keys((array) ($row['defaults'] ?? [])) as $channel) {
                if (! $this->hasChannel($channel)) {
                    $problems[] = "Notification \"{$key}\" names channel \"{$channel}\", which is not declared.";
                }
            }
        }

        foreach ($this->workflowPoints() as $key => $row) {
            foreach ((array) ($row['suggested_steps'] ?? []) as $index => $step) {
                $type = $step['approver_type'] ?? '';
                if (! array_key_exists($type, $this->approverTypes())) {
                    $problems[] = "Workflow point \"{$key}\" step ".($index + 1)." names approver type \"{$type}\", which is not declared.";
                }
            }
        }

        return $problems;
    }
}
