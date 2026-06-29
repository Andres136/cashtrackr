# Guia completa del proyecto CashTrackr

Esta guia explica el proyecto desde el principio: que hace cada parte, que archivo conecta con que, como se conecta Laravel con Neon y Mailtrap, que comandos usamos y que significan las clases Tailwind usadas en las vistas.

Nota importante: no se documentan contrasenas ni tokens reales. El archivo `.env` contiene secretos y no debe subirse a GitHub.

## 1. Que es CashTrackr

CashTrackr es una aplicacion Laravel para manejar usuarios y, mas adelante, presupuestos y gastos.

En este momento el flujo principal es:

1. El usuario entra a la pagina.
2. El usuario crea una cuenta.
3. Laravel guarda el usuario en la base de datos Neon.
4. Laravel dispara un evento de usuario registrado.
5. El modelo `User` envia una notificacion de verificacion.
6. La notificacion genera un enlace firmado.
7. Laravel envia el correo usando Mailtrap.
8. El usuario abre el correo y confirma la cuenta.
9. Laravel marca `email_verified_at`.
10. El usuario puede entrar al dashboard si esta autenticado y verificado.

## 2. Mapa general de carpetas

### `app/`

Contiene el codigo principal de la aplicacion.

- `app/Http/Controllers`: controladores que reciben las peticiones.
- `app/Http/Requests`: validaciones de formularios.
- `app/Models`: modelos de base de datos.
- `app/Notifications`: correos/notificaciones.
- `app/Providers`: proveedores de Laravel.

### `routes/`

Contiene las rutas de la aplicacion.

- `routes/web.php`: rutas web que se abren desde el navegador.
- `routes/console.php`: comandos de consola personalizados.

### `resources/`

Contiene archivos de frontend.

- `resources/views`: vistas Blade.
- `resources/css/app.css`: entrada de Tailwind CSS.
- `resources/js/app.js`: entrada de JavaScript.

### `database/`

Contiene estructura y datos iniciales de base de datos.

- `database/migrations`: crean tablas.
- `database/factories`: fabrican datos falsos para pruebas.
- `database/seeders`: insertan datos iniciales.
- `database/database.sqlite`: base SQLite local creada por Laravel, aunque tu proyecto usa Neon con PostgreSQL.

### `config/`

Contiene configuraciones de Laravel.

- `config/database.php`: conexion a base de datos.
- `config/mail.php`: envio de correos.
- `config/auth.php`: autenticacion.
- `config/session.php`: sesiones.
- `config/cache.php`: cache.
- `config/queue.php`: colas/jobs.

### `public/`

Carpeta publica del proyecto.

- `public/index.php`: archivo por donde entra toda peticion web.
- `public/img/logo.svg`: logo usado en el layout.

### `docs/`

Carpeta que creamos para documentar el proyecto.

- `docs/trabajo-auth-correo.md`: resumen del trabajo de autenticacion/correo.
- `docs/guia-completa-cashtrackr.md`: esta guia completa.

## 3. Flujo completo: de navegador a base de datos y correo

### Registro

Ruta:

```php
Route::get('/auth/register', [RegisterController::class, 'index'])->name('register');
Route::post('/auth/register', [RegisterController::class, 'store'])->name('register.store');
```

Flujo:

1. El navegador abre `/auth/register`.
2. Laravel ejecuta `RegisterController@index`.
3. El controlador retorna la vista `auth.register`.
4. El usuario llena nombre, email, password y confirmacion.
5. El formulario envia un `POST` a `register.store`.
6. Laravel ejecuta `RegisterController@store`.
7. `SignupRequest` valida los datos.
8. `User::create($data)` guarda el usuario en Neon.
9. `event(new Registered($user))` dispara el proceso de verificacion.
10. `Auth::login($user)` inicia sesion automaticamente.
11. Laravel redirige a `verification.notice`.

### Correo de verificacion

Flujo:

1. Se dispara el evento `Registered`.
2. Laravel detecta que `User` implementa `MustVerifyEmail`.
3. Laravel llama a `sendEmailVerificationNotification()`.
4. Tu modelo `User` envia `VerifyEmail`.
5. `VerifyEmail` crea una URL firmada temporal.
6. Mailtrap recibe el correo.
7. El usuario hace clic en `Confirmar Cuenta`.

### Confirmacion

Ruta:

```php
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect()
        ->route('dashboard')
        ->with('success', 'Tu correo fue verificado correctamente. Ya puedes crear presupuestos y gastos.');
})->middleware('auth', 'signed')->name('verification.verify');
```

Flujo:

1. El usuario abre el enlace del correo.
2. Laravel revisa el middleware `auth`.
3. Laravel revisa el middleware `signed`.
4. `EmailVerificationRequest` valida que el enlace corresponde al usuario.
5. `$request->fulfill()` actualiza `email_verified_at`.
6. Laravel redirige al dashboard.
7. `with('success', ...)` guarda un mensaje temporal en sesion.

### Login

Ruta:

```php
Route::get('/auth/login', [LoginController::class, 'index'])->name('login');
Route::post('/auth/login', [LoginController::class, 'store'])->name('login.store');
```

Flujo esperado:

1. El navegador abre `/auth/login`.
2. Laravel ejecuta `LoginController@index`.
3. Se muestra `auth.login`.
4. El usuario envia email y password.
5. Laravel ejecuta `LoginController@store`.
6. `SignInRequest` valida los datos.
7. El controlador deberia usar `Auth::attempt(...)`.
8. Si las credenciales son correctas, Laravel crea sesion.
9. El usuario entra al dashboard.

Estado actual:

```php
Public function store(SignInRequest $request)
{
    $data = $request->validated();

    dd($data);
}
```

`dd($data)` significa "dump and die": muestra los datos y detiene el programa. Sirve para probar que la validacion funciona, pero impide que el login continue.

## 4. Conexion con Neon

Neon es la base de datos PostgreSQL en la nube.

En `.env` se configura asi:

```env
DB_CONNECTION=pgsql
DB_URL=postgresql://USUARIO:CONTRASENA@HOST/neondb?sslmode=require&channel_binding=require
```

Que hace cada parte:

- `DB_CONNECTION=pgsql`: le dice a Laravel que use PostgreSQL.
- `DB_URL`: contiene toda la cadena de conexion.
- `postgresql://`: protocolo de conexion.
- `USUARIO`: usuario de base de datos, por ejemplo `neondb_owner`.
- `CONTRASENA`: clave de la base de datos.
- `HOST`: servidor de Neon.
- `neondb`: nombre de la base de datos.
- `sslmode=require`: obliga conexion segura.
- `channel_binding=require`: refuerza seguridad de conexion.

Laravel lee esa variable desde `config/database.php`:

```php
'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
]
```

Si `DB_URL` existe, Laravel puede usar esa URL completa para conectarse.

Cuando ejecutas una migracion:

```bash
php artisan migrate
```

Laravel toma las migraciones de `database/migrations` y crea las tablas en Neon.

## 5. Conexion con Mailtrap

Mailtrap es un inbox de pruebas. Sirve para probar correos sin enviarlos a usuarios reales.

En `.env` se configura asi:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=TU_USUARIO_MAILTRAP
MAIL_PASSWORD=TU_PASSWORD_MAILTRAP
MAIL_FROM_ADDRESS="cuentas@cashtrackr.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Que hace cada variable:

- `MAIL_MAILER=smtp`: Laravel enviara por SMTP.
- `MAIL_HOST=sandbox.smtp.mailtrap.io`: servidor SMTP de Mailtrap.
- `MAIL_PORT=2525`: puerto de Mailtrap.
- `MAIL_USERNAME`: usuario SMTP que Mailtrap da.
- `MAIL_PASSWORD`: clave SMTP que Mailtrap da.
- `MAIL_FROM_ADDRESS`: remitente visible del correo.
- `MAIL_FROM_NAME`: nombre visible del remitente.

Laravel lee eso en `config/mail.php`:

```php
'default' => env('MAIL_MAILER', 'log'),
```

Si `MAIL_MAILER=smtp`, usa la configuracion `smtp`:

```php
'smtp' => [
    'transport' => 'smtp',
    'host' => env('MAIL_HOST', '127.0.0.1'),
    'port' => env('MAIL_PORT', 2525),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
]
```

Cuando `VerifyEmail` retorna un `MailMessage`, Laravel lo convierte en correo y lo manda por Mailtrap.

## 6. Importancia de `APP_URL`

En `.env`:

```env
APP_URL=http://127.0.0.1:8000
```

Sirve para que Laravel genere enlaces correctos.

Ejemplo:

```php
URL::temporarySignedRoute(...)
```

Cuando crea el enlace de verificacion, Laravel necesita saber el dominio base. Si `APP_URL` dice `http://localhost` pero tu servidor corre en `http://127.0.0.1:8000`, el enlace del correo puede salir mal.

Por eso se dejo:

```env
APP_URL=http://127.0.0.1:8000
```

Si usas otro puerto o dominio, debes cambiarlo.

## 7. Explicacion de `routes/web.php`

Archivo: `routes/web.php`

```php
<?php
```

Abre el archivo PHP.

```php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
```

Importa clases:

- `LoginController`: controlador de login.
- `RegisterController`: controlador de registro.
- `EmailVerificationRequest`: request especial de Laravel para verificar correo.
- `Route`: fachada para definir rutas.

```php
Route::get('/', function () {
    return view('welcome');
});
```

Define la pagina inicial `/`.

```php
Route::get('/auth/register', [RegisterController::class, 'index'])->name('register');
```

Muestra el formulario de registro.

```php
Route::post('/auth/register', [RegisterController::class, 'store'])->name('register.store');
```

Recibe el formulario de registro.

```php
Route::get('/auth/login', [LoginController::class, 'index'])->name('login');
```

Muestra el formulario de login.

```php
Route::post('/auth/login', [LoginController::class, 'store'])->name('login.store');
```

Recibe el formulario de login.

```php
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
```

Define la ruta que recibe el clic del correo.

```php
$request->fulfill();
```

Marca el email como verificado.

```php
return redirect()
    ->route('dashboard')
    ->with('success', 'Tu correo fue verificado correctamente. Ya puedes crear presupuestos y gastos.');
```

Redirige al dashboard y guarda mensaje de exito en sesion.

```php
})->middleware('auth', 'signed')->name('verification.verify');
```

Agrega proteccion:

- `auth`: usuario logueado.
- `signed`: enlace firmado y no alterado.
- `verification.verify`: nombre usado por el correo.

```php
Route::get('email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');
```

Muestra la pantalla que dice "revisa tu correo".

```php
Route::redirect('/dasboard', '/dashboard');
```

Corrige una URL mal escrita: `/dasboard` redirige a `/dashboard`.

```php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
```

Muestra el dashboard solo si el usuario esta logueado y verificado.

## 8. Explicacion de `RegisterController`

Archivo: `app/Http/Controllers/Auth/RegisterController.php`

```php
namespace App\Http\Controllers\Auth;
```

Ubica la clase dentro del namespace de controladores Auth.

```php
use App\Http\Requests\SignupRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
```

Importa:

- `SignupRequest`: reglas del formulario de registro.
- `User`: modelo de usuario.
- `Registered`: evento que dispara verificacion.
- `Auth`: sistema de autenticacion.

```php
public function index()
{
    return view('auth.register');
}
```

Muestra la vista de registro.

```php
public function store(SignupRequest $request)
```

Recibe el `POST` del registro. Laravel valida automaticamente con `SignupRequest`.

```php
$data = $request->validated();
```

Obtiene los datos limpios y validados.

```php
$user = User::create($data);
```

Crea el usuario en la base de datos. La password se hashea automaticamente porque el modelo tiene cast `hashed`.

```php
event(new Registered($user));
```

Dispara el evento de usuario registrado.

```php
Auth::login($user);
```

Inicia sesion con el usuario nuevo.

```php
return redirect()->route('verification.notice');
```

Envia al usuario a la pantalla de confirmacion de correo.

## 9. Explicacion de `LoginController`

Archivo: `app/Http/Controllers/Auth/LoginController.php`

```php
use App\Http\Requests\SignInRequest;
```

Usa una clase separada para validar el login.

```php
public function index(): View
{
    return view('auth.login');
}
```

Retorna la vista de login.

```php
Public function store(SignInRequest $request)
```

Recibe el formulario de login y valida con `SignInRequest`.

```php
$data = $request->validated();
```

Obtiene email y password validados.

```php
dd($data);
```

Muestra los datos y detiene la ejecucion. Es para depurar.

Version recomendada para login real:

```php
public function store(SignInRequest $request): RedirectResponse
{
    $data = $request->validated();

    if (! Auth::attempt($data)) {
        throw ValidationException::withMessages([
            'email' => 'Las credenciales no coinciden con nuestros registros.',
        ]);
    }

    $request->session()->regenerate();

    return redirect()->intended(route('dashboard', absolute: false));
}
```

Que hace:

- `Auth::attempt($data)`: revisa email y password.
- Si falla, devuelve error.
- `session()->regenerate()`: crea nueva sesion segura.
- `redirect()->intended(...)`: vuelve a la pagina que el usuario queria visitar.

## 10. Explicacion de `SignupRequest`

Archivo: `app/Http/Requests/SignupRequest.php`

Este archivo valida el formulario de registro.

```php
public function messages()
```

Define mensajes personalizados.

Ejemplo:

```php
'email.unique' => 'El correo electronico ya esta registrado.',
```

Se muestra si el correo ya existe.

```php
public function rules(): array
```

Define las reglas.

```php
'name' => ['required', 'string']
```

El nombre es obligatorio y debe ser texto.

```php
'email' => ['required', 'email', 'unique:users,email']
```

El email es obligatorio, debe tener formato email y no puede existir ya en `users.email`.

```php
'password' => ['required', 'confirmed', Password::min(4)->letters()->mixedCase()->symbols()->numbers()->uncompromised()]
```

La password:

- es obligatoria.
- debe coincidir con `password_confirmation`.
- minimo 4 caracteres.
- debe tener letras.
- debe tener mayusculas y minusculas.
- debe tener simbolos.
- debe tener numeros.
- no debe estar comprometida en filtraciones conocidas.

Pendiente importante:

Este archivo deberia tener:

```php
public function authorize(): bool
{
    return true;
}
```

Sin eso, un `FormRequest` puede negar la peticion dependiendo de la version/configuracion.

## 11. Explicacion de `SignInRequest`

Archivo: `app/Http/Requests/SignInRequest.php`

```php
public function messages(): array
```

Define mensajes personalizados.

```php
'email.exists' => 'No encontramos una cuenta con ese correo electronico.',
```

Se muestra cuando el email no existe en la tabla `users`.

```php
public function rules(): array
{
    return [
        'email' => ['required', 'email','exists:users,email'],
        'password' => ['required'],
    ];
}
```

Reglas:

- `email required`: email obligatorio.
- `email`: formato correcto.
- `exists:users,email`: debe existir en la tabla `users`.
- `password required`: password obligatoria.

Pendiente:

```php
public function attributes8(): array
```

Deberia llamarse:

```php
public function attributes(): array
```

`attributes()` sirve para cambiar nombres de campos en mensajes de validacion.

## 12. Explicacion del modelo `User`

Archivo: `app/Models/User.php`

```php
class User extends Authenticatable implements MustVerifyEmail
```

Significa:

- `Authenticatable`: el usuario puede iniciar sesion.
- `MustVerifyEmail`: Laravel exige verificar correo.

```php
use HasFactory, Notifiable;
```

- `HasFactory`: permite crear usuarios falsos en pruebas.
- `Notifiable`: permite enviar notificaciones/correos.

```php
#[Fillable(['name', 'email', 'password'])]
```

Permite asignar estos campos con `User::create($data)`.

```php
#[Hidden(['password', 'remember_token'])]
```

Oculta campos sensibles cuando el usuario se convierte a array o JSON.

```php
public function sendEmailVerificationNotification()
{
   $this->notify(new VerifyEmail());
}
```

Personaliza el correo de verificacion.

```php
'email_verified_at' => 'datetime',
```

Convierte ese campo a fecha Carbon.

```php
'password' => 'hashed',
```

Hashea automaticamente la password al guardarla.

## 13. Explicacion de `VerifyEmail`

Archivo: `app/Notifications/VerifyEmail.php`

```php
class VerifyEmail extends Notification
```

Define una notificacion de Laravel.

```php
use Queueable;
```

Permite que la notificacion pueda ir a cola si luego implementas `ShouldQueue`.

```php
public function via(object $notifiable): array
{
    return ['mail'];
}
```

Dice que esta notificacion se envia por email.

```php
$verificationUrl = URL::temporarySignedRoute(...)
```

Crea una URL temporal y firmada.

```php
'verification.verify'
```

Nombre de la ruta que recibira el clic del correo.

```php
now()->addMinutes(60)
```

El enlace dura 60 minutos.

```php
'id' => $notifiable->getKey()
```

Incluye el ID del usuario.

```php
'hash' => sha1($notifiable->getEmailForVerification())
```

Incluye un hash del correo para seguridad.

```php
return (new MailMessage)
```

Construye el correo.

```php
->subject(...)
->greeting(...)
->line(...)
->action(...)
```

Define asunto, saludo, lineas de texto y boton.

## 14. Migraciones y tablas

### Tabla `users`

Archivo: `database/migrations/0001_01_01_000000_create_users_table.php`

```php
$table->id();
```

Crea columna `id` autoincremental.

```php
$table->string('name');
```

Nombre del usuario.

```php
$table->string('email')->unique();
```

Correo unico.

```php
$table->timestamp('email_verified_at')->nullable();
```

Fecha de verificacion. `nullable()` permite que empiece vacio.

```php
$table->string('password');
```

Password hasheada.

```php
$table->rememberToken();
```

Token para "recordarme".

```php
$table->timestamps();
```

Crea `created_at` y `updated_at`.

### Tabla `sessions`

Guarda sesiones cuando `SESSION_DRIVER=database`.

Columnas:

- `id`: ID de sesion.
- `user_id`: usuario logueado.
- `ip_address`: IP.
- `user_agent`: navegador.
- `payload`: datos de sesion.
- `last_activity`: ultima actividad.

### Tablas de cache

`cache` y `cache_locks` sirven para cache y locks.

### Tablas de jobs

`jobs`, `job_batches` y `failed_jobs` sirven para colas.

Como tienes:

```env
QUEUE_CONNECTION=database
```

Laravel puede guardar trabajos en esas tablas.

## 15. Vistas Blade y que conecta cada una

### `resources/views/layouts/base.blade.php`

Es el layout principal. Todas las paginas que lo extienden usan esta estructura base.

```blade
<!DOCTYPE html>
```

Indica HTML5.

```blade
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
```

Define el idioma segun configuracion de Laravel.

```blade
<meta charset="utf-8">
```

Permite caracteres como tildes y enies.

```blade
<meta name="viewport" content="width=device-width, initial-scale=1">
```

Hace que el sitio sea responsive.

```blade
<title>{{ config('app.name', 'CashTrackr') }} -@yield('title')</title>
```

Usa el nombre de la app y agrega el titulo de cada pagina.

```blade
@fonts
```

Carga fuentes configuradas con el plugin de Vite.

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

Carga CSS y JS compilados por Vite.

```blade
@yield('contents')
```

Punto donde cada vista hija inyecta su contenido.

### `resources/views/layouts/auth.blade.php`

Layout para paginas de autenticacion.

```blade
@extends('layouts.base')
```

Usa `base.blade.php` como plantilla.

```blade
@section('contents')
```

Define el contenido que se insertara en `@yield('contents')`.

```blade
<main class="max-w-2xl mt-10 mx-auto p-10 shadow-lg">
```

Contenedor visual del formulario.

```blade
<h1 class="font-bold text-4xl">@yield('title')</h1>
```

Muestra el titulo de cada pantalla.

```blade
@yield('auth-contents')
```

Punto donde login, registro y verificacion insertan su contenido.

### `resources/views/auth/register.blade.php`

Formulario de registro.

```blade
@extends('layouts.auth')
```

Usa el layout de autenticacion.

```blade
@section('title')
    Crear Cuenta
@endsection
```

Define el titulo.

```blade
<form method="POST" action="{{ route('register.store') }}" ...>
```

Envia el formulario a la ruta `register.store`.

Pendiente importante: deberia tener `@csrf` dentro del formulario.

```blade
name="name"
```

El campo se llama `name` y llega al request como `$request->name`.

```blade
value="{{ old('name') }}"
```

Recupera el valor anterior si la validacion falla.

```blade
@error('name')
    <p>{{ $message }}</p>
@enderror
```

Muestra error de validacion.

```blade
name="password_confirmation"
```

Necesario para la regla `confirmed` de Laravel.

### `resources/views/auth/login.blade.php`

Formulario de login.

```blade
<form method="POST" action="{{ route('login.store') }}" ...>
```

Envia email y password a `login.store`.

```blade
@csrf
```

Protege contra ataques CSRF.

```blade
name="email"
```

Campo que valida `SignInRequest`.

```blade
name="password"
```

Campo de password.

Pendiente:

El input de email tiene dos atributos `value`. Debe quedar solo:

```blade
value="{{ old('email') }}"
```

### `resources/views/auth/verify-email.blade.php`

Pantalla de aviso despues de registrarse.

```blade
Tu cuenta fue creada con exito. Ahora solo debes confirmarla, revisa tu e-mail.
```

Le dice al usuario que revise Mailtrap/correo.

### `resources/views/dashboard.blade.php`

Dashboard protegido.

```blade
@if (session('success'))
```

Pregunta si hay mensaje temporal en sesion.

```blade
{{ session('success') }}
```

Muestra el mensaje que se puso en `redirect()->with(...)`.

## 16. Tailwind CSS usado en el proyecto

Tailwind funciona con clases pequenas. Cada clase aplica una regla CSS.

No conviene documentar linea por linea el CSS generado dentro de `base.blade.php`, porque eso es codigo automatico de Tailwind. Lo importante es entender las clases que tu escribes.

### Clases de layout

```text
flex
```

Activa Flexbox.

```text
flex-col
```

Pone los elementos en columna.

```text
lg:flex-row
```

En pantallas grandes, cambia a fila.

```text
items-center
```

Centra elementos en el eje cruzado.

```text
justify-between
```

Separa elementos con espacio entre ellos.

```text
mx-auto
```

Centra horizontalmente con margen automatico.

```text
w-full
```

Ancho completo.

```text
max-w-6xl
```

Ancho maximo grande para contenedor.

```text
max-w-2xl
```

Ancho maximo para formularios.

### Clases de espacio

```text
p-2, p-3, p-10
```

Padding interno.

```text
py-5, py-3
```

Padding vertical.

```text
mt-5, mt-8, mt-10, mt-14
```

Margen superior.

```text
my-10
```

Margen vertical.

```text
gap-2, gap-4
```

Espacio entre elementos flex/grid.

```text
space-y-2, space-y-5
```

Espacio vertical entre hijos.

### Clases de texto

```text
font-bold
```

Texto en negrita.

```text
text-2xl, text-4xl, text-xl, text-lg
```

Tamanos de texto.

```text
uppercase
```

Convierte texto a mayusculas.

```text
text-center
```

Centra texto.

### Clases de color

```text
bg-purple-950
```

Fondo morado oscuro.

```text
hover:bg-purple-800
```

Cambia fondo al pasar el mouse.

```text
text-white
```

Texto blanco.

```text
text-red-600
```

Texto rojo para errores.

```text
text-green-700
```

Texto verde para exito.

```text
bg-green-100
```

Fondo verde claro.

```text
border-green-400
```

Borde verde.

### Clases de bordes y sombra

```text
border
```

Agrega borde.

```text
border-gray-300
```

Borde gris.

```text
rounded-lg
```

Bordes redondeados.

```text
shadow-lg
```

Sombra grande.

### Clases de cursor

```text
cursor-pointer
```

Muestra cursor de clic.

## 17. Tailwind y Vite

Archivo: `resources/css/app.css`

```css
@import 'tailwindcss';
```

Importa Tailwind.

```css
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
```

Le dice a Tailwind donde buscar clases usadas.

```css
@theme {
    --font-sans: 'Instrument Sans', ...
}
```

Define la fuente sans del proyecto.

Archivo: `vite.config.js`

```js
import { defineConfig } from 'vite';
```

Importa configuracion de Vite.

```js
import laravel from 'laravel-vite-plugin';
```

Conecta Vite con Laravel.

```js
import { bunny } from 'laravel-vite-plugin/fonts';
```

Permite cargar fuentes desde Bunny Fonts.

```js
import tailwindcss from '@tailwindcss/vite';
```

Conecta Tailwind 4 con Vite.

```js
input: ['resources/css/app.css', 'resources/js/app.js']
```

Archivos de entrada que Vite compila.

```js
refresh: true
```

Recarga el navegador cuando cambian archivos.

```js
tailwindcss()
```

Activa Tailwind.

## 18. Archivos de dependencias

### `composer.json`

Dependencias PHP.

Importantes:

- `laravel/framework`: Laravel.
- `laravel/tinker`: consola interactiva.
- `phpunit/phpunit`: pruebas.
- `fakerphp/faker`: datos falsos.
- `laravel/pint`: formateador.

Scripts importantes:

```json
"dev": [
    "npx concurrently ... \"php artisan serve\" ... \"npm run dev\" ..."
]
```

Levanta servidor Laravel, cola, logs y Vite juntos.

```json
"test": [
    "@php artisan config:clear --ansi @no_additional_args",
    "@php artisan test"
]
```

Limpia configuracion y corre pruebas.

### `package.json`

Dependencias frontend.

```json
"dev": "vite"
```

Levanta Vite en desarrollo.

```json
"build": "vite build"
```

Compila assets para produccion.

Dependencias:

- `vite`: bundler frontend.
- `tailwindcss`: CSS utilitario.
- `@tailwindcss/vite`: plugin Tailwind para Vite.
- `laravel-vite-plugin`: puente entre Laravel y Vite.

## 19. Comandos ejecutados y para que sirven

### `pwd`

```bash
pwd
```

Muestra la carpeta actual.

### `rg --files`

```bash
rg --files
```

Lista archivos del proyecto rapidamente.

### `rg -n "..."`

```bash
rg -n "mailtrap|smtp|email|verify|auth|login" -S .
```

Busca texto en archivos.

Lo usamos para encontrar donde estaban correo, verificacion y login.

### `ls -la`

```bash
ls -la
```

Lista archivos con permisos y archivos ocultos.

### `sed -n`

```bash
sed -n '1,180p' archivo.php
```

Muestra lineas especificas de un archivo.

### `tail`

```bash
tail -120 storage/logs/laravel.log
```

Muestra ultimas lineas del log de Laravel.

### `php artisan route:list`

```bash
php artisan route:list
```

Muestra rutas registradas.

Sirve para confirmar que Laravel conoce `login.store`, `register.store`, `verification.verify` y `dashboard`.

### `php artisan config:clear`

```bash
php artisan config:clear
```

Limpia cache de configuracion.

Sirve despues de modificar `.env`.

### `php artisan test`

```bash
php artisan test
```

Corre las pruebas.

### `php -l`

```bash
php -l app/Http/Controllers/Auth/LoginController.php
```

Revisa errores de sintaxis en un archivo PHP.

### `php artisan tinker`

```bash
XDG_CONFIG_HOME=/tmp php artisan tinker --execute="..."
```

Ejecuta codigo PHP dentro de Laravel.

Lo usamos para revisar configuracion de mail y probar envio.

### Probar envio con Mailtrap

```bash
XDG_CONFIG_HOME=/tmp php artisan tinker --execute="Mail::raw('Prueba CashTrackr', function (\$message) { \$message->to('test@example.com')->subject('Prueba Mailtrap CashTrackr'); }); dump('sent');"
```

Que hace:

- `Mail::raw(...)`: envia correo de texto simple.
- `to('test@example.com')`: destinatario de prueba.
- `subject(...)`: asunto.
- `dump('sent')`: muestra confirmacion si no fallo.

### `git diff`

```bash
git diff
```

Muestra cambios antes de guardar.

### `git status --short`

```bash
git status --short
```

Muestra archivos modificados o nuevos.

## 20. Errores que encontramos

### Error: `Route::with no existe`

Causa:

```php
Route::get(...)->name(...)->with(...);
```

Solucion:

```php
return redirect()
    ->route('dashboard')
    ->with('success', 'Mensaje...');
```

### El login no enviaba POST

Causa:

El formulario no tenia `method`, `action` ni `@csrf`.

Solucion:

```blade
<form method="POST" action="{{ route('login.store') }}">
    @csrf
</form>
```

### Enlace de correo podia salir mal

Causa:

`APP_URL` no tenia el puerto correcto.

Solucion:

```env
APP_URL=http://127.0.0.1:8000
```

### Mailtrap no respondia desde sandbox

Causa:

El entorno sin permisos de red no podia resolver `sandbox.smtp.mailtrap.io`.

Solucion:

Se probo con permiso de red real y el correo salio correctamente.

## 21. Pendientes del proyecto

1. Quitar `dd($data)` de `LoginController`.
2. Implementar `Auth::attempt(...)` para login real.
3. Corregir `Public` a `public` por estilo PHP.
4. Quitar imports no usados en `LoginController`: `RedirectResponse`, `Auth`, `ValidationException`, `Request` si no se usan.
5. Corregir `attributes8()` a `attributes()` en `SignInRequest`.
6. Agregar `authorize(): bool { return true; }` en `SignupRequest` si hace falta.
7. Agregar `@csrf` en el formulario de registro.
8. Quitar el segundo `value` duplicado en `login.blade.php`.
9. Revisar `w-fullblock` en el logo; probablemente deberia ser `w-full block`.
10. Revisar `items` en el `nav`; probablemente quisiste `items-center`.
11. No subir `.env` a GitHub.
12. Rotar credenciales si alguna vez se compartieron publicamente.

## 22. Comandos recomendados para trabajar

Levantar servidor:

```bash
php artisan serve
```

Levantar Vite:

```bash
npm run dev
```

Ejecutar migraciones:

```bash
php artisan migrate
```

Limpiar configuracion:

```bash
php artisan config:clear
```

Ver rutas:

```bash
php artisan route:list
```

Ejecutar pruebas:

```bash
php artisan test
```

Ver estado Git:

```bash
git status --short
```

Guardar cambios sin `.env`:

```bash
git add app routes resources database config docs
git commit -m "Documenta flujo completo del proyecto CashTrackr"
```

Si solo quieres guardar la documentacion:

```bash
git add docs/guia-completa-cashtrackr.md docs/trabajo-auth-correo.md
git commit -m "Documenta autenticacion correo y configuracion del proyecto"
```

## 23. Resumen mental del proyecto

Piensa el proyecto asi:

```text
Navegador
  -> routes/web.php
  -> Controller
  -> FormRequest
  -> Model
  -> Neon/PostgreSQL
  -> Event Registered
  -> User::sendEmailVerificationNotification()
  -> VerifyEmail
  -> config/mail.php
  -> Mailtrap
  -> enlace firmado
  -> verification.verify
  -> dashboard
```

Esa es la cadena principal.

Si algo falla:

- Si falla la URL, revisa `APP_URL`.
- Si falla la base, revisa `DB_CONNECTION` y `DB_URL`.
- Si falla el correo, revisa `MAIL_*`.
- Si falla el formulario, revisa `route(...)`, `method`, `@csrf` y el `FormRequest`.
- Si falla el acceso al dashboard, revisa `auth`, `verified` y `email_verified_at`.

## 24. Comandos `php artisan make:*` que usamos

```bash
php artisan make:controller Auth/RegisterController
```

Creo el archivo `app/Http/Controllers/Auth/RegisterController.php`.

Sirve para manejar el registro de usuarios. Muestra la vista `auth.register`, recibe el formulario, valida con `SignupRequest`, crea el usuario, dispara el evento `Registered`, inicia sesion y redirige a la pantalla de verificacion de correo.

```bash
php artisan make:controller Auth/LoginController
```

Creo el archivo `app/Http/Controllers/Auth/LoginController.php`.

Sirve para manejar el inicio de sesion. Muestra la vista `auth.login`, recibe email y password desde el formulario y debe validar los datos con `SignInRequest`. En este momento todavia tiene `dd($data)`, por eso solo muestra los datos y detiene el proceso.

```bash
php artisan make:request SignupRequest
```

Creo el archivo `app/Http/Requests/SignupRequest.php`.

Sirve para validar el formulario de registro. Revisa que el nombre, correo, password y confirmacion cumplan las reglas antes de crear el usuario.

```bash
php artisan make:request SignInRequest
```

Creo el archivo `app/Http/Requests/SignInRequest.php`.

Sirve para validar el formulario de login. Revisa que el correo tenga formato correcto, que exista en la tabla `users` y que la password venga presente.

```bash
php artisan make:model User
```

Creo el archivo `app/Models/User.php`.

Sirve para representar la tabla `users` en Laravel. Permite crear usuarios con `User::create($data)`, define que campos se pueden llenar, oculta datos sensibles como `password`, hashea la password automaticamente y envia la notificacion de verificacion de correo.

```bash
php artisan make:notification VerifyEmail
```

Creo el archivo `app/Notifications/VerifyEmail.php`.

Sirve para construir el correo de verificacion. Genera el enlace firmado temporal, define el asunto, saludo, texto y boton del correo, y Laravel lo envia usando la configuracion de Mailtrap.
