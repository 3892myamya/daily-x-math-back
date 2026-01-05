<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/question', [QuestionController::class, 'getQuestion']);
Route::get('/answer', [QuestionController::class, 'getAnswer']);
Route::get('/health', function () {
    return response()->noContent(); // 204 NoContent
});
// Route::get('/test', [QuestionController::class, 'solveTest']);