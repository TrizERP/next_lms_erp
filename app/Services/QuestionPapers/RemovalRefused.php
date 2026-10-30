<?php

namespace App\Services\QuestionPapers;

use RuntimeException;

/** A question paper cannot be removed right now; the message says why and is shown to the person. */
class RemovalRefused extends RuntimeException
{
}
