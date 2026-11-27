<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\CreatesAccessUsers;

abstract class TestCase extends BaseTestCase
{
    use CreatesAccessUsers, CreatesApplication;
}
