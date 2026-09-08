<?php

namespace App\Services\lms\Content;

use InvalidArgumentException;

/**
 * Resolves an authoring `content_type` to everything the endpoint needs to act on it.
 *
 * This is the mechanism that makes tracker row 3 / Decision #36 true rather than
 * aspirational: ONE endpoint serves Classroom Resource, Teacher Workspace and Question
 * Bank because the differences between them live in config
 * (`lms_content.authoring_types`) instead of in three controllers.
 *
 * Adding a fourth authoring surface is a config entry. That is the test of whether this
 * was actually consolidated or merely moved.
 */
class AuthoringTypeRegistry
{
    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return (array) config('lms_content.authoring_types', []);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string,mixed>
     * @throws InvalidArgumentException
     */
    public function get(string $type): array
    {
        $all = $this->all();

        if (! isset($all[$type])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown authoring content_type "%s". Registered: %s.',
                $type,
                implode(', ', array_keys($all))
            ));
        }

        return $all[$type];
    }

    public function supportsMode(string $type, string $mode): bool
    {
        return in_array($mode, (array) ($this->get($type)['modes'] ?? []), true);
    }

    public function permissionModule(string $type): string
    {
        return (string) ($this->get($type)['permission'] ?? 'lms.content');
    }

    public function entityType(string $type): string
    {
        return (string) ($this->get($type)['entity_type'] ?? 'content');
    }

    public function category(string $type): ?string
    {
        $category = $this->get($type)['category'] ?? null;

        return $category === null ? null : (string) $category;
    }

    public function provider(string $type): ?string
    {
        $provider = $this->get($type)['provider'] ?? null;

        return $provider === null ? null : (string) $provider;
    }

    /**
     * Allowed upload extensions for this type.
     *
     * Per type, not global. The mobile writer accepts mp3/mp4/jpg/png while the web
     * upload accepts only pdf/ppt/pptx; a single hardcoded list would break one of them.
     *
     * @return list<string>
     */
    public function uploadMimes(string $type): array
    {
        return array_values((array) ($this->get($type)['upload_mimes'] ?? []));
    }

    /**
     * The shape the frontend builds its form from.
     *
     * Mirrors how the PAL authoring console is driven by GET /api/pal/content/vocabulary -
     * the one place in this codebase where an authoring UI is schema-driven rather than
     * hardcoded. A new type appears in the UI without a React change.
     *
     * @return list<array<string,mixed>>
     */
    public function vocabulary(): array
    {
        $out = [];

        foreach ($this->all() as $key => $spec) {
            $out[] = [
                'content_type'  => $key,
                'label'         => $spec['label'] ?? $key,
                'modes'         => array_values((array) ($spec['modes'] ?? [])),
                'entity_type'   => $spec['entity_type'] ?? 'content',
                'category'      => $spec['category'] ?? null,
                'permission'    => $spec['permission'] ?? 'lms.content',
                'upload_mimes'  => array_values((array) ($spec['upload_mimes'] ?? [])),
                'has_generator' => ($spec['provider'] ?? null) !== null,
            ];
        }

        return $out;
    }
}
