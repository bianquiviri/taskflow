<?php

declare(strict_types=1);

use App\Mcp\Tools\Dashboard\GetDashboardSummaryTool;
use App\Mcp\Tools\Projects\GetProjectTool;
use App\Mcp\Tools\Projects\ListProjectsTool;
use App\Mcp\Tools\Tasks\GetTaskTool;
use App\Mcp\Tools\Tasks\ListMyTasksTool;
use App\Mcp\Tools\Tasks\ListTasksByProjectTool;
use App\Mcp\Tools\Teams\ListTeamsTool;
use PhpMcp\Laravel\Facades\Mcp;

Mcp::tool([ListProjectsTool::class, 'handle'])->name('taskflow.projects.list');
Mcp::tool([GetProjectTool::class, 'handle'])->name('taskflow.projects.get');
Mcp::tool([ListTeamsTool::class, 'handle'])->name('taskflow.teams.list');
Mcp::tool([ListMyTasksTool::class, 'handle'])->name('taskflow.tasks.mine');
Mcp::tool([ListTasksByProjectTool::class, 'handle'])->name('taskflow.tasks.by_project');
Mcp::tool([GetTaskTool::class, 'handle'])->name('taskflow.tasks.get');
Mcp::tool([GetDashboardSummaryTool::class, 'handle'])->name('taskflow.dashboard.summary');
