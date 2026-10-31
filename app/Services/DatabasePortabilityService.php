<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config\Config;
use Core\Database\Connection;
use Core\Database\DatabaseManager;

class DatabasePortabilityService
{
    public function getInfo(): array
    {
        $driver = DatabaseManager::instance()->getDriver();
        $conn = Connection::instance();
        $ping = $conn->ping();
        $version = null;
        $dbName = null;
        try {
            if ($ping['ok']) {
                $pdo = $conn->pdo();
                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    $version = $pdo->query('SELECT VERSION() as v')->fetch()['v'] ?? null;
                    $dbName = $pdo->query('SELECT DATABASE() as d')->fetch()['d'] ?? null;
                } elseif ($driver === 'pgsql') {
                    $version = $pdo->query('SELECT version() as v')->fetch()['v'] ?? null;
                    $dbName = $pdo->query('SELECT current_database() as d')->fetch()['d'] ?? null;
                } elseif ($driver === 'sqlite') {
                    $version = $pdo->query('SELECT sqlite_version() as v')->fetch()['v'] ?? null;
                    $dbName = Config::get('database.connections.sqlite.database');
                } elseif ($driver === 'sqlsrv') {
                    $version = $pdo->query('SELECT @@VERSION as v')->fetch()['v'] ?? null;
                    $dbName = Config::get('database.connections.sqlsrv.database');
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $migrationsPath = (string) Config::get('database.migrations_path');
        $applied = [];
        $pending = [];
        try {
            if ($ping['ok']) {
                $migrator = new \Database\Migrator($migrationsPath);
                $status = $migrator->status();
                $applied = $status['applied'];
                $pending = $status['pending'];
            }
        } catch (\Throwable $e) {
        }

        return [
            'driver' => $driver,
            'status' => $ping['ok'] ? 'connected' : 'disconnected',
            'message' => $ping['message'] ?? ($ping['error'] ?? null),
            'version' => $version,
            'database' => $dbName,
            'migrations_applied' => count($applied),
            'migrations_pending' => count($pending),
            'pending_list' => $pending,
        ];
    }

    public function testAllDrivers(): array
    {
        $results = [];
        $drivers = ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'];
        foreach ($drivers as $driver) {
            $results[$driver] = [
                'driver' => $driver,
                'configured' => array_key_exists($driver, (array) Config::get('database.connections', [])),
                'pdo_extension' => extension_loaded('pdo_' . ($driver === 'sqlsrv' ? 'sqlsrv' : ($driver === 'pgsql' ? 'pgsql' : ($driver === 'sqlite' ? 'sqlite' : 'mysql')))),
            ];
        }
        return $results;
    }
}
