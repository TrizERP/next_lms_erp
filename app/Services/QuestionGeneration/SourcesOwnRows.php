<?php

namespace App\Services\QuestionGeneration;

/**
 * A format whose rows are not written by the text model.
 *
 * Most formats are a prompt plus a validator: the service sends the prompt to the
 * language model and checks what comes back. A format that needs something a text
 * model cannot supply -- an image and the places in it, here -- implements this
 * instead, and the service asks the format for its rows where it would otherwise call
 * the model. The rows are then validated, prepared, de-duplicated and persisted by
 * exactly the code every other format goes through, so there is one write path.
 *
 * The service never branches on a format's code to do this: it tests for this
 * interface.
 */
interface SourcesOwnRows
{
    /**
     * @param array<int, array<string, mixed>> $batchQuota rows of level / count / dok / difficulty / points / sub_type
     * @param array<string, mixed>             $slice       the built concept slice (concept, knowledge_items, ...)
     * @param array<string, mixed>             $context     concept_id, concept_name and anything else the source needs
     *
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     rows?: array<int, array<string, mixed>>,
     *     underfilled?: bool,
     *     reason?: string|null
     * }
     */
    public function sourceRows(array $batchQuota, array $slice, array $context): array;
}
