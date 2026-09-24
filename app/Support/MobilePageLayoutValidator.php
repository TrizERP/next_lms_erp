<?php

namespace App\Support;

/**
 * Validates a Custom Mobile Page's layout_json before it is saved (draft or
 * publish) -- see MobilePageBuilderAdminApiController. Deliberately narrow:
 * it only rejects shapes that would break the Next.js renderer or point an
 * action/data binding somewhere unsafe. It never executes anything the JSON
 * describes -- an endpoint here is just a string the renderer later calls
 * through the SAME authenticated request path (and therefore the SAME
 * permission checks) every hand-coded page in lms_k12 already uses. See the
 * class doc on MobilePageBuilderAdminApiController for the full reasoning.
 *
 * A plain recursive function rather than Laravel FormRequest rules, matching
 * this codebase's dominant inline Validator::make() convention -- the shape
 * being validated (a component tree) does not fit flat "field => rule"
 * validation.
 */
class MobilePageLayoutValidator
{
    /**
     * Every component `type` the Next.js builder currently knows how to
     * render. Kept in lock-step with the block registry in
     * lms_k12/components/mobile-page-builder/blocks -- adding a component
     * type there requires adding it here too, which is intentional: an
     * unrecognised type must never reach a mobile user's screen as a blank
     * gap.
     */
    private const COMPONENT_TYPES = [
        'text', 'image', 'divider', 'spacer', 'input', 'button', 'container', 'card', 'list',
    ];

    private const CONTAINER_TYPES = ['container', 'card'];

    private const BACKGROUND_TYPES = ['color', 'image', 'gradient'];

    private const ACTION_TYPES = ['none', 'navigate', 'api'];

    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** Hard cap on the raw JSON, independent of PHP's own request size limits. */
    public const MAX_JSON_BYTES = 512000; // 500 KB

    /**
     * @return list<string> Empty when the layout is valid.
     */
    public static function validate(mixed $layout): array
    {
        $errors = [];

        if (! is_array($layout)) {
            return ['The layout must be a JSON object.'];
        }

        if (! isset($layout['page']) || ! is_array($layout['page'])) {
            $errors[] = 'The layout is missing its "page" section.';
        } else {
            self::validatePage($layout['page'], $errors);
        }

        if (! isset($layout['components']) || ! is_array($layout['components'])) {
            $errors[] = 'The layout is missing its "components" list.';
        } else {
            self::validateComponents($layout['components'], $errors, 'components');
        }

        return $errors;
    }

    private static function validatePage(array $page, array &$errors): void
    {
        if (! self::isNonEmptyString($page['name'] ?? null)) {
            $errors[] = 'page.name is required.';
        }

        foreach (['width', 'height'] as $dimension) {
            if (isset($page[$dimension]) && ! is_numeric($page[$dimension])) {
                $errors[] = "page.{$dimension} must be a number.";
            }
        }

        if (isset($page['background'])) {
            self::validateBackground($page['background'], $errors);
        }

        if (isset($page['dataSource'])) {
            self::validateDataSource($page['dataSource'], $errors, 'page.dataSource');
        }
    }

    private static function validateBackground(mixed $background, array &$errors): void
    {
        if (! is_array($background)) {
            $errors[] = 'page.background must be an object.';
            return;
        }

        $type = $background['type'] ?? null;
        if (! in_array($type, self::BACKGROUND_TYPES, true)) {
            $errors[] = 'page.background.type must be one of: ' . implode(', ', self::BACKGROUND_TYPES) . '.';
        }

        if (isset($background['opacity']) && (! is_numeric($background['opacity']) || $background['opacity'] < 0 || $background['opacity'] > 1)) {
            $errors[] = 'page.background.opacity must be a number between 0 and 1.';
        }

        if ($type === 'image' && isset($background['url']) && ! self::isSafeRelativeOrHttpUrl((string) $background['url'])) {
            $errors[] = 'page.background.url is not a valid image URL.';
        }
    }

    private static function validateDataSource(mixed $dataSource, array &$errors, string $path): void
    {
        if (! is_array($dataSource)) {
            $errors[] = "{$path} must be an object.";
            return;
        }

        $endpoint = $dataSource['endpoint'] ?? null;
        if ($endpoint !== null && ! self::isSafeRelativeEndpoint((string) $endpoint)) {
            $errors[] = "{$path}.endpoint must be a relative API path (no scheme, no host).";
        }
    }

    private static function validateComponents(array $components, array &$errors, string $path): void
    {
        foreach ($components as $index => $component) {
            $at = "{$path}[{$index}]";

            if (! is_array($component)) {
                $errors[] = "{$at} must be an object.";
                continue;
            }

            if (! self::isNonEmptyString($component['id'] ?? null)) {
                $errors[] = "{$at}.id is required.";
            }

            $type = $component['type'] ?? null;
            if (! in_array($type, self::COMPONENT_TYPES, true)) {
                $errors[] = "{$at}.type must be one of: " . implode(', ', self::COMPONENT_TYPES) . '.';
                $type = null;
            }

            if (isset($component['position'])) {
                // x/y from the editor's drag transform are always plain numbers.
                self::validateNumericPair($component['position'], $errors, "{$at}.position");
            }

            if (isset($component['size'])) {
                // width/height are NOT always plain numbers by the time a
                // save happens: OverlayWrapper (reused as-is from the
                // document-template editor -- see its own doc) writes back
                // "280px"-style CSS strings once a block has been resized,
                // and several blocks default to the string "auto". Only
                // position is guaranteed numeric; size just needs to be a
                // valid CSS length the renderer can use directly.
                self::validateSizePair($component['size'], $errors, "{$at}.size");
            }

            $props = $component['props'] ?? [];
            if (isset($component['props']) && ! is_array($props)) {
                $errors[] = "{$at}.props must be an object.";
                $props = [];
            }

            if (isset($props['dataBinding'])) {
                self::validateDataBinding($props['dataBinding'], $errors, "{$at}.props.dataBinding");
            }

            if ($type === 'button' && isset($props['action'])) {
                self::validateAction($props['action'], $errors, "{$at}.props.action");
            }

            if ($type === 'list') {
                self::validateListProps($props, $errors, "{$at}.props");
            }

            if ($type !== null && in_array($type, self::CONTAINER_TYPES, true) && isset($component['children'])) {
                if (! is_array($component['children'])) {
                    $errors[] = "{$at}.children must be a list.";
                } else {
                    self::validateComponents($component['children'], $errors, "{$at}.children");
                }
            } elseif (isset($component['children']) && $component['children'] !== []) {
                $errors[] = "{$at} does not support children (only container/card do).";
            }
        }
    }

    private static function validateNumericPair(mixed $pair, array &$errors, string $path): void
    {
        if (! is_array($pair)) {
            $errors[] = "{$path} must be an object.";
            return;
        }

        foreach ($pair as $key => $value) {
            if (! is_numeric($value)) {
                $errors[] = "{$path}.{$key} must be a number.";
            }
        }
    }

    /** A number, a numeric string, a CSS length ("280px", "50%"), or "auto". */
    private static function isValidSizeValue(mixed $value): bool
    {
        if (is_numeric($value)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return $value === 'auto' || preg_match('/^\d+(\.\d+)?(px|%)$/', $value) === 1;
    }

    private static function validateSizePair(mixed $pair, array &$errors, string $path): void
    {
        if (! is_array($pair)) {
            $errors[] = "{$path} must be an object.";
            return;
        }

        foreach ($pair as $key => $value) {
            if (! self::isValidSizeValue($value)) {
                $errors[] = "{$path}.{$key} must be a number, a CSS length like \"280px\", or \"auto\".";
            }
        }
    }

    private static function validateDataBinding(mixed $binding, array &$errors, string $path): void
    {
        if (! is_array($binding)) {
            $errors[] = "{$path} must be an object.";
            return;
        }

        if (isset($binding['field']) && (! is_string($binding['field']) || $binding['field'] === '' || strlen($binding['field']) > 200)) {
            $errors[] = "{$path}.field must be a short, non-empty string.";
        }
    }

    private static function validateAction(mixed $action, array &$errors, string $path): void
    {
        if (! is_array($action)) {
            $errors[] = "{$path} must be an object.";
            return;
        }

        $type = $action['type'] ?? 'none';
        if (! in_array($type, self::ACTION_TYPES, true)) {
            $errors[] = "{$path}.type must be one of: " . implode(', ', self::ACTION_TYPES) . '.';
            return;
        }

        if ($type !== 'api') {
            return;
        }

        $method = strtoupper((string) ($action['method'] ?? ''));
        if (! in_array($method, self::HTTP_METHODS, true)) {
            $errors[] = "{$path}.method must be one of: " . implode(', ', self::HTTP_METHODS) . '.';
        }

        $endpoint = (string) ($action['endpoint'] ?? '');
        if (! self::isSafeRelativeEndpoint($endpoint)) {
            $errors[] = "{$path}.endpoint must be a relative API path (no scheme, no host).";
        }

        if (isset($action['body']) && ! is_array($action['body'])) {
            $errors[] = "{$path}.body must be an object mapping field names to values or {{tokens}}.";
        }
    }

    /**
     * A List component's own search/submit config -- see MobileListBlock.tsx
     * (Next.js) for the full shape this mirrors. Validated at the same
     * "reject unsafe, don't police every nested detail" depth as everything
     * else here: searchFields/rowKeys content (labels, which item field
     * feeds which row key) is opaque config the renderer trusts, exactly
     * like an Input's own `options`/`optionsSource` already are -- only the
     * two things that could otherwise call somewhere unsafe (the search and
     * submit endpoints) are actually checked.
     */
    private static function validateListProps(array $props, array &$errors, string $path): void
    {
        if (isset($props['searchAction']) && $props['searchAction'] !== null) {
            self::validateHttpTarget($props['searchAction'], $errors, "{$path}.searchAction");
        }

        if (isset($props['submitAction']) && $props['submitAction'] !== null) {
            $submit = $props['submitAction'];
            if (! is_array($submit)) {
                $errors[] = "{$path}.submitAction must be an object.";
            } else {
                self::validateHttpTarget($submit, $errors, "{$path}.submitAction");
                if (isset($submit['rowKeys']) && ! is_array($submit['rowKeys'])) {
                    $errors[] = "{$path}.submitAction.rowKeys must be a list.";
                }
                if (isset($submit['extraBody']) && ! is_array($submit['extraBody'])) {
                    $errors[] = "{$path}.submitAction.extraBody must be an object.";
                }
            }
        }

        if (isset($props['searchFields']) && ! is_array($props['searchFields'])) {
            $errors[] = "{$path}.searchFields must be a list.";
        }
    }

    private static function validateHttpTarget(mixed $target, array &$errors, string $path): void
    {
        if (! is_array($target)) {
            $errors[] = "{$path} must be an object.";
            return;
        }

        $endpoint = (string) ($target['endpoint'] ?? '');
        if ($endpoint !== '' && ! self::isSafeRelativeEndpoint($endpoint)) {
            $errors[] = "{$path}.endpoint must be a relative API path (no scheme, no host).";
        }

        if (isset($target['method'])) {
            $method = strtoupper((string) $target['method']);
            if (! in_array($method, self::HTTP_METHODS, true)) {
                $errors[] = "{$path}.method must be one of: " . implode(', ', self::HTTP_METHODS) . '.';
            }
        }
    }

    private static function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** No scheme, no protocol-relative host -- resolved only ever against this app's own API base by the caller. */
    private static function isSafeRelativeEndpoint(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 300) {
            return false;
        }

        if (preg_match('#^[/\\\\]{2}#', $value)) {
            return false;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
            return false;
        }

        return true;
    }

    /** Background/asset image URLs may be absolute (uploaded, on the CDN disk) or relative. */
    private static function isSafeRelativeOrHttpUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 2000) {
            return false;
        }

        if (preg_match('#^https?://#i', $value)) {
            return true;
        }

        return self::isSafeRelativeEndpoint($value);
    }
}
