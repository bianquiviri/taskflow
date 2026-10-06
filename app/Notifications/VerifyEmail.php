<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as NativeVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;

final class VerifyEmail extends NativeVerifyEmail implements ShouldQueue
{
}
