# Historial técnico: autenticación y verificación de correo

Este documento distingue lo que el historial Git demuestra como corregido de los problemas todavía presentes. Los hashes permiten auditar cada cambio con `git show HASH`.

## Línea de tiempo de correcciones

| Commit | Cambio | Problema que resolvió |
|---|---|---|
| `3833f8e` | Registro con contraseña segura. | El alta no aplicaba todavía el conjunto completo de reglas de seguridad. |
| `0010de9` | `User` implementa `MustVerifyEmail` y se ajusta el formulario. | Los usuarios se creaban sin un contrato de verificación de correo. |
| `13fa931` | Ruta y notificación `VerifyEmail` personalizadas. | Faltaba generar y enviar un enlace firmado hacia una ruta reconocida. |
| `2306d54` | Vistas de auth/dashboard y rename `auht` → `auth`. | Las vistas extendían un layout mal escrito y el flujo visual estaba desconectado. |
| `f7e9d08` | `SignInRequest`, autenticación y traducciones. | El login no completaba la autenticación y faltaban mensajes en español. |
| `2c1f0e8` | Flujo de login, alertas y errores reutilizables. | La depuración detenía el proceso y los errores no tenían presentación consistente. |
| `7200e4e` | Estilos, sesión y mensajes del login. | Alertas de credenciales y colores/estilos se mostraban de forma incorrecta. |
| `17a73eb` | Mensaje visual y ruta de reenvío. | La vista no comunicaba correctamente el resultado del reenvío. |
| `fbaa180` | Reenvío sin `dd`. | `dd()` mostraba datos y detenía la petición en lugar de reenviar/redirigir. |
| `4bd64f7` | Verificación sin sesión previa, tests iniciales. | Un enlace abierto en otro navegador fallaba por exigir autenticación previa. |
| `917ce42` | Mensajes de `SignupRequest` y suite Pest. | Las expectativas de validación no coincidían con los textos reales. |

## Explicación de los errores principales

### `Route::with` no existe

`with()` para flash data pertenece a una respuesta de redirección, no a una definición de ruta. La forma correcta quedó conceptualmente así:

```php
return redirect()->route('dashboard')->with('success', '...');
```

o, al volver a la misma página:

```php
return back()->with('error', '...');
```

### Login detenido por `dd`

`dd()` significa “dump and die”: inspecciona una variable y termina la ejecución. Se reemplazó por `Auth::attempt`, una redirección al dashboard al acertar y flash `error` al fallar.

### Verificación desde otro navegador

El flujo estándar con middleware `auth` requiere que la persona conserve la sesión del registro. El proyecto cambió la ruta para localizar al usuario mediante `id`, verificar el hash del correo y exigir la firma temporal; luego marca el correo y autentica al usuario. Así el enlace funciona en una sesión nueva sin aceptar una URL manipulada.

### Mensajes y colores inconsistentes

Se introdujeron `App\View\Components\Alert`, `components/alert.blade.php` e `input-error.blade.php`. Las vistas ya no repiten toda la lógica: pasan `success`/`error` y el componente selecciona clases verdes o rojas. Los FormRequest definen textos en español por regla/campo.

### Reenvío sin control

La ruta `verification.send` llama a `sendEmailVerificationNotification()`, vuelve con confirmación y aplica `throttle:1,1`: como máximo un intento por minuto para reducir abuso.

## Estado vigente comprobado

Comandos de diagnóstico usados:

```bash
php artisan route:list --except-vendor
php artisan test
git log --oneline
```

La última ejecución de `php artisan test` finalizó correctamente: **13 tests, 49 aserciones y 0 fallos**.

## Archivos del flujo y comandos para crearlos

Los siguientes son los comandos Artisan reproducibles para generar los archivos principales. Los archivos que Laravel no genera mediante Artisan se crean manualmente en la ruta indicada.

| Comando | Archivo | Responsabilidad |
|---|---|---|
| `php artisan make:controller Auth/RegisterController` | `app/Http/Controllers/Auth/RegisterController.php` | Recibe el formulario de registro, crea el usuario y comienza el flujo de verificación. |
| `php artisan make:controller Auth/LoginController` | `app/Http/Controllers/Auth/LoginController.php` | Muestra el login, intenta autenticar y redirige según el resultado. |
| `php artisan make:request SignupRequest` | `app/Http/Requests/SignupRequest.php` | Centraliza reglas y mensajes de validación del registro. |
| `php artisan make:request SignInRequest` | `app/Http/Requests/SignInRequest.php` | Valida email y contraseña antes de ejecutar el login. |
| `php artisan make:notification VerifyEmail` | `app/Notifications/VerifyEmail.php` | Construye y envía el correo con el enlace firmado de verificación. |
| `php artisan make:component Alert` | `app/View/Components/Alert.php` y `resources/views/components/alert.blade.php` | Crea la lógica y la vista reutilizable de alertas. |
| `php artisan make:test --pest RegisterUserTest` | `tests/Feature/RegisterUserTest.php` | Prueba el registro y la verificación del correo. |
| `php artisan make:test --pest LoginUserTest` | `tests/Feature/LoginUserTest.php` | Prueba login correcto, credenciales inválidas y restricciones de verificación. |
| Creación manual | `routes/web.php` | Declara URLs, nombres de ruta, controladores y middleware. |
| Creación manual | `resources/views/auth/login.blade.php` | Renderiza el formulario de inicio de sesión. |
| Creación manual | `resources/views/auth/register.blade.php` | Renderiza el formulario de registro. |
| Creación manual | `resources/views/auth/verify-email.blade.php` | Informa que el correo debe verificarse y permite reenviar el enlace. |
| Creación manual | `resources/views/dashboard.blade.php` | Renderiza el área protegida para usuarios verificados. |

Para ejecutar todo el testing:

```bash
php artisan test
```

Para ejecutar solamente los tests de login:

```bash
php artisan test tests/Feature/LoginUserTest.php
```

## Explicación de `LoginUserTest.php`

### Preparación

```php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
```

- `use App\Models\User` importa el modelo correcto, que incluye el factory de usuarios.
- `use RefreshDatabase` importa la utilidad que prepara una base limpia para cada test.
- `uses(RefreshDatabase::class)` activa esa limpieza en todo el archivo y evita que un test contamine a otro.

### Login exitoso de un usuario verificado

```php
it('logs in a verified user successfully', function () {
    User::factory()->create([
        'email' => 'juan@juan.com',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'juan@juan.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});
```

- `it(...)` declara el comportamiento que se va a probar.
- `User::factory()->create(...)` inserta un usuario en la base de pruebas.
- `bcrypt('password')` guarda una contraseña cifrada que coincide con la enviada posteriormente.
- `email_verified_at => now()` identifica al usuario como verificado.
- `$this->post(...)` simula el envío del formulario a `login.store`.
- `assertRedirect(...)` confirma que el login exitoso envía al dashboard.
- `assertAuthenticated()` confirma que Laravel creó la sesión autenticada.

### Contraseña incorrecta

```php
$response = $this->from(route('login'))->post(route('login.store'), [
    'email' => 'juan@juan.com',
    'password' => 'incorrect-password',
]);

$response->assertRedirect(route('login'));
$response->assertSessionHas('error', 'Credenciales incorrectas.');
$this->assertGuest();
```

- `from(route('login'))` define la página de origen para que `back()` vuelva al login.
- La contraseña deliberadamente incorrecta hace fallar `Auth::attempt()`.
- `assertRedirect(route('login'))` verifica el regreso al formulario.
- `assertSessionHas(...)` comprueba exactamente el mensaje flash creado por el controlador.
- `assertGuest()` confirma que no se abrió una sesión.

### Usuario autenticado sin correo verificado

```php
$user = User::factory()->unverified()->create();

$response = $this->actingAs($user)->get(route('dashboard'));

$response->assertRedirect(route('verification.notice'));
```

- `unverified()` crea un usuario con `email_verified_at` en `null`.
- `actingAs($user)` inicia una sesión de prueba con ese usuario.
- `get(route('dashboard'))` intenta entrar a la ruta protegida.
- `assertRedirect(route('verification.notice'))` confirma que el middleware `verified` bloquea el acceso.

### Usuario inexistente

```php
$response = $this->from(route('login'))
    ->post(route('login.store'), [
        'email' => 'noexiste@dominio.com',
        'password' => 'password',
    ]);

$response->assertRedirect(route('login'));
$response->assertSessionHasErrors('email');
$this->assertGuest();
```

- Se envía un correo que no existe en la tabla `users`.
- La regla `exists:users,email` de `SignInRequest` rechaza la solicitud antes de ejecutar el controlador.
- `assertSessionHasErrors('email')` verifica el error por su campo sin cambiar el request ni depender del texto traducido por Laravel.
- `assertGuest()` confirma que la solicitud rechazada no autenticó a nadie.

## Últimas correcciones realizadas en los tests

- Se reemplazó el modelo incorrecto `Illuminate\Foundation\Auth\User` por `App\Models\User` para habilitar `factory()`.
- Se unificó la variable de respuesta como `$response`; antes aparecía también `$responde`.
- Se corrigieron los nombres `asserRedirect`/`assertReedirect` a `assertRedirect` y `actingAS` a `actingAs`.
- Se evitó combinar `assertRedirect()` (respuesta 3xx) con `assertOk()` (respuesta 200) sobre la misma petición.
- Las aserciones se ejecutan sobre `$response`, no directamente sobre `$this`.
- La contraseña cifrada del usuario coincide con la contraseña enviada en el login exitoso.
- Para credenciales incorrectas se comprueba el flash `error` que realmente crea `LoginController`.
- Para un correo inexistente se usa `assertSessionHasErrors('email')`, porque la regla `exists` detiene la solicitud antes del controlador.

## Trabajo realizado el 23 de julio de 2026

El historial Git registra dos entregas durante esta jornada:

| Commit | Cambio |
|---|---|
| `6a81ef3` | Se agregó el cierre de sesión, su redirección al login y la estructura inicial de presupuestos. |
| `bf38273` | Se agregó la pantalla para crear presupuestos y se mostraron sus campos mediante el componente `BudgetForm`. |

### Cierre de sesión

- Se creó `LogoutController`.
- El menú desplegable envía una petición `POST` con protección CSRF a la ruta `logout.store`.
- La ruta ejecuta `Auth::logout()` y redirige al formulario de inicio de sesión.
- Se corrigió la asociación de la ruta para que use `LogoutController` y no el controlador de registro.

### Estructura de presupuestos

- Se crearon el modelo `Budget`, su migración y `BudgetController`.
- La migración define nombre, tipo, propietario, marcas de tiempo y borrado lógico.
- El dashboard ahora enlaza con la ruta `budgets.create`.
- `BudgetController::create()` devuelve la vista `budgets.create`.
- Se creó el componente Blade `BudgetForm` con los campos `name`, `amount` y `type`.
- La vista de creación renderiza el componente con `<x-budget-form />`; tener el archivo del componente creado no hace que Laravel lo muestre automáticamente.

### Comandos de creación asociados

Estos comandos Artisan permiten reproducir la creación de los archivos generados durante la jornada:

| Comando | Archivos generados |
|---|---|
| `php artisan make:controller LogoutController` | `app/Http/Controllers/LogoutController.php` |
| `php artisan make:model Budget -mcr` | `app/Models/Budget.php`, la migración `create_budgets_table.php` y `app/Http/Controllers/BudgetController.php` |
| `php artisan make:component BudgetForm` | `app/View/Components/BudgetForm.php` y `resources/views/components/budget-form.blade.php` |

Las rutas y las vistas de página se editaron o crearon manualmente; no requieren un comando Artisan.

### Comandos de revisión utilizados

| Comando | Uso |
|---|---|
| `php artisan route:list --name=budgets` | Confirmar que Laravel reconoce la ruta de creación de presupuestos. |
| `php artisan view:clear` | Eliminar vistas Blade compiladas anteriormente. |
| `php artisan view:cache` | Compilar las vistas y comprobar que no tienen errores de sintaxis. |
| `git status --short` | Consultar archivos nuevos y modificados. |
| `git diff` y `git diff --check` | Revisar cambios y detectar errores de espacios. |
| `git log` y `git show` | Verificar los commits y los archivos incluidos. |
| `rg` | Buscar rutas, controladores, componentes y textos dentro del proyecto. |
| `sed` | Leer secciones concretas de los archivos durante el diagnóstico. |

### Archivos creados

| Archivo | Finalidad |
|---|---|
| `app/Http/Controllers/LogoutController.php` | Cierra la sesión y redirige al login. |
| `app/Http/Controllers/BudgetController.php` | Controlador de recursos para presupuestos. |
| `app/Models/Budget.php` | Modelo Eloquent de los presupuestos. |
| `database/migrations/2026_07_24_010555_create_budgets_table.php` | Crea la tabla `budgets`. El nombre quedó fechado el 24 de julio porque Laravel usa la hora del entorno al generar la migración. |
| `app/View/Components/BudgetForm.php` | Clase del componente reutilizable del formulario. |
| `resources/views/components/budget-form.blade.php` | Campos de nombre, cantidad y tipo de presupuesto. |
| `resources/views/budgets/create.blade.php` | Página para crear un presupuesto. |

### Archivos modificados

| Archivo | Modificación |
|---|---|
| `routes/web.php` | Rutas de cierre de sesión, dashboard y creación de presupuestos. |
| `resources/views/components/dropdown-menu.blade.php` | Formulario y botón para cerrar sesión. |
| `resources/views/dashboard.blade.php` | Enlace hacia “Nuevo Presupuesto”. |
| `resources/views/auth/register.blade.php` | Ajuste menor incluido en la entrega de la jornada. |

Consulte `docs/guia-completa-cashtrackr.md` para arquitectura, inventario completo y recomendaciones de despliegue.
