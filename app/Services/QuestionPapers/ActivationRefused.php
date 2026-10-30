<?php

namespace App\Services\QuestionPapers;

use RuntimeException;

/** One question cannot be activated; the message says why and is shown to the reviewer. */
class ActivationRefused extends RuntimeException
{
}
