<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentPlanController;
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

    Route::middleware('role:manager')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/content-plans', [ContentPlanController::class, 'store']);
        Route::put('/content-plans/{content_plan}', [ContentPlanController::class, 'update']);
        Route::delete('/content-plans/{content_plan}', [ContentPlanController::class, 'destroy']);
    });

    // مسار مشترك (يعرض الخطط حسب دور المستخدم)
    Route::get('/content-plans', [ContentPlanController::class, 'index']);
    // مسارات أفعال الموظف (لاحتساب الأوقات وإضافة اللينكات)
    Route::post('/content-plans/{content_plan}/review-complete', [ContentPlanController::class, 'markReviewComplete']);
    Route::post('/content-plans/{content_plan}/final-delivery', [ContentPlanController::class, 'markFinalDelivery']);
    Route::put('/content-plans/{content_plan}/details', [ContentPlanController::class, 'updateDetails']);
});
