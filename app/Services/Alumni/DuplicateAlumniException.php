<?php

namespace App\Services\Alumni;

/** Thrown when a create/update would duplicate an existing alumnus in the same tenant. */
class DuplicateAlumniException extends \RuntimeException
{
    /** @param array<int, array{id:int, alumni_code:string, full_name:string, matched_on:array<int,string>}> $matches */
    public function __construct(public readonly array $matches)
    {
        parent::__construct('A matching alumni record already exists.');
    }
}
