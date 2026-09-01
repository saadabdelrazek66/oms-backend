<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContentPlanController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Test Update

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/departments/list-all', [DepartmentController::class, 'listAll']);
    Route::middleware('role:manager')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/content-plans', [ContentPlanController::class, 'store']);
        Route::put('/content-plans/{content_plan}', [ContentPlanController::class, 'update']);
        Route::delete('/content-plans/{content_plan}', [ContentPlanController::class, 'destroy']);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('departments', DepartmentController::class);
    });

    Route::get('/content-plans', [ContentPlanController::class, 'index']);
    // مسارات أفعال الخطط
    Route::post('content-plans/{content_plan}/submit-review', [ContentPlanController::class, 'submitForReview']);
    Route::post('content-plans/{content_plan}/final-delivery', [ContentPlanController::class, 'submitFinalDelivery']);
    Route::post('content-plans/{content_plan}/approve', [ContentPlanController::class, 'approvePlan']);
    Route::post('content-plans/{content_plan}/reject', [ContentPlanController::class, 'rejectPlan']);
    Route::put('content-plans/{content_plan}/details', [ContentPlanController::class, 'updateDetails']);

    // مسارات متابعة العملاء (Client Follow-ups)
    Route::post('content-plans/{content_plan}/follow-ups', [App\Http\Controllers\Api\ClientFollowUpController::class, 'store']);
    Route::put('follow-ups/{client_follow_up}', [App\Http\Controllers\Api\ClientFollowUpController::class, 'update']);
    Route::delete('follow-ups/{client_follow_up}', [App\Http\Controllers\Api\ClientFollowUpController::class, 'destroy']);

    // مسارات خزنة العملاء (Client Vault) - للمديرين فقط
    Route::prefix('vault')->group(function () {
        Route::get('status', [App\Http\Controllers\Api\ClientVaultController::class, 'status']);
        Route::post('setup-pin', [App\Http\Controllers\Api\ClientVaultController::class, 'setupPin']);
        Route::post('verify-pin', [App\Http\Controllers\Api\ClientVaultController::class, 'verifyPin']);

        Route::get('clients', [App\Http\Controllers\Api\ClientVaultController::class, 'index']);
        Route::post('clients/{client}/credentials', [App\Http\Controllers\Api\ClientVaultController::class, 'store']);
        Route::put('credentials/{credential}', [App\Http\Controllers\Api\ClientVaultController::class, 'update']);
        Route::delete('credentials/{credential}', [App\Http\Controllers\Api\ClientVaultController::class, 'destroy']);
    });
    });
