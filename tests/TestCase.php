<?php

namespace Larasell\Larasell\Tests;

use Larasell\Larasell\LarasellServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PDO;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, true> */
    private static array $parallelDatabasesCreated = [];

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LarasellServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $connection = env('DB_CONNECTION', 'sqlite');
        $app['config']->set('database.default', $connection);

        $token = env('TEST_TOKEN');

        if ($token === null || $token === false || $connection === 'sqlite') {
            return;
        }

        if (! in_array($connection, ['mysql', 'pgsql'], true) || preg_match('/^\d+$/', (string) $token) !== 1) {
            throw new RuntimeException('Parallel tests require a numeric TEST_TOKEN and a MySQL or PostgreSQL connection.');
        }

        $config = $app['config']->get("database.connections.{$connection}");
        $database = $config['database'].'_test_'.$token;

        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new RuntimeException("Unsafe parallel test database name [{$database}].");
        }

        $this->ensureParallelDatabaseExists($connection, $config, $database);
        $app['config']->set("database.connections.{$connection}.database", $database);
    }

    /** @param array<string, mixed> $config */
    private function ensureParallelDatabaseExists(string $connection, array $config, string $database): void
    {
        if (isset(self::$parallelDatabasesCreated[$database])) {
            return;
        }

        $pdo = $connection === 'mysql'
            ? new PDO(
                sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']),
                $config['username'],
                $config['password'],
            )
            : new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
                $config['username'],
                $config['password'],
            );

        if ($connection === 'mysql') {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}`");
        } else {
            $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :database');
            $statement->execute(['database' => $database]);

            if ($statement->fetchColumn() === false) {
                $pdo->exec("CREATE DATABASE \"{$database}\"");
            }
        }

        self::$parallelDatabasesCreated[$database] = true;
    }
}
