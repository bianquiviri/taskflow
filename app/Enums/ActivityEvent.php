<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityEvent: string
{
    case ProjectCreated = 'project.created';
    case ProjectUpdated = 'project.updated';
    case ProjectArchived = 'project.archived';
    case TaskCreated = 'task.created';
    case TaskUpdated = 'task.updated';
    case TaskStatusChanged = 'task.status_changed';
    case MemberInvited = 'team.member_invited';
    case MemberAccepted = 'team.member_accepted';
    case MemberRevoked = 'team.member_revoked';
}
