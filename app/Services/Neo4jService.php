<?php

namespace App\Services;
use App\Exceptions\GraphUnavailableException;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Throwable;

class Neo4jService
{
    protected $client;
    protected ?Throwable $buildError = null;

    public function __construct()
    {
        try {
            $this->client = ClientBuilder::create()
            ->withDriver(
                'neo4j',
                config('neo4j.uri'),
                Authenticate::basic(
                    config('neo4j.username'),
                    config('neo4j.password')
                )
            )
            ->build();
        } catch (Throwable $e) {
            Log::error('Neo4j client could not be built: ' . $e->getMessage());
            $this->buildError = $e;
        }
    }

    /**
     * @throws GraphUnavailableException if the client could not be built (bad/blank credentials).
     */
    protected function client()
    {
        if ($this->client === null) {
            throw new GraphUnavailableException(
                'Neo4j client is not available: ' . ($this->buildError?->getMessage() ?? 'unknown build error'),
                previous: $this->buildError,
            );
        }

        return $this->client;
    }

    public function getClient()
    {
        return $this->client();
    }

    // ✅ ADD THIS METHOD
    public function testConnection()
    {
        try {
            $result = $this->client()->run('RETURN 1 AS status');
            return 'Neo4j Connected Successfully';
        } catch (\Exception $e) {
            Log::error('Neo4j Connection Error: ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    public function run($query, $params = [])
{
    try {
        return $this->client()->run($query, $params);
    } catch (GraphUnavailableException $e) {
        throw $e;
    } catch (Throwable $e) {
        Log::error('Neo4j query failed: ' . $e->getMessage());

        throw new GraphUnavailableException('Neo4j query failed: ' . $e->getMessage(), $e);
    }
}
}

