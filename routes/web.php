<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManageApiController;
use App\Http\Controllers\ManageRoleplayController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/manage-roleplay', [ManageRoleplayController::class, 'index']);
Route::get('/manage-api', [ManageApiController::class, 'index']);

Route::post('/providers', [ManageApiController::class, 'createProvider'])->name('providers.store');
Route::put('/providers/{id_provider}', [ManageApiController::class, 'updateProvider'])->name('providers.update');
Route::delete('/providers/{id_provider}', [ManageApiController::class, 'deleteProvider'])->name('providers.destroy');
Route::post('/providers/{id_provider}/api-keys', [ManageApiController::class, 'insertApiKey'])->name('api-keys.store');
