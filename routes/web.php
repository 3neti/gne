<?php

use App\Http\Controllers\DocumentSetController;
use App\Http\Controllers\RepositoryWorkbenchController;
use App\Http\Controllers\ResolvedDocumentController;
use App\Http\Controllers\ShowCompiledBrowserDocumentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', RepositoryWorkbenchController::class)->name('dashboard')->defaults('section', 'dashboard');
    Route::get('repository', RepositoryWorkbenchController::class)->name('repository')->defaults('section', 'repository');
    Route::get('profiles', RepositoryWorkbenchController::class)->name('profiles')->defaults('section', 'profiles');
    Route::get('scenarios', RepositoryWorkbenchController::class)->name('scenarios')->defaults('section', 'scenarios');
    Route::get('artifacts', RepositoryWorkbenchController::class)->name('artifacts')->defaults('section', 'artifacts');
    Route::get('materialization', RepositoryWorkbenchController::class)->name('materialization')->defaults('section', 'materialization');
    Route::get('documents', RepositoryWorkbenchController::class)->name('documents')->defaults('section', 'documents');
    Route::get('document-sets', [DocumentSetController::class, 'index'])->name('document_sets.index');
    Route::get('document-sets/{subject}', [DocumentSetController::class, 'show'])->name('document_sets.show');
    Route::get('documents/{document}/{subject}', ResolvedDocumentController::class)->name('documents.show');
    Route::match(['GET', 'HEAD'], 'subjects/{subject}/documents/{document}/browser', ShowCompiledBrowserDocumentController::class)->name('documents.browser');
});

require __DIR__.'/settings.php';
