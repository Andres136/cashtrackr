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

`route:list` reconoce diez rutas de la aplicación. `php artisan test` no llega a ejecutar pruebas porque el cambio local actual en `tests/Feature/RegisterUserTest.php` contiene sintaxis incompleta. Además, faltan tokens CSRF en los formularios de registro y reenvío. Estos dos puntos son deuda vigente, no correcciones históricas.

Consulte `docs/guia-completa-cashtrackr.md` para arquitectura, inventario completo y recomendaciones de despliegue.
