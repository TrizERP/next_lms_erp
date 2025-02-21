<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HRMS\HrmsController;
use App\Http\Controllers\HRMS\HrmsLeaveController;
use App\Http\Controllers\Payroll\PayrollController;
use App\Http\Controllers\AJAXController;
use App\Http\Controllers\leave\leaveEncashmentController;
use App\Http\Controllers\HRMS\departmentController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//PAYROLL SYSTEM
Route::group([ 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
   
    Route::get('/payroll-type', [PayrollController::class, 'payrollType'])->name('payroll_type.index');
    Route::get('/payroll-type/create', [PayrollController::class, 'payrollCreate'])->name('payroll_type.create');
    Route::post('/payroll-type/store', [PayrollController::class, 'payrollStore'])->name('payroll_type.store');
    Route::get('/payroll-type/create/{id}', [PayrollController::class, 'payrollCreate']);
    Route::delete('/payroll-type/destroy/{id}', [PayrollController::class, 'payrollDestroy'])->name('payroll_type.destroy');

    Route::get('/payroll-type-report', [PayrollController::class, 'payrollTypeReport'])->name('payrollTypeReport.index');
    Route::get('/payroll-type-report/create', [PayrollController::class, 'payrollTypeReportCreate'])->name('payrollTypeReport.create');

    Route::get('/employee-salary-structure', [PayrollController::class, 'employeeSalaryStructure'])->name('employee_salary_structure.index');
    Route::post('/employee-salary-structure', [PayrollController::class, 'employeeSalaryStructure'])->name('payroll.show_employee_salary_structure');
    Route::get('/roll-over', [PayrollController::class, 'rollOver'])->name('employee_salary_structure.rollover');
    Route::post('/employee-salary-structure/store', [PayrollController::class, 'employeeSalaryStructureStore'])->name('employee_salary_structure.store');
    Route::post('/rollover-employee-salary-structure/store', [PayrollController::class, 'rolloverEmployeeSalaryStructure'])->name('rollover_employee_salary_structure.store');
    
    Route::get('/salary-structure-report', [PayrollController::class, 'salaryStructureReport'])->name('salary_structure_report.index');
    Route::post('/salary-structure-report', [PayrollController::class, 'showSalaryStructureReport']);

    Route::get('/form16',[PayrollController::class, 'form16'])->name('form16.index');
    Route::post('/form16-get-employees-list', [PayrollController::class, 'getEmployeeLists'])->name('form16.get.employees.list');
    Route::post('/form16-report', [PayrollController::class, 'form16Report'])->name('form16.report');

    Route::get('/payroll-deduction', [PayrollController::class, 'payrollDeduction'])->name('payroll_deduction.index');
    Route::post('/payroll-deduction/store', [PayrollController::class, 'payrollDeductionStore'])->name('payroll_deduction.store');

    Route::get('/monthly-payroll-report', [PayrollController::class, 'monthlyPayrollReport'])->name('monthly_payroll_report.index');
    Route::post('/monthly-payroll-report', [PayrollController::class, 'monthlyPayrollReport'])->name('payroll.store_monthly_payroll_report');

    Route::post('/show-monthly-payroll-report', [PayrollController::class, 'monthlyPayrollReport'])->name('payroll.show_monthly_payroll_report');
    Route::get('/monthly-payroll-report/pdf/{id}/{month}/{year}', [PayrollController::class, 'monthlyPayrollPdf']);

    Route::get('/payroll-report', [PayrollController::class, 'payrollReport'])->name('payroll_report.index');
    Route::post('/payroll-report', [PayrollController::class, 'payrollReport'])->name('payroll.show_payroll_report');

    Route::get('/employee-payroll-history', [PayrollController::class, 'employeePayrollHistory'])->name('employee_payroll_history.index');
    Route::post('/employee-payroll-history', [PayrollController::class, 'employeePayrollHistory'])->name('payroll.show_employee_payroll_history');

    Route::get('/payroll-bank-wise-report', [PayrollController::class, 'payrollBankWiseReport'])->name('payroll_bankwise_report.index');
    Route::post('/payroll-bank-wise-report', [PayrollController::class, 'payrollBankWiseReport'])->name('payroll.show_payroll_bankwise_report');

    Route::get('hrms-salary-certificate',[PayrollController::Class,'hrmsSalaryCertificateIndex'])->name('hrms_salary_certificate.index');
    Route::post('/hrms-salary-certificate-report', [PayrollController::class, 'hrmsSalaryCertificateReport'])->name('hrms_salary_certificate.report');
    Route::get('salary-certificate-pdf-download',[PayrollController::Class,'SalaryCertificatePdfDownload'])->name('salary_certificate_pdf_download');

    Route::get('hrms-job-title',[HrmsController::Class,'hrmsJobTitle'])->name('hrms_job_title.index');
    Route::get('/hrms-job-title/create', [HrmsController::class, 'hrmsCreate'])->name('hrms_job_title.create');
    Route::get('/hrms-job-title/create/{id}', [HrmsController::class, 'hrmsCreate']);
    Route::post('/hrms-job-title/store', [HrmsController::class, 'hrmsStore'])->name('hrms_job_title.store');
    Route::delete('/hrms-job-title/destroy/{id}', [HrmsController::class, 'hrmsDestroy'])->name('hrms_job_title.destroy');

    Route::get('hrms-inout-time',[HrmsController::Class,'hrmsInOutTime'])->name('hrms_inout_time.index');
    Route::post('hrms-in-time/store',[HrmsController::Class,'hrmsInTimeStore'])->name('hrms_in_time.store');
    Route::post('hrms-out-time/store',[HrmsController::Class,'hrmsOutTimeStore'])->name('hrms_out_time.store');

    Route::get('hrms-attendance',[HrmsController::Class,'hrmsAttendance'])->name('hrms_attendance.index');
    Route::post('hrms-attendance-in-time/store',[HrmsController::Class,'hrmsAttendanceInTimeStore'])->name('hrms_attendance_in_time.store');
    Route::post('hrms-attendance-out-time/store',[HrmsController::Class,'hrmsAttendanceOutTimeStore'])->name('hrms_attendance_out_time.store');

    Route::get('hrms-attendance-report',[HrmsController::Class,'hrmsAttendanceReportIndex'])->name('hrms_attendance_report.index');
    Route::post('/show-hrms-attendance-report', [HrmsController::class, 'hrmsAttendanceReport'])->name('hrms.show_hrms_attendance_report');
    Route::post('/get-employees-list', [HrmsController::class, 'getEmployeeLists'])->name('get.employees.list');

    Route::get('early-going-hrms-attendance-report',[HrmsController::Class,'earlyGoingHrmsAttendanceReportIndex'])->name('hrms_attendance_report.early_going_report');

    Route::post('/show-early-going-hrms-attendance-report', [HrmsController::class, 'earlyGoingHrmsAttendanceReport'])->name('hrms.show_early_going_hrms_attendance_report');
    Route::get('hrms-general-setting',[HrmsController::Class,'generalSettingIndex'])->name('hrms_general_setting.index');
    Route::post('hrms-general-setting/store',[HrmsController::Class,'generalSettingStore'])->name('hrms_general_setting.store');
    
    Route::get('departmentwise-attendance-report',[HrmsController::Class,'departmentAttendanceReport'])->name('department_attendance_report.index');
    Route::get('departmentwise-attendance-report/create',[HrmsController::Class,'departmentAttendanceReportCreate'])->name('department_attendance_report.create');

    Route::get('departmentwise-emplist',[AJAXController::Class,'getDepEmployeeLists'])->name('departmentwise-emplist');

    Route::get('get-holidays',[HrmsController::Class,'getHolidays']);
    Route::get('get-present-days',[HrmsController::Class,'getPresentDays']);
    Route::get('get-absent-days',[HrmsController::Class,'getAbsentDays']);
    Route::get('get-half-day',[HrmsController::Class,'getHalfDays']);

    // new monthly payroll report
    Route::get('/monthly-payroll', [PayrollController::class, 'monthlyPayroll'])->name('monthly_payroll.index');
    Route::get('/monthly-payroll/create', [PayrollController::class, 'monthlyPayrollCreate'])->name('monthly_payroll.create');
    Route::post('/monthly-payroll-store', [PayrollController::class, 'monthlyPayrollStore'])->name('monthly_payroll.store');

    Route::post('/monthly-payroll-delete', [PayrollController::class, 'deleteMonthlyPayrolls'])->name('monthly_payroll.delete');

   // web.php
   Route::get('/getMonthlyData', [PayrollController::class, 'getEmpMonthlyData'])->name('getMonthlyData');
});

Route::group(['prefix' => 'hrms', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function () {
    Route::resource('designation_leave', HrmsLeaveController::class);
    Route::resource('leave_encashment', leaveEncashmentController::class);
    Route::resource('add_department', departmentController::class);
    route::get('department-Emp-Lists',[departmentController::class, 'departmentEmpLists'])->name('departmentEmpLists');
    route::get('sub-department-list',[departmentController::class, 'subDepartmentList'])->name('subDepartmentList');
    route::get('department-employee-list',[departmentController::class, 'departmentEmployeeList'])->name('departmentEmployeeList');

    route::get('attendance-by-id',[HrmsController::class, 'getAttandanceData'])->name('attendance_by_id');
    
    // multiple employee hrms attendance
    Route::get('multiple_attendance_report',[HrmsController::Class,'multipleAttendanceReportIndex'])->name('multiple_attendance_report.index');
    Route::get('multiple_attendance_report/create',[HrmsController::Class,'multipleAttendanceReportCreate'])->name('multiple_attendance_report.create');
    // daywise employee attendance altius
    Route::get('daywise_attendance_report',[HrmsController::Class,'DaywiseAttendanceReportIndex'])->name('daywise_attendance_report.index');
    Route::get('daywise_attendance_report/create',[HrmsController::Class,'DaywiseAttendanceportCreate'])->name('daywise_attendance_report.create');
});

Route::get('getTotalDays',[PayrollController::class, 'getTotalDays'])->name('getTotalDays');
