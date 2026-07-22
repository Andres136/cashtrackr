# Guía técnica de CashTrackr

Documento actualizado contra el código de la rama `main` (commit `917ce42`) y los cambios locales existentes al 19 de julio de 2026.

## 1. Alcance y arquitectura

CashTrackr es hoy una aplicación Laravel monolítica renderizada en servidor. “Backend” y “frontend” viven en el mismo repositorio:

```text
Navegador
   │ HTTP GET/POST
   ▼
public/index.php → bootstrap/app.php → routes/web.php
                                      │
                       middleware + controlador/closure
                                      │
                  FormRequest → modelo Eloquent → base de datos
                                      │
                                 vista Blade
                                      │
                          layout + componentes + Vite
                                      ▼
                                  HTML/CSS
```

No existe una API ni un frontend SPA en React/Vue. Blade crea el HTML en PHP; Vite compila CSS/JS; Tailwind aporta las utilidades visuales. `resources/js/app.js` está vacío, por lo que no hay comportamiento JavaScript propio actualmente.

## 2. Instalación y configuración

### Requisitos

- PHP `^8.3` y Composer.
- Node.js compatible con Vite 8 y npm.
- Una base de datos soportada por Laravel.
- SMTP opcional para recibir correos reales/de prueba.

### Instalación automática

```bash
composer run setup
```

Ejecuta, en orden:

1. `composer install`: instala paquetes PHP en `vendor/`.
2. Copia `.env.example` a `.env` si aún no existe.
3. `php artisan key:generate`: crea la clave de cifrado de Laravel.
4. `php artisan migrate --force`: crea/actualiza tablas.
5. `npm install --ignore-scripts`: instala dependencias frontend.
6. `npm run build`: compila CSS/JS para producción.

Después se inicia desarrollo con:

```bash
composer run dev
```

Este comando ejecuta en paralelo `php artisan serve`, `queue:listen`, `php artisan pail` y `npm run dev`. Si uno termina, `--kill-others` detiene los restantes.

### Variables importantes de `.env`

| Variable | Función |
|---|---|
| `APP_NAME`, `APP_URL` | Nombre y URL usados por vistas y enlaces firmados. |
| `APP_KEY` | Cifrado de cookies, sesiones y datos sensibles. |
| `APP_ENV`, `APP_DEBUG` | Entorno y detalle de errores; en producción, `APP_DEBUG=false`. |
| `DB_CONNECTION`, `DB_URL`/`DB_*` | Driver y credenciales de base de datos. |
| `SESSION_DRIVER` | Persistencia de sesión; por defecto usa tabla `sessions`. |
| `QUEUE_CONNECTION` | Backend de colas; por defecto usa tabla `jobs`. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT` | Transporte SMTP. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Credenciales SMTP; nunca se versionan. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Remitente visible. |

`.env.example` usa SQLite y correo en logs. Para Neon normalmente se configura `DB_CONNECTION=pgsql` y una `DB_URL` SSL; para Mailtrap, las credenciales SMTP que entrega su sandbox.

## 3. Comandos del proyecto

| Comando | Qué hace | Cuándo usarlo |
|---|---|---|
| `composer run setup` | Instalación integral de backend, entorno, BD y frontend. | Primera instalación. |
| `composer run dev` | Servidor, cola, logs y Vite simultáneos. | Desarrollo diario. |
| `composer test` | Limpia caché de configuración y corre `artisan test`. | Antes de integrar cambios. |
| `composer install` | Instala versiones fijadas en `composer.lock`. | Clonar/desplegar. |
| `composer update` | Actualiza dependencias y lock; puede introducir cambios. | Mantenimiento controlado. |
| `npm install` | Instala dependencias fijadas en `package-lock.json`. | Preparar frontend. |
| `npm run dev` | Vite en modo desarrollo/HMR. | Trabajar estilos o JS. |
| `npm run build` | Crea assets versionados en `public/build`. | Producción/validación. |
| `php artisan serve` | Servidor PHP local. | Backend sin procesos extra. |
| `php artisan migrate` | Ejecuta migraciones pendientes. | Tras cambios de esquema. |
| `php artisan migrate:fresh --seed` | Recrea todas las tablas y carga seeders; borra datos. | Solo desarrollo/pruebas. |
| `php artisan db:seed` | Ejecuta `DatabaseSeeder`. | Datos iniciales. |
| `php artisan route:list` | Lista rutas, nombres, acciones y middleware. | Diagnóstico del flujo HTTP. |
| `php artisan config:clear` | Elimina caché de configuración. | Tras editar `.env`. |
| `php artisan optimize:clear` | Limpia cachés de config, rutas, vistas y eventos. | Resolver caché obsoleta. |
| `php artisan queue:work` | Procesa trabajos en cola. | Si se encolan notificaciones/jobs. |
| `php artisan pail` | Sigue el log de Laravel. | Diagnóstico en desarrollo. |
| `php artisan tinker` | Consola interactiva de Laravel. | Consultas y pruebas puntuales. |

## 4. Rutas y permisos

| Método y URL | Nombre | Acción | Protección |
|---|---|---|---|
| `GET /` | — | Renderiza `welcome`. | Pública. |
| `GET /auth/register` | `register` | `RegisterController@index`. | Pública. |
| `POST /auth/register` | `register.store` | Valida y crea usuario. | Pública, middleware web. |
| `GET /auth/login` | `login` | `LoginController@index`. | Pública. |
| `POST /auth/login` | `login.store` | Intenta autenticar. | Pública, middleware web. |
| `GET /email/verify` | `verification.notice` | Vista para revisar el correo. | `auth`. |
| `GET /email/verify/{id}/{hash}` | `verification.verify` | Valida firma/hash y confirma. | `signed`. |
| `POST /email/verification-notification` | `verification.send` | Reenvía notificación. | `auth`, máximo 1/minuto. |
| `GET /dashboard` | `dashboard` | Renderiza dashboard. | `auth`, `verified`. |
| `ANY /dasboard` | — | Corrige el typo hacia `/dashboard`. | Redirección. |
| `GET /up` | — | Health check de Laravel. | Pública. |

El grupo `web` aplica cookies, sesión, errores compartidos y protección CSRF. El middleware `signed` evita modificar parámetros/expiración del enlace; `verified` bloquea el dashboard hasta llenar `email_verified_at`.

## 5. Flujos funcionales

### Registro

1. `GET /auth/register` llama a `RegisterController@index`.
2. Se renderiza `auth/register.blade.php` dentro de `layouts.auth` y `layouts.base`.
3. El `POST` llega a `RegisterController@store` mediante `SignupRequest`.
4. Se valida nombre, email único y contraseña confirmada, con letras, mayúscula/minúscula, símbolo, número y comprobación de filtraciones.
5. `User::create()` inserta el usuario. El cast `hashed` cifra la contraseña automáticamente.
6. El evento `Registered` provoca el envío de verificación porque `User` implementa `MustVerifyEmail`.
7. La aplicación autentica al usuario y redirige a `verification.notice`.

### Verificación del correo

1. `App\Notifications\VerifyEmail` crea una URL firmada con vencimiento de 60 minutos, `id` y hash SHA-1 del email.
2. El canal `mail` entrega el mensaje mediante el mailer configurado.
3. Al abrir el enlace, `signed` comprueba firma y expiración.
4. La closure busca el usuario, compara el hash con `hash_equals`, marca el email si procede y dispara `Verified`.
5. Inicia sesión con ese usuario y redirige al dashboard con un flash `success`.

La ruta permite confirmar desde otro navegador porque no exige una sesión previa; compensa esto con firma temporal y hash. Es una decisión distinta al flujo estándar de Laravel con `auth` + `EmailVerificationRequest`.

### Login

1. `SignInRequest` valida email existente y password obligatorio.
2. `Auth::attempt($data, true)` compara el hash y crea sesión persistente (“remember”).
3. Si falla, vuelve atrás con flash `error`; si funciona, redirige a `dashboard`.
4. `verified` decide si el usuario puede ver el dashboard o debe confirmar el correo.

Observación: después de autenticar conviene ejecutar `$request->session()->regenerate()` para prevenir session fixation. Tampoco existe aún una ruta de logout ni recuperación de contraseña.

## 6. Cómo se renderiza una vista

Ejemplo del login:

```text
GET /auth/login
 → routes/web.php
 → LoginController@index
 → view('auth.login')
 → login.blade.php extiende layouts.auth
 → layouts.auth extiende layouts.base
 → @section('auth-contents') entra en @yield('auth-contents')
 → @section('contents') entra en @yield('contents') de base
 → @vite agrega CSS/JS compilado
 → Blade devuelve HTML al navegador
```

`layouts/base.blade.php` construye `<html>`, título, fuentes, assets, encabezado, logo y navegación condicional (`@auth`). Si existe el manifest de build o el archivo `public/hot`, usa `@vite`; en caso contrario contiene un CSS de fallback incrustado. `layouts/auth.blade.php` agrega el panel centrado y sus dos slots Blade. Las vistas concretas definen título y contenido.

Los componentes se usan como etiquetas Blade:

- `<x-input-error field="email" />` consulta el error de validación del campo y lo imprime.
- `<x-alert type="error" :message="session('error')" />` usa la clase `App\View\Components\Alert` y la plantilla `components/alert.blade.php` para elegir colores y escapar el mensaje.

Vite toma `resources/css/app.css` y `resources/js/app.js`. El plugin Laravel activa recarga al cambiar archivos y el plugin Tailwind genera únicamente las clases detectadas en las fuentes. La directiva `@fonts` carga Instrument Sans mediante el plugin de fuentes.

## 7. Inventario de archivos

### Backend y entrada HTTP

| Archivo | Responsabilidad |
|---|---|
| `artisan` | Punto de entrada de la CLI de Laravel. |
| `public/index.php` | Front controller: recibe toda petición del servidor web. |
| `bootstrap/app.php` | Crea la aplicación, conecta rutas, health check, middleware y política JSON para `/api/*`. |
| `bootstrap/providers.php` | Registra `AppServiceProvider`. |
| `app/Providers/AppServiceProvider.php` | Hooks globales de arranque; actualmente sin personalización funcional. |
| `routes/web.php` | Contrato HTTP de registro, login, verificación y dashboard. |
| `routes/console.php` | Comando `artisan inspire`. |
| `app/Http/Controllers/Controller.php` | Clase base para controladores. |
| `app/Http/Controllers/Auth/RegisterController.php` | Presenta y procesa registro. |
| `app/Http/Controllers/Auth/LoginController.php` | Presenta y procesa login. |
| `app/Http/Requests/SignupRequest.php` | Reglas y mensajes españoles del registro. |
| `app/Http/Requests/SignInRequest.php` | Reglas, atributos y mensajes del login. |
| `app/Models/User.php` | Entidad autenticable, campos asignables/ocultos, casts y notificación personalizada. |
| `app/Notifications/VerifyEmail.php` | Construye enlace firmado y contenido del correo. |
| `app/View/Components/Alert.php` | Componente PHP para alertas Blade. |

### Frontend

| Archivo | Responsabilidad |
|---|---|
| `resources/views/layouts/base.blade.php` | Documento HTML raíz, assets, header y sesión visual. |
| `resources/views/layouts/auth.blade.php` | Contenedor común de pantallas de autenticación. |
| `resources/views/auth/register.blade.php` | Formulario de registro y errores por campo. |
| `resources/views/auth/login.blade.php` | Formulario de login y alerta de credenciales. |
| `resources/views/auth/verify-email.blade.php` | Aviso y formulario de reenvío. |
| `resources/views/dashboard.blade.php` | Área protegida; hoy solo presenta mensajes flash. |
| `resources/views/welcome.blade.php` | Landing inicial heredada del starter de Laravel. |
| `resources/views/components/alert.blade.php` | HTML/colores de alertas success/error. |
| `resources/views/components/input-error.blade.php` | Error de validación de un campo. |
| `resources/css/app.css` | Entrada Tailwind, fuentes de escaneo y tema tipográfico. |
| `resources/js/app.js` | Entrada JS vacía, reservada para lógica futura. |
| `public/img/logo.svg` | Logo público servido sin pasar por Vite. |
| `vite.config.js` | Entradas, HMR, Bunny Fonts y Tailwind. |
| `package.json` / `package-lock.json` | Scripts y versiones frontend. |

### Datos, pruebas y configuración

| Archivo/grupo | Responsabilidad |
|---|---|
| `database/migrations/*users*` | Tablas `users`, `password_reset_tokens` y `sessions`. |
| `database/migrations/*cache*` | Tablas de caché y locks. |
| `database/migrations/*jobs*` | Jobs, lotes y jobs fallidos. |
| `database/factories/UserFactory.php` | Usuarios falsos verificados/no verificados. |
| `database/seeders/DatabaseSeeder.php` | Inserta `test@example.com`. |
| `tests/Pest.php` | Vincula pruebas Feature con `Tests\TestCase`. |
| `tests/TestCase.php` | Base de pruebas que levanta Laravel. |
| `tests/Feature/RegisterUserTest.php` | Pruebas de vista, alta, validación, duplicados y evento. |
| `phpunit.xml` | Entorno aislado de test: SQLite en memoria, array mail/queue/session/cache. |
| `composer.json` / `composer.lock` | Dependencias, autoload y scripts PHP. |
| `.env.example` | Plantilla de configuración sin secretos. |
| `config/app.php` | Nombre, entorno, URL, locale y cifrado. |
| `config/auth.php` | Guard web, provider Eloquent y reset de contraseña. |
| `config/database.php` | Conexiones SQLite/MySQL/PostgreSQL/SQL Server/Redis. |
| `config/mail.php` | Mailers y remitente. |
| `config/session.php` | Driver, duración, cookies y seguridad de sesión. |
| `config/cache.php` | Stores de caché. |
| `config/queue.php` | Drivers, jobs fallidos y batching. |
| `config/filesystems.php` | Discos local/public/S3. |
| `config/logging.php` | Canales y niveles de logs. |
| `config/services.php` | Credenciales de servicios externos comunes. |

`storage/` contiene logs, sesiones/caché/plantillas compiladas y archivos privados; `vendor/` y `node_modules/` contienen dependencias generadas. No se documenta archivo por archivo su código de terceros. Los archivos `*:Zone.Identifier`, `Untitled-1` y `,` son residuos sin función en la aplicación y deberían evaluarse para eliminación.

## 8. Errores solucionados

La evidencia detallada por commit está en `docs/trabajo-auth-correo.md`. En resumen:

- Se añadió almacenamiento seguro de contraseñas y validación robusta.
- `User` pasó a exigir verificación y se implementó el correo firmado.
- Se corrigió el layout mal nombrado `auht.blade.php` a `auth.blade.php` y se conectaron vistas/dashboard.
- El login dejó de detenerse con depuración y pasó a usar `Auth::attempt` con mensajes visibles.
- Se corrigió el uso inválido de `Route::with`; los flash messages ahora pertenecen a `redirect()/back()`.
- Se añadieron componentes reutilizables para alertas y errores de campos, incluidos colores correctos.
- El reenvío dejó de usar `dd`, añadió límite de frecuencia y mensaje de éxito.
- La verificación se adaptó para enlaces abiertos sin sesión, manteniendo firma/hash y autenticando después.
- Se sincronizaron mensajes personalizados de `SignupRequest` con pruebas Pest.

## 9. Deuda técnica y riesgos vigentes

Estos puntos no están solucionados en el estado inspeccionado:

1. **Pruebas rotas por cambios locales:** `RegisterUserTest.php` tiene una coma duplicada, una consulta incompleta y falta `;`; `php artisan test` termina en `ParseError` antes de ejecutar casos. Es una modificación local preexistente y no se alteró durante esta documentación.
2. **CSRF ausente:** `register.blade.php` y `verify-email.blade.php` no contienen `@csrf`, de modo que sus `POST` normalmente reciben HTTP 419 bajo el middleware web. Login sí lo incluye.
3. **Regeneración de sesión:** login debería regenerar el ID tras `Auth::attempt`.
4. **Validación de contraseña:** `Password::min(4)` es muy bajo aunque las reglas adicionales eleven la complejidad; conviene mínimo 8–12. `uncompromised()` depende del servicio externo de comprobación y puede afectar tests/red restringida.
5. **Regla `exists` en login:** revela potencialmente si un correo está registrado y divide los errores; es preferible validar solo formato y devolver un mensaje genérico desde `Auth::attempt`.
6. **Navegación incompleta:** no hay logout, recuperación de contraseña ni contenido funcional de presupuestos/gastos.
7. **HTML/layout:** hay etiquetas `nav` desbalanceadas y una clase concatenada `w-fullblock` en `base.blade.php`.
8. **Fallback CSS enorme:** CSS compilado está incrustado en el layout; puede quedar obsoleto. Es mejor garantizar `npm run build` y una pantalla simple si falta el manifest.
9. **Residuos del repositorio:** archivos `Zone.Identifier`, `Untitled-1` y `,` no aportan funcionalidad.

## 10. Pruebas y criterio de despliegue

El entorno de pruebas usa SQLite en memoria y drivers `array`/`sync`, por lo que no envía correo ni toca servicios reales. La intención de la suite es cubrir pantalla de registro, creación sin verificar, evento `Registered`, obligatorios y email duplicado.

Antes de desplegar deben pasar, como mínimo:

```bash
composer test
npm run build
php artisan route:list
```

También deben validarse manualmente registro → correo → verificación → dashboard, login de usuario verificado/no verificado y reenvío con límite de frecuencia. Nunca se debe ejecutar `migrate:fresh` contra una base con datos que deban conservarse.
