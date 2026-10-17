<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_swagger_page_points_to_the_served_specification(): void
    {
        $this->withoutVite();

        $this->get('/docs/api')->assertOk()
            ->assertSee('id="swagger-ui"', false)
            ->assertSee(route('api.docs.spec'), false);
    }

    public function test_openapi_document_is_available_for_the_live_identity_routes(): void
    {
        $response = $this->get('/docs/api/openapi.yaml')->assertOk();

        $this->assertStringStartsWith('application/yaml', $response->headers->get('Content-Type'));
        $response->assertSee('openapi: 3.0.3', false)
            ->assertSee('/v1/sessions:', false)
            ->assertSee('/v1/sessions/current:', false)
            ->assertSee('/v1/users/me:', false)
            ->assertSee('/v1/roles:', false)
            ->assertSee('/v1/users:', false)
            ->assertSee('/v1/users/{user}:assignRole:', false)
            ->assertSee('/v1/users/{user}:deactivate:', false);
    }
}
