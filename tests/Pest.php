<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Prépare une commande Artisan pour les assertions de sortie.
 *
 * @param  array<string, mixed>  $parameters
 */
function artisanCommand(TestCase $test, string $command, array $parameters = []): PendingCommand
{
    $pendingCommand = $test->artisan($command, $parameters);

    if (! $pendingCommand instanceof PendingCommand) {
        throw new RuntimeException('La commande Artisan n’a pas pu être mise en attente.');
    }

    return $pendingCommand;
}

/**
 * Retourne le contenu d’une réponse HTTP en garantissant qu’il s’agit d’une chaîne.
 *
 * @template TResponse of \Symfony\Component\HttpFoundation\Response
 *
 * @param  TestResponse<TResponse>  $response
 */
function testResponseContent(TestResponse $response): string
{
    $content = $response->getContent();

    if ($content === false) {
        throw new RuntimeException('La réponse HTTP ne contient pas de contenu.');
    }

    return $content;
}
