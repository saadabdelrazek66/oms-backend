<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContentPlanController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PlanPostController;
use App\Http\Controllers\Api\UserController;
use App\Modules\QuickTasks\Controllers\QuickTaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Test Update

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 🛡️ مستوى المصادقة: 5 طلبات في الدقيقة لمنع التخمين العشوائي للباسورد
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth_limit');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/content-plans/{plan}/posts', [PlanPostController::class, 'index']);
    Route::post('/content-plans/{plan}/posts', [PlanPostController::class, 'store']);
    Route::put('/plan-posts/{post}', [PlanPostController::class, 'update']);
    Route::post('/plan-posts/{post}/review', [PlanPostController::class, 'review']);

    Route::get('/departments/list-all', [DepartmentController::class, 'listAll']);
    Route::apiResource('users', UserController::class);
    Route::middleware('role:manager')->group(function () {
        Route::post('/content-plans', [ContentPlanController::class, 'store']);
        Route::put('/content-plans/{content_plan}', [ContentPlanController::class, 'update']);
        Route::delete('/content-plans/{content_plan}', [ContentPlanController::class, 'destroy']);
        Route::apiResource('departments', DepartmentController::class);
    });
    Route::apiResource('clients', ClientController::class);

    // إعدادات النظام - تقدير ساعات العمل لعناصر الخطط (خاص بالمدير فقط)
        // جلب قائمة خفيفة ومجردة بأنواع الخطط للقوائم المنسدلة
    Route::get('/plan-categories/options', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'options']);

    Route::middleware('role:manager')->prefix('settings')->group(function () {
        Route::get('/plan-item-estimates', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'index']);
        Route::post('/plan-item-categories', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'storeCategory']);
        Route::put('/plan-item-categories/{category}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'updateCategory']);
        Route::delete('/plan-item-categories/{category}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'destroyCategory']);

        Route::post('/plan-item-estimates', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'storeItem']);
        Route::put('/plan-item-estimates/bulk-update', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'bulkUpdate']);
        Route::put('/plan-item-estimates/{estimate}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'updateItem']);
        Route::delete('/plan-item-estimates/{estimate}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'destroyItem']);
    });


    Route::get('/content-plans', [ContentPlanController::class, 'index']);
    Route::get('/content-plans/{content_plan}', [ContentPlanController::class, 'show']);
    Route::get('/plans/boards', [App\Http\Controllers\Api\ContentPlanController::class, 'boardPlans']);

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

    Route::post('/plan-posts/{post}/resubmit', [PlanPostController::class, 'resubmit']);
    Route::post('/plan-posts/{post}/start-execution', [PlanPostController::class, 'startExecution']);
    Route::delete('/plan-posts/{post}', [App\Http\Controllers\Api\PlanPostController::class, 'destroy']);
    Route::post('/plan-posts/{post}/unlock-field', [App\Http\Controllers\Api\PlanPostController::class, 'unlockField']);

    Route::get('/user-tasks', [PlanPostController::class, 'getUserTasks']);

    Route::post('/content-plans/{contentPlan}/duplicate', [App\Http\Controllers\Api\ContentPlanController::class, 'duplicate']);
    Route::patch('/content-plans/{contentPlan}/toggle-recurrence', [ContentPlanController::class, 'toggleRecurrence']);
    Route::patch('/content-plans/{contentPlan}/toggle-client-notify-permission', [ContentPlanController::class, 'toggleClientNotifyPermission']);
    Route::post('/content-plans/{contentPlan}/client-approval', [ContentPlanController::class, 'toggleClientApproval']);

    Route::apiResource('projects', App\Http\Controllers\Api\ProjectController::class);
    Route::get('/projects/{project}/tasks', [App\Http\Controllers\Api\TaskController::class, 'index']);
    Route::post('/projects/{project}/tasks', [App\Http\Controllers\Api\TaskController::class, 'store']);

    Route::put('/tasks/{task}', [App\Http\Controllers\Api\TaskController::class, 'update']);
    Route::patch('/tasks/{task}', [App\Http\Controllers\Api\TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [App\Http\Controllers\Api\TaskController::class, 'destroy']);

    Route::get('/tasks/{task}/comments', [App\Http\Controllers\Api\TaskCommentController::class, 'index']);
    Route::post('/tasks/{task}/comments', [App\Http\Controllers\Api\TaskCommentController::class, 'store']);
    Route::put('/task-comments/{comment}', [App\Http\Controllers\Api\TaskCommentController::class, 'update']);
    Route::delete('/task-comments/{comment}', [App\Http\Controllers\Api\TaskCommentController::class, 'destroy']);

    Route::get('/dashboard/employee', [App\Http\Controllers\Api\DashboardController::class, 'employeeDashboard']);
    Route::get('/dashboard/manager', [App\Http\Controllers\Api\ManagerDashboardController::class, 'index']);
    Route::get('/dashboard/manager/leaderboard', [App\Http\Controllers\Api\ManagerDashboardController::class, 'leaderboard']);

    Route::get('/system-logs', [App\Http\Controllers\Api\SystemLogController::class, 'index']);
    Route::delete('/system-logs', [App\Http\Controllers\Api\SystemLogController::class, 'destroy']);

    // سلة المهملات (Trash / Recovery Center) - خاص بالمدير فقط
    Route::middleware('role:manager')->prefix('trash')->group(function () {
        Route::get('/counts', [\App\Http\Controllers\Api\TrashController::class, 'counts']);
        Route::get('/{category}', [\App\Http\Controllers\Api\TrashController::class, 'index']);
        Route::post('/{category}/{id}/restore', [\App\Http\Controllers\Api\TrashController::class, 'restore']);
        Route::delete('/{category}/{id}/force', [\App\Http\Controllers\Api\TrashController::class, 'forceDelete']);
        Route::post('/{category}/restore-all', [\App\Http\Controllers\Api\TrashController::class, 'restoreAll']);
        Route::delete('/{category}/empty', [\App\Http\Controllers\Api\TrashController::class, 'emptyTrash']);
    });
});

// مجموعة المهام السريعة
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/quick-tasks', [QuickTaskController::class, 'index']);

    Route::post('/quick-tasks', [QuickTaskController::class, 'store'])->middleware('throttle:uploads_limit');
    Route::post('/quick-tasks/{quickTask}/submit', [QuickTaskController::class, 'submit'])->middleware('throttle:uploads_limit');
    Route::post('/quick-tasks/{quickTask}/review', [QuickTaskController::class, 'review'])->middleware('throttle:uploads_limit');
    Route::post('/quick-tasks/{quickTask}', [QuickTaskController::class, 'update'])->middleware('throttle:uploads_limit');

    Route::delete('/quick-tasks/{quickTask}', [QuickTaskController::class, 'destroy']);
});
