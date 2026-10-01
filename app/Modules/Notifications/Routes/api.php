<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Notifications\Controllers\NotificationController;


Route::get('/', [NotificationController::class, 'index']);
Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);