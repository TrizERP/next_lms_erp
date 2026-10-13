<?php

namespace App\Services\StudyDeck\Contracts;

/**
 * An image store that remembers where each picture was downloaded from, so a run that needs the same
 * third-party picture again reads it back instead of fetching it a second time.
 */
interface SourceImageCache
{
    /** @return array{bytes:string,mime:string,width:int,height:int}|null */
    public function cached(string $url): ?array;
}
