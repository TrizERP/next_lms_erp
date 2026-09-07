// =====================================================================
//  FINANCE — fee masters and non-authoritative aggregates
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//      php artisan neo4j:csv-export --module=finance
//      php artisan neo4j:cypher --module=finance
//
//  EVERY NODE AND EDGE HERE CARRIES `authoritative: false`.
//  MariaDB is the ledger. What the graph adds is structure — which fee applies to
//  which class, which learner owes which head, who spent from which petty-cash head —
//  and counts. Amounts that were already aggregated ride along so a dashboard can
//  read them, but nothing is DERIVED here: no per-student liability is computed from
//  the schedule, because that would put money arithmetic in an eventually-consistent
//  store that nobody reconciles.
//
//  `fees_breackoff` (182,379 rows) has NO student_id. It is a fee SCHEDULE per
//  (grade, standard, quota, month), so it attaches to :Standard, not to a learner.
//  `fees_breakoff_other` does have student_id and is the one that reaches :StuDetail.
//
//  ADDITIVE. MERGE + ON CREATE SET only. No protected relationship type is written.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS
// ---------------------------------------------------------------------

CREATE CONSTRAINT feehead_feeheadId_unique IF NOT EXISTS
FOR (fh:FeeHead) REQUIRE fh.feeheadId IS UNIQUE;

CREATE CONSTRAINT feetitle_feetitleId_unique IF NOT EXISTS
FOR (ft:FeeTitle) REQUIRE ft.feetitleId IS UNIQUE;

CREATE CONSTRAINT feetitlemaster_feetitlemasterId_unique IF NOT EXISTS
FOR (ftm:FeeTitleMaster) REQUIRE ftm.feetitlemasterId IS UNIQUE;

CREATE CONSTRAINT feeotherhead_feeotherheadId_unique IF NOT EXISTS
FOR (fo:FeeOtherHead) REQUIRE fo.feeotherheadId IS UNIQUE;

CREATE CONSTRAINT feeconfig_feeconfigId_unique IF NOT EXISTS
FOR (fc:FeeConfig) REQUIRE fc.feeconfigId IS UNIQUE;

CREATE CONSTRAINT latefeerule_latefeeruleId_unique IF NOT EXISTS
FOR (lf:LateFeeRule) REQUIRE lf.latefeeruleId IS UNIQUE;

CREATE CONSTRAINT feemonth_feemonthId_unique IF NOT EXISTS
FOR (fm:FeeMonth) REQUIRE fm.feemonthId IS UNIQUE;

CREATE CONSTRAINT feecircular_feecircularId_unique IF NOT EXISTS
FOR (fcr:FeeCircular) REQUIRE fcr.feecircularId IS UNIQUE;

CREATE CONSTRAINT feecanceltype_feecanceltypeId_unique IF NOT EXISTS
FOR (fct:FeeCancelType) REQUIRE fct.feecanceltypeId IS UNIQUE;

CREATE CONSTRAINT bank_bankId_unique IF NOT EXISTS
FOR (bk:Bank) REQUIRE bk.bankId IS UNIQUE;

CREATE CONSTRAINT receiptbook_receiptbookId_unique IF NOT EXISTS
FOR (rb:ReceiptBook) REQUIRE rb.receiptbookId IS UNIQUE;

CREATE CONSTRAINT pettycashhead_pettycashheadId_unique IF NOT EXISTS
FOR (pc:PettyCashHead) REQUIRE pc.pettycashheadId IS UNIQUE;

CREATE CONSTRAINT donation_donationId_unique IF NOT EXISTS
FOR (dn:Donation) REQUIRE dn.donationId IS UNIQUE;


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///fees_head_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fh:FeeHead {feeheadId: toInteger(trim(row.id))})
ON CREATE SET
  fh.code             = CASE WHEN trim(coalesce(row.code, '')) = '' THEN null ELSE trim(row.code) END,
  fh.head_title       = CASE WHEN trim(coalesce(row.head_title, '')) = '' THEN null ELSE trim(row.head_title) END,
  fh.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  fh.mandatory        = CASE WHEN trim(coalesce(row.mandatory, '')) = '' THEN null ELSE trim(row.mandatory) END,
  fh.syear            = toInteger(trim(row.syear)),
  fh.displayLabel     = "FeeHead:" + trim(coalesce(row.head_title, '')),
  fh.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  fh.authoritative    = false,
  fh.src              = "fees_head_master"
RETURN count(fh) AS feeHeadProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_title.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ft:FeeTitle {feetitleId: toInteger(trim(row.id))})
ON CREATE SET
  ft.fees_title       = CASE WHEN trim(coalesce(row.fees_title, '')) = '' THEN null ELSE trim(row.fees_title) END,
  ft.display_name     = CASE WHEN trim(coalesce(row.display_name, '')) = '' THEN null ELSE trim(row.display_name) END,
  ft.cumulative_name  = CASE WHEN trim(coalesce(row.cumulative_name, '')) = '' THEN null ELSE trim(row.cumulative_name) END,
  ft.title_master_id  = toInteger(trim(row.fees_title_id)),
  ft.other_fee_id     = toInteger(trim(row.other_fee_id)),
  ft.mandatory        = CASE WHEN trim(coalesce(row.mandatory, '')) = '' THEN null ELSE trim(row.mandatory) END,
  ft.sort_order       = toInteger(trim(row.sort_order)),
  ft.syear            = toInteger(trim(row.syear)),
  ft.displayLabel     = "FeeTitle:" + trim(coalesce(row.display_name, '')),
  ft.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ft.authoritative    = false,
  ft.src              = "fees_title"
RETURN count(ft) AS feeTitleProcessed;


// No tenant column — global reference data.
LOAD CSV WITH HEADERS FROM 'file:///fees_title_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ftm:FeeTitleMaster {feetitlemasterId: toInteger(trim(row.id))})
ON CREATE SET
  ftm.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ftm.fee_paid_title   = CASE WHEN trim(coalesce(row.fee_paid_title, '')) = '' THEN null ELSE trim(row.fee_paid_title) END,
  ftm.displayLabel     = "FeeTitleMaster:" + trim(coalesce(row.title, '')),
  ftm.sub_institute_id = 0,
  ftm.scope            = "global",
  ftm.authoritative    = false,
  ftm.src              = "fees_title_master"
RETURN count(ftm) AS feeTitleMasterProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_other_head.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fo:FeeOtherHead {feeotherheadId: toInteger(trim(row.id))})
ON CREATE SET
  fo.display_name     = CASE WHEN trim(coalesce(row.display_name, '')) = '' THEN null ELSE trim(row.display_name) END,
  fo.amount           = toFloat(trim(row.amount)),
  fo.include_imprest  = CASE WHEN trim(coalesce(row.include_imprest, '')) = '' THEN null ELSE trim(row.include_imprest) END,
  fo.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  fo.sort_order       = toInteger(trim(row.sort_order)),
  fo.syear            = toInteger(trim(row.syear)),
  fo.displayLabel     = "FeeOtherHead:" + trim(coalesce(row.display_name, '')),
  fo.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  fo.authoritative    = false,
  fo.src              = "fees_other_head"
RETURN count(fo) AS feeOtherHeadProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_config_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fc:FeeConfig {feeconfigId: toInteger(trim(row.id))})
ON CREATE SET
  fc.institute_name     = CASE WHEN trim(coalesce(row.institute_name, '')) = '' THEN null ELSE trim(row.institute_name) END,
  fc.late_fees_amount   = toFloat(trim(row.late_fees_amount)),
  fc.send_sms           = CASE WHEN trim(coalesce(row.send_sms, '')) = '' THEN null ELSE trim(row.send_sms) END,
  fc.send_email         = CASE WHEN trim(coalesce(row.send_email, '')) = '' THEN null ELSE trim(row.send_email) END,
  fc.auto_head_counting = CASE WHEN trim(coalesce(row.auto_head_counting, '')) = '' THEN null ELSE trim(row.auto_head_counting) END,
  fc.syear              = toInteger(trim(row.syear)),
  fc.displayLabel       = "FeeConfig:" + trim(coalesce(row.institute_name, '')),
  fc.sub_institute_id   = toInteger(trim(row.sub_institute_id)),
  fc.authoritative      = false,
  fc.src                = "fees_config_master"
RETURN count(fc) AS feeConfigProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_late_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (lf:LateFeeRule {latefeeruleId: toInteger(trim(row.id))})
ON CREATE SET
  lf.late_date        = CASE WHEN trim(coalesce(row.late_date, '')) = '' THEN null ELSE trim(row.late_date) END,
  lf.fine_type        = CASE WHEN trim(coalesce(row.fine_type, '')) = '' THEN null ELSE trim(row.fine_type) END,
  lf.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  lf.standard_id      = toInteger(trim(row.standard_id)),
  lf.term_id          = toInteger(trim(row.term_id)),
  lf.syear            = toInteger(trim(row.syear)),
  lf.displayLabel     = "LateFeeRule:" + trim(coalesce(row.late_date, '')),
  lf.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  lf.authoritative    = false,
  lf.src              = "fees_late_master"
RETURN count(lf) AS lateFeeRuleProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_month_header.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fm:FeeMonth {feemonthId: toInteger(trim(row.id))})
ON CREATE SET
  fm.month_id         = toInteger(trim(row.month_id)),
  fm.header           = CASE WHEN trim(coalesce(row.header, '')) = '' THEN null ELSE trim(row.header) END,
  fm.displayLabel     = "FeeMonth:" + trim(coalesce(row.header, '')),
  fm.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  fm.authoritative    = false,
  fm.src              = "fees_month_header"
RETURN count(fm) AS feeMonthProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_circular_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fcr:FeeCircular {feecircularId: toInteger(trim(row.id))})
ON CREATE SET
  fcr.bank_name        = CASE WHEN trim(coalesce(row.bank_name, '')) = '' THEN null ELSE trim(row.bank_name) END,
  fcr.branch           = CASE WHEN trim(coalesce(row.branch, '')) = '' THEN null ELSE trim(row.branch) END,
  fcr.shift            = CASE WHEN trim(coalesce(row.shift, '')) = '' THEN null ELSE trim(row.shift) END,
  fcr.form_no          = CASE WHEN trim(coalesce(row.form_no, '')) = '' THEN null ELSE trim(row.form_no) END,
  fcr.paid_collection  = CASE WHEN trim(coalesce(row.paid_collection, '')) = '' THEN null ELSE trim(row.paid_collection) END,
  fcr.standard_id      = toInteger(trim(row.standard_id)),
  fcr.grade_id         = toInteger(trim(row.grade_id)),
  fcr.syear            = toInteger(trim(row.syear)),
  fcr.displayLabel     = "FeeCircular:" + trim(coalesce(row.form_no, '')),
  fcr.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  fcr.authoritative    = false,
  fcr.src              = "fees_circular_master"
RETURN count(fcr) AS feeCircularProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_cancel_type.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fct:FeeCancelType {feecanceltypeId: toInteger(trim(row.id))})
ON CREATE SET
  fct.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  fct.displayLabel     = "FeeCancelType:" + trim(coalesce(row.title, '')),
  fct.sub_institute_id = 0,
  fct.scope            = "global",
  fct.authoritative    = false,
  fct.src              = "fees_cancel_type"
RETURN count(fct) AS feeCancelTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///bank_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (bk:Bank {bankId: toInteger(trim(row.id))})
ON CREATE SET
  bk.bank_name        = CASE WHEN trim(coalesce(row.bank_name, '')) = '' THEN null ELSE trim(row.bank_name) END,
  bk.displayLabel     = "Bank:" + trim(coalesce(row.bank_name, '')),
  bk.sub_institute_id = 0,
  bk.scope            = "global",
  bk.authoritative    = false,
  bk.src              = "bank_master"
RETURN count(bk) AS bankProcessed;


LOAD CSV WITH HEADERS FROM 'file:///fees_receipt_book_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (rb:ReceiptBook {receiptbookId: toInteger(trim(row.id))})
ON CREATE SET
  rb.receipt_id           = toInteger(trim(row.receipt_id)),
  rb.receipt_prefix       = CASE WHEN trim(coalesce(row.receipt_prefix, '')) = '' THEN null ELSE trim(row.receipt_prefix) END,
  rb.receipt_postfix      = CASE WHEN trim(coalesce(row.receipt_postfix, '')) = '' THEN null ELSE trim(row.receipt_postfix) END,
  rb.last_receipt_number  = CASE WHEN trim(coalesce(row.last_receipt_number, '')) = '' THEN null ELSE trim(row.last_receipt_number) END,
  rb.fees_head_id         = toInteger(trim(row.fees_head_id)),
  rb.standard_id          = toInteger(trim(row.standard_id)),
  rb.grade_id             = toInteger(trim(row.grade_id)),
  rb.status               = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  rb.syear                = toInteger(trim(row.syear)),
  rb.displayLabel         = "ReceiptBook:" + trim(coalesce(row.receipt_prefix, '')),
  rb.sub_institute_id     = toInteger(trim(row.sub_institute_id)),
  rb.authoritative        = false,
  rb.src                  = "fees_receipt_book_master"
RETURN count(rb) AS receiptBookProcessed;


LOAD CSV WITH HEADERS FROM 'file:///petty_cash_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (pc:PettyCashHead {pettycashheadId: toInteger(trim(row.id))})
ON CREATE SET
  pc.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  pc.displayLabel     = "PettyCashHead:" + trim(coalesce(row.title, '')),
  pc.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  pc.authoritative    = false,
  pc.src              = "petty_cash_master"
RETURN count(pc) AS pettyCashHeadProcessed;


// A donation is its own record rather than a self-loop on :Institute — 26 rows, each
// with a donor, a head and a receipt.
LOAD CSV WITH HEADERS FROM 'file:///donation_collection.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (dn:Donation {donationId: toInteger(trim(row.id))})
ON CREATE SET
  dn.donar_id         = toInteger(trim(row.donar_id)),
  dn.donation_head    = CASE WHEN trim(coalesce(row.donation_head, '')) = '' THEN null ELSE trim(row.donation_head) END,
  dn.donation_amount  = toFloat(trim(row.donation_amount)),
  dn.payment_mode     = CASE WHEN trim(coalesce(row.payment_mode, '')) = '' THEN null ELSE trim(row.payment_mode) END,
  dn.reciept_no       = CASE WHEN trim(coalesce(row.reciept_no, '')) = '' THEN null ELSE trim(row.reciept_no) END,
  dn.paid_date        = CASE WHEN trim(coalesce(row.paid_date, '')) = '' THEN null ELSE trim(row.paid_date) END,
  dn.bank_name        = CASE WHEN trim(coalesce(row.bank_name, '')) = '' THEN null ELSE trim(row.bank_name) END,
  dn.displayLabel     = "Donation:" + trim(coalesce(row.reciept_no, '')),
  dn.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  dn.authoritative    = false,
  dn.src              = "donation_collection"
RETURN count(dn) AS donationProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///fees_title.csv' AS row
WITH row WHERE row.fees_title_id IS NOT NULL AND trim(row.fees_title_id) <> '' AND trim(row.fees_title_id) <> '0'
MATCH (ft:FeeTitle {feetitleId: toInteger(trim(row.id))})
MATCH (ftm:FeeTitleMaster {feetitlemasterId: toInteger(trim(row.fees_title_id))})
MERGE (ft)-[:OF_TITLE_MASTER]->(ftm)
RETURN count(*) AS ofTitleMaster;


LOAD CSV WITH HEADERS FROM 'file:///fees_late_master.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard_id IS NOT NULL AND trim(row.standard_id) <> '' AND trim(row.standard_id) <> '0'
MATCH (lf:LateFeeRule {latefeeruleId: toInteger(trim(row.id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH lf, coalesce(n1, n2) AS st
WHERE st IS NOT NULL
MERGE (lf)-[:APPLIES_TO_STANDARD]->(st)
RETURN count(*) AS lateFeeApplies;


LOAD CSV WITH HEADERS FROM 'file:///fees_circular_master.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard_id IS NOT NULL AND trim(row.standard_id) <> '' AND trim(row.standard_id) <> '0'
MATCH (fcr:FeeCircular {feecircularId: toInteger(trim(row.id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH fcr, coalesce(n1, n2) AS st
WHERE st IS NOT NULL
MERGE (fcr)-[:FOR_STANDARD]->(st)
RETURN count(*) AS feeCircularForStandard;


// FeeConfig -> AcademicYear, the fee calendar for a year.
LOAD CSV WITH HEADERS FROM 'file:///fees_map_years.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.syear IS NOT NULL AND trim(row.syear) <> ''
MATCH (ay:AcademicYear)
WHERE ay.uid STARTS WITH 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear))) + ':'
WITH row, ay ORDER BY ay.uid
WITH row, head(collect(ay)) AS ay
MATCH (i:Institute {uid: 'Institute:' + toString(toInteger(trim(row.sub_institute_id))) + ':0:'
                          + toString(toInteger(trim(row.sub_institute_id)))})
MERGE (i)-[r:FEE_YEAR {syear: toInteger(trim(row.syear)), type: trim(coalesce(row.type, ''))}]->(ay)
ON CREATE SET
  r.from_month       = toInteger(trim(row.from_month)),
  r.to_month         = toInteger(trim(row.to_month)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "fees_map_years"
RETURN count(r) AS feeYear;


// Institute -> Donation.
LOAD CSV WITH HEADERS FROM 'file:///donation_collection.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (dn:Donation {donationId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[r:RECEIVED_DONATION]->(dn)
ON CREATE SET r.authoritative = false
RETURN count(r) AS receivedDonation;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES
// ---------------------------------------------------------------------

// The fee SCHEDULE: which fee title applies to which class, in which year, for which
// quota. 182,379 rows -> 10,178 edges. `fee_type_id` resolves to fees_title on 499 of
// 500 distinct values.
LOAD CSV WITH HEADERS FROM 'file:///fees_breackoff_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.fee_type_id IS NOT NULL AND row.standard_id IS NOT NULL

MATCH (ft:FeeTitle {feetitleId: toInteger(trim(row.fee_type_id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH row, ft, coalesce(n1, n2) AS st
WHERE st IS NOT NULL

MERGE (ft)-[r:APPLIES_TO {syear: toInteger(trim(row.syear)),
                          quota: trim(coalesce(row.quota, ''))}]->(st)
ON CREATE SET
  r.months           = toInteger(trim(row.months)),
  r.total_amount     = toFloat(trim(row.total_amount)),
  r.rows_merged      = toInteger(trim(row.rows_merged)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "fees_breackoff"
RETURN count(r) AS appliesTo;


// What a learner owes under the "other fees" heads. This is the one breakoff table
// that carries a student_id.
LOAD CSV WITH HEADERS FROM 'file:///fees_breakoff_other_agg.csv' AS row
WITH row WHERE row.student_id IS NOT NULL AND row.fee_type_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (fo:FeeOtherHead {feeotherheadId: toInteger(trim(row.fee_type_id))})

MERGE (sd)-[r:LIABLE_FOR {syear: toInteger(trim(row.syear))}]->(fo)
ON CREATE SET
  r.months           = toInteger(trim(row.months)),
  r.total_amount     = toFloat(trim(row.total_amount)),
  r.rows_merged      = toInteger(trim(row.rows_merged)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "fees_breakoff_other"
RETURN count(r) AS liableFor;


LOAD CSV WITH HEADERS FROM 'file:///fees_other_collection_agg.csv' AS row
WITH row WHERE row.student_id IS NOT NULL AND row.deduction_head_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (fo:FeeOtherHead {feeotherheadId: toInteger(trim(row.deduction_head_id))})

MERGE (sd)-[r:PAID {syear: toInteger(trim(row.syear))}]->(fo)
ON CREATE SET
  r.receipts         = toInteger(trim(row.receipts)),
  r.total_amount     = toFloat(trim(row.total_amount)),
  r.first_paid       = CASE WHEN trim(coalesce(row.first_paid, '')) = '' THEN null ELSE trim(row.first_paid) END,
  r.last_paid        = CASE WHEN trim(coalesce(row.last_paid, '')) = '' THEN null ELSE trim(row.last_paid) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "fees_other_collection"
RETURN count(r) AS paidOtherHead;


// The main collection ledger holds 20 rows in this database. It attaches to the
// academic year because a receipt names no fee head.
LOAD CSV WITH HEADERS FROM 'file:///fees_collect_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL AND row.academic_year_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})

MERGE (sd)-[r:PAID_FEES {syear: toInteger(trim(row.syear))}]->(ay)
ON CREATE SET
  r.receipts         = toInteger(trim(row.receipts)),
  r.total_amount     = toFloat(trim(row.total_amount)),
  r.first_paid       = CASE WHEN trim(coalesce(row.first_paid, '')) = '' THEN null ELSE trim(row.first_paid) END,
  r.last_paid        = CASE WHEN trim(coalesce(row.last_paid, '')) = '' THEN null ELSE trim(row.last_paid) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "fees_collect"
RETURN count(r) AS paidFees;


// Person -> PettyCashHead. `user_id` here is a staff member.
LOAD CSV WITH HEADERS FROM 'file:///petty_cash_agg.csv' AS row
WITH row WHERE row.user_id IS NOT NULL AND row.title_id IS NOT NULL

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (pc:PettyCashHead {pettycashheadId: toInteger(trim(row.title_id))})

MERGE (person)-[r:PETTY_CASH]->(pc)
ON CREATE SET
  r.entries          = toInteger(trim(row.entries)),
  r.total_amount     = toFloat(trim(row.total_amount)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "petty_cash"
RETURN count(r) AS pettyCash;


// @section verify
// ---------------------------------------------------------------------
// 5. VERIFY
// ---------------------------------------------------------------------

MATCH (fh:FeeHead) RETURN 'FeeHead nodes' AS check, count(fh) AS n;
MATCH (ft:FeeTitle) RETURN 'FeeTitle nodes' AS check, count(ft) AS n;
MATCH (fo:FeeOtherHead) RETURN 'FeeOtherHead nodes' AS check, count(fo) AS n;
MATCH (rb:ReceiptBook) RETURN 'ReceiptBook nodes' AS check, count(rb) AS n;
MATCH (bk:Bank) RETURN 'Bank nodes' AS check, count(bk) AS n;
MATCH (dn:Donation) RETURN 'Donation nodes' AS check, count(dn) AS n;
MATCH (:FeeTitle)-[r:APPLIES_TO]->(:Standard) RETURN 'APPLIES_TO' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:LIABLE_FOR]->(:FeeOtherHead) RETURN 'LIABLE_FOR' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:PAID]->(:FeeOtherHead) RETURN 'PAID' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:PAID_FEES]->(:AcademicYear) RETURN 'PAID_FEES' AS check, count(r) AS n;
MATCH ()-[r:PETTY_CASH]->(:PettyCashHead) RETURN 'PETTY_CASH' AS check, count(r) AS n;
MATCH (:Institute)-[r:RECEIVED_DONATION]->(:Donation) RETURN 'RECEIVED_DONATION' AS check, count(r) AS n;
MATCH (:Institute)-[r:FEE_YEAR]->(:AcademicYear) RETURN 'FEE_YEAR' AS check, count(r) AS n;
MATCH (n) WHERE n.authoritative = false RETURN 'nodes marked non-authoritative' AS check, count(n) AS n;
