-- Simulation Learning in K12: independent reconciliation. READ-ONLY (SELECT only), runs on MySQL/MariaDB.
-- Run it against the K12 database after an import and read every row: each check returns the offending rows,
-- so ZERO ROWS = PASS. The one summary query at the end prints the counts a human compares with the package files.
-- It does not depend on any PHP in this repo; it only needs the table and column names in CONTRACT.md.

-- 1. A link whose simulation is missing or removed.
SELECT 'orphan link: simulation missing' AS failed_check, l.id AS link_id, l.simulation_id
FROM lms_simulation_concept l
LEFT JOIN lms_simulation s ON s.id = l.simulation_id AND s.deleted_at IS NULL
WHERE s.id IS NULL;

-- 2. A link whose concept is missing.
SELECT 'orphan link: concept missing' AS failed_check, l.id AS link_id, l.concept_id
FROM lms_simulation_concept l
LEFT JOIN lms_concept c ON c.id = l.concept_id
WHERE c.id IS NULL;

-- 3. Denormalised columns that disagree with the concept (standard, subject, chapter, topic).
SELECT 'link disagrees with its concept' AS failed_check, l.id AS link_id, l.concept_id,
       l.standard_id, c.standard_id AS concept_standard, l.subject_id, c.subject_id AS concept_subject,
       l.chapter_id, c.chapter_id AS concept_chapter, l.topic_id, c.topic_id AS concept_topic
FROM lms_simulation_concept l
JOIN lms_concept c ON c.id = l.concept_id
WHERE l.standard_id <> c.standard_id OR l.subject_id <> c.subject_id OR l.chapter_id <> c.chapter_id
   OR NOT (l.topic_id <=> c.topic_id);

-- 4. A concept that is not a tenant-1 (platform) concept.
SELECT 'link to a non-platform concept' AS failed_check, l.id AS link_id, l.concept_id, c.sub_institute_id
FROM lms_simulation_concept l
JOIN lms_concept c ON c.id = l.concept_id
WHERE c.sub_institute_id <> 1;

-- 5. Duplicate links (should be impossible: UNIQUE simulation_id + concept_id).
SELECT 'duplicate link' AS failed_check, simulation_id, concept_id, COUNT(*) AS n
FROM lms_simulation_concept GROUP BY simulation_id, concept_id HAVING COUNT(*) > 1;

-- 6. A published link that nobody reviewed (learners see published links only: it needs a reviewer and a time).
SELECT 'published link without reviewer' AS failed_check, id AS link_id, simulation_id, concept_id
FROM lms_simulation_concept
WHERE review_status = 'published' AND (reviewed_by IS NULL OR reviewed_at IS NULL);

-- 7. A current version that does not exist, or whose stored text no longer hashes to its recorded sha256.
SELECT 'current version missing' AS failed_check, s.id AS simulation_id, s.slug, s.current_version
FROM lms_simulation s
LEFT JOIN lms_simulation_version v ON v.simulation_id = s.id AND v.version = s.current_version
WHERE s.deleted_at IS NULL AND s.current_version > 0 AND v.id IS NULL;

SELECT 'spec text does not match its sha256' AS failed_check, v.simulation_id, v.version, v.sha256 AS recorded, SHA2(v.spec, 256) AS recomputed
FROM lms_simulation_version v
WHERE v.sha256 <> SHA2(v.spec, 256);

-- 8. The Traffic Control Room must NOT be in K12.
SELECT 'traffic-control-room present' AS failed_check, id, slug FROM lms_simulation WHERE slug = 'traffic-control-room';

-- 9. An asset row with no licence (every downloaded model needs its credit for CC BY).
SELECT 'asset without licence' AS failed_check, id, sha256, name
FROM lms_simulation_asset WHERE deleted_at IS NULL AND (licence IS NULL OR licence = '');

-- 10. Simulations visible to learners (published, linked through a published link) must be inside tenant 1.
SELECT 'learner-visible simulation outside the platform tenant' AS failed_check, s.id, s.slug, s.sub_institute_id
FROM lms_simulation s
JOIN lms_simulation_concept l ON l.simulation_id = s.id AND l.review_status = 'published'
WHERE s.status = 'published' AND s.deleted_at IS NULL AND s.sub_institute_id <> 1;

-- Summary (not a check): compare by eye with the four package files and mapping.json (expected: 4 simulations, 16 links).
SELECT s.slug, s.title, s.status, s.current_version,
       (SELECT COUNT(*) FROM lms_simulation_concept l WHERE l.simulation_id = s.id) AS links,
       (SELECT COUNT(*) FROM lms_simulation_concept l WHERE l.simulation_id = s.id AND l.review_status = 'published') AS links_published
FROM lms_simulation s WHERE s.deleted_at IS NULL ORDER BY s.slug;
