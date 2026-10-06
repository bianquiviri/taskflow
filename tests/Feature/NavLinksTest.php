<?php

declare(strict_types=1);

use App\Models\User;

/**
 * The literal hrefs the navigation components render. Template literals such
 * as `/teams/${id}` cannot be resolved statically and are covered by their own
 * feature tests.
 *
 * @return list<string>
 */
function renderedNavigationHrefs(): array
{
    $files = [
        resource_path('js/Layouts/AppLayout.vue'),
        resource_path('js/Components/Onboarding/Checklist.vue'),
    ];

    $hrefs = [];

    foreach ($files as $file) {
        preg_match_all("/href:\\s*'([^']+)'/", file_get_contents($file), $matches);

        $hrefs = [...$hrefs, ...$matches[1]];
    }

    return array_values(array_unique(array_filter($hrefs, fn (string $href): bool => str_starts_with($href, '/'))));
}

it('points every rendered navigation link at a real route', function () {
    $hrefs = renderedNavigationHrefs();

    expect($hrefs)
        ->toContain('/dashboard', '/projects', '/tasks/mine', '/teams/create', '/profile')
        ->not->toContain('/tasks', '/settings');

    $user = User::factory()->create();

    foreach ($hrefs as $href) {
        $this->actingAs($user)->get($href)->assertOk();
    }
});

it('sends guests to the login page from every rendered navigation link', function () {
    foreach (renderedNavigationHrefs() as $href) {
        $this->get($href)->assertRedirect(route('login'));
    }
});
