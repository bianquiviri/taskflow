<?php

declare(strict_types=1);

use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyTaskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskFileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInvitationAcceptController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\WelcomeController;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Gate::define('teams.create', fn (User $user): bool => $user->can('create', Team::class));

Route::middleware('auth')->group(function (): void {
    Route::patch('/theme', [ThemeController::class, 'update'])->name('theme.update');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::get('/projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
    Route::get('/tasks/mine', [MyTaskController::class, 'index'])->name('tasks.mine.index');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    Route::get('/tasks/{task}/comments', [CommentController::class, 'index'])->name('comments.index');
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::post('/tasks/{task}/files', [TaskFileController::class, 'store'])->name('tasks.files.store');
    Route::scopeBindings()->group(function (): void {
        Route::get('/tasks/{task}/files/{file}/download', [TaskFileController::class, 'download'])->name('tasks.files.download');
        Route::delete('/tasks/{task}/files/{file}', [TaskFileController::class, 'destroy'])->name('tasks.files.destroy');
    });
    Route::get('/team-invitations/{token}', [TeamInvitationAcceptController::class, 'show'])->name('team-invitations.accept');
    Route::post('/team-invitations/{token}', [TeamInvitationAcceptController::class, 'store'])->name('team-invitations.accept.store');

    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::get('/teams/{team}/settings', [TeamController::class, 'settings'])->name('teams.settings');

    Route::scopeBindings()->group(function (): void {
        Route::post('/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
        Route::post('/teams/{team}/invitations/{invitation}/resend', [TeamInvitationController::class, 'resend'])->name('teams.invitations.resend');
        Route::delete('/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
        Route::patch('/teams/{team}/members/{membership}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('/teams/{team}/members/{membership}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
    });
});

// The profile stays reachable while the address is unverified, so a mistyped
// email can still be corrected before the account is used again.
Route::middleware('auth.unverified')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::post('/profile/avatar', [ProfileController::class, 'storeAvatar'])->name('profile.avatar.store');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::get('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
});
