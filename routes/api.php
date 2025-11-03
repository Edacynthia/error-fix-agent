<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ErrorFixController;

Route::post('/error-fix', [ErrorFixController::class, 'fix']);
