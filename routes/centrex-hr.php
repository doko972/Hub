<?php

use App\Http\Controllers\Tools\CentrexHrDashboardController;
use Illuminate\Support\Facades\Route;

// Espace parallèle : aucune route /tools/centrex existante n'est modifiée.
// Le Hub utilise déjà l'alias admin pour ses actions Centrex sensibles.
Route::middleware(['auth', 'admin'])
    ->prefix('tools/centrex-hr')
    ->name('tools.centrex_hr.')
    ->group(function (): void {
        Route::get('/', [CentrexHrDashboardController::class, 'index'])->name('index');
        Route::post('/actualiser-ovh', [CentrexHrDashboardController::class, 'refresh'])->name('refresh');
        Route::get('/mises-a-jour', [CentrexHrDashboardController::class, 'updates'])->name('updates');
        Route::get('/instances/{inventoryKey}', [CentrexHrDashboardController::class, 'instance'])
            ->where('inventoryKey', '[a-f0-9]{64}')->name('instance');
        Route::get('/campagnes/{campaign}', [CentrexHrDashboardController::class, 'show'])
            ->name('campaign');
    });
