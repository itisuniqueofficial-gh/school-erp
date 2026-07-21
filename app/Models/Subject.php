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
    protected array $fillable = ['name', 'code', 'type'];
}
