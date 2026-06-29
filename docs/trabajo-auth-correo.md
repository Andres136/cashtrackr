# Trabajo realizado en autenticacion y correo

Este documento resume lo que hicimos en CashTrackr para revisar el registro, el envio del correo de confirmacion, la verificacion del email y el inicio de sesion.

## Problema inicial

La aplicacion registraba usuarios, pero habia dos problemas principales:

- El correo de confirmacion no parecia llegar o el enlace no funcionaba bien.
- No se podia iniciar sesion porque el formulario de login no estaba conectado a una ruta `POST` ni a una logica de autenticacion.

Tambien aparecio este error:

```text
BadMethodCallException
El metodo Illuminate\Routing\Route::with no existe.
```

Ese error ocurria porque se habia usado `with()` sobre la ruta:

```php
Route::get(...)->name('verification.verify')->with(...);
```

En Laravel, `with()` se usa sobre un `redirect()`, no sobre `Route`.

## Archivos revisados o modificados

### `routes/web.php`

Este archivo define las rutas web de Laravel.

Rutas importantes:

```php
Route::get('/auth/register', [RegisterController::class, 'index'])->name('register');
Route::post('/auth/register', [RegisterController::class, 'store'])->name('register.store');
```

Estas dos rutas sirven para el registro:

- `GET /auth/register`: muestra el formulario para crear cuenta.
- `POST /auth/register`: recibe los datos del formulario y crea el usuario.

Tambien agregamos o revisamos estas rutas para login:

```php
Route::get('/auth/login', [LoginController::class, 'index'])->name('login');
Route::post('/auth/login', [LoginController::class, 'store'])->name('login.store');
```

Estas rutas sirven para iniciar sesion:

- `GET /auth/login`: muestra el formulario de login.
- `POST /auth/login`: recibe email y password para validar el inicio de sesion.

La ruta de verificacion de correo quedo asi:

```php
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect()
        ->route('dashboard')
        ->with('success', 'Tu correo fue verificado correctamente. Ya puedes crear presupuestos y gastos.');
})->middleware('auth', 'signed')->name('verification.verify');
```

Que hace cada parte:

- `/email/verify/{id}/{hash}`: es la URL que llega al correo.
- `EmailVerificationRequest`: valida que el enlace sea correcto.
- `$request->fulfill()`: marca el email del usuario como verificado.
- `redirect()->route('dashboard')`: envia al usuario al dashboard.
- `->with('success', ...)`: guarda un mensaje temporal en la sesion.
- `middleware('auth', 'signed')`: exige que el usuario este autenticado y que el enlace firmado sea valido.
- `name('verification.verify')`: nombre interno de la ruta usado para generar el enlace del correo.

Tambien se protegio el dashboard:

```php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
```

Esto significa:

- `auth`: solo usuarios logueados pueden entrar.
- `verified`: solo usuarios con correo verificado pueden entrar.

### `app/Http/Controllers/Auth/RegisterController.php`

Este controlador maneja el registro.

Codigo importante:

```php
$data = $request->validated();
$user = User::create($data);
event(new Registered($user));
Auth::login($user);
return redirect()->route('verification.notice');
```

Que hace:

- `$request->validated()`: toma solo los datos validados del formulario.
- `User::create($data)`: crea el usuario en la base de datos.
- `event(new Registered($user))`: dispara el evento de Laravel para usuario registrado.
- `Auth::login($user)`: inicia sesion automaticamente despues del registro.
- `redirect()->route('verification.notice')`: envia a la pantalla que dice que revise el correo.

### `app/Models/User.php`

El modelo `User` implementa:

```php
implements MustVerifyEmail
```

Eso le dice a Laravel que este usuario necesita verificar su correo.

Tambien tiene:

```php
public function sendEmailVerificationNotification()
{
   $this->notify(new VerifyEmail());
}
```

Esto reemplaza el correo de verificacion por una notificacion personalizada.

### `app/Notifications/VerifyEmail.php`

Este archivo crea el correo de confirmacion personalizado.

Parte importante:

```php
$verificationUrl = URL::temporarySignedRoute(
    'verification.verify',
    now()->addMinutes(60),
    [
        'id' => $notifiable->getKey(),
        'hash' => sha1($notifiable->getEmailForVerification()),
    ]
);
```

Que hace:

- Crea un enlace firmado temporal.
- Usa la ruta llamada `verification.verify`.
- El enlace dura 60 minutos.
- Incluye el ID del usuario y un hash del correo para verificar que corresponde al usuario correcto.

Luego arma el contenido del email:

```php
return (new MailMessage)
    ->subject('confirma tu cuenta en CashTrackr')
    ->greeting('!Hola')
    ->line('Gracias por registrarse en Cashtrackr, tu cuenta esta lista solo debes confirmarla')
    ->action('Confirmar Cuenta', $verificationUrl)
    ->line('Si no creaste este correo puedes ignorar este mensaje');
```

Que hace:

- Define el asunto del correo.
- Agrega un saludo.
- Agrega texto explicativo.
- Crea un boton llamado `Confirmar Cuenta`.
- El boton apunta al enlace de verificacion.

### `app/Http/Controllers/Auth/LoginController.php`

Este controlador muestra el login y recibe el intento de inicio de sesion.

Actualmente esta parte muestra el formulario:

```php
public function index(): View
{
    return view('auth.login');
}
```

Y esta parte recibe el formulario:

```php
Public function store(SignInRequest $request)
{
    $data = $request->validated();

    dd($data);
}
```

Que hace actualmente:

- Usa `SignInRequest` para validar email y password.
- Guarda los datos validados en `$data`.
- `dd($data)` muestra los datos y detiene la aplicacion.

Importante: `dd($data)` sirve para probar, pero mientras este ahi el login no va a continuar. Cuando quieras que el login funcione completo, esa linea debe cambiarse por la autenticacion con `Auth::attempt(...)`.

Ejemplo de logica final esperada:

```php
if (! Auth::attempt($data)) {
    throw ValidationException::withMessages([
        'email' => 'Las credenciales no coinciden con nuestros registros.',
    ]);
}

$request->session()->regenerate();

return redirect()->intended(route('dashboard', absolute: false));
```

### `app/Http/Requests/SignInRequest.php`

Este archivo valida el formulario de login.

Reglas actuales:

```php
return [
    'email' => ['required', 'email','exists:users,email'],
    'password' => ['required'],
];
```

Que hacen:

- `required`: el campo es obligatorio.
- `email`: debe tener formato de correo.
- `exists:users,email`: el correo debe existir en la tabla `users`.
- `password required`: la contrasena es obligatoria.

Mensaje personalizado:

```php
'email.exists' => 'No encontramos una cuenta con ese correo electronico.',
```

Ese mensaje aparece cuando el correo no existe en la base de datos.

Detalle a revisar:

```php
public function attributes8(): array
```

Ese metodo parece tener un error de nombre. En Laravel deberia ser:

```php
public function attributes(): array
```

### `resources/views/auth/login.blade.php`

Este archivo es la vista del formulario de inicio de sesion.

Parte importante:

```blade
<form method="POST" action="{{ route('login.store') }}" class="mt-14 space-y-5" novalidate>
    @csrf
```

Que hace:

- `method="POST"`: envia los datos por POST.
- `action="{{ route('login.store') }}"`: envia el formulario a la ruta de login.
- `@csrf`: agrega el token de seguridad de Laravel.

Campo email:

```blade
<input
    id="email"
    type="email"
    name="email"
    value="{{ old('email') }}"
/>
```

Que hace:

- Permite escribir el correo.
- `old('email')` conserva el correo escrito si hay error de validacion.

Mensajes de error:

```blade
@error('email')
    <p class="text-red-600">{{ $message }}</p>
@enderror
```

Esto muestra el mensaje de error debajo del campo.

Detalle a revisar:

En el archivo actual aparece dos veces `value` en el input de email. Deberia quedar solo una vez:

```blade
value="{{ old('email') }}"
```

### `.env`

Este archivo guarda configuracion local del proyecto.

Variables importantes:

```env
APP_URL=http://127.0.0.1:8000
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_FROM_ADDRESS="cuentas@cashtrackr.com"
DB_CONNECTION=pgsql
DB_URL=...
```

Que hace cada una:

- `APP_URL`: URL base usada por Laravel para generar enlaces, incluyendo el enlace de verificacion.
- `MAIL_MAILER=smtp`: indica que Laravel envia correos por SMTP.
- `MAIL_HOST`: servidor SMTP de Mailtrap.
- `MAIL_PORT`: puerto SMTP.
- `MAIL_FROM_ADDRESS`: correo que aparece como remitente.
- `DB_CONNECTION=pgsql`: indica que se usa PostgreSQL.
- `DB_URL`: contiene la conexion completa a Neon.

Importante: `.env` contiene credenciales. No debe subirse a GitHub.

## Comandos ejecutados y para que sirven

### Revisar archivos del proyecto

```bash
rg --files
```

Sirve para listar rapidamente los archivos del proyecto.

### Buscar texto dentro del proyecto

```bash
rg -n "mailtrap|smtp|email|verify|auth|login" -S .
```

Sirve para encontrar donde se menciona correo, login, verificacion y autenticacion.

### Ver rutas registradas

```bash
php artisan route:list
```

Sirve para ver todas las rutas que Laravel conoce. Lo usamos para confirmar que existia:

- `GET auth/login`
- `POST auth/login`
- `GET email/verify/{id}/{hash}`
- `GET dashboard`

### Limpiar cache de configuracion

```bash
php artisan config:clear
```

Sirve para que Laravel vuelva a leer los valores actuales del `.env`.

Lo usamos despues de revisar/cambiar `APP_URL`.

### Revisar configuracion con Tinker

```bash
XDG_CONFIG_HOME=/tmp php artisan tinker --execute="dump([...]);"
```

Sirve para ejecutar codigo PHP dentro de Laravel y confirmar valores como:

- mailer activo
- host SMTP
- puerto SMTP
- remitente
- `APP_URL`

Se uso `XDG_CONFIG_HOME=/tmp` porque PsySH queria escribir historial fuera del permiso del entorno.

### Probar envio real de correo

```bash
XDG_CONFIG_HOME=/tmp php artisan tinker --execute="Mail::raw(...);"
```

Sirve para enviar un correo simple de prueba usando la configuracion SMTP.

Con esto confirmamos que Mailtrap aceptaba el envio.

### Revisar errores en logs

```bash
tail -120 storage/logs/laravel.log
```

Sirve para ver errores recientes de Laravel.

Alli aparecio un problema de resolucion DNS hacia Neon en una ejecucion previa. Eso puede pasar si el entorno no tiene red disponible.

### Revisar sintaxis PHP

```bash
php -l app/Http/Controllers/Auth/LoginController.php
```

Sirve para confirmar que un archivo PHP no tiene errores de sintaxis.

### Ejecutar pruebas

```bash
php artisan test
```

Sirve para ejecutar las pruebas del proyecto.

El resultado fue correcto:

```text
tests: 2
passed: 2
assertions: 2
```

### Revisar cambios de Git

```bash
git diff
git status --short
```

Sirven para ver que archivos cambiaron antes de hacer commit.

## Comando sugerido para guardar cambios

Si quieres guardar los cambios relacionados con autenticacion y verificacion:

```bash
git add app/Http/Controllers/Auth/LoginController.php resources/views/auth/login.blade.php routes/web.php app/Http/Requests/SignInRequest.php docs/trabajo-auth-correo.md
git commit -m "Corrige login y verificacion de correo"
```

No agregues `.env` al commit porque contiene credenciales.

## Pendientes recomendados

- Quitar `dd($data)` de `LoginController` cuando ya no estes depurando.
- Completar el login con `Auth::attempt(...)`.
- Corregir `attributes8()` por `attributes()` en `SignInRequest`.
- Dejar solo un `value="{{ old('email') }}"` en el input de email.
- Mostrar el mensaje `session('success')` en el dashboard si quieres ver el aviso despues de verificar el correo.
