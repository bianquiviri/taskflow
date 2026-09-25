<?php

declare(strict_types=1);

use App\Models\Project;

it('generates a slug from the project name on creation', function () {
    $project = Project::factory()->create(['name' => 'Website Redesign']);

    expect($project->slug)->toBe('website-redesign');
});

it('disambiguates duplicate slugs with a numeric suffix', function () {
    Project::factory()->create(['name' => 'Website']);
    $second = Project::factory()->create(['name' => 'Website']);

    expect($second->slug)->toBe('website-2');
});

it('casts archived_at to a datetime instance', function () {
    $project = Project::factory()->archived()->create();

    expect($project->archived_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('scopes active and archived projects', function () {
    Project::factory()->create();
    $archived = Project::factory()->archived()->create();

    expect(Project::active()->pluck('id'))->not->toContain($archived->id)
        ->and(Project::archived()->pluck('id'))->toContain($archived->id);
});
