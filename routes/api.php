<?php

use App\Http\Controllers\HelpdeskController;
use Illuminate\Support\Facades\Route;

Route::get('helpdesk', [HelpdeskController::class, 'apiIndex'])
    ->middleware('grafana.api')
    ->name('api.helpdesk.index');