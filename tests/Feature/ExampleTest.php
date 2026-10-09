<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ModaExpressSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_vendedor_redirected_to_pos(): void
    {
        $cajero = User::where('email', 'cajero@modaexpress.com')->first();
        $response = $this->actingAs($cajero)->get('/');
        $response->assertRedirect(route('ventas.pos'));
    }

    public function test_authenticated_gerente_redirected_to_stock(): void
    {
        $gerente = User::where('email', 'gerente@modaexpress.com')->first();
        $response = $this->actingAs($gerente)->get('/');
        $response->assertRedirect(route('stock.index'));
    }
}
