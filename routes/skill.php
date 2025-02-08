<?php

use App\Http\Controllers\skill\SkillMatrixController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'skill', 'middleware' => ['session', 'menu', 'logRoute','check_permissions']], function() {
    Route::get('/matrix', [SkillMatrixController::class, 'index'])->name('matrix');
    Route::post('/matrix/save', [SkillMatrixController::class, 'store'])->name('matrix.save');
    Route::get('/jobrole', [SkillMatrixController::class, 'JobRole'])->name('jobrole.index');
    Route::get('/jobdescription', [SkillMatrixController::class, 'JobDescription'])->name('jobrole.jobdescription');
});
