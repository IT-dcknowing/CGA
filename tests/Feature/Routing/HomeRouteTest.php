<?php

namespace Tests\Feature\Routing;

use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    public function test_home_points_to_the_api_without_revealing_the_framework(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertExactJson([
            'name' => 'CGA Connect API',
            'api' => url('/api/v1'),
        ]);
        $this->assertStringNotContainsString('Laravel', $response->getContent());
    }

    public function test_visiting_the_home_opens_no_session(): void
    {
        $response = $this->get('/');

        $response->assertCookieMissing(config('session.cookie'))->assertCookieMissing('XSRF-TOKEN');
    }
}
