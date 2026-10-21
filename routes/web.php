<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetaineeController;
use App\Http\Controllers\DetaineeAdmissionDraftController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\EstablishmentStructureController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\VisitsController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MagistrateController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('team.dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'sigec'])->name('dashboard');
    Route::get('reports/occupancy', [DashboardController::class, 'occupancyReport'])->name('reports.occupancy');
    Route::get('reports/occupancy.csv', [DashboardController::class, 'occupancyCsv'])->name('reports.occupancy.csv');
    Route::get('reports/overcrowding.csv', [DashboardController::class, 'overcrowdingCsv'])->name('reports.overcrowding');
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::get('clinic', [ClinicController::class, 'index'])->name('clinic.index');
    Route::get('establishments/{establishment}/clinic-detainees', [ClinicController::class, 'searchDetainees'])->name('clinic.detainees.search');
    Route::get('visits', [VisitsController::class, 'index'])->name('visits.index');
    Route::get('visits/create', [VisitsController::class, 'create'])->name('visits.create');
    Route::get('visitors', [VisitsController::class, 'visitors'])->name('visitors.index');
    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('magistrates', [MagistrateController::class, 'index'])->name('magistrates.index');
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('audit-log/export.csv', [AuditLogController::class, 'export'])->name('audit.export');
    Route::post('magistrates', [MagistrateController::class, 'store'])->name('magistrates.store');
    Route::patch('magistrates/{magistrate}/status', [MagistrateController::class, 'updateStatus'])->name('magistrates.status');
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::patch('incidents/{incident}/status', [IncidentController::class, 'updateStatus'])->name('incidents.status');
    Route::get('establishments/{establishment}/visit-detainees', [VisitsController::class, 'searchDetainees'])->name('visits.detainees.search');
    Route::get('establishments/{establishment}/visit-visitors', [VisitsController::class, 'searchVisitors'])->name('visits.visitors.search');
    Route::post('visitors', [VisitsController::class, 'storeVisitor'])->name('visitors.store');
    Route::post('visits', [VisitsController::class, 'store'])->name('visits.store');
    Route::post('offline-visits/sync', [VisitsController::class, 'syncOffline'])->name('visits.offline.sync');
    Route::post('offline-incidents/sync', [IncidentController::class, 'syncOffline'])->name('incidents.offline.sync');
    Route::post('offline-stocks/sync', [StockController::class, 'syncOffline'])->name('stocks.offline.sync');
    Route::post('offline-clinic/sync', [ClinicController::class, 'syncOffline'])->name('clinic.offline.sync');
    Route::post('offline-detainee-transfers/sync', [DetaineeController::class, 'syncOfflineTransfer'])->name('detainees.offline-transfer.sync');
    Route::post('offline-detainee-admissions/sync', [DetaineeAdmissionDraftController::class, 'syncOffline'])->name('detainees.offline-admission.sync');
    Route::get('offline-detainee-admissions/{offlineReference}/status', [DetaineeAdmissionDraftController::class, 'status'])->name('detainees.offline-admission.status');
    Route::get('admission-reviews', [DetaineeAdmissionDraftController::class, 'index'])->name('admission-reviews.index');
    Route::post('admission-reviews/{draft}/approve', [DetaineeAdmissionDraftController::class, 'approve'])->name('admission-reviews.approve');
    Route::post('admission-reviews/{draft}/reject', [DetaineeAdmissionDraftController::class, 'reject'])->name('admission-reviews.reject');
    Route::patch('visits/{visit}/status', [VisitsController::class, 'updateStatus'])->name('visits.status');
    Route::post('establishments/{establishment}/medical-consultations', [ClinicController::class, 'store'])->name('clinic.consultations.store');
    Route::post('stocks/items', [StockController::class, 'storeItem'])->name('stocks.items.store');
    Route::post('establishments/{establishment}/stock-movements', [StockController::class, 'movement'])->name('stocks.movements.store');
    Route::get('establishments/{establishment}/stock-items', [StockController::class, 'searchItems'])->name('stocks.items.search');
    Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
    Route::post('users/{managedUser}/access', [UserManagementController::class, 'grantAccess'])->name('users.access');
    Route::patch('users/{managedUser}/status', [UserManagementController::class, 'setActive'])->name('users.status');
    Route::get('establishments', [EstablishmentController::class, 'index'])->name('establishments.index');
    Route::get('establishments/create', [EstablishmentController::class, 'create'])->name('establishments.create');
    Route::post('establishments', [EstablishmentController::class, 'store'])->name('establishments.store');
    Route::get('establishments/{establishment}/edit', [EstablishmentController::class, 'edit'])->name('establishments.edit');
    Route::put('establishments/{establishment}', [EstablishmentController::class, 'update'])->name('establishments.update');
    Route::get('establishments/{establishment}/structure', [EstablishmentStructureController::class, 'show'])->name('establishments.structure');
    Route::get('establishments/{establishment}/unassigned-detainees', [EstablishmentStructureController::class, 'searchUnassignedDetainees'])->name('establishments.detainees.search');
    Route::get('establishments/{establishment}/available-cells', [EstablishmentStructureController::class, 'searchAvailableCells'])->name('establishments.cells.search');
    Route::post('establishments/{establishment}/buildings', [EstablishmentStructureController::class, 'storeBuilding'])->name('establishments.buildings.store');
    Route::post('establishments/{establishment}/cells', [EstablishmentStructureController::class, 'storeCell'])->name('establishments.cells.store');
    Route::post('establishments/{establishment}/cell-assignments', [EstablishmentStructureController::class, 'assign'])->name('establishments.cell-assignments.store');
    Route::post('establishments/{establishment}/cell-assignments/{assignment}/end', [EstablishmentStructureController::class, 'endAssignment'])->name('establishments.cell-assignments.end');
    Route::post('establishments/{establishment}/cell-assignments/{assignment}/move', [EstablishmentStructureController::class, 'moveAssignment'])->name('establishments.cell-assignments.move');
    Route::get('detainees', [DetaineeController::class, 'index'])->name('detainees.index');
    Route::get('detainees/create', [DetaineeController::class, 'create'])->name('detainees.create');
    Route::get('detainees/{detainee}/edit-identity', [DetaineeController::class, 'editIdentity'])->name('detainees.identity.edit');
    Route::patch('detainees/{detainee}/identity', [DetaineeController::class, 'updateIdentity'])->name('detainees.identity.update');
    Route::get('detainees/{detainee}', [DetaineeController::class, 'show'])->name('detainees.show');
    Route::get('detainees/{detainee}/photo', [DetaineeController::class, 'photo'])->name('detainees.photo');
    Route::post('detainees/{detainee}/transfer', [DetaineeController::class, 'transfer'])->name('detainees.transfer');
    Route::get('establishments/{establishment}/transfer-cells', [DetaineeController::class, 'searchTransferCells'])->name('detainees.transfer-cells.search');
    Route::get('establishments/{establishment}/admission-buildings', [DetaineeController::class, 'searchAdmissionBuildings'])->name('detainees.admission-buildings.search');
    Route::get('establishments/{establishment}/buildings/{building}/admission-cells', [DetaineeController::class, 'searchAdmissionCells'])->name('detainees.admission-cells.search');
    Route::post('detainees', [DetaineeController::class, 'store'])->name('detainees.store');
    Route::post('detainees/{detainee}/release', [DetaineeController::class, 'release'])->name('detainees.release');
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';








