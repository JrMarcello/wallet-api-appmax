<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Esta trait é responsável por dar Boot na aplicação Laravel
    use CreatesApplication;
}
