<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

class Neo4jService
{
    protected $client;

    public function __construct()
    {
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
    }

    public function getClient()
    {
        return $this->client;
    }

    // ✅ ADD THIS METHOD
    public function testConnection()
    {
        try {
            $result = $this->client->run('RETURN 1 AS status');
            return 'Neo4j Connected Successfully';
        } catch (\Exception $e) {
            Log::error('Neo4j Connection Error: ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    public function createNode($data)
    {
        // Created a node with selected fields from the model

        $query = 'CREATE (n:Content {Organization : $Organization , Departments : $Departments , 
                  JobRoles: $JobRoles, Skill: $Skill,EducationLevel: $EducationLevel,ExperienceLevel: $ExperienceLevel }) RETURN n';

        return $this->client->run($query, [
            'Organization'   => $data->Organization ,
            'Departments'    => $data->Departments ,
            'JobRoles'       => $data->JobRoles,
            'Skill'          => $data->Skill,
            'EducationLevel' => $data->EducationLevel,  
            'ExperienceLevel' => $data->ExperienceLevel,  
        ]);
         if ($result->count() > 0) {
        // Node was created successfully
        $node = $result->first()->get('n')->getProperties();
        return [
            'status' => true,
            'message' => 'Node created successfully ',
            'data' => $node
        ];
    }

    // If no record returned
    return [
        'status' => false,
        'message' => 'Node not created '
    ];
    }

    public function createOrGetNode($label, $property, $value)
{
    // Update the query to correctly use the $param syntax
    $query = "MERGE (n:$label { $property: \$value }) RETURN n";
    
    $params = ['value' => $value];
    
    // Execute the query
    $result = $this->client->run($query, $params);

    // Get the first record from the result
    $record = $result->first();

    // Check if a record exists
    if ($record) {
        // Get the node 'n' from the record
        $node = $record->get('n');

        // If the node exists, get its properties
        if ($node) {
            $nodeProperties = $node->getProperties();
            
            // Return the node properties (or you can return the node itself)
            return $nodeProperties;
        }
    }

    return null; // Return null if no node is found
}

  // Create relationship between two nodes
  public function createRelationship($startNode, $endNode,$alias, $relationshipType,$keyStart,$keyEnd)
  {
      Log::Info("Relationship Created");
  
      // Access the node's properties using get()
    //   $startNodeProps = $startNode->get('acedemic_section');  // Assuming 'acedemic_section' is a property key
    //   $endNodeProps = $endNode->get('standard');  // Assuming 'standard' is a property key

      // Update the query to match nodes by properties instead of internal IDs
    //   $query = "EXPLAIN MATCH (a {acedemic_section: '".$startNode."'}), (b {standard: '".$endNode."'})
    //       MERGE (a)-[$alias:$relationshipType]->(b)";

    $query = "MATCH (a {".$keyStart.": '".$startNode."'}), (b {".$keyEnd.": '".$endNode."'}) 
              MERGE (a)-[".$alias.":".$relationshipType."]->(b)";
         // MATCH (a {acedemic_section: 'PRIMARY'}), (b {standard: '1'}) CREATE (a)-[r1:OFFERS]->(b)
         // MATCH (a {standard: '2'}), (b {subject: 'Social Awareness'}) CREATE (a)-[r2:OFFERS]->(b)
      Log::Info("Relationship In process");
    //   return $query;
      $params = [
        'startNode' => $startNode,
        'endNode' => $endNode,
        'alias' => $alias,
        'relationshipType' => $relationshipType,
      ];
  
      // Execute the query with timeout settings
      try {
          $checkRelation = $this->client->run($query, $params);
          
          if ($checkRelation) {
              $message = response()->json($checkRelation);
          } else {
              $message = "failed";
          }
      } catch (\Exception $e) {
          Log::error("Error executing query: " . $e->getMessage());
          $message =  $e->getMessage();
      }
  
      return $message;
  }
  
  
}

