# CashTrackr

CashTrackr es una aplicación web en Laravel para gestionar usuarios, presupuestos y gastos. El alcance implementado actualmente incluye registro, inicio de sesión, verificación de correo, acceso protegido al dashboard y creación, listado clasificado y edición inicial de presupuestos asociados al usuario autenticado.

## Stack

- PHP 8.3 y Laravel 13.
- Blade para renderizado del lado del servidor.
- Tailwind CSS 4, Vite 8 y fuentes Bunny para la interfaz.
- Eloquent y una base configurable (SQLite por defecto; PostgreSQL/Neon mediante `.env`).
- Notificaciones de Laravel y SMTP/Mailtrap para correo.
- Pest 4 y PHPUnit 12 para pruebas.

## Inicio rápido

Requisitos: PHP 8.3+, Composer, Node.js/npm y las extensiones PHP requeridas por Laravel.

```bash
composer run setup
composer run dev
```

`composer run setup` instala dependencias, crea `.env`, genera `APP_KEY`, ejecuta migraciones e instala/compila el frontend. `composer run dev` inicia servidor web, worker de colas, visor de logs y Vite en paralelo.

La aplicación queda disponible normalmente en `http://127.0.0.1:8000`.

## Comandos frecuentes

```bash
composer run dev       # entorno de desarrollo completo
composer test          # limpia caché de config y ejecuta la suite
npm run dev            # Vite con recarga en caliente
npm run build          # genera assets optimizados en public/build
php artisan migrate    # aplica migraciones pendientes
php artisan route:list # muestra rutas, métodos y middleware
php artisan pail       # muestra logs en tiempo real
php artisan queue:work # procesa trabajos en cola
```

## Documentación

- [Guía técnica completa](docs/guia-completa-cashtrackr.md): arquitectura, comandos, archivos, rutas y renderizado.
- [Últimos cambios de presupuestos](docs/ultimos-cambios-presupuestos.md): listado, clasificación, edición, historial por commit y limitaciones vigentes.
- [Historial de autenticación y correo](docs/trabajo-auth-correo.md): errores corregidos, evidencia Git y deuda técnica vigente.

## Estado actual

El núcleo de autenticación está implementado. El módulo de presupuestos permite validar, guardar, listar por usuario y actualizar registros mediante un formulario reutilizable. El menú de acciones, la eliminación, la autorización por propietario y las pruebas específicas del módulo siguen pendientes. Consulte el documento de últimos cambios para conocer las limitaciones detectadas.

## Seguridad

Nunca se debe versionar `.env`, credenciales de Neon/Mailtrap, tokens ni claves. Use `.env.example` como plantilla sin secretos y `APP_DEBUG=false` en producción.
