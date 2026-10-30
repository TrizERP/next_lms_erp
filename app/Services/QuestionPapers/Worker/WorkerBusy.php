<?php

namespace App\Services\QuestionPapers\Worker;

use RuntimeException;

/** Another worker start or stop is already in progress. */
class WorkerBusy extends RuntimeException
{
}
