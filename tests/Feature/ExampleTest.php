<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The application root redirects to the dashboard, which is behind auth.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertRedirect('/login');
    }
}
