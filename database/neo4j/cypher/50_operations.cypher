// =====================================================================
//  OPERATIONS — library, transport, hostel, inventory, front desk
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//      php artisan neo4j:csv-export --module=operations
//      php artisan neo4j:cypher --module=operations
//
//  Every label here is new to the graph, so nothing can collide with the reference
//  layer. Two names are chosen to avoid a collision inside this work:
//    :TransportShift — `tbluser_shift_master` already became :StaffShift in the hr
//                      module, and both id spaces start at 1.
//    :BookCopy       — `library_items` are physical copies, not titles.
//
//  Student-keyed tables attach to :StuDetail {sdId}: `library_book_circulations`
//  resolves 99.6% against tblstudent.id and 33% against the enrolment id, and
//  `transport_map_student` 100% against tblstudent.id.
//
//  ADDITIVE. MERGE + ON CREATE SET only. No protected relationship type is written.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS
// ---------------------------------------------------------------------

CREATE CONSTRAINT book_bookId_unique IF NOT EXISTS
FOR (b:Book) REQUIRE b.bookId IS UNIQUE;

CREATE CONSTRAINT bookcopy_bookcopyId_unique IF NOT EXISTS
FOR (bc:BookCopy) REQUIRE bc.bookcopyId IS UNIQUE;

CREATE CONSTRAINT route_routeId_unique IF NOT EXISTS
FOR (rt:Route) REQUIRE rt.routeId IS UNIQUE;

CREATE CONSTRAINT stop_stopId_unique IF NOT EXISTS
FOR (sp:Stop) REQUIRE sp.stopId IS UNIQUE;

CREATE CONSTRAINT vehicle_vehicleId_unique IF NOT EXISTS
FOR (v:Vehicle) REQUIRE v.vehicleId IS UNIQUE;

CREATE CONSTRAINT vehicletype_vehicletypeId_unique IF NOT EXISTS
FOR (vt:VehicleType) REQUIRE vt.vehicletypeId IS UNIQUE;

CREATE CONSTRAINT driver_driverId_unique IF NOT EXISTS
FOR (dr:Driver) REQUIRE dr.driverId IS UNIQUE;

CREATE CONSTRAINT transportshift_transportshiftId_unique IF NOT EXISTS
FOR (ts:TransportShift) REQUIRE ts.transportshiftId IS UNIQUE;

CREATE CONSTRAINT hostel_hostelId_unique IF NOT EXISTS
FOR (ho:Hostel) REQUIRE ho.hostelId IS UNIQUE;

CREATE CONSTRAINT hostelbuilding_hostelbuildingId_unique IF NOT EXISTS
FOR (hb:HostelBuilding) REQUIRE hb.hostelbuildingId IS UNIQUE;

CREATE CONSTRAINT hostelfloor_hostelfloorId_unique IF NOT EXISTS
FOR (hf:HostelFloor) REQUIRE hf.hostelfloorId IS UNIQUE;

CREATE CONSTRAINT hostelroom_hostelroomId_unique IF NOT EXISTS
FOR (hr:HostelRoom) REQUIRE hr.hostelroomId IS UNIQUE;

CREATE CONSTRAINT hosteltype_hosteltypeId_unique IF NOT EXISTS
FOR (ht:HostelType) REQUIRE ht.hosteltypeId IS UNIQUE;

CREATE CONSTRAINT roomtype_roomtypeId_unique IF NOT EXISTS
FOR (rmt:RoomType) REQUIRE rmt.roomtypeId IS UNIQUE;

CREATE CONSTRAINT hostelvisitor_hostelvisitorId_unique IF NOT EXISTS
FOR (hv:HostelVisitor) REQUIRE hv.hostelvisitorId IS UNIQUE;

CREATE CONSTRAINT inventoryitem_inventoryitemId_unique IF NOT EXISTS
FOR (it:InventoryItem) REQUIRE it.inventoryitemId IS UNIQUE;

CREATE CONSTRAINT itemcategory_itemcategoryId_unique IF NOT EXISTS
FOR (ic:ItemCategory) REQUIRE ic.itemcategoryId IS UNIQUE;

CREATE CONSTRAINT itemsubcategory_itemsubcategoryId_unique IF NOT EXISTS
FOR (isc:ItemSubCategory) REQUIRE isc.itemsubcategoryId IS UNIQUE;

CREATE CONSTRAINT itemtype_itemtypeId_unique IF NOT EXISTS
FOR (ity:ItemType) REQUIRE ity.itemtypeId IS UNIQUE;

CREATE CONSTRAINT vendor_vendorId_unique IF NOT EXISTS
FOR (vn:Vendor) REQUIRE vn.vendorId IS UNIQUE;

CREATE CONSTRAINT filelocation_filelocationId_unique IF NOT EXISTS
FOR (fl:FileLocation) REQUIRE fl.filelocationId IS UNIQUE;

CREATE CONSTRAINT visitor_visitorId_unique IF NOT EXISTS
FOR (vi:Visitor) REQUIRE vi.visitorId IS UNIQUE;

CREATE CONSTRAINT visitortype_visitortypeId_unique IF NOT EXISTS
FOR (vty:VisitorType) REQUIRE vty.visitortypeId IS UNIQUE;

CREATE CONSTRAINT inwarddocument_inwarddocumentId_unique IF NOT EXISTS
FOR (iw:InwardDocument) REQUIRE iw.inwarddocumentId IS UNIQUE;

CREATE CONSTRAINT outwarddocument_outwarddocumentId_unique IF NOT EXISTS
FOR (ow:OutwardDocument) REQUIRE ow.outwarddocumentId IS UNIQUE;

CREATE CONSTRAINT frontdeskentry_frontdeskentryId_unique IF NOT EXISTS
FOR (fd:FrontDeskEntry) REQUIRE fd.frontdeskentryId IS UNIQUE;

CREATE CONSTRAINT complaint_complaintId_unique IF NOT EXISTS
FOR (cm:Complaint) REQUIRE cm.complaintId IS UNIQUE;

CREATE CONSTRAINT circular_circularId_unique IF NOT EXISTS
FOR (ci:Circular) REQUIRE ci.circularId IS UNIQUE;

CREATE CONSTRAINT circulartype_circulartypeId_unique IF NOT EXISTS
FOR (ct:CircularType) REQUIRE ct.circulartypeId IS UNIQUE;

CREATE CONSTRAINT announcement_announcementId_unique IF NOT EXISTS
FOR (an:Announcement) REQUIRE an.announcementId IS UNIQUE;


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES — library
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///library_books.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (b:Book {bookId: toInteger(trim(row.id))})
ON CREATE SET
  b.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  b.sub_title        = CASE WHEN trim(coalesce(row.sub_title, '')) = '' THEN null ELSE trim(row.sub_title) END,
  b.author_name      = CASE WHEN trim(coalesce(row.author_name, '')) = '' THEN null ELSE trim(row.author_name) END,
  b.publisher_name   = CASE WHEN trim(coalesce(row.publisher_name, '')) = '' THEN null ELSE trim(row.publisher_name) END,
  b.isbn_issn        = CASE WHEN trim(coalesce(row.isbn_issn, '')) = '' THEN null ELSE trim(row.isbn_issn) END,
  b.edition          = CASE WHEN trim(coalesce(row.edition, '')) = '' THEN null ELSE trim(row.edition) END,
  b.publish_year     = CASE WHEN trim(coalesce(row.publish_year, '')) = '' THEN null ELSE trim(row.publish_year) END,
  b.language         = CASE WHEN trim(coalesce(row.language, '')) = '' THEN null ELSE trim(row.language) END,
  b.call_number      = CASE WHEN trim(coalesce(row.call_number, '')) = '' THEN null ELSE trim(row.call_number) END,
  b.classification   = CASE WHEN trim(coalesce(row.classification, '')) = '' THEN null ELSE trim(row.classification) END,
  b.subject          = CASE WHEN trim(coalesce(row.subject, '')) = '' THEN null ELSE trim(row.subject) END,
  b.standard         = CASE WHEN trim(coalesce(row.standard, '')) = '' THEN null ELSE trim(row.standard) END,
  b.resource_type    = CASE WHEN trim(coalesce(row.material_resource_type, '')) = '' THEN null ELSE trim(row.material_resource_type) END,
  b.doc_type         = CASE WHEN trim(coalesce(row.doc_type, '')) = '' THEN null ELSE trim(row.doc_type) END,
  b.academic_year    = CASE WHEN trim(coalesce(row.academic_year, '')) = '' THEN null ELSE trim(row.academic_year) END,
  b.pages            = toInteger(trim(row.pages)),
  b.displayLabel     = "Book:" + trim(coalesce(row.title, '')),
  b.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  b.src              = "library_books"
RETURN count(b) AS bookProcessed;


LOAD CSV WITH HEADERS FROM 'file:///library_items.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (bc:BookCopy {bookcopyId: toInteger(trim(row.id))})
ON CREATE SET
  bc.book_id          = toInteger(trim(row.book_id)),
  bc.item_code        = CASE WHEN trim(coalesce(row.item_code, '')) = '' THEN null ELSE trim(row.item_code) END,
  bc.call_number      = CASE WHEN trim(coalesce(row.call_number, '')) = '' THEN null ELSE trim(row.call_number) END,
  bc.item_status      = CASE WHEN trim(coalesce(row.item_status, '')) = '' THEN null ELSE trim(row.item_status) END,
  bc.received_date    = CASE WHEN trim(coalesce(row.received_date, '')) = '' THEN null ELSE trim(row.received_date) END,
  bc.displayLabel     = "BookCopy:" + trim(coalesce(row.item_code, '')),
  bc.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  bc.src              = "library_items"
RETURN count(bc) AS bookCopyProcessed;


// ---------------------------------------------------------------------
//    NODES — transport
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///transport_route.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (rt:Route {routeId: toInteger(trim(row.id))})
ON CREATE SET
  rt.route_name      = CASE WHEN trim(coalesce(row.route_name, '')) = '' THEN null ELSE trim(row.route_name) END,
  rt.from_time       = CASE WHEN trim(coalesce(row.from_time, '')) = '' THEN null ELSE trim(row.from_time) END,
  rt.to_time         = CASE WHEN trim(coalesce(row.to_time, '')) = '' THEN null ELSE trim(row.to_time) END,
  rt.syear           = toInteger(trim(row.syear)),
  rt.displayLabel    = "Route:" + trim(coalesce(row.route_name, '')),
  rt.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  rt.src             = "transport_route"
RETURN count(rt) AS routeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///transport_stop.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sp:Stop {stopId: toInteger(trim(row.id))})
ON CREATE SET
  sp.stop_name        = CASE WHEN trim(coalesce(row.stop_name, '')) = '' THEN null ELSE trim(row.stop_name) END,
  sp.syear            = toInteger(trim(row.syear)),
  sp.displayLabel     = "Stop:" + trim(coalesce(row.stop_name, '')),
  sp.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  sp.src              = "transport_stop"
RETURN count(sp) AS stopProcessed;


LOAD CSV WITH HEADERS FROM 'file:///transport_vehicle.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (v:Vehicle {vehicleId: toInteger(trim(row.id))})
ON CREATE SET
  v.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  v.vehicle_number   = CASE WHEN trim(coalesce(row.vehicle_number, '')) = '' THEN null ELSE trim(row.vehicle_number) END,
  v.vehicle_type_id  = toInteger(trim(row.vehicle_type)),
  v.sitting_capacity = toInteger(trim(row.sitting_capacity)),
  v.shift_id         = toInteger(trim(row.school_shift)),
  v.driver_id        = toInteger(trim(row.driver)),
  v.conductor_id     = toInteger(trim(row.conductor)),
  v.identity_number  = CASE WHEN trim(coalesce(row.vehicle_identity_number, '')) = '' THEN null ELSE trim(row.vehicle_identity_number) END,
  v.displayLabel     = "Vehicle:" + trim(coalesce(row.vehicle_number, '')),
  v.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  v.src              = "transport_vehicle"
RETURN count(v) AS vehicleProcessed;


// Global reference data — this table has no tenant column, so tenant 0 + scope
// 'global', the same treatment the batch pipeline gives its reference labels.
LOAD CSV WITH HEADERS FROM 'file:///transport_vehicle_type.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (vt:VehicleType {vehicletypeId: toInteger(trim(row.id))})
ON CREATE SET
  vt.name             = CASE WHEN trim(coalesce(row.name, '')) = '' THEN null ELSE trim(row.name) END,
  vt.displayLabel     = "VehicleType:" + trim(coalesce(row.name, '')),
  vt.sub_institute_id = 0,
  vt.scope            = "global",
  vt.src              = "transport_vehicle_type"
RETURN count(vt) AS vehicleTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///transport_driver_detail.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (dr:Driver {driverId: toInteger(trim(row.id))})
ON CREATE SET
  dr.first_name       = CASE WHEN trim(coalesce(row.first_name, '')) = '' THEN null ELSE trim(row.first_name) END,
  dr.last_name        = CASE WHEN trim(coalesce(row.last_name, '')) = '' THEN null ELSE trim(row.last_name) END,
  dr.mobile           = CASE WHEN trim(coalesce(row.mobile, '')) = '' THEN null ELSE trim(row.mobile) END,
  dr.type             = CASE WHEN trim(coalesce(row.type, '')) = '' THEN null ELSE trim(row.type) END,
  dr.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  dr.displayLabel     = "Driver:" + trim(coalesce(row.first_name, '')) + " " + trim(coalesce(row.last_name, '')),
  dr.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  dr.src              = "transport_driver_detail"
RETURN count(dr) AS driverProcessed;


LOAD CSV WITH HEADERS FROM 'file:///transport_school_shift.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ts:TransportShift {transportshiftId: toInteger(trim(row.id))})
ON CREATE SET
  ts.shift_title      = CASE WHEN trim(coalesce(row.shift_title, '')) = '' THEN null ELSE trim(row.shift_title) END,
  ts.shift_rate       = toFloat(trim(row.shift_rate)),
  ts.km_amount        = toFloat(trim(row.km_amount)),
  ts.displayLabel     = "TransportShift:" + trim(coalesce(row.shift_title, '')),
  ts.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ts.src              = "transport_school_shift"
RETURN count(ts) AS transportShiftProcessed;


// ---------------------------------------------------------------------
//    NODES — hostel
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///hostel_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ho:Hostel {hostelId: toInteger(trim(row.id))})
ON CREATE SET
  ho.name             = CASE WHEN trim(coalesce(row.name, '')) = '' THEN null ELSE trim(row.name) END,
  ho.code             = CASE WHEN trim(coalesce(row.code, '')) = '' THEN null ELSE trim(row.code) END,
  ho.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  ho.warden           = CASE WHEN trim(coalesce(row.warden, '')) = '' THEN null ELSE trim(row.warden) END,
  ho.warden_contact   = CASE WHEN trim(coalesce(row.warden_contact, '')) = '' THEN null ELSE trim(row.warden_contact) END,
  ho.hostel_type_id   = toInteger(trim(row.hostel_type_id)),
  ho.displayLabel     = "Hostel:" + trim(coalesce(row.name, '')),
  ho.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ho.src              = "hostel_master"
RETURN count(ho) AS hostelProcessed;


LOAD CSV WITH HEADERS FROM 'file:///hostel_building_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (hb:HostelBuilding {hostelbuildingId: toInteger(trim(row.id))})
ON CREATE SET
  hb.building_name    = CASE WHEN trim(coalesce(row.building_name, '')) = '' THEN null ELSE trim(row.building_name) END,
  hb.hostel_id        = toInteger(trim(row.hostel_id)),
  hb.hostel_type_id   = toInteger(trim(row.hostel_type_id)),
  hb.displayLabel     = "HostelBuilding:" + trim(coalesce(row.building_name, '')),
  hb.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  hb.src              = "hostel_building_master"
RETURN count(hb) AS hostelBuildingProcessed;


LOAD CSV WITH HEADERS FROM 'file:///hostel_floor_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (hf:HostelFloor {hostelfloorId: toInteger(trim(row.id))})
ON CREATE SET
  hf.floor_name       = CASE WHEN trim(coalesce(row.floor_name, '')) = '' THEN null ELSE trim(row.floor_name) END,
  hf.building_id      = toInteger(trim(row.building_id)),
  hf.displayLabel     = "HostelFloor:" + trim(coalesce(row.floor_name, '')),
  hf.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  hf.src              = "hostel_floor_master"
RETURN count(hf) AS hostelFloorProcessed;


LOAD CSV WITH HEADERS FROM 'file:///hostel_room_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (hr:HostelRoom {hostelroomId: toInteger(trim(row.id))})
ON CREATE SET
  hr.room_name        = CASE WHEN trim(coalesce(row.room_name, '')) = '' THEN null ELSE trim(row.room_name) END,
  hr.floor_id         = toInteger(trim(row.floor_id)),
  hr.displayLabel     = "HostelRoom:" + trim(coalesce(row.room_name, '')),
  hr.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  hr.src              = "hostel_room_master"
RETURN count(hr) AS hostelRoomProcessed;


LOAD CSV WITH HEADERS FROM 'file:///hostel_type_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ht:HostelType {hosteltypeId: toInteger(trim(row.id))})
ON CREATE SET
  ht.hostel_type      = CASE WHEN trim(coalesce(row.hostel_type, '')) = '' THEN null ELSE trim(row.hostel_type) END,
  ht.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  ht.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  ht.displayLabel     = "HostelType:" + trim(coalesce(row.hostel_type, '')),
  ht.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ht.src              = "hostel_type_master"
RETURN count(ht) AS hostelTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///room_type_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (rmt:RoomType {roomtypeId: toInteger(trim(row.id))})
ON CREATE SET
  rmt.room_type        = CASE WHEN trim(coalesce(row.room_type, '')) = '' THEN null ELSE trim(row.room_type) END,
  rmt.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  rmt.displayLabel     = "RoomType:" + trim(coalesce(row.room_type, '')),
  rmt.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  rmt.src              = "room_type_master"
RETURN count(rmt) AS roomTypeProcessed;


// A visitor log with names only — no FK to a student or a staff member, so it is a
// node in its own right rather than an edge.
LOAD CSV WITH HEADERS FROM 'file:///hostel_visitor_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (hv:HostelVisitor {hostelvisitorId: toInteger(trim(row.id))})
ON CREATE SET
  hv.name             = CASE WHEN trim(coalesce(row.name, '')) = '' THEN null ELSE trim(row.name) END,
  hv.contact          = CASE WHEN trim(coalesce(row.contact, '')) = '' THEN null ELSE trim(row.contact) END,
  hv.coming_from      = CASE WHEN trim(coalesce(row.coming_from, '')) = '' THEN null ELSE trim(row.coming_from) END,
  hv.to_meet          = CASE WHEN trim(coalesce(row.to_meet, '')) = '' THEN null ELSE trim(row.to_meet) END,
  hv.relation         = CASE WHEN trim(coalesce(row.relation, '')) = '' THEN null ELSE trim(row.relation) END,
  hv.meet_date        = CASE WHEN trim(coalesce(row.meet_date, '')) = '' THEN null ELSE trim(row.meet_date) END,
  hv.displayLabel     = "HostelVisitor:" + trim(coalesce(row.name, '')),
  hv.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  hv.src              = "hostel_visitor_master"
RETURN count(hv) AS hostelVisitorProcessed;


// ---------------------------------------------------------------------
//    NODES — inventory
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///inventory_item_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (it:InventoryItem {inventoryitemId: toInteger(trim(row.id))})
ON CREATE SET
  it.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  it.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  it.category_id      = toInteger(trim(row.category_id)),
  it.sub_category_id  = toInteger(trim(row.sub_category_id)),
  it.item_type_id     = toInteger(trim(row.item_type_id)),
  it.opening_stock    = toInteger(trim(row.opening_stock)),
  it.minimum_stock    = toInteger(trim(row.minimum_stock)),
  it.item_status      = CASE WHEN trim(coalesce(row.item_status, '')) = '' THEN null ELSE trim(row.item_status) END,
  it.syear            = toInteger(trim(row.syear)),
  it.displayLabel     = "InventoryItem:" + trim(coalesce(row.title, '')),
  it.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  it.src              = "inventory_item_master"
RETURN count(it) AS inventoryItemProcessed;


LOAD CSV WITH HEADERS FROM 'file:///inventory_item_category_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ic:ItemCategory {itemcategoryId: toInteger(trim(row.id))})
ON CREATE SET
  ic.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ic.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  ic.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  ic.syear            = toInteger(trim(row.syear)),
  ic.displayLabel     = "ItemCategory:" + trim(coalesce(row.title, '')),
  ic.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ic.src              = "inventory_item_category_master"
RETURN count(ic) AS itemCategoryProcessed;


LOAD CSV WITH HEADERS FROM 'file:///inventory_item_sub_category_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (isc:ItemSubCategory {itemsubcategoryId: toInteger(trim(row.id))})
ON CREATE SET
  isc.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  isc.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  isc.category_id      = toInteger(trim(row.category_id)),
  isc.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  isc.syear            = toInteger(trim(row.syear)),
  isc.displayLabel     = "ItemSubCategory:" + trim(coalesce(row.title, '')),
  isc.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  isc.src              = "inventory_item_sub_category_master"
RETURN count(isc) AS itemSubCategoryProcessed;


LOAD CSV WITH HEADERS FROM 'file:///inventory_item_type.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ity:ItemType {itemtypeId: toInteger(trim(row.id))})
ON CREATE SET
  ity.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ity.displayLabel     = "ItemType:" + trim(coalesce(row.title, '')),
  ity.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ity.src              = "inventory_item_type"
RETURN count(ity) AS itemTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///inventory_vendor_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (vn:Vendor {vendorId: toInteger(trim(row.id))})
ON CREATE SET
  vn.vendor_name      = CASE WHEN trim(coalesce(row.vendor_name, '')) = '' THEN null ELSE trim(row.vendor_name) END,
  vn.short_name       = CASE WHEN trim(coalesce(row.short_name, '')) = '' THEN null ELSE trim(row.short_name) END,
  vn.company_name     = CASE WHEN trim(coalesce(row.company_name, '')) = '' THEN null ELSE trim(row.company_name) END,
  vn.business_type    = CASE WHEN trim(coalesce(row.business_type, '')) = '' THEN null ELSE trim(row.business_type) END,
  vn.contact_number   = CASE WHEN trim(coalesce(row.contact_number, '')) = '' THEN null ELSE trim(row.contact_number) END,
  vn.email            = CASE WHEN trim(coalesce(row.email, '')) = '' THEN null ELSE trim(row.email) END,
  vn.syear            = toInteger(trim(row.syear)),
  vn.displayLabel     = "Vendor:" + trim(coalesce(row.vendor_name, '')),
  vn.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  vn.src              = "inventory_vendor_master"
RETURN count(vn) AS vendorProcessed;


// ---------------------------------------------------------------------
//    NODES — front desk, visitors, documents
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///physical_file_location.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fl:FileLocation {filelocationId: toInteger(trim(row.id))})
ON CREATE SET
  fl.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  fl.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  fl.file_code        = CASE WHEN trim(coalesce(row.file_code, '')) = '' THEN null ELSE trim(row.file_code) END,
  fl.file_location    = CASE WHEN trim(coalesce(row.file_location, '')) = '' THEN null ELSE trim(row.file_location) END,
  fl.displayLabel     = "FileLocation:" + trim(coalesce(row.title, '')),
  fl.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  fl.src              = "physical_file_location"
RETURN count(fl) AS fileLocationProcessed;


LOAD CSV WITH HEADERS FROM 'file:///visitor_type.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (vty:VisitorType {visitortypeId: toInteger(trim(row.id))})
ON CREATE SET
  vty.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  vty.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  vty.displayLabel     = "VisitorType:" + trim(coalesce(row.title, '')),
  vty.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  vty.src              = "visitor_type"
RETURN count(vty) AS visitorTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///visitor_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (vi:Visitor {visitorId: toInteger(trim(row.id))})
ON CREATE SET
  vi.name             = CASE WHEN trim(coalesce(row.name, '')) = '' THEN null ELSE trim(row.name) END,
  vi.contact          = CASE WHEN trim(coalesce(row.contact, '')) = '' THEN null ELSE trim(row.contact) END,
  vi.email            = CASE WHEN trim(coalesce(row.email, '')) = '' THEN null ELSE trim(row.email) END,
  vi.visitor_type_id  = toInteger(trim(row.visitor_type)),
  vi.appointment_type = CASE WHEN trim(coalesce(row.appointment_type, '')) = '' THEN null ELSE trim(row.appointment_type) END,
  vi.coming_from      = CASE WHEN trim(coalesce(row.coming_from, '')) = '' THEN null ELSE trim(row.coming_from) END,
  vi.to_meet          = CASE WHEN trim(coalesce(row.to_meet, '')) = '' THEN null ELSE trim(row.to_meet) END,
  vi.purpose          = CASE WHEN trim(coalesce(row.purpose, '')) = '' THEN null ELSE trim(row.purpose) END,
  vi.meet_date        = CASE WHEN trim(coalesce(row.meet_date, '')) = '' THEN null ELSE trim(row.meet_date) END,
  vi.displayLabel     = "Visitor:" + trim(coalesce(row.name, '')),
  vi.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  vi.src              = "visitor_master"
RETURN count(vi) AS visitorProcessed;


LOAD CSV WITH HEADERS FROM 'file:///inward.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (iw:InwardDocument {inwarddocumentId: toInteger(trim(row.id))})
ON CREATE SET
  iw.inward_number    = CASE WHEN trim(coalesce(row.inward_number, '')) = '' THEN null ELSE trim(row.inward_number) END,
  iw.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  iw.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  iw.inward_date      = CASE WHEN trim(coalesce(row.inward_date, '')) = '' THEN null ELSE trim(row.inward_date) END,
  iw.place_id         = toInteger(trim(row.place_id)),
  iw.file_location_id = toInteger(trim(row.file_location_id)),
  iw.syear            = toInteger(trim(row.syear)),
  iw.displayLabel     = "InwardDocument:" + trim(coalesce(row.inward_number, '')),
  iw.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  iw.src              = "inward"
RETURN count(iw) AS inwardProcessed;


LOAD CSV WITH HEADERS FROM 'file:///outward.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ow:OutwardDocument {outwarddocumentId: toInteger(trim(row.id))})
ON CREATE SET
  ow.outward_number   = CASE WHEN trim(coalesce(row.outward_number, '')) = '' THEN null ELSE trim(row.outward_number) END,
  ow.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ow.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  ow.outward_date     = CASE WHEN trim(coalesce(row.outward_date, '')) = '' THEN null ELSE trim(row.outward_date) END,
  ow.place_id         = toInteger(trim(row.place_id)),
  ow.file_location_id = toInteger(trim(row.file_location_id)),
  ow.syear            = toInteger(trim(row.syear)),
  ow.displayLabel     = "OutwardDocument:" + trim(coalesce(row.outward_number, '')),
  ow.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ow.src              = "outward"
RETURN count(ow) AS outwardProcessed;


LOAD CSV WITH HEADERS FROM 'file:///front_desk.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (fd:FrontDeskEntry {frontdeskentryId: toInteger(trim(row.id))})
ON CREATE SET
  fd.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  fd.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  fd.visitor_type     = CASE WHEN trim(coalesce(row.visitor_type, '')) = '' THEN null ELSE trim(row.visitor_type) END,
  fd.visit_date       = CASE WHEN trim(coalesce(row.visit_date, '')) = '' THEN null ELSE trim(row.visit_date) END,
  fd.to_whom_meet     = CASE WHEN trim(coalesce(row.to_whom_meet, '')) = '' THEN null ELSE trim(row.to_whom_meet) END,
  fd.student_id       = toInteger(trim(row.student_id)),
  fd.syear            = toInteger(trim(row.syear)),
  fd.displayLabel     = "FrontDeskEntry:" + trim(coalesce(row.title, '')),
  fd.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  fd.src              = "front_desk"
RETURN count(fd) AS frontDeskProcessed;


LOAD CSV WITH HEADERS FROM 'file:///complaint.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (cm:Complaint {complaintId: toInteger(trim(row.id))})
ON CREATE SET
  cm.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  cm.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  cm.complaint_date   = CASE WHEN trim(coalesce(row.complaint_date, '')) = '' THEN null ELSE trim(row.complaint_date) END,
  cm.complaint_by     = toInteger(trim(row.complaint_by)),
  cm.solution         = CASE WHEN trim(coalesce(row.solution, '')) = '' THEN null ELSE trim(row.solution) END,
  cm.syear            = toInteger(trim(row.syear)),
  cm.displayLabel     = "Complaint:" + trim(coalesce(row.title, '')),
  cm.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  cm.src              = "complaint"
RETURN count(cm) AS complaintProcessed;


LOAD CSV WITH HEADERS FROM 'file:///circular.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ci:Circular {circularId: toInteger(trim(row.id))})
ON CREATE SET
  ci.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ci.message          = CASE WHEN trim(coalesce(row.message, '')) = '' THEN null ELSE trim(row.message) END,
  ci.circular_date    = CASE WHEN trim(coalesce(row.circular_date, '')) = '' THEN null ELSE trim(row.circular_date) END,
  ci.circular_type_id = toInteger(trim(row.type)),
  ci.standard_id      = toInteger(trim(row.standard_id)),
  ci.division_id      = toInteger(trim(row.division_id)),
  ci.syear            = toInteger(trim(row.syear)),
  ci.displayLabel     = "Circular:" + trim(coalesce(row.title, '')),
  ci.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ci.src              = "circular"
RETURN count(ci) AS circularProcessed;


LOAD CSV WITH HEADERS FROM 'file:///circular_type.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ct:CircularType {circulartypeId: toInteger(trim(row.id))})
ON CREATE SET
  ct.type             = CASE WHEN trim(coalesce(row.type, '')) = '' THEN null ELSE trim(row.type) END,
  ct.displayLabel     = "CircularType:" + trim(coalesce(row.type, '')),
  ct.sub_institute_id = 0,
  ct.scope            = "global",
  ct.src              = "circular_type"
RETURN count(ct) AS circularTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///announcement.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (an:Announcement {announcementId: toInteger(trim(row.id))})
ON CREATE SET
  an.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  an.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  an.from_date        = CASE WHEN trim(coalesce(row.from_date, '')) = '' THEN null ELSE trim(row.from_date) END,
  an.to_date          = CASE WHEN trim(coalesce(row.to_date, '')) = '' THEN null ELSE trim(row.to_date) END,
  an.user_profile_id  = toInteger(trim(row.user_profile_id)),
  an.syear            = toInteger(trim(row.syear)),
  an.displayLabel     = "Announcement:" + trim(coalesce(row.title, '')),
  an.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  an.src              = "announcement"
RETURN count(an) AS announcementProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

// BookCopy -> Book.
LOAD CSV WITH HEADERS FROM 'file:///library_items.csv' AS row
WITH row WHERE row.book_id IS NOT NULL AND trim(row.book_id) <> '' AND trim(row.book_id) <> '0'
MATCH (bc:BookCopy {bookcopyId: toInteger(trim(row.id))})
MATCH (b:Book {bookId: toInteger(trim(row.book_id))})
MERGE (bc)-[:COPY_OF]->(b)
RETURN count(*) AS copyOf;


// Route -> Stop, with the pickup order preserved on the edge.
LOAD CSV WITH HEADERS FROM 'file:///transport_route_stop.csv' AS row
WITH row WHERE row.route_id IS NOT NULL AND row.stop_id IS NOT NULL
MATCH (rt:Route {routeId: toInteger(trim(row.route_id))})
MATCH (sp:Stop {stopId: toInteger(trim(row.stop_id))})
MERGE (rt)-[r:HAS_STOP {syear: toInteger(trim(row.syear))}]->(sp)
ON CREATE SET
  r.pickuptime       = CASE WHEN trim(coalesce(row.pickuptime, '')) = '' THEN null ELSE trim(row.pickuptime) END,
  r.droptime         = CASE WHEN trim(coalesce(row.droptime, '')) = '' THEN null ELSE trim(row.droptime) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "transport_route_stop"
RETURN count(r) AS hasStop;


// Vehicle -> Route.
LOAD CSV WITH HEADERS FROM 'file:///transport_route_bus.csv' AS row
WITH row WHERE row.route_id IS NOT NULL AND row.bus_id IS NOT NULL
MATCH (v:Vehicle {vehicleId: toInteger(trim(row.bus_id))})
MATCH (rt:Route {routeId: toInteger(trim(row.route_id))})
MERGE (v)-[r:SERVES {syear: toInteger(trim(row.syear))}]->(rt)
ON CREATE SET
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "transport_route_bus"
RETURN count(r) AS serves;


// Vehicle -> Driver / VehicleType / TransportShift.
LOAD CSV WITH HEADERS FROM 'file:///transport_vehicle.csv' AS row
WITH row WHERE row.driver IS NOT NULL AND trim(row.driver) <> '' AND trim(row.driver) <> '0'
MATCH (v:Vehicle {vehicleId: toInteger(trim(row.id))})
MATCH (dr:Driver {driverId: toInteger(trim(row.driver))})
MERGE (v)-[:DRIVEN_BY]->(dr)
RETURN count(*) AS drivenBy;


LOAD CSV WITH HEADERS FROM 'file:///transport_vehicle.csv' AS row
WITH row WHERE row.vehicle_type IS NOT NULL AND trim(row.vehicle_type) <> '' AND trim(row.vehicle_type) <> '0'
MATCH (v:Vehicle {vehicleId: toInteger(trim(row.id))})
MATCH (vt:VehicleType {vehicletypeId: toInteger(trim(row.vehicle_type))})
MERGE (v)-[:OF_VEHICLE_TYPE]->(vt)
RETURN count(*) AS ofVehicleType;


LOAD CSV WITH HEADERS FROM 'file:///transport_vehicle.csv' AS row
WITH row WHERE row.school_shift IS NOT NULL AND trim(row.school_shift) <> '' AND trim(row.school_shift) <> '0'
MATCH (v:Vehicle {vehicleId: toInteger(trim(row.id))})
MATCH (ts:TransportShift {transportshiftId: toInteger(trim(row.school_shift))})
MERGE (v)-[:RUNS_IN_SHIFT]->(ts)
RETURN count(*) AS runsInShift;


// StuDetail -> Stop, twice per row: where the learner boards in the morning and where
// they are dropped in the afternoon. Both are the same relationship type with a
// `direction` property, so a single traversal answers "which stops does this child use".
LOAD CSV WITH HEADERS FROM 'file:///transport_map_student.csv' AS row
WITH row WHERE row.from_stop IS NOT NULL AND trim(row.from_stop) <> '' AND trim(row.from_stop) <> '0'
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (sp:Stop {stopId: toInteger(trim(row.from_stop))})
MERGE (sd)-[r:BOARDS_AT {syear: toInteger(trim(row.syear)), direction: "from"}]->(sp)
ON CREATE SET
  r.bus_id           = toInteger(trim(row.from_bus_id)),
  r.shift_id         = toInteger(trim(row.from_shift_id)),
  r.distance         = toFloat(trim(row.distance)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "transport_map_student"
RETURN count(r) AS boardsAtFrom;


LOAD CSV WITH HEADERS FROM 'file:///transport_map_student.csv' AS row
WITH row WHERE row.to_stop IS NOT NULL AND trim(row.to_stop) <> '' AND trim(row.to_stop) <> '0'
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (sp:Stop {stopId: toInteger(trim(row.to_stop))})
MERGE (sd)-[r:BOARDS_AT {syear: toInteger(trim(row.syear)), direction: "to"}]->(sp)
ON CREATE SET
  r.bus_id           = toInteger(trim(row.to_bus_id)),
  r.shift_id         = toInteger(trim(row.to_shift_id)),
  r.distance         = toFloat(trim(row.distance)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "transport_map_student"
RETURN count(r) AS boardsAtTo;


// Hostel hierarchy.
LOAD CSV WITH HEADERS FROM 'file:///hostel_master.csv' AS row
WITH row WHERE row.hostel_type_id IS NOT NULL AND trim(row.hostel_type_id) <> '0'
MATCH (ho:Hostel {hostelId: toInteger(trim(row.id))})
MATCH (ht:HostelType {hosteltypeId: toInteger(trim(row.hostel_type_id))})
MERGE (ho)-[:OF_HOSTEL_TYPE]->(ht)
RETURN count(*) AS ofHostelType;


LOAD CSV WITH HEADERS FROM 'file:///hostel_building_master.csv' AS row
WITH row WHERE row.hostel_id IS NOT NULL AND trim(row.hostel_id) <> '0'
MATCH (hb:HostelBuilding {hostelbuildingId: toInteger(trim(row.id))})
MATCH (ho:Hostel {hostelId: toInteger(trim(row.hostel_id))})
MERGE (ho)-[:HAS_BUILDING]->(hb)
RETURN count(*) AS hasBuilding;


LOAD CSV WITH HEADERS FROM 'file:///hostel_floor_master.csv' AS row
WITH row WHERE row.building_id IS NOT NULL AND trim(row.building_id) <> '0'
MATCH (hf:HostelFloor {hostelfloorId: toInteger(trim(row.id))})
MATCH (hb:HostelBuilding {hostelbuildingId: toInteger(trim(row.building_id))})
MERGE (hb)-[:HAS_FLOOR]->(hf)
RETURN count(*) AS hasFloor;


LOAD CSV WITH HEADERS FROM 'file:///hostel_room_master.csv' AS row
WITH row WHERE row.floor_id IS NOT NULL AND trim(row.floor_id) <> '0'
MATCH (hr:HostelRoom {hostelroomId: toInteger(trim(row.id))})
MATCH (hf:HostelFloor {hostelfloorId: toInteger(trim(row.floor_id))})
MERGE (hf)-[:HAS_ROOM]->(hr)
RETURN count(*) AS hasRoom;


// Room allocation. `user_id` is the resident; only 9 rows, so both the student master
// and staff are tried rather than assuming which one it means.
LOAD CSV WITH HEADERS FROM 'file:///hostel_room_allocation.csv' AS row
WITH row WHERE row.room_id IS NOT NULL AND trim(row.room_id) <> '0'
  AND row.user_id IS NOT NULL AND trim(row.user_id) <> '0'
MATCH (hr:HostelRoom {hostelroomId: toInteger(trim(row.room_id))})
OPTIONAL MATCH (sd:StuDetail {sdId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (st:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, hr, coalesce(sd, t, st) AS resident
WHERE resident IS NOT NULL
MERGE (resident)-[r:ALLOCATED_ROOM {syear: toInteger(trim(row.syear))}]->(hr)
ON CREATE SET
  r.bed_no           = CASE WHEN trim(coalesce(row.bed_no, '')) = '' THEN null ELSE trim(row.bed_no) END,
  r.locker_no        = CASE WHEN trim(coalesce(row.locker_no, '')) = '' THEN null ELSE trim(row.locker_no) END,
  r.term_id          = toInteger(trim(row.term_id)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "hostel_room_allocation"
RETURN count(r) AS allocatedRoom;


// Inventory hierarchy.
LOAD CSV WITH HEADERS FROM 'file:///inventory_item_master.csv' AS row
WITH row WHERE row.category_id IS NOT NULL AND trim(row.category_id) <> '0'
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.id))})
MATCH (ic:ItemCategory {itemcategoryId: toInteger(trim(row.category_id))})
MERGE (it)-[:IN_CATEGORY]->(ic)
RETURN count(*) AS inCategory;


LOAD CSV WITH HEADERS FROM 'file:///inventory_item_master.csv' AS row
WITH row WHERE row.sub_category_id IS NOT NULL AND trim(row.sub_category_id) <> '0'
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.id))})
MATCH (isc:ItemSubCategory {itemsubcategoryId: toInteger(trim(row.sub_category_id))})
MERGE (it)-[:IN_SUB_CATEGORY]->(isc)
RETURN count(*) AS inSubCategory;


LOAD CSV WITH HEADERS FROM 'file:///inventory_item_master.csv' AS row
WITH row WHERE row.item_type_id IS NOT NULL AND trim(row.item_type_id) <> '0'
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.id))})
MATCH (ity:ItemType {itemtypeId: toInteger(trim(row.item_type_id))})
MERGE (it)-[:OF_ITEM_TYPE]->(ity)
RETURN count(*) AS ofItemType;


LOAD CSV WITH HEADERS FROM 'file:///inventory_item_sub_category_master.csv' AS row
WITH row WHERE row.category_id IS NOT NULL AND trim(row.category_id) <> '0'
MATCH (isc:ItemSubCategory {itemsubcategoryId: toInteger(trim(row.id))})
MATCH (ic:ItemCategory {itemcategoryId: toInteger(trim(row.category_id))})
MERGE (isc)-[:UNDER_CATEGORY]->(ic)
RETURN count(*) AS underCategory;


// Person -> InventoryItem: who asked for it, and who holds it.
LOAD CSV WITH HEADERS FROM 'file:///inventory_requisition_details.csv' AS row
WITH row WHERE row.item_id IS NOT NULL AND trim(row.item_id) <> '0'
  AND row.requisition_by IS NOT NULL AND trim(row.requisition_by) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.requisition_by))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.requisition_by))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.item_id))})
MERGE (person)-[r:REQUISITIONED {requisitionId: toInteger(trim(row.id))}]->(it)
ON CREATE SET
  r.item_qty           = toFloat(trim(row.item_qty)),
  r.approved_qty       = toFloat(trim(row.approved_qty)),
  r.requisition_no     = CASE WHEN trim(coalesce(row.requisition_no, '')) = '' THEN null ELSE trim(row.requisition_no) END,
  r.requisition_date   = CASE WHEN trim(coalesce(row.requisition_date, '')) = '' THEN null ELSE trim(row.requisition_date) END,
  r.requisition_status = CASE WHEN trim(coalesce(row.requisition_status, '')) = '' THEN null ELSE trim(row.requisition_status) END,
  r.syear              = toInteger(trim(row.syear)),
  r.sub_institute_id   = toInteger(trim(row.sub_institute_id)),
  r.src                = "inventory_requisition_details"
RETURN count(r) AS requisitioned;


LOAD CSV WITH HEADERS FROM 'file:///inventory_allocation_details.csv' AS row
WITH row WHERE row.item_id IS NOT NULL AND trim(row.item_id) <> '0'
  AND row.person_responsible IS NOT NULL AND trim(row.person_responsible) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.person_responsible))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.person_responsible))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.item_id))})
MERGE (person)-[r:ALLOCATED_ITEM {allocationId: toInteger(trim(row.id))}]->(it)
ON CREATE SET
  r.location_of_material = CASE WHEN trim(coalesce(row.location_of_material, '')) = '' THEN null ELSE trim(row.location_of_material) END,
  r.syear                = toInteger(trim(row.syear)),
  r.sub_institute_id     = toInteger(trim(row.sub_institute_id)),
  r.src                  = "inventory_allocation_details"
RETURN count(r) AS allocatedItem;


// Vendor -> InventoryItem. Amounts stay on the edge with authoritative:false — the
// purchase ledger is MariaDB's, not the graph's.
LOAD CSV WITH HEADERS FROM 'file:///inventory_item_direct_purchase.csv' AS row
WITH row WHERE row.item_id IS NOT NULL AND trim(row.item_id) <> '0'
  AND row.vendor_id IS NOT NULL AND trim(row.vendor_id) <> '0'
MATCH (vn:Vendor {vendorId: toInteger(trim(row.vendor_id))})
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.item_id))})
MERGE (vn)-[r:SUPPLIED {purchaseId: toInteger(trim(row.id))}]->(it)
ON CREATE SET
  r.item_qty         = toFloat(trim(row.item_qty)),
  r.bill_no          = CASE WHEN trim(coalesce(row.bill_no, '')) = '' THEN null ELSE trim(row.bill_no) END,
  r.bill_date        = CASE WHEN trim(coalesce(row.bill_date, '')) = '' THEN null ELSE trim(row.bill_date) END,
  r.syear            = toInteger(trim(row.syear)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "inventory_item_direct_purchase"
RETURN count(r) AS supplied;


LOAD CSV WITH HEADERS FROM 'file:///inventory_generate_po_details.csv' AS row
WITH row WHERE row.item_id IS NOT NULL AND trim(row.item_id) <> '0'
  AND row.vendor_id IS NOT NULL AND trim(row.vendor_id) <> '0'
MATCH (vn:Vendor {vendorId: toInteger(trim(row.vendor_id))})
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.item_id))})
MERGE (vn)-[r:PURCHASE_ORDER {poId: toInteger(trim(row.id))}]->(it)
ON CREATE SET
  r.po_number        = CASE WHEN trim(coalesce(row.po_number, '')) = '' THEN null ELSE trim(row.po_number) END,
  r.qty              = toFloat(trim(row.qty)),
  r.approval_status  = CASE WHEN trim(coalesce(row.po_approval_status, '')) = '' THEN null ELSE trim(row.po_approval_status) END,
  r.syear            = toInteger(trim(row.syear)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "inventory_generate_po_details"
RETURN count(r) AS purchaseOrder;


LOAD CSV WITH HEADERS FROM 'file:///inventory_item_quotation_details.csv' AS row
WITH row WHERE row.item_id IS NOT NULL AND trim(row.item_id) <> '0'
  AND row.vendor_id IS NOT NULL AND trim(row.vendor_id) <> '0'
MATCH (vn:Vendor {vendorId: toInteger(trim(row.vendor_id))})
MATCH (it:InventoryItem {inventoryitemId: toInteger(trim(row.item_id))})
MERGE (vn)-[r:QUOTED {quotationId: toInteger(trim(row.id))}]->(it)
ON CREATE SET
  r.qty              = toFloat(trim(row.qty)),
  r.approved_status  = CASE WHEN trim(coalesce(row.approved_status, '')) = '' THEN null ELSE trim(row.approved_status) END,
  r.syear            = toInteger(trim(row.syear)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "inventory_item_quotation_details"
RETURN count(r) AS quoted;


// Documents -> where they are filed, and which tenant owns them.
LOAD CSV WITH HEADERS FROM 'file:///inward.csv' AS row
WITH row WHERE row.file_location_id IS NOT NULL AND trim(row.file_location_id) <> '0'
MATCH (iw:InwardDocument {inwarddocumentId: toInteger(trim(row.id))})
MATCH (fl:FileLocation {filelocationId: toInteger(trim(row.file_location_id))})
MERGE (iw)-[:FILED_AT]->(fl)
RETURN count(*) AS inwardFiledAt;


LOAD CSV WITH HEADERS FROM 'file:///outward.csv' AS row
WITH row WHERE row.file_location_id IS NOT NULL AND trim(row.file_location_id) <> '0'
MATCH (ow:OutwardDocument {outwarddocumentId: toInteger(trim(row.id))})
MATCH (fl:FileLocation {filelocationId: toInteger(trim(row.file_location_id))})
MERGE (ow)-[:FILED_AT]->(fl)
RETURN count(*) AS outwardFiledAt;


LOAD CSV WITH HEADERS FROM 'file:///visitor_master.csv' AS row
WITH row WHERE row.visitor_type IS NOT NULL AND trim(row.visitor_type) <> '' AND trim(row.visitor_type) <> '0'
MATCH (vi:Visitor {visitorId: toInteger(trim(row.id))})
MATCH (vty:VisitorType {visitortypeId: toInteger(trim(row.visitor_type))})
MERGE (vi)-[:OF_VISITOR_TYPE]->(vty)
RETURN count(*) AS ofVisitorType;


LOAD CSV WITH HEADERS FROM 'file:///circular.csv' AS row
WITH row WHERE row.type IS NOT NULL AND trim(row.type) <> '' AND trim(row.type) <> '0'
MATCH (ci:Circular {circularId: toInteger(trim(row.id))})
MATCH (ct:CircularType {circulartypeId: toInteger(trim(row.type))})
MERGE (ci)-[:OF_CIRCULAR_TYPE]->(ct)
RETURN count(*) AS ofCircularType;


// Institute -> the operational records it owns. This is what makes each of these
// reachable from the tenant rather than sitting orphaned.
LOAD CSV WITH HEADERS FROM 'file:///inward.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (iw:InwardDocument {inwarddocumentId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_INWARD]->(iw)
RETURN count(*) AS hasInward;


LOAD CSV WITH HEADERS FROM 'file:///outward.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (ow:OutwardDocument {outwarddocumentId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_OUTWARD]->(ow)
RETURN count(*) AS hasOutward;


LOAD CSV WITH HEADERS FROM 'file:///visitor_master.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (vi:Visitor {visitorId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_VISITOR]->(vi)
RETURN count(*) AS hasVisitor;


LOAD CSV WITH HEADERS FROM 'file:///complaint.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (cm:Complaint {complaintId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_COMPLAINT]->(cm)
RETURN count(*) AS hasComplaint;


LOAD CSV WITH HEADERS FROM 'file:///circular.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (ci:Circular {circularId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_CIRCULAR]->(ci)
RETURN count(*) AS hasCircular;


LOAD CSV WITH HEADERS FROM 'file:///announcement.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (an:Announcement {announcementId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_ANNOUNCEMENT]->(an)
RETURN count(*) AS hasAnnouncement;


LOAD CSV WITH HEADERS FROM 'file:///hostel_master.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (ho:Hostel {hostelId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_HOSTEL]->(ho)
RETURN count(*) AS hasHostel;


LOAD CSV WITH HEADERS FROM 'file:///transport_route.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (rt:Route {routeId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_ROUTE]->(rt)
RETURN count(*) AS hasRoute;


// Circular -> Standard / Division, so a notice is reachable from the class it targets.
LOAD CSV WITH HEADERS FROM 'file:///circular.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard_id IS NOT NULL AND trim(row.standard_id) <> '' AND trim(row.standard_id) <> '0'
MATCH (ci:Circular {circularId: toInteger(trim(row.id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH ci, coalesce(n1, n2) AS st
WHERE st IS NOT NULL
MERGE (ci)-[:CIRCULATED_TO]->(st)
RETURN count(*) AS circulatedTo;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES
// ---------------------------------------------------------------------

// 67,487 loans -> one edge per (learner, title). This is the edge that answers
// "students who borrowed X also borrowed Y", which is why the library is in the graph
// at all rather than staying a transactional ledger.
LOAD CSV WITH HEADERS FROM 'file:///library_book_circulations_agg.csv' AS row
WITH row WHERE row.student_id IS NOT NULL AND row.book_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (b:Book {bookId: toInteger(trim(row.book_id))})

MERGE (sd)-[r:BORROWED]->(b)
ON CREATE SET
  r.times_borrowed   = toInteger(trim(row.times_borrowed)),
  r.first_issued     = CASE WHEN trim(coalesce(row.first_issued, '')) = '' THEN null ELSE trim(row.first_issued) END,
  r.last_issued      = CASE WHEN trim(coalesce(row.last_issued, '')) = '' THEN null ELSE trim(row.last_issued) END,
  r.outstanding      = toInteger(trim(row.outstanding)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "library_book_circulations"
RETURN count(r) AS borrowed;


// @section verify
// ---------------------------------------------------------------------
// 5. VERIFY
// ---------------------------------------------------------------------

MATCH (b:Book) RETURN 'Book nodes' AS check, count(b) AS n;
MATCH (bc:BookCopy) RETURN 'BookCopy nodes' AS check, count(bc) AS n;
MATCH (rt:Route) RETURN 'Route nodes' AS check, count(rt) AS n;
MATCH (sp:Stop) RETURN 'Stop nodes' AS check, count(sp) AS n;
MATCH (v:Vehicle) RETURN 'Vehicle nodes' AS check, count(v) AS n;
MATCH (dr:Driver) RETURN 'Driver nodes' AS check, count(dr) AS n;
MATCH (ho:Hostel) RETURN 'Hostel nodes' AS check, count(ho) AS n;
MATCH (hr:HostelRoom) RETURN 'HostelRoom nodes' AS check, count(hr) AS n;
MATCH (it:InventoryItem) RETURN 'InventoryItem nodes' AS check, count(it) AS n;
MATCH (vn:Vendor) RETURN 'Vendor nodes' AS check, count(vn) AS n;
MATCH (vi:Visitor) RETURN 'Visitor nodes' AS check, count(vi) AS n;
MATCH (iw:InwardDocument) RETURN 'InwardDocument nodes' AS check, count(iw) AS n;
MATCH (:BookCopy)-[r:COPY_OF]->(:Book) RETURN 'COPY_OF' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:BORROWED]->(:Book) RETURN 'BORROWED' AS check, count(r) AS n;
MATCH (:Route)-[r:HAS_STOP]->(:Stop) RETURN 'HAS_STOP' AS check, count(r) AS n;
MATCH (:Vehicle)-[r:SERVES]->(:Route) RETURN 'SERVES' AS check, count(r) AS n;
MATCH (:Vehicle)-[r:DRIVEN_BY]->(:Driver) RETURN 'DRIVEN_BY' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:BOARDS_AT]->(:Stop) RETURN 'BOARDS_AT' AS check, count(r) AS n;
MATCH (:HostelFloor)-[r:HAS_ROOM]->(:HostelRoom) RETURN 'HAS_ROOM' AS check, count(r) AS n;
MATCH ()-[r:ALLOCATED_ROOM]->(:HostelRoom) RETURN 'ALLOCATED_ROOM' AS check, count(r) AS n;
MATCH (:InventoryItem)-[r:IN_CATEGORY]->(:ItemCategory) RETURN 'IN_CATEGORY' AS check, count(r) AS n;
MATCH ()-[r:REQUISITIONED]->(:InventoryItem) RETURN 'REQUISITIONED' AS check, count(r) AS n;
MATCH (:Vendor)-[r:SUPPLIED]->(:InventoryItem) RETURN 'SUPPLIED' AS check, count(r) AS n;
MATCH (:Institute)-[r:HAS_INWARD]->(:InwardDocument) RETURN 'HAS_INWARD' AS check, count(r) AS n;
MATCH (:Institute)-[r:HAS_VISITOR]->(:Visitor) RETURN 'HAS_VISITOR' AS check, count(r) AS n;
MATCH (b:Book) WHERE NOT (b)--() RETURN 'Book with no edge' AS check, count(b) AS n;
