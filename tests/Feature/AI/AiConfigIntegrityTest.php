<?php

namespace Tests\Feature\AI;

use Tests\TestCase;

/**
 * `config/ai.php` says what it looks like it says.
 *
 * WHY THIS EXISTS
 *
 * A duplicate key in a PHP array literal is not an error. The last one wins and the rest
 * are discarded silently, so a block can be present in the file, readable, commented, and
 * have no effect whatever.
 *
 * That happened. `lifecycle.modules` already carried a one-line `'user'` stub binding a
 * single tool; a full `'user'` block was added 110 lines earlier, and PHP kept the stub.
 * The Users module bound one tool instead of five, its own data tool was unreachable, and
 * nothing anywhere reported a problem — `config()` returned a perfectly valid array that
 * simply was not the one in front of you.
 *
 * The file is 1,700 lines with two large keyed arrays in it, so this will happen again
 * unless something checks. It reads the SOURCE rather than the loaded config, because by
 * the time the config is loaded the evidence is gone.
 *
 * NO DATABASE. It parses a file.
 */
class AiConfigIntegrityTest extends TestCase
{
    /**
     * The two arrays that are keyed by module and long enough to collide in.
     *
     * Both live at the same indentation inside `lifecycle`, so the parse below tracks
     * which one it is in rather than matching on indentation alone.
     *
     * @var array<int, string>
     */
    private const KEYED_BLOCKS = ['modules', 'module_keywords'];

    public function test_no_module_key_is_declared_twice_in_the_same_block(): void
    {
        foreach (self::KEYED_BLOCKS as $block) {
            $keys = $this->keysIn($block);

            $this->assertNotEmpty($keys, "Found no keys at all in `lifecycle.{$block}` — the parse is wrong.");

            $duplicates = array_keys(array_filter(array_count_values($keys), static fn (int $n) => $n > 1));

            $this->assertSame(
                [],
                $duplicates,
                "These keys are declared more than once in `lifecycle.{$block}` of config/ai.php:\n  "
                    .implode("\n  ", $duplicates)
                    ."\nPHP keeps the LAST one and discards the others without warning, so the earlier "
                    .'block is dead code that looks live.'
            );
        }
    }

    /**
     * Every module that binds tools binds ones that exist and are read-only.
     *
     * The companion failure to a duplicate key: a block that IS live but names a tool that
     * was renamed or never registered. The binding looks right in the file and resolves to
     * nothing, which shows up as a module whose tabs are mysteriously empty.
     */
    public function test_every_bound_tool_exists_and_is_read_only(): void
    {
        $registry = app(\App\Mcp\ToolRegistry::class);

        $byName = [];

        foreach ($registry->tools() as $tool) {
            $definition = $tool->definition();
            $byName[(string) $definition['name']] = ($definition['annotations']['read_only'] ?? false) === true;
        }

        $problems = [];

        foreach ((array) config('ai.lifecycle.modules', []) as $moduleKey => $module) {
            foreach ((array) ($module['mcp_tools'] ?? []) as $tool) {
                if (! array_key_exists($tool, $byName)) {
                    $problems[] = "{$moduleKey} binds {$tool}, which is not registered";

                    continue;
                }

                // A write tool bound to a module is reachable from a report layout and the
                // conversational fallback without anybody naming it. Admissions owns the
                // only write tools in this platform and binds them deliberately.
                if (! $byName[$tool] && $moduleKey !== 'admissions') {
                    $problems[] = "{$moduleKey} binds {$tool}, which writes";
                }
            }

            foreach ((array) ($module['detail_tools'] ?? []) as $field => $tool) {
                if (! array_key_exists($tool, $byName)) {
                    $problems[] = "{$moduleKey} names {$tool} as the detail tool for {$field}, but it is not registered";

                    continue;
                }

                // A detail tool that the module does not also bind cannot be called: the
                // binding is the permission.
                if (! in_array($tool, (array) ($module['mcp_tools'] ?? []), true)) {
                    $problems[] = "{$moduleKey} names {$tool} as a detail tool but does not bind it";
                }
            }
        }

        $this->assertSame([], $problems, "config/ai.php binds tools that cannot work:\n  ".implode("\n  ", $problems));
    }

    /**
     * The keys in one of the two blocks, in source order, including duplicates.
     *
     * @return array<int, string>
     */
    private function keysIn(string $block): array
    {
        $source = (string) file_get_contents(config_path('ai.php'));
        $lines = preg_split("/\r\n|\n/", $source) ?: [];

        $keys = [];
        $inside = false;
        $depth = 0;

        foreach ($lines as $line) {
            if (! $inside) {
                if (preg_match("/^\s*'".preg_quote($block, '/')."' => \[\s*$/", $line)) {
                    $inside = true;
                    $depth = 1;
                }

                continue;
            }

            // A key at the block's own top level is one indent in, and the line opens an
            // array — either `'key' => [` or a single-line `'key' => [...]`.
            if ($depth === 1 && preg_match("/^\s*'([a-zA-Z0-9_-]+)' => \[/", $line, $match)) {
                $keys[] = $match[1];
            }

            $depth += substr_count($line, '[') - substr_count($line, ']');

            if ($depth <= 0) {
                break;
            }
        }

        return $keys;
    }
}
