-- ============================================================
-- PAL V4 - Complete Database Schema (All Tables)
-- Generated for next_lms_erp
-- Run this in phpMyAdmin, MySQL Workbench, or via a privileged user
-- ============================================================

-- 1. Core Domain
CREATE TABLE `pal_subjects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `grade_level` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_concepts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` bigint unsigned DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `bloom_level` tinyint unsigned DEFAULT 1,
  `abstractness_level` tinyint unsigned DEFAULT 1,
  `requires_visual` tinyint(1) DEFAULT 0,
  `requires_manipulation` tinyint(1) DEFAULT 0,
  `requires_simulation` tinyint(1) DEFAULT 0,
  `recommended_pedagogy` varchar(191) DEFAULT NULL,
  `pedagogy_tags` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_concepts_subject_id_bloom_level_index` (`subject_id`,`bloom_level`),
  CONSTRAINT `pal_concepts_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `pal_subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_competencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `concept_id` bigint unsigned DEFAULT NULL,
  `mastery_score` double DEFAULT 0,
  `bloom_level` tinyint unsigned DEFAULT 1,
  `proficiency_trend` varchar(191) DEFAULT NULL,
  `last_assessed` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_competencies_learner_id_concept_id_index` (`learner_id`,`concept_id`),
  KEY `pal_competencies_mastery_score_index` (`mastery_score`),
  CONSTRAINT `pal_competencies_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `pal_subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pal_competencies_concept_id_foreign` FOREIGN KEY (`concept_id`) REFERENCES `pal_concepts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Sessions & Telemetry
CREATE TABLE `pal_learning_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `status` varchar(191) DEFAULT 'active',
  `difficulty_level` tinyint unsigned DEFAULT 1,
  `duration_minutes` int unsigned DEFAULT 0,
  `interaction_count` int unsigned DEFAULT 0,
  `engagement_score` double DEFAULT 0,
  `mastery_score` double DEFAULT 0,
  `device_type` varchar(191) DEFAULT NULL,
  `initiated_by` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_learning_sessions_learner_id_status_index` (`learner_id`,`status`),
  CONSTRAINT `pal_learning_sessions_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `pal_subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_session_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `session_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(191) NOT NULL,
  `event_data` json DEFAULT NULL,
  `duration_seconds` int unsigned DEFAULT 0,
  `response_time_ms` int unsigned DEFAULT 0,
  `metadata` json DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_session_events_learner_id_session_id_event_type_index` (`learner_id`,`session_id`,`event_type`),
  CONSTRAINT `pal_session_events_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `pal_learning_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_assessment_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `response_time_ms` int unsigned DEFAULT 0,
  `score` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_assessment_results_learner_id_is_correct_index` (`learner_id`,`is_correct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_telemetry_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint unsigned NOT NULL,
  `verb` varchar(191) NOT NULL,
  `object_id` varchar(191) DEFAULT NULL,
  `context_id` json DEFAULT NULL,
  `result` json DEFAULT NULL,
  `raw_statement` json DEFAULT NULL,
  `duration_seconds` int unsigned DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_telemetry_events_actor_id_verb_index` (`actor_id`,`verb`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Misconceptions & Remediation
CREATE TABLE `pal_misconceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `concept_id` bigint unsigned DEFAULT NULL,
  `pattern` varchar(191) NOT NULL,
  `category` varchar(191) DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `frequency` int unsigned DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `pal_misconceptions_concept_id_foreign` FOREIGN KEY (`concept_id`) REFERENCES `pal_concepts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_learner_misconceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `concept_id` bigint unsigned DEFAULT NULL,
  `misconception_id` bigint unsigned NOT NULL,
  `status` varchar(191) DEFAULT 'active',
  `severity` tinyint unsigned DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_learner_misconceptions_learner_id_status_index` (`learner_id`,`status`),
  CONSTRAINT `pal_learner_misconceptions_concept_id_foreign` FOREIGN KEY (`concept_id`) REFERENCES `pal_concepts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pal_learner_misconceptions_misconception_id_foreign` FOREIGN KEY (`misconception_id`) REFERENCES `pal_misconceptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_remediations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `misconception_id` bigint unsigned NOT NULL,
  `type` varchar(191) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `pedagogy` varchar(191) DEFAULT NULL,
  `effectiveness_score` double DEFAULT 0,
  `status` varchar(191) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `pal_remediations_misconception_id_foreign` FOREIGN KEY (`misconception_id`) REFERENCES `pal_misconceptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_remediation_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `misconception_id` bigint unsigned DEFAULT NULL,
  `status` varchar(191) DEFAULT 'in_progress',
  `attempts` int unsigned DEFAULT 0,
  `success_rate` double DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_remediation_sessions_learner_id_index` (`learner_id`),
  CONSTRAINT `pal_remediation_sessions_misconception_id_foreign` FOREIGN KEY (`misconception_id`) REFERENCES `pal_misconceptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Content
CREATE TABLE `pal_contents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `concept_id` bigint unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `body` text DEFAULT NULL,
  `content_type` varchar(191) DEFAULT NULL,
  `format` varchar(191) DEFAULT NULL,
  `difficulty_level` tinyint unsigned DEFAULT 1,
  `bloom_level` tinyint unsigned DEFAULT 1,
  `pedagogy_tags` json DEFAULT NULL,
  `h5p_type` varchar(191) DEFAULT NULL,
  `status` varchar(191) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `pal_contents_concept_id_foreign` FOREIGN KEY (`concept_id`) REFERENCES `pal_concepts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_content_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `content_id` bigint unsigned NOT NULL,
  `event_type` varchar(191) DEFAULT NULL,
  `engagement_score` int unsigned DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_content_recommendations_learner_id_content_id_index` (`learner_id`,`content_id`),
  CONSTRAINT `pal_content_recommendations_content_id_foreign` FOREIGN KEY (`content_id`) REFERENCES `pal_contents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Pedagogy & Reflection
CREATE TABLE `pal_reflections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `prompt_type` varchar(191) DEFAULT NULL,
  `learner_response` text NOT NULL,
  `quality_score` tinyint unsigned DEFAULT 0,
  `context_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_reflections_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_pedagogy_effectiveness` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `pedagogy_type` varchar(191) NOT NULL,
  `outcome` varchar(191) DEFAULT NULL,
  `effectiveness_score` double DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_pedagogy_effectiveness_learner_id_pedagogy_type_index` (`learner_id`,`pedagogy_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_learner_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `pref_key` varchar(191) NOT NULL,
  `pref_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pal_learner_preferences_learner_id_pref_key_unique` (`learner_id`,`pref_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Learner State
CREATE TABLE `pal_learner_states` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `session_id` bigint unsigned DEFAULT NULL,
  `competency_state` json DEFAULT NULL,
  `behavioral_state` json DEFAULT NULL,
  `motivational_state` json DEFAULT NULL,
  `social_state` json DEFAULT NULL,
  `contextual_state` json DEFAULT NULL,
  `metacognition_state` json DEFAULT NULL,
  `disengagement_risk` double DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_learner_states_learner_id_index` (`learner_id`),
  CONSTRAINT `pal_learner_states_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `pal_learning_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Behavioral / Social / Metacognition
CREATE TABLE `pal_collaboration_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `activity_type` varchar(191) DEFAULT NULL,
  `peer_count` int unsigned DEFAULT 0,
  `engagement_score` double DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_collaboration_activities_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_classroom_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `participation_score` double DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_classroom_activities_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_discussions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `content` text DEFAULT NULL,
  `upvotes` int unsigned DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_discussions_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_group_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `performance_score` double DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_group_activities_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_self_corrections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `successful` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_self_corrections_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_learning_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `plan_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_learning_plans_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_strategy_selections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `strategy_type` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_strategy_selections_learner_id_strategy_type_index` (`learner_id`,`strategy_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_learning_journals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `entry` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_learning_journals_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Additional Analytics Tables
CREATE TABLE `pal_learning_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `event_type` varchar(191) NOT NULL,
  `event_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_learning_events_learner_id_event_type_index` (`learner_id`,`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pal_learning_patterns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `learner_id` bigint unsigned NOT NULL,
  `pattern_type` varchar(191) NOT NULL,
  `pattern_data` json DEFAULT NULL,
  `confidence` double DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pal_learning_patterns_learner_id_index` (`learner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;