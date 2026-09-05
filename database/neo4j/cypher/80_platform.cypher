// =====================================================================
//  PLATFORM — calendar, tasks, PTM, leaderboard, communication
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//      php artisan neo4j:csv-export --module=platform
//      php artisan neo4j:cypher --module=platform
//
//  The four send logs (SMS, WhatsApp, portal message, email) are ~90,000 rows and
//  become ~one edge per (learner, channel, year). A send log mirrored row-for-row
//  would add ninety thousand nodes that answer no question a traversal asks; the
//  count and the date range are what a dashboard actually reads.
//
//  ADDITIVE. MERGE + ON CREATE SET only. No protected relationship type is written.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS
// ---------------------------------------------------------------------

CREATE CONSTRAINT calendarevent_calendareventId_unique IF NOT EXISTS
FOR (ce:CalendarEvent) REQUIRE ce.calendareventId IS UNIQUE;

CREATE CONSTRAINT task_taskId_unique IF NOT EXISTS
FOR (tk:Task) REQUIRE tk.taskId IS UNIQUE;

CREATE CONSTRAINT timeslot_timeslotId_unique IF NOT EXISTS
FOR (tl:TimeSlot) REQUIRE tl.timeslotId IS UNIQUE;

CREATE CONSTRAINT leaderboardrule_leaderboardruleId_unique IF NOT EXISTS
FOR (lb:LeaderboardRule) REQUIRE lb.leaderboardruleId IS UNIQUE;


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///calendar_events.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ce:CalendarEvent {calendareventId: toInteger(trim(row.id))})
ON CREATE SET
  ce.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ce.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  ce.school_date      = CASE WHEN trim(coalesce(row.school_date, '')) = '' THEN null ELSE trim(row.school_date) END,
  ce.event_type       = CASE WHEN trim(coalesce(row.event_type, '')) = '' THEN null ELSE trim(row.event_type) END,
  ce.standard         = CASE WHEN trim(coalesce(row.standard, '')) = '' THEN null ELSE trim(row.standard) END,
  ce.syear            = toInteger(trim(row.syear)),
  ce.displayLabel     = "CalendarEvent:" + trim(coalesce(row.title, '')),
  ce.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ce.src              = "calendar_events"
RETURN count(ce) AS calendarEventProcessed;


LOAD CSV WITH HEADERS FROM 'file:///task.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (tk:Task {taskId: toInteger(trim(row.id))})
ON CREATE SET
  tk.task_title       = CASE WHEN trim(coalesce(row.task_title, '')) = '' THEN null ELSE trim(row.task_title) END,
  tk.task_description = CASE WHEN trim(coalesce(row.task_description, '')) = '' THEN null ELSE trim(row.task_description) END,
  tk.task_date        = CASE WHEN trim(coalesce(row.task_date, '')) = '' THEN null ELSE trim(row.task_date) END,
  tk.task_type        = CASE WHEN trim(coalesce(row.task_type, '')) = '' THEN null ELSE trim(row.task_type) END,
  tk.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  tk.status_label     = CASE WHEN trim(coalesce(row.status_label, '')) = '' THEN null ELSE trim(row.status_label) END,
  tk.approve_status   = CASE WHEN trim(coalesce(row.approve_status, '')) = '' THEN null ELSE trim(row.approve_status) END,
  tk.kra              = CASE WHEN trim(coalesce(row.kra, '')) = '' THEN null ELSE trim(row.kra) END,
  tk.kpa              = CASE WHEN trim(coalesce(row.kpa, '')) = '' THEN null ELSE trim(row.kpa) END,
  tk.required_skill   = CASE WHEN trim(coalesce(row.required_skill, '')) = '' THEN null ELSE trim(row.required_skill) END,
  tk.estimated_hours  = toFloat(trim(row.estimated_hours)),
  tk.actual_hours     = toFloat(trim(row.actual_hours)),
  tk.syear            = toInteger(trim(row.syear)),
  tk.displayLabel     = "Task:" + trim(coalesce(row.task_title, '')),
  tk.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  tk.src              = "task"
RETURN count(tk) AS taskProcessed;


LOAD CSV WITH HEADERS FROM 'file:///ptm_time_slots_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (tl:TimeSlot {timeslotId: toInteger(trim(row.id))})
ON CREATE SET
  tl.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  tl.ptm_date         = CASE WHEN trim(coalesce(row.ptm_date, '')) = '' THEN null ELSE trim(row.ptm_date) END,
  tl.from_time        = CASE WHEN trim(coalesce(row.from_time, '')) = '' THEN null ELSE trim(row.from_time) END,
  tl.to_time          = CASE WHEN trim(coalesce(row.to_time, '')) = '' THEN null ELSE trim(row.to_time) END,
  tl.standard_id      = toInteger(trim(row.standard_id)),
  tl.division_id      = toInteger(trim(row.division_id)),
  tl.syear            = toInteger(trim(row.syear)),
  tl.displayLabel     = "TimeSlot:" + trim(coalesce(row.title, '')),
  tl.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  tl.src              = "ptm_time_slots_master"
RETURN count(tl) AS timeSlotProcessed;


LOAD CSV WITH HEADERS FROM 'file:///lb_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (lb:LeaderboardRule {leaderboardruleId: toInteger(trim(row.id))})
ON CREATE SET
  lb.module_name      = CASE WHEN trim(coalesce(row.module_name, '')) = '' THEN null ELSE trim(row.module_name) END,
  lb.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  lb.points           = toFloat(trim(row.points)),
  lb.per_value        = toFloat(trim(row.per_value)),
  lb.standard_id      = toInteger(trim(row.standard_id)),
  lb.grade_id         = toInteger(trim(row.grade_id)),
  lb.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  lb.displayLabel     = "LeaderboardRule:" + trim(coalesce(row.module_name, '')),
  lb.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  lb.src              = "lb_master"
RETURN count(lb) AS leaderboardRuleProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

// Institute -> CalendarEvent.
LOAD CSV WITH HEADERS FROM 'file:///calendar_events.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (ce:CalendarEvent {calendareventId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_CALENDAR_EVENT]->(ce)
RETURN count(*) AS hasCalendarEvent;


// Person -> Task, both who it is assigned to and who raised it.
LOAD CSV WITH HEADERS FROM 'file:///task.csv' AS row
WITH row WHERE row.task_allocated_to IS NOT NULL AND trim(row.task_allocated_to) <> ''
  AND trim(row.task_allocated_to) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.task_allocated_to))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.task_allocated_to))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (tk:Task {taskId: toInteger(trim(row.id))})
MERGE (person)-[r:ASSIGNED_TASK]->(tk)
ON CREATE SET r.src = "task.TASK_ALLOCATED_TO"
RETURN count(r) AS assignedTask;


LOAD CSV WITH HEADERS FROM 'file:///task.csv' AS row
WITH row WHERE row.created_by IS NOT NULL AND trim(row.created_by) <> '' AND trim(row.created_by) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.created_by))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.created_by))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (tk:Task {taskId: toInteger(trim(row.id))})
MERGE (tk)-[r:CREATED_BY]->(person)
ON CREATE SET r.src = "task.CREATED_BY"
RETURN count(r) AS taskCreatedBy;


// TimeSlot -> Standard / Division.
LOAD CSV WITH HEADERS FROM 'file:///ptm_time_slots_master.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard_id IS NOT NULL AND trim(row.standard_id) <> '' AND trim(row.standard_id) <> '0'
MATCH (tl:TimeSlot {timeslotId: toInteger(trim(row.id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH tl, coalesce(n1, n2) AS st
WHERE st IS NOT NULL
MERGE (tl)-[:FOR_STANDARD]->(st)
RETURN count(*) AS slotForStandard;


// PTM booking: the learner books a slot with a member of staff.
LOAD CSV WITH HEADERS FROM 'file:///ptm_booking_master.csv' AS row
WITH row WHERE row.student_id IS NOT NULL AND trim(row.student_id) <> '' AND trim(row.student_id) <> '0'
  AND row.teacher_id IS NOT NULL AND trim(row.teacher_id) <> '0'
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.teacher_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.teacher_id))})
WITH row, sd, coalesce(t, s) AS person
WHERE person IS NOT NULL
MERGE (sd)-[r:BOOKED_PTM {bookingId: toInteger(trim(row.id))}]->(person)
ON CREATE SET
  r.booking_date     = CASE WHEN trim(coalesce(row.booking_date, '')) = '' THEN null ELSE trim(row.booking_date) END,
  r.confirm_status   = CASE WHEN trim(coalesce(row.confirm_status, '')) = '' THEN null ELSE trim(row.confirm_status) END,
  r.attended_status  = CASE WHEN trim(coalesce(row.attended_status, '')) = '' THEN null ELSE trim(row.attended_status) END,
  r.time_slot_id     = toInteger(trim(row.time_slot_id)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "ptm_booking_master"
RETURN count(r) AS bookedPtm;


LOAD CSV WITH HEADERS FROM 'file:///ptm_booking_master.csv' AS row
WITH row WHERE row.student_id IS NOT NULL AND trim(row.student_id) <> '0'
  AND row.time_slot_id IS NOT NULL AND trim(row.time_slot_id) <> '0'
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (tl:TimeSlot {timeslotId: toInteger(trim(row.time_slot_id))})
MERGE (sd)-[r:IN_SLOT {bookingId: toInteger(trim(row.id))}]->(tl)
ON CREATE SET r.src = "ptm_booking_master"
RETURN count(r) AS inSlot;


// Person -> Content viewed. :Content exists only under the uid convention.
LOAD CSV WITH HEADERS FROM 'file:///user_activities.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.user_id IS NOT NULL AND trim(row.user_id) <> '0'
  AND row.content_id IS NOT NULL AND trim(row.content_id) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (c:Content {uid: 'Content:' + T + ':0:' + toString(toInteger(trim(row.content_id)))})
MERGE (person)-[r:VIEWED {activityId: toInteger(trim(row.id))}]->(c)
ON CREATE SET
  r.action = CASE WHEN trim(coalesce(row.action, '')) = '' THEN null ELSE trim(row.action) END,
  r.src    = "user_activities"
RETURN count(r) AS viewed;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES — communication and points
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///sms_sent_parents_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL AND row.academic_year_id IS NOT NULL
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})
MERGE (sd)-[r:COMMUNICATION {channel: "sms", syear: toInteger(trim(row.syear))}]->(ay)
ON CREATE SET
  r.messages         = toInteger(trim(row.messages)),
  r.first_sent       = CASE WHEN trim(coalesce(row.first_sent, '')) = '' THEN null ELSE trim(row.first_sent) END,
  r.last_sent        = CASE WHEN trim(coalesce(row.last_sent, '')) = '' THEN null ELSE trim(row.last_sent) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "sms_sent_parents"
RETURN count(r) AS smsCommunication;


LOAD CSV WITH HEADERS FROM 'file:///whatsapp_sent_messages_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL AND row.academic_year_id IS NOT NULL
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})
MERGE (sd)-[r:COMMUNICATION {channel: "whatsapp", syear: toInteger(trim(row.syear))}]->(ay)
ON CREATE SET
  r.messages         = toInteger(trim(row.messages)),
  r.first_sent       = CASE WHEN trim(coalesce(row.first_sent, '')) = '' THEN null ELSE trim(row.first_sent) END,
  r.last_sent        = CASE WHEN trim(coalesce(row.last_sent, '')) = '' THEN null ELSE trim(row.last_sent) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "whatsapp_sent_messages"
RETURN count(r) AS whatsappCommunication;


LOAD CSV WITH HEADERS FROM 'file:///parent_communication_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL AND row.academic_year_id IS NOT NULL
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})
MERGE (sd)-[r:COMMUNICATION {channel: "portal", syear: toInteger(trim(row.syear))}]->(ay)
ON CREATE SET
  r.messages         = toInteger(trim(row.messages)),
  r.first_sent       = CASE WHEN trim(coalesce(row.first_sent, '')) = '' THEN null ELSE trim(row.first_sent) END,
  r.last_sent        = CASE WHEN trim(coalesce(row.last_sent, '')) = '' THEN null ELSE trim(row.last_sent) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "parent_communication"
RETURN count(r) AS portalCommunication;


// Email and staff SMS key on the SENDER (a staff member), not on a learner — those
// tables have no student column.
LOAD CSV WITH HEADERS FROM 'file:///email_sent_parents_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.user_id IS NOT NULL
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (person)-[r:SENT_COMMUNICATION {channel: "email", syear: toInteger(trim(row.syear))}]->(i)
ON CREATE SET
  r.messages         = toInteger(trim(row.messages)),
  r.first_sent       = CASE WHEN trim(coalesce(row.first_sent, '')) = '' THEN null ELSE trim(row.first_sent) END,
  r.last_sent        = CASE WHEN trim(coalesce(row.last_sent, '')) = '' THEN null ELSE trim(row.last_sent) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "email_sent_parents"
RETURN count(r) AS emailCommunication;


LOAD CSV WITH HEADERS FROM 'file:///sms_sent_staff_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.staff_id IS NOT NULL
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.staff_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.staff_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (person)-[r:SENT_COMMUNICATION {channel: "sms_staff", syear: toInteger(trim(row.syear))}]->(i)
ON CREATE SET
  r.messages         = toInteger(trim(row.messages)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "sms_sent_staff"
RETURN count(r) AS staffSmsCommunication;


// Leaderboard points. `lb_points.user_id` is a tbluser id, not a learner.
LOAD CSV WITH HEADERS FROM 'file:///lb_points_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.user_id IS NOT NULL
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (person)-[r:EARNED_POINTS {module_name: trim(coalesce(row.module_name, '')),
                                 syear: toInteger(trim(row.syear))}]->(i)
ON CREATE SET
  r.points           = toFloat(trim(row.points)),
  r.awards           = toInteger(trim(row.awards)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "lb_points"
RETURN count(r) AS earnedPoints;


// @section verify
// ---------------------------------------------------------------------
// 5. VERIFY
// ---------------------------------------------------------------------

MATCH (ce:CalendarEvent) RETURN 'CalendarEvent nodes' AS check, count(ce) AS n;
MATCH (tk:Task) RETURN 'Task nodes' AS check, count(tk) AS n;
MATCH (tl:TimeSlot) RETURN 'TimeSlot nodes' AS check, count(tl) AS n;
MATCH (lb:LeaderboardRule) RETURN 'LeaderboardRule nodes' AS check, count(lb) AS n;
MATCH (:Institute)-[r:HAS_CALENDAR_EVENT]->(:CalendarEvent) RETURN 'HAS_CALENDAR_EVENT' AS check, count(r) AS n;
MATCH ()-[r:ASSIGNED_TASK]->(:Task) RETURN 'ASSIGNED_TASK' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:BOOKED_PTM]->() RETURN 'BOOKED_PTM' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:IN_SLOT]->(:TimeSlot) RETURN 'IN_SLOT' AS check, count(r) AS n;
MATCH ()-[r:VIEWED]->(:Content) RETURN 'VIEWED' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:COMMUNICATION]->(:AcademicYear) RETURN 'COMMUNICATION (learner)' AS check, count(r) AS n;
MATCH ()-[r:SENT_COMMUNICATION]->(:Institute) RETURN 'SENT_COMMUNICATION (staff)' AS check, count(r) AS n;
MATCH ()-[r:EARNED_POINTS]->(:Institute) RETURN 'EARNED_POINTS' AS check, count(r) AS n;
