<?php

namespace App\Services\QuestionPapers;

use RuntimeException;

/** Another worker took over the job (this one stalled past its lease); stop without writing. */
class LeaseLostException extends RuntimeException
{
}
