<?php

declare(strict_types=1);

use App\Mcp\Tools\Teams\ListTeamsTool;
use App\Models\Team;
use App\Models\User;

it('lists the teams the authenticated user belongs to', function () {
    $owner = User::factory()->create();
    Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    $response = json_decode((new ListTeamsTool())->handle(), true);

    expect($response)->toHaveKey('teams');
});
