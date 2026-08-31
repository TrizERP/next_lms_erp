<?php

namespace App\Domain\Templates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Loads and renders prompt templates.
 *
 * Rendering is literal substitution of {{variable}} placeholders — no expression
 * language, no code execution. That is a security position as much as a design one:
 * templates are editable by administrators through the AI Administration screens, and
 * an editable template that could execute would be a remote code execution hole.
 *
 * Values are stringified and length-capped on the way in, so an oversized field
 * cannot be used to push a prompt past its context window and drop the system
 * instructions off the front.
 */
class TemplateRegistry
{
    private const MAX_VARIABLE_LENGTH = 8000;

    /** @var array<string, PromptTemplate|null> */
    private array $memo = [];

    /**
     * The published template for a key. Falls back to the platform baseline when the
     * tenant has no override of its own.
     */
    public function find(string $templateKey, ?int $subInstituteId = null, ?int $version = null): ?PromptTemplate
    {
        $memoKey = $templateKey . ':' . ($subInstituteId ?? 'global') . ':' . ($version ?? 'latest');

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        if (! Schema::hasTable('ai_templates')) {
            return $this->memo[$memoKey] = null;
        }

        $query = DB::table('ai_templates')
            ->where('template_key', $templateKey)
            ->where(function ($inner) use ($subInstituteId) {
                $inner->whereNull('sub_institute_id');
                if ($subInstituteId !== null) {
                    $inner->orWhere('sub_institute_id', $subInstituteId);
                }
            });

        if ($version !== null) {
            $query->where('version', $version);
        } else {
            $query->where('status', 'published');
        }

        $row = $query
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->orderByDesc('version')
            ->first();

        return $this->memo[$memoKey] = $row ? PromptTemplate::fromRow($row) : null;
    }

    /**
     * @return array<int, PromptTemplate>
     */
    public function all(?int $subInstituteId = null, ?string $category = null): array
    {
        if (! Schema::hasTable('ai_templates')) {
            return [];
        }

        $query = DB::table('ai_templates')
            ->where('status', 'published')
            ->where(function ($inner) use ($subInstituteId) {
                $inner->whereNull('sub_institute_id');
                if ($subInstituteId !== null) {
                    $inner->orWhere('sub_institute_id', $subInstituteId);
                }
            });

        if ($category !== null) {
            $query->where('category', $category);
        }

        $rows = $query->orderByRaw('sub_institute_id IS NULL ASC')
            ->orderByDesc('version')
            ->get();

        $byKey = [];

        foreach ($rows as $row) {
            if (! isset($byKey[$row->template_key])) {
                $byKey[$row->template_key] = PromptTemplate::fromRow($row);
            }
        }

        return array_values($byKey);
    }

    /**
     * Render a template's prompts.
     *
     * @throws RuntimeException when a required variable is missing — better a clear
     *                          failure than a prompt containing the literal "{{name}}"
     * @return array{system:string|null, user:string}
     */
    public function render(PromptTemplate $template, array $variables): array
    {
        $missing = $template->missingVariables($variables);

        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'Template "%s" is missing required variables: %s.',
                $template->key,
                implode(', ', $missing)
            ));
        }

        $prepared = $this->prepare($variables);

        return [
            'system' => $template->systemPrompt === null
                ? null
                : $this->substitute($template->systemPrompt, $prepared),
            'user' => $this->substitute($template->userPrompt, $prepared),
        ];
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    /**
     * Literal {{key}} replacement. An unknown placeholder is left as-is so a template
     * bug shows up in review rather than silently producing a sentence with a hole.
     */
    private function substitute(string $text, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn ($matches) => $variables[$matches[1]] ?? $matches[0],
            $text
        ) ?? $text;
    }

    /**
     * Flatten and cap. Nested arrays become JSON so a template can interpolate a
     * whole evidence list without the caller pre-formatting it.
     *
     * @return array<string, string>
     */
    private function prepare(array $variables, string $prefix = ''): array
    {
        $prepared = [];

        foreach ($variables as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                // Available both as JSON and, for nested maps, by dot path.
                $prepared[$path] = $this->cap(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                $prepared += $this->prepare($value, $path);

                continue;
            }

            if (is_bool($value)) {
                $prepared[$path] = $value ? 'true' : 'false';

                continue;
            }

            $prepared[$path] = $this->cap($value === null ? '' : (string) $value);
        }

        return $prepared;
    }

    private function cap(string $value): string
    {
        return mb_strlen($value) > self::MAX_VARIABLE_LENGTH
            ? mb_substr($value, 0, self::MAX_VARIABLE_LENGTH) . ' […truncated]'
            : $value;
    }
}
