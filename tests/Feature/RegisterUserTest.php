<?php

use Illuminate\Auth\Events\Registered;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('shows the registration screen', function () {
    $response = $this->get(route('register'));
    $response->assertOk();
    $response->assertStatus(200);
    $response->assertSee('Crear Cuenta');
     $response->assertSee('Registrarme');

     $response->assertSeeInOrder([
        'Crear Cuenta',
        'Registrarme',
        
        
     ]);
});

it('registers a new user as  unverified and dispactches the registered event', function () {
    Event::fake();

     $response = $this->post(route('register.store'), [
      'name'  =>  'Juan Perez',
      'email' =>  'juan@juan.com',
      'password' => 'CashTrackr_Test9@Secure',
      'password_confirmation' => 'CashTrackr_Test9@Secure'
    ]);

    $response->assertRedirect(route('verification.notice'));
    $user = User::where('email', 'juan@juan.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Juan Perez');
    expect($user->email)->toBe('juan@juan.com');
    expect($user->hasVerifiedEmail())->toBeFalse();

    Event::assertDispatched(Registered::class);
});

it('should validate required fiels when the request body is empty', function() {
   $response = $this->post(route('register.store'),  []);

      $response->assertSessionHasErrors( [
         
         'name',
         'email',
         'password'
   ]);
       
      $response->assertSessionHasErrors( [
         'name' => 'El Nombre es obligatorio.',
         'email' => 'El  Email es obligatorio.',
         'password' => 'La Contraseña es obligatoria.'
    ]);

});

it('prevents duplicate email addresses', function(){
     
     User::factory()->create([
        'email'=> 'juan@juan.com'
     ]);
      
     $response = $this->post(route('register.store'), [
      'name'  =>  'Juan Perez',
      'email' =>  'juan@juan.com',
      'password' => 'CashTrackr_Test9@Secure',
      'password_confirmation' => 'CashTrackr_Test9@Secure'
    ]);

    $response->assertRedirect();

    $response->assertSessionHasErrors([ 'email' => 'El Correo Electrónico ya está registrado.' ]);
});

it('sends the verification email notification after registracion', function() {
  Notification::fake();

$response = $this->post(route('register.store'), [
    'name' => 'Juan Perez',
    'email' => 'juan@juan.com',
    'password' => 'CashTrackr_Test9@Secure',
    'password_confirmation' => 'CashTrackr_Test9@Secure',
]);

$user = User::where('email', 'juan@juan.com')->first();

Notification::assertSentTo(
    $user,
    VerifyEmail::class);

});

it('verifies the user email from a signed  verification link', function() {

       $user = User::factory()->unverified()->create();
       $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(route('dashboard'));

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('does not allow an unverified user to access the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

it('allow an unverified user to access the dashboard', function () {
    $user = User::factory()->unverified()->create([
        'email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    
    $response->assertOk();
});