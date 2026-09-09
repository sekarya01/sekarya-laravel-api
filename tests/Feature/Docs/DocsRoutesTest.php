<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class DocsRoutesTest extends TestCase
{
    public function test_it_serves_the_rendered_reference(): void
    {
        // docs/api.html is a generated file; skip rather than fail if it isn't built.
        if (! is_file(base_path('docs/api.html'))) {
            $this->markTestSkipped('docs/api.html not built — run redocly build-docs.');
        }

        $this->get(route('docs.index'))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=utf-8');
    }

    public function test_it_serves_the_raw_spec_as_yaml(): void
    {
        $this->get(route('docs.spec'))
            ->assertOk()
            ->assertHeader('content-type', 'application/yaml; charset=utf-8');
    }

    public function test_the_typed_url_redirects_to_the_canonical_one(): void
    {
        $this->get('/docs/api.html')->assertRedirect(route('docs.index'));
    }

    /**
     * The spec enumerates every endpoint and error code, so exposing it must be a
     * deliberate decision rather than a side effect of deploying.
     *
     * Routes are registered once per process, so the production path cannot be
     * asserted in-process — this shells out to a real production-env boot.
     */
    public function test_the_docs_routes_are_absent_in_production(): void
    {
        $result = Process::path(base_path())
            ->env(['APP_ENV' => 'production'])
            ->run('php artisan route:list --path=docs');

        $combined = $result->output().$result->errorOutput();

        $this->assertStringNotContainsString('docs.index', $combined);
        $this->assertStringNotContainsString('docs.spec', $combined);
        $this->assertStringNotContainsString('docs/openapi.yaml', $combined);
    }
}
