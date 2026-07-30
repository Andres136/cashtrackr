<?php

use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows empty state when the user has no budgets', function() {
   
 $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    
     
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('No hay presupuestos.');
    $response->assertSee('Comienza creando uno.');
   
});

it('only show the authenticated user budgets', function() {
   
 $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    
     
   $otherUser = User::factory()->create([
        'email_verified_at' => now()
    ]);

     Budget::factory()->for($user)->create([
        'name' => 'Mi Presupuesto'
    ]);

    Budget::factory()->for($otherUser)->create([
        'name' => 'Otro Presupuesto'
    ]);
    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Mi Presupuesto');
    $response->assertDontSee('Otro Presupuesto');

});