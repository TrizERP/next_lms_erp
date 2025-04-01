<?php

use App\Http\Controllers\skill\SkillMatrixController;
use App\Http\Controllers\lms\library\jobOccupationController;
use App\Http\Controllers\lms\library\jobroleSkillController;
use App\Http\Controllers\lms\library\jobroleTaskController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'skill', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function() {
    Route::get('/matrix', [SkillMatrixController::class, 'index'])->name('matrix');
    Route::post('/matrix/save', [SkillMatrixController::class, 'store'])->name('matrix.save');
    Route::get('/jobrole', [SkillMatrixController::class, 'JobRole'])->name('jobrole.index');
    Route::get('/jobdescription', [SkillMatrixController::class, 'JobDescription'])->name('jobrole.jobdescription');
    Route::get('/assessment_library', [SkillMatrixController::class, 'AssessmentLibrary'])->name('assessment_library');
    Route::view('/gap_analysis', 'skill.assessment.gap_analysis')->name('gap_analysis');
    Route::resource('jobroleOccupation',jobOccupationController::class); // added on 01-04-2025
    Route::resource('jobroleSkill',jobroleSkillController::class); // added on 01-04-2025
    Route::resource('jobroleTask',jobroleTaskController::class); // added on 01-04-2025
});