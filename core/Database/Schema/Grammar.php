<?php

declare(strict_types=1);

namespace Core\Database\Schema;

use Core\Database\DatabaseManager;

class Grammar
{
    public static function driver(): string
    {
        return DatabaseManager::instance()->getDriver();
    }

    public static function autoIncrement(): string
    {
        $d = self::driver();
        if ($d === 'pgsql') {
            return 'SERIAL';
        }
        if ($d === 'sqlite') {
            return 'INTEGER PRIMARY KEY AUTOINCREMENT';
        }
        if ($d === 'sqlsrv') {
            return 'INT IDENTITY(1,1)';
        }
        return 'INT UNSIGNED NOT NULL AUTO_INCREMENT';
    }

    public static function primaryKey(string $column = 'id'): string
    {
        $d = self::driver();
        if ($d === 'sqlite') {
            return '`' . $column . '` INTEGER PRIMARY KEY AUTOINCREMENT';
        }
        if ($d === 'pgsql') {
            return '"' . $column . '" SERIAL PRIMARY KEY';
        }
        if ($d === 'sqlsrv') {
            return '[' . $column . '] INT IDENTITY(1,1) PRIMARY KEY';
        }
        return '`' . $column . '` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    }

    public static function engine(): string
    {
        $d = self::driver();
        if (in_array($d, ['mysql', 'mariadb'], true)) {
            return ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC';
        }
        return '';
    }

    public static function boolean(string $name, bool $default = false): string
    {
        $d = self::driver();
        $def = $default ? 'TRUE' : 'FALSE';
        if (in_array($d, ['mysql', 'mariadb'], true)) {
            return "`$name` TINYINT(1) NOT NULL DEFAULT " . ($default ? '1' : '0');
        }
        if ($d === 'pgsql') {
            return "\"$name\" BOOLEAN NOT NULL DEFAULT $def";
        }
        if ($d === 'sqlite') {
            return "`$name` INTEGER NOT NULL DEFAULT " . ($default ? '1' : '0');
        }
        if ($d === 'sqlsrv') {
            return "[$name] BIT NOT NULL DEFAULT " . ($default ? '1' : '0');
        }
        return "`$name` TINYINT(1) NOT NULL DEFAULT " . ($default ? '1' : '0');
    }

    public static function enum(string $name, array $values, $default = null): string
    {
        $d = self::driver();
        if (in_array($d, ['mysql', 'mariadb'], true)) {
            $vals = "'" . implode("','", array_map(function ($v) { return str_replace("'", "\\'", $v); }, $values)) . "'";
            $def = $default !== null ? " DEFAULT '$default'" : '';
            return "`$name` ENUM($vals) NOT NULL$def";
        }
        $max = 0;
        foreach ($values as $v) { if (strlen($v) > $max) $max = strlen($v); }
        if ($max < 1) $max = 50;
        $def = $default !== null ? " DEFAULT '$default'" : '';
        if ($d === 'pgsql') {
            $check = '"' . $name . '" IN (' . "'" . implode("','", array_map(function ($v) { return str_replace("'", "''", $v); }, $values)) . "')";
            return "\"$name\" VARCHAR($max) NOT NULL$def CHECK ($check)";
        }
        if ($d === 'sqlsrv') {
            $check = '[' . $name . '] IN (' . "'" . implode("','", array_map(function ($v) { return str_replace("'", "''", $v); }, $values)) . "')";
            return "[$name] NVARCHAR($max) NOT NULL$def CHECK ($check)";
        }
        return "`$name` VARCHAR($max) NOT NULL$def";
    }

    public static function nowDefault(): string
    {
        $d = self::driver();
        if ($d === 'pgsql') {
            return 'DEFAULT CURRENT_TIMESTAMP';
        }
        if ($d === 'sqlite') {
            return "DEFAULT (datetime('now'))";
        }
        if ($d === 'sqlsrv') {
            return 'DEFAULT GETDATE()';
        }
        return 'DEFAULT CURRENT_TIMESTAMP';
    }

    public static function onUpdateNow(): string
    {
        $d = self::driver();
        if (in_array($d, ['mysql', 'mariadb'], true)) {
            return ' ON UPDATE CURRENT_TIMESTAMP';
        }
        return '';
    }

    public static function quote(string $identifier): string
    {
        return DatabaseManager::instance()->quoteIdentifier($identifier);
    }
}
