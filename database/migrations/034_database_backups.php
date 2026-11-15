<?php

declare(strict_types=1);

use Core\Database\Schema\Blueprint;
use Core\Database\Schema\Schema;

/**
 * Backup register: one row per backup file the application created, with the checksum and the result of
 * its verification. Defined with the portable schema builder, so the same definition creates the table
 * on MySQL, MariaDB and PostgreSQL. The dashboard and `backup:*` commands read it.
 */
return [
    'up' => function (Schema $s): void {
        $s->create('database_backups', function (Blueprint $t): void {
            $t->id();
            $t->string('filename', 190)->unique();
            $t->enum('kind', ['manual', 'scheduled', 'pre_migration', 'pre_restore'])->default('manual');
            $t->string('engine', 20);
            $t->bigInteger('size_bytes')->default(0);
            $t->integer('tables_count')->default(0);
            $t->bigInteger('rows_count')->default(0);
            $t->char('sha256', 64);
            $t->boolean('encrypted')->default(0);
            $t->enum('status', ['verified', 'failed', 'restore_tested'])->default('verified');
            $t->dateTime('verified_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $t->timestamps();
            $t->index('created_at');
        });
    },
    // Refused by the Migrator while the register holds rows: it is the only record of which backups exist.
    'down' => function (Schema $s): void {
        $s->dropIfEmpty('database_backups');
    },
];
