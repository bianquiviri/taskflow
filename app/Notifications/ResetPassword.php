<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as NativeResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ResetPassword extends NativeResetPassword implements ShouldQueue
{
}
