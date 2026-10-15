<?php

namespace App\Mcp\Tools;

/**
 * The course catalogue, under the name the Teach/Learn report template was published with.
 *
 * `k12.teach_learn.report` (seeded by 2026_09_28_100400) binds its rows to `teach_learn.courses`,
 * a tool that did not exist, so the layout was unusable and Teach/Learn was never listed as a
 * module a report can be generated for. Teach/Learn reads the same course records as `lms.courses`
 * (see config `ai.lifecycle.modules.teach_learn`), so this is that tool under the module's own
 * prefix rather than a second reading of the data — and it needs no change to the stored template.
 */
class TeachLearnCoursesTool extends LmsCoursesTool
{
    public function name(): string
    {
        return 'teach_learn.courses';
    }
}
