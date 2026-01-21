<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\GameController;

Route::get('/game', [GameController::class, 'index']);
Route::post('/game/action', [GameController::class, 'action']);
Route::get('/game/adventure', [GameController::class, 'adventure']);
Route::post('/game/adventure/action', [GameController::class, 'adventureAction']);
Route::post('/game/adventure/start', [GameController::class, 'adventureStart']);
