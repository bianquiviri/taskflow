<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('attaches a file to a task', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->post(route('tasks.files.store', $task), [
            'file' => UploadedFile::fake()->createWithContent('Quarterly report.pdf', "%PDF-1.7\nquarterly numbers"),
        ])
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('success', 'File attached.');

    $this->assertDatabaseHas('task_files', [
        'task_id' => $task->id,
        'user_id' => $owner->id,
        'disk' => 'attachments',
        'original_name' => 'Quarterly report.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $file = TaskFile::query()->sole();

    Storage::disk('attachments')->assertExists($file->path);
});

it('stores the file under a sanitised unique name of the task', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->post(route('tasks.files.store', $task), [
            'file' => UploadedFile::fake()->createWithContent('../../../etc/passwd.pdf', "%PDF-1.7\nsecret"),
        ])
        ->assertRedirect(route('tasks.show', $task));

    $file = TaskFile::query()->sole();

    expect($file->path)->toStartWith("tasks/{$task->id}/")
        ->and($file->path)->toEndWith('.pdf')
        ->and($file->path)->not->toContain('passwd')
        ->and($file->path)->not->toContain('..')
        ->and($file->original_name)->toBe('passwd.pdf');
});

it('gives two uploads of the same name two different stored files', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $upload = fn (): UploadedFile => UploadedFile::fake()->createWithContent('notes.txt', 'same name, same bytes');

    $this->actingAs($owner)
        ->post(route('tasks.files.store', $task), ['file' => $upload()])
        ->assertRedirect(route('tasks.show', $task));

    $this->actingAs($owner)
        ->post(route('tasks.files.store', $task), ['file' => $upload()])
        ->assertRedirect(route('tasks.show', $task));

    $paths = TaskFile::query()->pluck('path');

    expect($paths)->toHaveCount(2)->and($paths->unique())->toHaveCount(2);
});

it('rejects a file whose type is not allowed', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->post(route('tasks.files.store', $task), [
            'file' => UploadedFile::fake()->create('payload.exe', 12, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors([
            'file' => 'The file type is not allowed.',
        ]);

    expect(TaskFile::query()->count())->toBe(0);
    expect(Storage::disk('attachments')->allFiles())->toBe([]);
});

it('rejects a file above the size limit', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->post(route('tasks.files.store', $task), [
            'file' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
        ])
        ->assertSessionHasErrors([
            'file' => 'The file may not be larger than 5 MB.',
        ]);

    expect(TaskFile::query()->count())->toBe(0);
    expect(Storage::disk('attachments')->allFiles())->toBe([]);
});

it('rejects an upload without a file', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->post(route('tasks.files.store', $task))
        ->assertSessionHasErrors([
            'file' => 'Please choose a file to attach.',
        ]);

    expect(TaskFile::query()->count())->toBe(0);
});

it('lets a project member download an attachment', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => ($member = User::factory()->create())->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create([
        'original_name' => 'spec.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $response = $this->actingAs($member)->get(route('tasks.files.download', [$task, $file]));

    $response->assertOk();
    $response->assertDownload('spec.pdf');
    $response->assertHeader('Content-Type', 'application/pdf');

    expect($response->streamedContent())->toBe('binary-payload');
});

it('never leaks the stored name or the disk to the browser', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create(['original_name' => 'spec.pdf']);
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $response = $this->actingAs($owner)->get(route('tasks.files.download', [$task, $file]));

    expect($response->baseResponse->headers->all())
        ->not->toHaveKey('X-Storage-Path')
        ->and(json_encode($response->baseResponse->headers->all()))
        ->not->toContain($file->path)
        ->and($response->streamedContent())->not->toContain($file->path);

    $this->actingAs($owner)
        ->get(route('tasks.show', $task))
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Show')
            ->missing('attachments.0.path')
            ->missing('attachments.0.disk'),
        );
});

it('answers 404 when the stored file is gone', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();

    $this->actingAs($owner)
        ->get(route('tasks.files.download', [$task, $file]))
        ->assertNotFound();
});

it('answers 404 when the file belongs to another task', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $other = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($other, 'task')->for($owner, 'user')->create();
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $this->actingAs($owner)
        ->get(route('tasks.files.download', [$task, $file]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('tasks.files.destroy', [$task, $file]))
        ->assertNotFound();

    $this->assertDatabaseHas('task_files', ['id' => $file->id]);
});

it('forbids an outsider from uploading or downloading', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $this->actingAs($outsider)
        ->post(route('tasks.files.store', $task), [
            'file' => UploadedFile::fake()->createWithContent('sneak.pdf', "%PDF-1.7\nsneaking in"),
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->get(route('tasks.files.download', [$task, $file]))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->delete(route('tasks.files.destroy', [$task, $file]))
        ->assertForbidden();

    expect(TaskFile::query()->count())->toBe(1);
});

it('answers 404 for a guest', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $this->get(route('tasks.files.download', [$task, $file]))->assertRedirect(route('login'));
    $this->post(route('tasks.files.store', $task), [
        'file' => UploadedFile::fake()->createWithContent('sneak.pdf', "%PDF-1.7\nsneaking in"),
    ])->assertRedirect(route('login'));
});

it('removes an attachment uploaded by the current user', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => ($member = User::factory()->create())->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($member, 'user')->create();
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $this->actingAs($member)
        ->from(route('tasks.show', $task))
        ->delete(route('tasks.files.destroy', [$task, $file]))
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('success', 'File removed.');

    $this->assertDatabaseMissing('task_files', ['id' => $file->id]);

    Storage::disk('attachments')->assertMissing($file->path);
});

it('lets a project admin remove an attachment uploaded by someone else', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => ($admin = User::factory()->create())->id, 'role' => ProjectRole::Admin]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $this->actingAs($admin)
        ->from(route('tasks.show', $task))
        ->delete(route('tasks.files.destroy', [$task, $file]))
        ->assertSessionHas('success', 'File removed.');

    Storage::disk('attachments')->assertMissing($file->path);
});

it('forbids a plain member from removing an attachment they did not upload', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => ($member = User::factory()->create())->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();
    Storage::disk('attachments')->put($file->path, 'binary-payload');

    $this->actingAs($member)
        ->delete(route('tasks.files.destroy', [$task, $file]))
        ->assertForbidden();

    $this->assertDatabaseHas('task_files', ['id' => $file->id]);

    Storage::disk('attachments')->assertExists($file->path);
});

it('lists the attachments of a task in the task show page', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create([
        'original_name' => 'spec.pdf',
        'size' => 2048,
    ]);

    $this->actingAs($owner)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Show')
            ->where('attachments.0.id', $file->id)
            ->where('attachments.0.name', 'spec.pdf')
            ->where('attachments.0.mime_type', 'application/pdf')
            ->where('attachments.0.size', 2048)
            ->where('attachments.0.human_size', '2 KB')
            ->where('attachments.0.uploaded_by', 'Ada Lovelace')
            ->where('attachments.0.download_url', route('tasks.files.download', [$task, $file]))
            ->where('attachments.0.delete_url', route('tasks.files.destroy', [$task, $file]))
            ->where('attachments.0.can_delete', true)
            ->where('attachmentRules.max_size', '5 MB')
            ->has('attachmentRules.extensions')
            ->has('attachments', 1),
        );
});

it('tells a member that they may not remove a file they did not upload', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $member = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create();
    $file = TaskFile::factory()->for($task, 'task')->for($owner, 'user')->create();

    $this->actingAs($member)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Show')
            ->where('attachments.0.can_delete', false),
        );
});

it('shows an empty attachment list for a task without files', function () {
    Storage::fake('attachments');

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $task = Task::factory()->for($project, 'project')->create();

    $this->actingAs($owner)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
            ->component('Tasks/Show')
            ->has('attachments', 0),
        );
});
