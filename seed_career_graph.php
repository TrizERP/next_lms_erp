<?php
require 'vendor/autoload.php';

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

$client = ClientBuilder::create()
    ->withDriver('neo4j', 'bolt://dev.triz.co.in:7688', Authenticate::basic('neo4j', 'admin'))
    ->build();

// 1. CONSTRAINTS
$constraints = [
    "CREATE CONSTRAINT occ_id IF NOT EXISTS FOR (o:Occupation) REQUIRE o.occupation_id IS UNIQUE;",
    "CREATE CONSTRAINT stream_id IF NOT EXISTS FOR (s:Stream) REQUIRE s.code IS UNIQUE;",
    "CREATE CONSTRAINT subject_id IF NOT EXISTS FOR (s:Subject) REQUIRE s.code IS UNIQUE;",
    "CREATE CONSTRAINT exam_id IF NOT EXISTS FOR (e:Exam) REQUIRE e.exam_id IS UNIQUE;",
    "CREATE CONSTRAINT degree_id IF NOT EXISTS FOR (d:Degree) REQUIRE d.degree_id IS UNIQUE;",
    "CREATE CONSTRAINT policy_id IF NOT EXISTS FOR (p:StreamPolicy) REQUIRE p.policy_id IS UNIQUE;",
];

foreach ($constraints as $cypher) {
    try {
        $client->run($cypher);
        echo "OK: $cypher\n";
    } catch (Exception $e) {
        echo "ERR: $cypher -> " . $e->getMessage() . "\n";
    }
}

// 2. SEED SHAPE — Architect
$seed = "
MERGE (o:Occupation {occupation_id:'OCC-ARCHITECT'})
  SET o.title = 'Architect';

MERGE (st:Stream  {code:'SCIENCE_PCM'}) SET st.name = 'Science (PCM)';
MERGE (m:Subject  {code:'MATHEMATICS'}) SET m.name = 'Mathematics';
MERGE (p:Subject  {code:'PHYSICS'})     SET p.name = 'Physics';
MERGE (ex:Exam    {exam_id:'EXAM-NATA'})SET ex.name = 'NATA';
MERGE (dg:Degree  {degree_id:'DEG-B-ARCH'}) SET dg.name = 'B.Arch';

MERGE (o)-[rs:REQUIRES_STREAM]->(st)
  SET rs.source_url = 'https://www.coa.gov.in/', rs.verified_at = date('2026-08-01');
MERGE (o)-[r1:REQUIRES_SUBJECT]->(m)
  SET r1.essentiality='essential', r1.source_url='https://www.coa.gov.in/', r1.verified_at=date('2026-08-01');
MERGE (o)-[r2:REQUIRES_SUBJECT]->(p)
  SET r2.essentiality='essential', r2.source_url='https://www.coa.gov.in/', r2.verified_at=date('2026-08-01');
MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url='https://www.nata.in/', re.verified_at=date('2026-08-01');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url='https://www.coa.gov.in/', rd.verified_at=date('2026-08-01');

MERGE (pol:StreamPolicy {policy_id:'CBSE-G9-2026-2027'})
  SET pol.board='CBSE', pol.grade=9, pol.academic_year='2026-2027',
      pol.change_deadline = date('2026-10-31'),
      pol.source_url='https://www.cbse.gov.in/', pol.verified_at=date('2026-08-01');
";

try {
    $client->run($seed);
    echo "OK: Architect seed\n";
} catch (Exception $e) {
    echo "ERR: Architect seed -> " . $e->getMessage() . "\n";
}

// Verify
$verify = "MATCH (o:Occupation {occupation_id:'OCC-ARCHITECT'})-[r]->(x) RETURN o,r,x;";
$result = $client->run($verify);
echo "Verification rows: " . $result->count() . "\n";
foreach ($result as $record) {
    $o = $record->get('o');
    $r = $record->get('r');
    $x = $record->get('x');
    echo sprintf("  %s -[%s]-> %s\n", $o->getIdentity()->getLabel(), $r->getType(), $x->getIdentity()->getLabel());
}
