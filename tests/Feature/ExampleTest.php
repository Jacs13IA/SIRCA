<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_homepage_displays_the_public_chat_and_login_link(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('¿Qué sucede en tu colonia?')
            ->assertSee('href="' . route('login') . '"', false)
            ->assertDontSee('Registrar');
    }
}
