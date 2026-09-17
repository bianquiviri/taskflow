<?php

declare(strict_types=1);

it('returns a successful response', function () {
    $this->get('/')->assertOk();
});

it('renders the welcome page as an Inertia response', function () {
    $this->get('/')
        ->assertInertia(
            fn ($page) => $page
            ->component('Welcome')
            ->has('appName')
            ->has('version'),
        );
});
