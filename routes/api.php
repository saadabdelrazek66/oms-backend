<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContentPlanController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PlanPostController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Modules\QuickTasks\Controllers\QuickTaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Test Update

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 🛡️ مستوى المصادقة: 5 طلبات في الدقيقة لمنع التخمين العشوائي للباسورد
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth_limit');
Route::get('/settings/public', [SystemSettingController::class, 'publicSettings']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/content-plans/{plan}/posts', [PlanPostController::class, 'index']);
    Route::post('/content-plans/{plan}/posts', [PlanPostController::class, 'store']);
    Route::put('/plan-posts/{post}', [PlanPostController::class, 'update']);
    Route::post('/plan-posts/{post}/review', [PlanPostController::class, 'review']);

    Route::get('/departments/list-all', [DepartmentController::class, 'listAll']);
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view,plans.view,posts.view,projects.view,tasks.view,quick_tasks.view');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

    // --- نظام إدارة الصلاحيات (RBAC Permissions) للمدير ---
    Route::middleware('role:manager')->prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/users/{user}', [PermissionController::class, 'getUserPermissions']);
        Route::post('/users/{user}', [PermissionController::class, 'updateUserPermissions']);
        Route::post('/users/{user}/preset', [PermissionController::class, 'applyPreset']);
    });

    // Content Plans CRUD
    Route::post('/content-plans', [ContentPlanController::class, 'store'])->middleware('permission:plans.create');
    Route::put('/content-plans/{content_plan}', [ContentPlanController::class, 'update'])->middleware('permission:plans.edit');
    Route::delete('/content-plans/{content_plan}', [ContentPlanController::class, 'destroy'])->middleware('permission:plans.delete');

    Route::get('/departments', [DepartmentController::class, 'index'])->middleware('permission:departments.view');
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])->middleware('permission:departments.view');
    Route::post('/departments', [DepartmentController::class, 'store'])->middleware('permission:departments.manage');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:departments.manage');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.manage');
    Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:clients.view');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->middleware('permission:clients.view');
    Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:clients.create');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->middleware('permission:clients.edit');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->middleware('permission:clients.delete');

    // إعدادات النظام - تقدير ساعات العمل لعناصر الخطط (خاص بالمدير فقط)
        // جلب قائمة خفيفة ومجردة بأنواع الخطط للقوائم المنسدلة
    
    Route::get('/plan-categories/options', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'options']);

    Route::middleware('role:manager')->prefix('settings')->group(function () {
        // الإعدادات العامة للمنظومة
        Route::get('/general', [SystemSettingController::class, 'index']);
        Route::post('/general', [SystemSettingController::class, 'update']);
        Route::post('/general/reset', [SystemSettingController::class, 'resetGroup']);

        Route::get('/plan-item-estimates', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'index']);
        Route::post('/plan-item-categories', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'storeCategory']);
        Route::put('/plan-item-categories/{category}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'updateCategory']);
        Route::delete('/plan-item-categories/{category}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'destroyCategory']);

        Route::post('/plan-item-estimates', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'storeItem']);
        Route::put('/plan-item-estimates/bulk-update', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'bulkUpdate']);
        Route::put('/plan-item-estimates/{estimate}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'updateItem']);
        Route::delete('/plan-item-estimates/{estimate}', [\App\Http\Controllers\Api\PlanItemEstimateController::class, 'destroyItem']);
    });


    Route::get('/content-plans', [ContentPlanController::class, 'index'])->middleware('permission:plans.view');
    Route::get('/content-plans/{content_plan}', [ContentPlanController::class, 'show'])->middleware('permission:plans.view');
    Route::get('/plans/boards', [App\Http\Controllers\Api\ContentPlanController::class, 'boardPlans'])->middleware('permission:plans.view,posts.view');

    // مسارات أفعال الخطط
    Route::post('content-plans/{content_plan}/submit-review', [ContentPlanController::class, 'submitForReview']);
    Route::post('content-plans/{content_plan}/final-delivery', [ContentPlanController::class, 'submitFinalDelivery']);
    Route::post('content-plans/{content_plan}/approve', [ContentPlanController::class, 'approvePlan'])->middleware('permission:plans.approve');
    Route::post('content-plans/{content_plan}/reject', [ContentPlanController::class, 'rejectPlan'])->middleware('permission:plans.reject');
    Route::put('content-plans/{content_plan}/details', [ContentPlanController::class, 'updateDetails']);

    // مسارات متابعة العملاء (Client Follow-ups)
    Route::post('content-plans/{content_plan}/follow-ups', [App\Http\Controllers\Api\ClientFollowUpController::class, 'store']);
    Route::put('follow-ups/{client_follow_up}', [App\Http\Controllers\Api\ClientFollowUpController::class, 'update']);
    Route::delete('follow-ups/{client_follow_up}', [App\Http\Controllers\Api\ClientFollowUpController::class, 'destroy']);

    // مسارات خزنة العملاء (Client Vault) - للمديرين فقط
    Route::prefix('vault')->group(function () {
        Route::get('status', [App\Http\Controllers\Api\ClientVaultController::class, 'status'])->middleware('permission:vault.view');
        Route::post('setup-pin', [App\Http\Controllers\Api\ClientVaultController::class, 'setupPin'])->middleware('permission:vault.manage');
        Route::post('verify-pin', [App\Http\Controllers\Api\ClientVaultController::class, 'verifyPin'])->middleware('permission:vault.view');

        Route::get('clients', [App\Http\Controllers\Api\ClientVaultController::class, 'index'])->middleware('permission:vault.view');
        Route::post('clients/{client}/credentials', [App\Http\Controllers\Api\ClientVaultController::class, 'store'])->middleware('permission:vault.manage');
        Route::put('credentials/{credential}', [App\Http\Controllers\Api\ClientVaultController::class, 'update'])->middleware('permission:vault.manage');
        Route::delete('credentials/{credential}', [App\Http\Controllers\Api\ClientVaultController::class, 'destroy'])->middleware('permission:vault.manage');
    });

    Route::post('/plan-posts/{post}/resubmit', [PlanPostController::class, 'resubmit']);
    Route::post('/plan-posts/{post}/start-execution', [PlanPostController::class, 'startExecution']);
    Route::delete('/plan-posts/{post}', [App\Http\Controllers\Api\PlanPostController::class, 'destroy']);
    Route::post('/plan-posts/{post}/unlock-field', [App\Http\Controllers\Api\PlanPostController::class, 'unlockField']);
    Route::post('/plan-posts/{post}/restore-deadline', [PlanPostController::class, 'restoreDeadline']);

    Route::get('/user-tasks', [PlanPostController::class, 'getUserTasks']);

    // --- محرك سعة العمل والترحيل التلقائي للطوارئ ---
    Route::get('/workload/today-board', [\App\Http\Controllers\Api\WorkloadController::class, 'getTodayBoard']);
    Route::get('/workload/daily-capacity', [\App\Http\Controllers\Api\WorkloadController::class, 'getDailyCapacity']);
    Route::get('/workload/week-capacity', [\App\Http\Controllers\Api\WorkloadController::class, 'getWeekCapacity']);
    Route::post('/workload/cascade-urgent', [\App\Http\Controllers\Api\WorkloadController::class, 'triggerAutoCascade']);
    Route::post('/workload/rollback-urgent', [\App\Http\Controllers\Api\WorkloadController::class, 'triggerAutoRollback']);
    Route::post('/workload/shift-hours', [\App\Http\Controllers\Api\WorkloadController::class, 'shiftHours']);
    Route::post('/workload/restore-shifted-hours', [\App\Http\Controllers\Api\WorkloadController::class, 'restoreShiftedHours']);

    Route::post('/content-plans/{contentPlan}/duplicate', [App\Http\Controllers\Api\ContentPlanController::class, 'duplicate'])->middleware('permission:plans.duplicate');
    Route::patch('/content-plans/{contentPlan}/toggle-recurrence', [ContentPlanController::class, 'toggleRecurrence']);
    Route::patch('/content-plans/{contentPlan}/toggle-client-notify-permission', [ContentPlanController::class, 'toggleClientNotifyPermission']);
    Route::post('/content-plans/{contentPlan}/client-approval', [ContentPlanController::class, 'toggleClientApproval']);

    Route::get('/projects', [App\Http\Controllers\Api\ProjectController::class, 'index'])->middleware('permission:projects.view');
    Route::get('/projects/{project}', [App\Http\Controllers\Api\ProjectController::class, 'show'])->middleware('permission:projects.view');
    Route::post('/projects', [App\Http\Controllers\Api\ProjectController::class, 'store'])->middleware('permission:projects.create');
    Route::put('/projects/{project}', [App\Http\Controllers\Api\ProjectController::class, 'update'])->middleware('permission:projects.edit');
    Route::delete('/projects/{project}', [App\Http\Controllers\Api\ProjectController::class, 'destroy'])->middleware('permission:projects.delete');
    Route::get('/projects/{project}/tasks', [App\Http\Controllers\Api\TaskController::class, 'index'])->middleware('permission:tasks.view');
    Route::post('/projects/{project}/tasks', [App\Http\Controllers\Api\TaskController::class, 'store'])->middleware('permission:tasks.create');

    Route::put('/tasks/{task}', [App\Http\Controllers\Api\TaskController::class, 'update'])->middleware('permission:tasks.edit');
    Route::patch('/tasks/{task}', [App\Http\Controllers\Api\TaskController::class, 'update'])->middleware('permission:tasks.edit');
    Route::delete('/tasks/{task}', [App\Http\Controllers\Api\TaskController::class, 'destroy'])->middleware('permission:tasks.delete');
    Route::post('/tasks/{task}/restore-due-date', [App\Http\Controllers\Api\TaskController::class, 'restoreDueDate']);

    Route::get('/tasks/{task}/comments', [App\Http\Controllers\Api\TaskCommentController::class, 'index']);
    Route::post('/tasks/{task}/comments', [App\Http\Controllers\Api\TaskCommentController::class, 'store']);
    Route::put('/task-comments/{comment}', [App\Http\Controllers\Api\TaskCommentController::class, 'update']);
    Route::delete('/task-comments/{comment}', [App\Http\Controllers\Api\TaskCommentController::class, 'destroy']);

    Route::get('/dashboard/employee', [App\Http\Controllers\Api\DashboardController::class, 'employeeDashboard']);
    Route::get('/dashboard/manager', [App\Http\Controllers\Api\ManagerDashboardController::class, 'index']);
    Route::get('/dashboard/manager/leaderboard', [App\Http\Controllers\Api\ManagerDashboardController::class, 'leaderboard']);
    Route::get('/dashboard/manager/drilldown', [App\Http\Controllers\Api\ManagerDashboardController::class, 'drilldown']);

    Route::get('/system-logs', [App\Http\Controllers\Api\SystemLogController::class, 'index'])->middleware('permission:logs.view');
    Route::delete('/system-logs', [App\Http\Controllers\Api\SystemLogController::class, 'destroy']);

    // سلة المهملات (Trash / Recovery Center) - خاص بالمدير فقط
    Route::prefix('trash')->group(function () {
        Route::get('/counts', [\App\Http\Controllers\Api\TrashController::class, 'counts'])->middleware('permission:trash.view');
        Route::get('/{category}', [\App\Http\Controllers\Api\TrashController::class, 'index'])->middleware('permission:trash.view');
        Route::post('/{category}/{id}/restore', [\App\Http\Controllers\Api\TrashController::class, 'restore'])->middleware('permission:trash.restore');
        Route::post('/{category}/restore-all', [\App\Http\Controllers\Api\TrashController::class, 'restoreAll'])->middleware('permission:trash.restore');
        Route::delete('/{category}/{id}/force', [\App\Http\Controllers\Api\TrashController::class, 'forceDelete'])->middleware('permission:trash.force_delete');
        Route::delete('/{category}/empty', [\App\Http\Controllers\Api\TrashController::class, 'emptyTrash'])->middleware('permission:trash.force_delete');
    });
});

// مجموعة المهام السريعة
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/quick-tasks', [QuickTaskController::class, 'index'])->middleware('permission:quick_tasks.view');

    Route::post('/quick-tasks', [QuickTaskController::class, 'store'])->middleware(['throttle:uploads_limit', 'permission:quick_tasks.create']);
    Route::post('/quick-tasks/{quickTask}/submit', [QuickTaskController::class, 'submit'])->middleware('throttle:uploads_limit');
    Route::post('/quick-tasks/{quickTask}/review', [QuickTaskController::class, 'review'])->middleware(['throttle:uploads_limit', 'permission:quick_tasks.review']);
    Route::post('/quick-tasks/{quickTask}', [QuickTaskController::class, 'update'])->middleware(['throttle:uploads_limit', 'permission:quick_tasks.edit']);

    Route::delete('/quick-tasks/{quickTask}', [QuickTaskController::class, 'destroy'])->middleware('permission:quick_tasks.delete');
});
