<?php

//$menu[] = array(__('test_catalog'), MODULES_WEB_ROOT_DIR.'reporting/customs/Catalog_Statics_old.php', __('List of collection/items'));
$menu[] = array(gettext('Catalog Statistics'), MODULES_WEB_ROOT_DIR.'reporting/customs/Catalog_Statics.php', gettext('Catalog Static Report'));
//DONE//
//$menu[] = array(__('Loan Report_latest'), MODULES_WEB_ROOT_DIR.'reporting/customs/loan_history_new1.php', __('Loan Report'));
///TEMPORARY/
$menu[] = array(gettext('Summary Report'), MODULES_WEB_ROOT_DIR.'reporting/customs/loan_history_new.php', gettext('Issue Summary Report'));
//$menu[] = array(__('Loan old'), MODULES_WEB_ROOT_DIR.'reporting/customs/loan_history.php', __('Title and Collection recapitulation based on classification and others'));
//$menu[] = array(__('Custom Recapitulations'), MODULES_WEB_ROOT_DIR.'reporting/customs/class_recap.php', __('Title and Collection recapitulation based on classification and others'));
$menu[] = array(gettext('Title List'), MODULES_WEB_ROOT_DIR.'reporting/customs/titles_list.php', gettext('List of bibliographic titles'));

$menu[] = array(gettext('Items Book List Report'), MODULES_WEB_ROOT_DIR.'reporting/customs/item_book_list_report.php', gettext('List of Item Book'));
$menu[] = array(gettext('Books Title List'), MODULES_WEB_ROOT_DIR.'reporting/customs/item_titles_list.php', gettext('List of collection/items'));
//$menu[] = array(__('Items Usage Statistics'), MODULES_WEB_ROOT_DIR.'reporting/customs/item_usage.php', __('List of Collection/items usage statistic'));
//$menu[] = array(__('Issue by Classification'), MODULES_WEB_ROOT_DIR.'reporting/customs/loan_by_class.php', __('Issue statistic by classification'));
//$menu[] = array(__('Active/Inactive Report'), MODULES_WEB_ROOT_DIR.'reporting/customs/active_inactive_member.php', __('View Active/Inactive Member Report'));
$menu[] = array(gettext('Member List'), MODULES_WEB_ROOT_DIR.'reporting/customs/member_list.php', gettext('List of library member/patron'));
//$menu[] = array(__('Loan List by Member'), MODULES_WEB_ROOT_DIR.'reporting/customs/member_loan_list.php', __('List of loan by each member'));
$menu[] = array(gettext('Issue History'), MODULES_WEB_ROOT_DIR.'reporting/customs/loan_history.php', gettext('Issue History Overview'));
$menu[] = array(gettext('Due Date Warning'), MODULES_WEB_ROOT_DIR.'reporting/customs/due_date_warning.php', gettext('Issue Due Date Warnings'));
$menu[] = array(gettext('Overdued List'), MODULES_WEB_ROOT_DIR.'reporting/customs/overdued_list.php', gettext('View Members Having Overdues'));
$menu[] = array(gettext('All Book List'), MODULES_WEB_ROOT_DIR.'reporting/customs/all_book_list.php', gettext('List of Books'));
$menu[] = array(gettext('Books Lost/Damage'), MODULES_WEB_ROOT_DIR.'reporting/customs/book_verification_lost_report.php', gettext('Books Lost/Damage'));
$menu[] = array(gettext('Books Verified List'), MODULES_WEB_ROOT_DIR.'reporting/customs/book_verification_report.php', gettext('Books Verified List'));
$menu[] = array(gettext('Book Pending Verify List'), MODULES_WEB_ROOT_DIR.'reporting/customs/book_verification_pending_report.php', gettext('Book Pending Verify List'));
//$menu[] = array(__('Staff Activity'), MODULES_WEB_ROOT_DIR.'reporting/customs/staff_act.php', __('Staff activity log recapitulation'));
//$menu[] = array(__('Visitor Statistic'), MODULES_WEB_ROOT_DIR.'reporting/customs/visitor_report.php', __('Visitor Statistic'));
//$menu[] = array(__('Visitor Statistic (by Day)'), MODULES_WEB_ROOT_DIR.'reporting/customs/visitor_report_day.php', __('Visitor Statistic (by Day)'));
//$menu[] = array(__('Visitor List'), MODULES_WEB_ROOT_DIR.'reporting/customs/visitor_list.php', __('Visitor List'));
//$menu[] = array(__('Fines Report'), MODULES_WEB_ROOT_DIR.'reporting/customs/fines_report.php', __('Fines Report'));
?>
