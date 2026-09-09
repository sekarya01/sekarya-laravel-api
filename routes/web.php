<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * API documentation, served straight out of docs/ — which sits outside public/,
 * so it is not otherwise reachable over HTTP.
 *
 * Registered only outside production: the spec describes every endpoint and error
 * code, and that is not something to hand out publicly by default. Delete the guard
 * deliberately if you ever decide the docs should be public.
 */
if (! app()->isProduction()) {
    Route::prefix('docs')->name('docs.')->group(function (): void {
        Route::get('/', function () {
            $html = base_path('docs/api.html');

            abort_unless(is_file($html), Response::HTTP_NOT_FOUND, <<<'TXT'
                docs/api.html has not been built yet. Run:
                npx --yes -p @redocly/cli redocly build-docs docs/openapi.yaml -o docs/api.html
                TXT);

            return response()->file($html);
        })->name('index');

        // The raw spec, for linters, client generators and IDE plugins.
        Route::get('openapi.yaml', function () {
            $spec = base_path('docs/openapi.yaml');

            abort_unless(is_file($spec), Response::HTTP_NOT_FOUND, 'docs/openapi.yaml is missing.');

            return response()->file($spec, ['Content-Type' => 'application/yaml; charset=utf-8']);
        })->name('spec');

        // The URL people naturally type after reading about docs/api.html.
        Route::get('api.html', fn () => redirect()->route('docs.index'));
    });
}
