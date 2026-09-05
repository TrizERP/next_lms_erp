// =====================================================================
//  SKILLS — skills, job roles, SQAA, and the O*NET reference subgraph
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//      php artisan neo4j:csv-export --module=skills
//      php artisan neo4j:cypher --module=skills
//
//  :OnetOccupation, NOT :Occupation
//  The career-intelligence seed (app/Console/Commands/CareerIntelligence/
//  SeedCareerGraphCommand.php) already owns `:Occupation {occupation_id}` with a
//  curated vocabulary — 'OCC-ARCHITECT' and friends — and `CaiCoreService::CAI_CORE_QUERY`
//  matches on it. O*NET occupations are keyed on SOC codes ('17-1011.00'), a different
//  id space entirely. Loading them into :Occupation would silently break that query.
//  The same reasoning keeps `:Subject {code}` (2 seed nodes) untouched.
//
//  JOBROLE-KEY: `s_jobrole_skills` joins on NAME STRINGS. Names are resolved to ids in
//  SQL at export (see the manifest), so the graph never MERGEs on a name.
//
//  O*NET is US reference data with no tenant: sub_institute_id 0, scope 'global'.
//
//  ADDITIVE. MERGE + ON CREATE SET only. No protected relationship type is written.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS
// ---------------------------------------------------------------------

CREATE CONSTRAINT skill_skillId_unique IF NOT EXISTS
FOR (sk:Skill) REQUIRE sk.skillId IS UNIQUE;

CREATE CONSTRAINT jobrole_jobroleId_unique IF NOT EXISTS
FOR (jr:JobRole) REQUIRE jr.jobroleId IS UNIQUE;

CREATE CONSTRAINT jobtask_jobtaskKey_unique IF NOT EXISTS
FOR (jt:JobTask) REQUIRE jt.jobtaskKey IS UNIQUE;

CREATE CONSTRAINT industry_industryId_unique IF NOT EXISTS
FOR (ind:Industry) REQUIRE ind.industryId IS UNIQUE;

CREATE CONSTRAINT skillassessment_skillassessmentId_unique IF NOT EXISTS
FOR (sa:SkillAssessment) REQUIRE sa.skillassessmentId IS UNIQUE;

CREATE CONSTRAINT sqaastandard_sqaastandardId_unique IF NOT EXISTS
FOR (sq:SQAAStandard) REQUIRE sq.sqaastandardId IS UNIQUE;

CREATE CONSTRAINT sqaadocument_sqaadocumentId_unique IF NOT EXISTS
FOR (sd:SQAADocument) REQUIRE sd.sqaadocumentId IS UNIQUE;

CREATE CONSTRAINT onetoccupation_onetsocCode_unique IF NOT EXISTS
FOR (oc:OnetOccupation) REQUIRE oc.onetsocCode IS UNIQUE;

CREATE CONSTRAINT onetelement_elementId_unique IF NOT EXISTS
FOR (el:OnetElement) REQUIRE el.elementId IS UNIQUE;

CREATE CONSTRAINT onetscale_scaleId_unique IF NOT EXISTS
FOR (sc:OnetScale) REQUIRE sc.scaleId IS UNIQUE;

CREATE CONSTRAINT jobzone_jobzoneId_unique IF NOT EXISTS
FOR (jz:JobZone) REQUIRE jz.jobzoneId IS UNIQUE;

CREATE CONSTRAINT unspsccategory_commodityCode_unique IF NOT EXISTS
FOR (un:UnspscCategory) REQUIRE un.commodityCode IS UNIQUE;

CREATE CONSTRAINT careercluster_careerclusterId_unique IF NOT EXISTS
FOR (cl:CareerCluster) REQUIRE cl.careerclusterId IS UNIQUE;

CREATE CONSTRAINT onettask_taskId_unique IF NOT EXISTS
FOR (ot:OnetTask) REQUIRE ot.taskId IS UNIQUE;

CREATE CONSTRAINT workcontextcategory_workcontextcategoryKey_unique IF NOT EXISTS
FOR (wc:WorkContextCategory) REQUIRE wc.workcontextcategoryKey IS UNIQUE;

// Indexes, not constraints: neither name is unique (1,895 distinct job-role names
// across 5,805 rows). They exist so REQUIRES_SKILL can resolve 174,268 name pairs by
// point read instead of a label scan — see the note on that statement.
CREATE INDEX jobrole_name_idx IF NOT EXISTS FOR (jr:JobRole) ON (jr.jobrole);

CREATE INDEX skill_title_idx IF NOT EXISTS FOR (sk:Skill) ON (sk.title);


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES — skills and job roles
// ---------------------------------------------------------------------

// `title` is the real skill name; `name` holds a single constant across all 16,239
// rows and is not loaded.
LOAD CSV WITH HEADERS FROM 'file:///master_skills.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sk:Skill {skillId: toInteger(trim(row.id))})
ON CREATE SET
  sk.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  sk.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  sk.industries       = CASE WHEN trim(coalesce(row.industries, '')) = '' THEN null ELSE trim(row.industries) END,
  sk.category         = CASE WHEN trim(coalesce(row.category, '')) = '' THEN null ELSE trim(row.category) END,
  sk.sub_category     = CASE WHEN trim(coalesce(row.sub_category, '')) = '' THEN null ELSE trim(row.sub_category) END,
  sk.proficiency_level = CASE WHEN trim(coalesce(row.proficiency_level, '')) = '' THEN null ELSE trim(row.proficiency_level) END,
  sk.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  sk.displayLabel     = "Skill:" + trim(coalesce(row.title, '')),
  sk.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  sk.src              = "master_skills"
RETURN count(sk) AS skillProcessed;


LOAD CSV WITH HEADERS FROM 'file:///s_jobrole.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (jr:JobRole {jobroleId: toInteger(trim(row.id))})
ON CREATE SET
  jr.jobrole          = CASE WHEN trim(coalesce(row.jobrole, '')) = '' THEN null ELSE trim(row.jobrole) END,
  jr.track            = CASE WHEN trim(coalesce(row.track, '')) = '' THEN null ELSE trim(row.track) END,
  jr.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  jr.code             = CASE WHEN trim(coalesce(row.code, '')) = '' THEN null ELSE trim(row.code) END,
  jr.type             = CASE WHEN trim(coalesce(row.type, '')) = '' THEN null ELSE trim(row.type) END,
  jr.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  jr.displayLabel     = "JobRole:" + trim(coalesce(row.jobrole, '')),
  jr.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  jr.src              = "s_jobrole"
RETURN count(jr) AS jobRoleProcessed;


LOAD CSV WITH HEADERS FROM 'file:///s_industries.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ind:Industry {industryId: toInteger(trim(row.id))})
ON CREATE SET
  ind.industries       = CASE WHEN trim(coalesce(row.industries, '')) = '' THEN null ELSE trim(row.industries) END,
  ind.department       = CASE WHEN trim(coalesce(row.department, '')) = '' THEN null ELSE trim(row.department) END,
  ind.sub_department   = CASE WHEN trim(coalesce(row.sub_department, '')) = '' THEN null ELSE trim(row.sub_department) END,
  ind.type             = CASE WHEN trim(coalesce(row.type, '')) = '' THEN null ELSE trim(row.type) END,
  ind.displayLabel     = "Industry:" + trim(coalesce(row.industries, '')),
  ind.sub_institute_id = 0,
  ind.scope            = "global",
  ind.src              = "s_industries"
RETURN count(ind) AS industryProcessed;


LOAD CSV WITH HEADERS FROM 'file:///s_assessment_library.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sa:SkillAssessment {skillassessmentId: toInteger(trim(row.id))})
ON CREATE SET
  sa.title             = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  sa.description       = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  sa.total_questions   = toInteger(trim(row.total_questions)),
  sa.attempted_users   = toInteger(trim(row.attempted_users)),
  sa.duration          = CASE WHEN trim(coalesce(row.duration, '')) = '' THEN null ELSE trim(row.duration) END,
  sa.type              = CASE WHEN trim(coalesce(row.type, '')) = '' THEN null ELSE trim(row.type) END,
  sa.level             = CASE WHEN trim(coalesce(row.level, '')) = '' THEN null ELSE trim(row.level) END,
  sa.job_role          = CASE WHEN trim(coalesce(row.job_role, '')) = '' THEN null ELSE trim(row.job_role) END,
  sa.displayLabel      = "SkillAssessment:" + trim(coalesce(row.title, '')),
  sa.sub_institute_id  = 0,
  sa.scope             = "global",
  sa.src               = "s_assessment_library"
RETURN count(sa) AS skillAssessmentProcessed;


// A task is free text with no id of its own, so the node is keyed on a SHA1 of
// (job role, task) computed at export — stable across re-runs, unlike a row id.
LOAD CSV WITH HEADERS FROM 'file:///s_jobrole_task_agg.csv' AS row
WITH row WHERE row.task_key IS NOT NULL AND trim(row.task_key) <> ''
MERGE (jt:JobTask {jobtaskKey: trim(row.task_key)})
ON CREATE SET
  jt.task                   = CASE WHEN trim(coalesce(row.task, '')) = '' THEN null ELSE trim(row.task) END,
  jt.critical_work_function = CASE WHEN trim(coalesce(row.critical_work_function, '')) = '' THEN null ELSE trim(row.critical_work_function) END,
  jt.jobrole_id             = toInteger(trim(row.jobrole_id)),
  jt.displayLabel           = "JobTask:" + trim(coalesce(row.task, '')),
  jt.sub_institute_id       = 0,
  jt.scope                  = "global",
  jt.src                    = "s_jobrole_task"
RETURN count(jt) AS jobTaskProcessed;


// ---------------------------------------------------------------------
//    NODES — SQAA
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///sqaa_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sq:SQAAStandard {sqaastandardId: toInteger(trim(row.id))})
ON CREATE SET
  sq.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  sq.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  sq.parent_id        = toInteger(trim(row.parent_id)),
  sq.level            = CASE WHEN trim(coalesce(row.level, '')) = '' THEN null ELSE trim(row.level) END,
  sq.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  sq.sort_order       = toInteger(trim(row.sort_order)),
  sq.displayLabel     = "SQAAStandard:" + trim(coalesce(row.title, '')),
  sq.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  sq.src              = "sqaa_master"
RETURN count(sq) AS sqaaStandardProcessed;


LOAD CSV WITH HEADERS FROM 'file:///sqaa_documant_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sd:SQAADocument {sqaadocumentId: toInteger(trim(row.id))})
ON CREATE SET
  sd.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  sd.standard_id      = toInteger(trim(row.menu_id)),
  sd.displayLabel     = "SQAADocument:" + trim(coalesce(row.title, '')),
  sd.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  sd.src              = "sqaa_documant_master"
RETURN count(sd) AS sqaaDocumentProcessed;


// ---------------------------------------------------------------------
//    NODES — O*NET reference (global)
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///onet_occupation_data.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND trim(row.onetsoc_code) <> ''
MERGE (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
ON CREATE SET
  oc.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  oc.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  oc.displayLabel     = "OnetOccupation:" + trim(coalesce(row.title, '')),
  oc.sub_institute_id = 0,
  oc.scope            = "global",
  oc.src              = "onet_occupation_data"
RETURN count(oc) AS onetOccupationProcessed;


LOAD CSV WITH HEADERS FROM 'file:///onet_content_model_reference.csv' AS row
WITH row WHERE row.element_id IS NOT NULL AND trim(row.element_id) <> ''
MERGE (el:OnetElement {elementId: trim(row.element_id)})
ON CREATE SET
  el.element_name     = CASE WHEN trim(coalesce(row.element_name, '')) = '' THEN null ELSE trim(row.element_name) END,
  el.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  el.type             = CASE WHEN trim(coalesce(row.type, '')) = '' THEN null ELSE trim(row.type) END,
  el.level            = CASE WHEN trim(coalesce(row.level, '')) = '' THEN null ELSE trim(row.level) END,
  el.displayLabel     = "OnetElement:" + trim(coalesce(row.element_name, '')),
  el.sub_institute_id = 0,
  el.scope            = "global",
  el.src              = "onet_content_model_reference"
RETURN count(el) AS onetElementProcessed;


LOAD CSV WITH HEADERS FROM 'file:///onet_scales_reference.csv' AS row
WITH row WHERE row.scale_id IS NOT NULL AND trim(row.scale_id) <> ''
MERGE (sc:OnetScale {scaleId: trim(row.scale_id)})
ON CREATE SET
  sc.scale_name       = CASE WHEN trim(coalesce(row.scale_name, '')) = '' THEN null ELSE trim(row.scale_name) END,
  sc.minimum          = toFloat(trim(row.minimum)),
  sc.maximum          = toFloat(trim(row.maximum)),
  sc.displayLabel     = "OnetScale:" + trim(coalesce(row.scale_name, '')),
  sc.sub_institute_id = 0,
  sc.scope            = "global",
  sc.src              = "onet_scales_reference"
RETURN count(sc) AS onetScaleProcessed;


LOAD CSV WITH HEADERS FROM 'file:///onet_job_zone_reference.csv' AS row
WITH row WHERE row.job_zone IS NOT NULL AND trim(row.job_zone) <> ''
MERGE (jz:JobZone {jobzoneId: toInteger(trim(row.job_zone))})
ON CREATE SET
  jz.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  jz.name             = CASE WHEN trim(coalesce(row.name, '')) = '' THEN null ELSE trim(row.name) END,
  jz.experience       = CASE WHEN trim(coalesce(row.experience, '')) = '' THEN null ELSE trim(row.experience) END,
  jz.education        = CASE WHEN trim(coalesce(row.education, '')) = '' THEN null ELSE trim(row.education) END,
  jz.job_training     = CASE WHEN trim(coalesce(row.job_training, '')) = '' THEN null ELSE trim(row.job_training) END,
  jz.svp_range        = CASE WHEN trim(coalesce(row.svp_range, '')) = '' THEN null ELSE trim(row.svp_range) END,
  jz.displayLabel     = "JobZone:" + trim(coalesce(row.name, '')),
  jz.sub_institute_id = 0,
  jz.scope            = "global",
  jz.src              = "onet_job_zone_reference"
RETURN count(jz) AS jobZoneProcessed;


LOAD CSV WITH HEADERS FROM 'file:///onet_unspsc_reference.csv' AS row
WITH row WHERE row.commodity_code IS NOT NULL AND trim(row.commodity_code) <> ''
MERGE (un:UnspscCategory {commodityCode: trim(row.commodity_code)})
ON CREATE SET
  un.commodity_title  = CASE WHEN trim(coalesce(row.commodity_title, '')) = '' THEN null ELSE trim(row.commodity_title) END,
  un.class_title      = CASE WHEN trim(coalesce(row.class_title, '')) = '' THEN null ELSE trim(row.class_title) END,
  un.family_title     = CASE WHEN trim(coalesce(row.family_title, '')) = '' THEN null ELSE trim(row.family_title) END,
  un.segment_title    = CASE WHEN trim(coalesce(row.segment_title, '')) = '' THEN null ELSE trim(row.segment_title) END,
  un.displayLabel     = "UnspscCategory:" + trim(coalesce(row.commodity_title, '')),
  un.sub_institute_id = 0,
  un.scope            = "global",
  un.src              = "onet_unspsc_reference"
RETURN count(un) AS unspscProcessed;


LOAD CSV WITH HEADERS FROM 'file:///onet_career_cluster.csv' AS row
WITH row WHERE row.career_id IS NOT NULL AND trim(row.career_id) <> ''
MERGE (cl:CareerCluster {careerclusterId: toInteger(trim(row.career_id))})
ON CREATE SET
  cl.career_cluster   = CASE WHEN trim(coalesce(row.career_cluster, '')) = '' THEN null ELSE trim(row.career_cluster) END,
  cl.career_pathway   = CASE WHEN trim(coalesce(row.career_pathway, '')) = '' THEN null ELSE trim(row.career_pathway) END,
  cl.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  cl.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  cl.onetsoc_code     = CASE WHEN trim(coalesce(row.onetsoc_code, '')) = '' THEN null ELSE trim(row.onetsoc_code) END,
  cl.displayLabel     = "CareerCluster:" + trim(coalesce(row.career_cluster, '')),
  cl.sub_institute_id = 0,
  cl.scope            = "global",
  cl.src              = "onet_career_cluster"
RETURN count(cl) AS careerClusterProcessed;


LOAD CSV WITH HEADERS FROM 'file:///onet_task_statements.csv' AS row
WITH row WHERE row.task_id IS NOT NULL AND trim(row.task_id) <> ''
MERGE (ot:OnetTask {taskId: toInteger(trim(row.task_id))})
ON CREATE SET
  ot.task                  = CASE WHEN trim(coalesce(row.task, '')) = '' THEN null ELSE trim(row.task) END,
  ot.task_type             = CASE WHEN trim(coalesce(row.task_type, '')) = '' THEN null ELSE trim(row.task_type) END,
  ot.incumbents_responding = toFloat(trim(row.incumbents_responding)),
  ot.onetsoc_code          = CASE WHEN trim(coalesce(row.onetsoc_code, '')) = '' THEN null ELSE trim(row.onetsoc_code) END,
  ot.displayLabel          = "OnetTask:" + trim(coalesce(row.task, '')),
  ot.sub_institute_id      = 0,
  ot.scope                 = "global",
  ot.src                   = "onet_task_statements"
RETURN count(ot) AS onetTaskProcessed;


// Keyed on (element, scale, category) — the table has no single-column key.
LOAD CSV WITH HEADERS FROM 'file:///onet_work_context_categories.csv' AS row
WITH row WHERE row.element_id IS NOT NULL AND trim(row.element_id) <> ''
MERGE (wc:WorkContextCategory {workcontextcategoryKey:
        trim(row.element_id) + ':' + trim(coalesce(row.scale_id, '')) + ':' + trim(coalesce(row.category, ''))})
ON CREATE SET
  wc.element_id           = trim(row.element_id),
  wc.scale_id             = CASE WHEN trim(coalesce(row.scale_id, '')) = '' THEN null ELSE trim(row.scale_id) END,
  wc.category             = CASE WHEN trim(coalesce(row.category, '')) = '' THEN null ELSE trim(row.category) END,
  wc.category_description = CASE WHEN trim(coalesce(row.category_description, '')) = '' THEN null ELSE trim(row.category_description) END,
  wc.displayLabel         = "WorkContextCategory:" + trim(coalesce(row.category_description, '')),
  wc.sub_institute_id     = 0,
  wc.scope                = "global",
  wc.src                  = "onet_work_context_categories"
RETURN count(wc) AS workContextCategoryProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

// JobRole -> Skill. This is the JOBROLE-KEY case: the source joins on NAME STRINGS,
// which violates the rule that a MERGE keys on an id, so the names are resolved to ids
// HERE and the MERGE still runs on the id.
//
// A name is not unique — 1,895 distinct job-role names across 5,805 rows — so each
// name is collapsed to its LOWEST id with `min()`. That is what keeps one CSV row
// producing exactly one edge instead of fanning out across every row sharing the name.
// Rows whose name resolves to nothing simply do not match and are dropped; measured
// 2026-09-03, 4,594 of 4,596 job-role names and 15,420 of 15,474 skill names resolve.
LOAD CSV WITH HEADERS FROM 'file:///s_jobrole_skills_agg.csv' AS row
WITH row
WHERE row.jobrole IS NOT NULL AND trim(row.jobrole) <> ''
  AND row.skill IS NOT NULL AND trim(row.skill) <> ''

MATCH (j:JobRole {jobrole: trim(row.jobrole)})
MATCH (s:Skill {title: trim(row.skill)})
WITH row, min(j.jobroleId) AS jobroleId, min(s.skillId) AS skillId

MATCH (jr:JobRole {jobroleId: jobroleId})
MATCH (sk:Skill {skillId: skillId})

MERGE (jr)-[r:REQUIRES_SKILL]->(sk)
ON CREATE SET
  r.proficiency_level = CASE WHEN trim(coalesce(row.proficiency_level, '')) = '' THEN null ELSE trim(row.proficiency_level) END,
  r.sector            = CASE WHEN trim(coalesce(row.sector, '')) = '' THEN null ELSE trim(row.sector) END,
  r.track             = CASE WHEN trim(coalesce(row.track, '')) = '' THEN null ELSE trim(row.track) END,
  r.src               = "s_jobrole_skills"
RETURN count(r) AS requiresSkill;


LOAD CSV WITH HEADERS FROM 'file:///s_jobrole_task_agg.csv' AS row
WITH row WHERE row.jobrole_id IS NOT NULL AND row.task_key IS NOT NULL
MATCH (jr:JobRole {jobroleId: toInteger(trim(row.jobrole_id))})
MATCH (jt:JobTask {jobtaskKey: trim(row.task_key)})
MERGE (jr)-[r:INVOLVES_TASK]->(jt)
ON CREATE SET r.src = "s_jobrole_task"
RETURN count(r) AS involvesTask;


// Person -> Skill, from the staff skill matrix.
LOAD CSV WITH HEADERS FROM 'file:///s_skill_matrix.csv' AS row
WITH row WHERE row.user_id IS NOT NULL AND trim(row.user_id) <> '0'
  AND row.skill_id IS NOT NULL AND trim(row.skill_id) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (sk:Skill {skillId: toInteger(trim(row.skill_id))})
MERGE (person)-[r:HAS_SKILL]->(sk)
ON CREATE SET
  r.skill_level    = CASE WHEN trim(coalesce(row.skill_level, '')) = '' THEN null ELSE trim(row.skill_level) END,
  r.interest_level = CASE WHEN trim(coalesce(row.interest_level, '')) = '' THEN null ELSE trim(row.interest_level) END,
  r.knowledge      = CASE WHEN trim(coalesce(row.knowledge, '')) = '' THEN null ELSE trim(row.knowledge) END,
  r.ability        = CASE WHEN trim(coalesce(row.ability, '')) = '' THEN null ELSE trim(row.ability) END,
  r.src            = "s_skill_matrix"
RETURN count(r) AS hasSkill;


// SQAA hierarchy and submissions.
LOAD CSV WITH HEADERS FROM 'file:///sqaa_master.csv' AS row
WITH row WHERE row.parent_id IS NOT NULL AND trim(row.parent_id) <> '' AND trim(row.parent_id) <> '0'
MATCH (child:SQAAStandard {sqaastandardId: toInteger(trim(row.id))})
MATCH (parent:SQAAStandard {sqaastandardId: toInteger(trim(row.parent_id))})
MERGE (parent)-[:PARENT_OF_STANDARD]->(child)
RETURN count(*) AS parentOfStandard;


LOAD CSV WITH HEADERS FROM 'file:///sqaa_documant_master.csv' AS row
WITH row WHERE row.menu_id IS NOT NULL AND trim(row.menu_id) <> '' AND trim(row.menu_id) <> '0'
MATCH (sd:SQAADocument {sqaadocumentId: toInteger(trim(row.id))})
MATCH (sq:SQAAStandard {sqaastandardId: toInteger(trim(row.menu_id))})
MERGE (sq)-[:REQUIRES_DOCUMENT]->(sd)
RETURN count(*) AS requiresDocument;


LOAD CSV WITH HEADERS FROM 'file:///sqaa_documents.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.document_id IS NOT NULL AND trim(row.document_id) <> '' AND trim(row.document_id) <> '0'
MATCH (sd:SQAADocument {sqaadocumentId: toInteger(trim(row.document_id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[r:SUBMITTED {submissionId: toInteger(trim(row.id))}]->(sd)
ON CREATE SET
  r.availability = CASE WHEN trim(coalesce(row.availability, '')) = '' THEN null ELSE trim(row.availability) END,
  r.reasons      = CASE WHEN trim(coalesce(row.reasons, '')) = '' THEN null ELSE trim(row.reasons) END,
  r.src          = "sqaa_documents"
RETURN count(r) AS submitted;


LOAD CSV WITH HEADERS FROM 'file:///sqaa_marks.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.menu_id IS NOT NULL AND trim(row.menu_id) <> '' AND trim(row.menu_id) <> '0'
MATCH (sq:SQAAStandard {sqaastandardId: toInteger(trim(row.menu_id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[r:SCORED_SQAA]->(sq)
ON CREATE SET
  r.mark = toFloat(trim(row.mark)),
  r.src  = "sqaa_marks"
RETURN count(r) AS scoredSqaa;


// ---------------------------------------------------------------------
//    O*NET relationships
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///onet_job_zones.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.job_zone IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (jz:JobZone {jobzoneId: toInteger(trim(row.job_zone))})
MERGE (oc)-[:IN_JOB_ZONE]->(jz)
RETURN count(*) AS inJobZone;


LOAD CSV WITH HEADERS FROM 'file:///onet_career_cluster.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND trim(row.onetsoc_code) <> ''
MATCH (cl:CareerCluster {careerclusterId: toInteger(trim(row.career_id))})
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MERGE (cl)-[:CLUSTERS_OCCUPATION]->(oc)
RETURN count(*) AS clustersOccupation;


LOAD CSV WITH HEADERS FROM 'file:///onet_task_statements.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND trim(row.onetsoc_code) <> ''
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (ot:OnetTask {taskId: toInteger(trim(row.task_id))})
MERGE (oc)-[:HAS_TASK]->(ot)
RETURN count(*) AS hasTask;


LOAD CSV WITH HEADERS FROM 'file:///onet_work_context_categories.csv' AS row
WITH row WHERE row.element_id IS NOT NULL AND trim(row.element_id) <> ''
MATCH (wc:WorkContextCategory {workcontextcategoryKey:
        trim(row.element_id) + ':' + trim(coalesce(row.scale_id, '')) + ':' + trim(coalesce(row.category, ''))})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (wc)-[:FOR_ELEMENT]->(el)
RETURN count(*) AS forElement;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES — the O*NET ratings, pivoted at export
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///onet_skills_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:REQUIRES_SKILL_ELEMENT]->(el)
ON CREATE SET
  r.importance = toFloat(trim(row.importance)),
  r.level      = toFloat(trim(row.level_value)),
  r.src        = "onet_skills"
RETURN count(r) AS onetSkills;


LOAD CSV WITH HEADERS FROM 'file:///onet_abilities_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:REQUIRES_ABILITY]->(el)
ON CREATE SET
  r.importance = toFloat(trim(row.importance)),
  r.level      = toFloat(trim(row.level_value)),
  r.src        = "onet_abilities"
RETURN count(r) AS onetAbilities;


LOAD CSV WITH HEADERS FROM 'file:///onet_knowledge_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:REQUIRES_KNOWLEDGE]->(el)
ON CREATE SET
  r.importance = toFloat(trim(row.importance)),
  r.level      = toFloat(trim(row.level_value)),
  r.src        = "onet_knowledge"
RETURN count(r) AS onetKnowledge;


LOAD CSV WITH HEADERS FROM 'file:///onet_work_activities_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:INVOLVES_ACTIVITY]->(el)
ON CREATE SET
  r.importance = toFloat(trim(row.importance)),
  r.level      = toFloat(trim(row.level_value)),
  r.src        = "onet_work_activities"
RETURN count(r) AS onetWorkActivities;


LOAD CSV WITH HEADERS FROM 'file:///onet_work_styles_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:HAS_WORK_STYLE]->(el)
ON CREATE SET
  r.importance = toFloat(trim(row.importance)),
  r.src        = "onet_work_styles"
RETURN count(r) AS onetWorkStyles;


LOAD CSV WITH HEADERS FROM 'file:///onet_interests_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:HAS_INTEREST]->(el)
ON CREATE SET
  r.data_value = toFloat(trim(row.data_value)),
  r.src        = "onet_interests"
RETURN count(r) AS onetInterests;


LOAD CSV WITH HEADERS FROM 'file:///onet_work_values_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:HAS_WORK_VALUE]->(el)
ON CREATE SET
  r.data_value = toFloat(trim(row.data_value)),
  r.src        = "onet_work_values"
RETURN count(r) AS onetWorkValues;


LOAD CSV WITH HEADERS FROM 'file:///onet_work_context_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.element_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (el:OnetElement {elementId: trim(row.element_id)})
MERGE (oc)-[r:HAS_WORK_CONTEXT]->(el)
ON CREATE SET
  r.context_value = toFloat(trim(row.context_value)),
  r.max_value     = toFloat(trim(row.max_value)),
  r.src           = "onet_work_context"
RETURN count(r) AS onetWorkContext;


LOAD CSV WITH HEADERS FROM 'file:///onet_task_ratings_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.task_id IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (ot:OnetTask {taskId: toInteger(trim(row.task_id))})
MERGE (oc)-[r:PERFORMS_TASK]->(ot)
ON CREATE SET
  r.importance = toFloat(trim(row.importance)),
  r.relevance  = toFloat(trim(row.relevance)),
  r.frequency  = toFloat(trim(row.frequency)),
  r.src        = "onet_task_ratings"
RETURN count(r) AS onetTaskRatings;


LOAD CSV WITH HEADERS FROM 'file:///onet_technology_skills_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.commodity_code IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (un:UnspscCategory {commodityCode: trim(row.commodity_code)})
MERGE (oc)-[r:USES_TECHNOLOGY]->(un)
ON CREATE SET
  r.examples       = toInteger(trim(row.examples)),
  r.hot_technology = CASE WHEN trim(coalesce(row.hot_technology, '')) = '' THEN null ELSE trim(row.hot_technology) END,
  r.in_demand      = CASE WHEN trim(coalesce(row.in_demand, '')) = '' THEN null ELSE trim(row.in_demand) END,
  r.src            = "onet_technology_skills"
RETURN count(r) AS usesTechnology;


LOAD CSV WITH HEADERS FROM 'file:///onet_tools_used_agg.csv' AS row
WITH row WHERE row.onetsoc_code IS NOT NULL AND row.commodity_code IS NOT NULL
MATCH (oc:OnetOccupation {onetsocCode: trim(row.onetsoc_code)})
MATCH (un:UnspscCategory {commodityCode: trim(row.commodity_code)})
MERGE (oc)-[r:USES_TOOL]->(un)
ON CREATE SET
  r.examples = toInteger(trim(row.examples)),
  r.src      = "onet_tools_used"
RETURN count(r) AS usesTool;


// @section verify
// ---------------------------------------------------------------------
// 5. VERIFY
// ---------------------------------------------------------------------

MATCH (sk:Skill) RETURN 'Skill nodes' AS check, count(sk) AS n;
MATCH (jr:JobRole) RETURN 'JobRole nodes' AS check, count(jr) AS n;
MATCH (jt:JobTask) RETURN 'JobTask nodes' AS check, count(jt) AS n;
MATCH (sq:SQAAStandard) RETURN 'SQAAStandard nodes' AS check, count(sq) AS n;
MATCH (oc:OnetOccupation) RETURN 'OnetOccupation nodes' AS check, count(oc) AS n;
MATCH (el:OnetElement) RETURN 'OnetElement nodes' AS check, count(el) AS n;
MATCH (ot:OnetTask) RETURN 'OnetTask nodes' AS check, count(ot) AS n;
MATCH (un:UnspscCategory) RETURN 'UnspscCategory nodes' AS check, count(un) AS n;
MATCH (:JobRole)-[r:REQUIRES_SKILL]->(:Skill) RETURN 'REQUIRES_SKILL' AS check, count(r) AS n;
MATCH (:JobRole)-[r:INVOLVES_TASK]->(:JobTask) RETURN 'INVOLVES_TASK' AS check, count(r) AS n;
MATCH ()-[r:HAS_SKILL]->(:Skill) RETURN 'HAS_SKILL' AS check, count(r) AS n;
MATCH (:Institute)-[r:SUBMITTED]->(:SQAADocument) RETURN 'SUBMITTED' AS check, count(r) AS n;
MATCH (:OnetOccupation)-[r:REQUIRES_SKILL_ELEMENT]->(:OnetElement) RETURN 'REQUIRES_SKILL_ELEMENT' AS check, count(r) AS n;
MATCH (:OnetOccupation)-[r:REQUIRES_ABILITY]->(:OnetElement) RETURN 'REQUIRES_ABILITY' AS check, count(r) AS n;
MATCH (:OnetOccupation)-[r:HAS_WORK_CONTEXT]->(:OnetElement) RETURN 'HAS_WORK_CONTEXT' AS check, count(r) AS n;
MATCH (:OnetOccupation)-[r:PERFORMS_TASK]->(:OnetTask) RETURN 'PERFORMS_TASK' AS check, count(r) AS n;
MATCH (:OnetOccupation)-[r:USES_TECHNOLOGY]->(:UnspscCategory) RETURN 'USES_TECHNOLOGY' AS check, count(r) AS n;
// The career-intelligence seed's own labels must be untouched by this module.
MATCH (o:Occupation) RETURN 'Occupation (career seed, unchanged)' AS check, count(o) AS n;
MATCH (e:Exam) RETURN 'Exam (career seed, unchanged)' AS check, count(e) AS n;
MATCH (s:Subject) WHERE s.code IS NOT NULL RETURN 'Subject{code} (career seed, unchanged)' AS check, count(s) AS n;
