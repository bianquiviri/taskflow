<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->enum('role', array_map(fn (TeamRole $role) => $role->value, TeamRole::cases()))
                ->default(TeamRole::Member->value)
                ->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
