<?php
require 'vendor/autoload.php';

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

$client = ClientBuilder::create()
    ->withDriver('neo4j', 'bolt://dev.triz.co.in:7688', Authenticate::basic('neo4j', 'admin'))
    ->build();

$result = $client->run('RETURN 1 AS status');
echo $result->first()->get('status');
