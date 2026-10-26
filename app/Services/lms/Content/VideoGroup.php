<?php

namespace App\Services\lms\Content;

/**
 * Which section of a chapter's Videos tab a video belongs to.
 *
 * Every chapter video is a content_master row in the 'Recorded Videos' category (other systems read that category, so it is
 * not changed). The section is a namespaced tag in the row's existing comma-separated `meta_tags`, next to tags such as
 * "LMS,ERP":
 *
 *   vgrp:chapter | vgrp:concept | vgrp:morning-circle | vgrp:sustainable-cities
 *
 * A video with no such tag has no section (null) and is listed as before. Nothing is read from the title, so renaming a
 * video never moves it.
 */
class VideoGroup
{
    public const CHAPTER = 'chapter';

    public const CONCEPT = 'concept';

    public const MORNING_CIRCLE = 'morning-circle';

    public const SUSTAINABLE_CITIES = 'sustainable-cities';

    public const GROUPS = [self::CHAPTER, self::CONCEPT, self::MORNING_CIRCLE, self::SUSTAINABLE_CITIES];

    private const PREFIX = 'vgrp:';

    public static function tagFor(string $group): string
    {
        return self::PREFIX . $group;
    }

    /** The section named by the tags, or null when none (or an unknown or ambiguous one) is named. */
    public static function fromMetaTags(?string $metaTags): ?string
    {
        $found = [];
        foreach (explode(',', (string) $metaTags) as $tag) {
            $tag = strtolower(trim($tag));
            if (str_starts_with($tag, self::PREFIX) && in_array(substr($tag, strlen(self::PREFIX)), self::GROUPS, true)) {
                $found[substr($tag, strlen(self::PREFIX))] = true;
            }
        }

        return count($found) === 1 ? (string) array_key_first($found) : null;
    }
}
