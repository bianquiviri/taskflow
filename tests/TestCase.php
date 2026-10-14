<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel resolves environment variables from $_SERVER before $_ENV, while
     * the Docker app container exports its own database, mail and queue values
     * into the process environment. PHPUnit's <env> entries only reach $_ENV
     * (and putenv), so they are mirrored into $_SERVER here — before the
     * application boots — to keep every test run hermetic (see phpunit.xml).
     */
    public function createApplication()
    {
        foreach (self::testingEnvironmentVariables() as $variable) {
            if (array_key_exists($variable, $_ENV)) {
                $_SERVER[$variable] = $_ENV[$variable];
            }
        }

        return parent::createApplication();
    }

    /**
     * @return list<string>
     */
    private static function testingEnvironmentVariables(): array
    {
        return [
            'APP_ENV',
            'APP_MAINTENANCE_DRIVER',
            'BCRYPT_ROUNDS',
            'BROADCAST_CONNECTION',
            'CACHE_STORE',
            'DB_CONNECTION',
            'DB_DATABASE',
            'DB_URL',
            'MAIL_MAILER',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
            'PULSE_ENABLED',
            'TELESCOPE_ENABLED',
            'NIGHTWATCH_ENABLED',
        ];
    }
}
