<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;

/**
 * Subject model.
 */
final class Subject extends Model
{
    protected string $table = 'subjects';
    protected array $fillable = ['name', 'uid', 'public_id', 'code', 'type', 'max_marks', 'pass_marks', 'description', 'status'];
}
