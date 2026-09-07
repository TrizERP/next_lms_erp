// =============================================================================
// CAI-CORE — graph constraints (CI-GUIDE-DEV-001 Group B, Step B1)
// Verbatim from the supplied cai_core.cypher "1. CONSTRAINTS" section.
// IF NOT EXISTS makes this safe to re-run.
// =============================================================================
CREATE CONSTRAINT occ_id     IF NOT EXISTS FOR (o:Occupation)   REQUIRE o.occupation_id IS UNIQUE;
CREATE CONSTRAINT stream_id  IF NOT EXISTS FOR (s:Stream)       REQUIRE s.code IS UNIQUE;
CREATE CONSTRAINT subject_id IF NOT EXISTS FOR (s:Subject)      REQUIRE s.code IS UNIQUE;
CREATE CONSTRAINT exam_id    IF NOT EXISTS FOR (e:Exam)         REQUIRE e.exam_id IS UNIQUE;
CREATE CONSTRAINT degree_id  IF NOT EXISTS FOR (d:Degree)       REQUIRE d.degree_id IS UNIQUE;
CREATE CONSTRAINT policy_id  IF NOT EXISTS FOR (p:StreamPolicy) REQUIRE p.policy_id IS UNIQUE;
