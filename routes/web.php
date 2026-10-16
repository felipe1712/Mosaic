<?php

use App\Http\Controllers\MosaicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MosaicStudio Pro Web & API Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [MosaicController::class, 'index'])->name('home');
Route::get('/studio', [MosaicController::class, 'index'])->name('mosaic.studio');

// REST API for MosaicStudio Pro
Route::prefix('api')->group(function () {
    Route::post('/projects', [MosaicController::class, 'createProject'])->name('api.projects.create');
    Route::get('/projects', [MosaicController::class, 'listProjects'])->name('api.projects.list');
    Route::post('/projects/{id}/target', [MosaicController::class, 'uploadTarget'])->name('api.projects.target');
    Route::post('/projects/{id}/tiles', [MosaicController::class, 'uploadTiles'])->name('api.projects.tiles');
    Route::post('/projects/{id}/generate', [MosaicController::class, 'generate'])->name('api.projects.generate');
    Route::get('/projects/{id}/status', [MosaicController::class, 'status'])->name('api.projects.status');
    Route::get('/projects/{id}/download/{type}', [MosaicController::class, 'download'])->name('mosaic.download');
});
