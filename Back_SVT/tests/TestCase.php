<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('DB_URL');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        unset($_ENV['DB_URL'], $_SERVER['DB_URL']);

        if (! $this->app) {
            $this->refreshApplication();
        }

        $padrao = (string) config('database.default');
        $nome = (string) config("database.connections.{$padrao}.database");

        if ($padrao !== 'sqlite' || $nome !== ':memory:') {
            throw new \RuntimeException(
                'PHPUnit recusou o banco '.$padrao.' ['.$nome.']. A suíte só roda em sqlite :memory:.'
            );
        }

        parent::setUp();
    }
}
