<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AttachmentRules;
use Database\Factories\TaskFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class TaskFile extends Model
{
    /** @use HasFactory<TaskFileFactory> */
    use HasFactory;

    /**
     * The private disk an attachment is kept on unless another one is given.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk' => AttachmentRules::DISK,
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
