<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Define que os testes nas pastas 'Feature' e 'Unit' devem usar a classe
| base Tests\TestCase (que carrega o Laravel).
|
*/

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
