<?php

namespace App\Services;

use Laudis\Neo4j\ClientBuilder;

class Neo4jService
{
    protected $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
        ->withDriver('bolt', 'bolt://' . env('NEO4J_USER') . ':' . env('NEO4J_PASSWORD') . '@' . env('NEO4J_HOST') . ':' . env('NEO4J_PORT'))
            ->build();
    }
    public function getClient()
    {
        return $this->client;
    }

    public function createNode($data)
    {
        // Created a node with selected fields from the model
        $query = 'CREATE (n:Content {institute_name: $institute_name, acedemic_section: $acedemic_section, 
                  standard: $standard, subject: $subject, chapter: $chapter, source: $source, 
                  title: $title, filepath: $filepath}) RETURN n';

        return $this->client->run($query, [
            'institute_name'   => $data->institute_name,
            'acedemic_section' => $data->acedemic_section,
            'standard'         => $data->standard,
            'subject'          => $data->subject,
            'chapter'          => $data->chapter,
            'source'           => $data->source,
            'title'            => $data->title,
            'filepath'         => $data->filepath,
        ]);
    }
}

