<?php

use App\Http\Controllers\SwiftController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/getAllContainers', [SwiftController::class, 'listAllContainers']);
Route::get('/getAllObjects', [SwiftController::class, 'listAllObjects']);

Route::get('/connect', [SwiftController::class, 'objectStorageConnection']);
Route::get('/upload-file', [SwiftController::class, 'uploadFile']);
Route::get('/getLocalFiles', [SwiftController::class, 'getAllFilesFromLocal']);
Route::get('/playVideo', [SwiftController::class, 'videoStream']);
