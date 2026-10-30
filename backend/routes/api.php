<?php

use App\Http\Controllers\AIChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NutritionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update']);

    Route::get('/ai-chat/conversations', [AIChatController::class, 'index']);
    Route::post('/ai-chat/conversations', [AIChatController::class, 'store']);
    Route::get('/ai-chat/conversations/{conversationId}', [AIChatController::class, 'show']);
    Route::post('/ai-chat/conversations/{conversationId}/messages', [AIChatController::class, 'storeMessage']);

    Route::get('/nutrition/meals', [NutritionController::class, 'index']);
    Route::post('/nutrition/meals', [NutritionController::class, 'store']);
    Route::get('/nutrition/meals/{mealId}', [NutritionController::class, 'show']);
    Route::match(['put', 'patch'], '/nutrition/meals/{mealId}', [NutritionController::class, 'update']);
    Route::get('/nutrition/goals', [NutritionController::class, 'goalIndex']);
    Route::post('/nutrition/goals', [NutritionController::class, 'storeGoal']);
    Route::get('/nutrition/goals/{goalId}', [NutritionController::class, 'showGoal']);
    Route::match(['put', 'patch'], '/nutrition/goals/{goalId}', [NutritionController::class, 'updateGoal']);

    Route::get('/progress', [ProgressController::class, 'index']);
    Route::post('/progress', [ProgressController::class, 'store']);
    Route::get('/progress/{progressId}', [ProgressController::class, 'show']);
    Route::match(['put', 'patch'], '/progress/{progressId}', [ProgressController::class, 'update']);

    Route::get('/workouts', [WorkoutController::class, 'index']);
    Route::post('/workouts', [WorkoutController::class, 'store']);
    Route::get('/workouts/{workoutId}', [WorkoutController::class, 'show']);
    Route::match(['put', 'patch'], '/workouts/{workoutId}', [WorkoutController::class, 'update']);
});
