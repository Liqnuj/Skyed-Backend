# Skyed — Backend

API REST construida en Laravel para **Skyed**, una plataforma que unifica
dos módulos de negocio:

- **Skyed Deportivo**: gestión de eventos deportivos (ciclismo, atletismo,
  senderismo), inscripciones, pagos, kits, resultados y premios.
- **Skyed Social**: gestión de ambientes/salones, servicios, eventos
  sociales y reservas.

Proyecto desarrollado en el marco de la formación **Análisis y Desarrollo
de Software (ADSO)** — SENA.

---

## Tecnologías

| Tecnología | Versión |
|---|---|
| PHP | ^8.3 |
| Laravel | ^13.17 |
| Laravel Sanctum (autenticación por token) | ^4.0 |
| Base de datos (desarrollo) | SQLite |

---

## Instalación

### 1. Clonar el proyecto e instalar dependencias

```bash
composer install
```

### 2. Configurar el entorno

Copia el archivo de ejemplo y genera la llave de la aplicación:

```bash
cp .env.example .env
php artisan key:generate
```

Revisa que en tu `.env` quede así (usa SQLite por defecto, no necesitas
instalar MySQL para desarrollar):

```env
DB_CONNECTION=sqlite
```

Si usas SQLite, crea el archivo de la base de datos:

```bash
touch database/database.sqlite
```

### 3. Ejecutar migraciones y seeders

```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=AdminUserSeeder
```

Esto crea:
- Los roles base del sistema (`adminDeportivo`, `adminSocial`).
- Un usuario administrador de prueba con ambos roles asignados, para
  poder probar todos los endpoints desde el primer momento.

### 4. Levantar el servidor

```bash
php artisan serve
```

La API queda disponible en `http://127.0.0.1:8000/api`.

---

## Sistema de roles y permisos

El proyecto usa un esquema de **rol + contexto**, gestionado por el
middleware personalizado `role.context`.

| Rol | Puede administrar |
|---|---|
| `adminDeportivo` | Eventos deportivos, categorías, patrocinadores, kits, estaciones, rutas, resultados, pagos, entregas de kit, premios, copias de seguridad |
| `adminSocial` | Ambientes, servicios, eventos sociales, reservas (gestión), PQR (respuesta) |
| `Cliente` / `Participante` | Autoservicio: registrarse, inscribirse a eventos, reservar ambientes, radicar PQR — solo pueden ver y gestionar **sus propios** registros |

Un mismo usuario puede tener varios roles en distintos contextos (por
ejemplo, ser `adminDeportivo` y `adminSocial` a la vez, como el usuario
sembrado por `AdminUserSeeder`).

Ejemplo de uso en las rutas:

```php
Route::post('/eventos', [EventoDeportivoController::class, 'store'])
    ->middleware('role.context:adminDeportivo');

// Varios roles permitidos, separados por '|'
Route::get('/copias-seguridad', [CopiaSeguridadController::class, 'index'])
    ->middleware('role.context:adminDeportivo|adminSocial');
```

---

## Autenticación

La API usa **Laravel Sanctum** con tokens (no cookies/sesión).

| Endpoint | Descripción |
|---|---|
| `POST /api/register` | Registro público de un nuevo usuario |
| `POST /api/login` | Inicia sesión, devuelve un token |
| `POST /api/logout` | Cierra la sesión (requiere token) |
| `GET /api/me` | Datos del usuario autenticado |
| `PUT /api/cambiar-contrasena` | Cambiar contraseña (requiere token) |
| `POST /api/forgot-password` | Solicita código de recuperación por correo |
| `POST /api/reset-password` | Restablece la contraseña con el código recibido |

Para las rutas protegidas, envía el token en el header:

```
Authorization: Bearer {token}
```

Los endpoints de autenticación tienen límite de intentos (`throttle`)
para prevenir fuerza bruta.

---

## Estructura de módulos de la API

| Módulo | Prefijo de ruta | Autoservicio | Solo admin |
|---|---|---|---|
| Usuarios | `/users` | — | Todo el CRUD |
| Eventos deportivos | `/eventos` | Ver | Crear/editar/borrar/cambiar estado |
| Categorías de competencia | `/categorias` | Ver | Crear/editar/borrar |
| Patrocinadores | `/patrocinadores` | Ver | Crear/editar/borrar |
| Inscripciones | `/inscripciones` | Crear la propia, editar contacto de emergencia, cancelar | Ver todas |
| Entregas de kit | `/entregas-kit` | Ver | Crear/editar |
| Historial de participación | `/historial-participacion` | Ver | Crear/editar |
| Estaciones y rutas de evento | `/estaciones`, `/rutas` | Ver | Crear/editar/borrar |
| Resultados y premios | `/resultados`, `/premios` | Ver | Crear/editar/borrar |
| Pagos | `/pagos` | Ver | Cambiar estado |
| QR de entrada | `/qr` | Ver | Validar |
| Tipos de evento | `/tipos-evento` | Ver | Crear/editar/borrar |
| Ambientes y servicios | `/ambientes`, `/servicios` | Ver | Crear/editar/borrar |
| Eventos sociales | `/eventos-sociales` | Ver | Crear/editar/borrar/cambiar estado |
| Reservas | `/reservas` | Crear la propia | Ver todas, editar, borrar, seguimiento |
| PQR | `/pqr` | Crear la propia | Ver todas, responder |
| Invitados | `/invitados` | CRUD | — |
| Notificaciones | `/notificaciones` | Ver las propias, marcar como leídas | — |
| Copias de seguridad | `/copias-seguridad` | — | Ver y crear |

Todos los endpoints devuelven JSON. Los listados (`index`) están
paginados — usa `?page=2` y opcionalmente `?per_page=20` en la URL.

---

## Notificaciones automáticas

El sistema genera notificaciones internas (tabla `notificaciones`,
consultable en `GET /api/notificaciones`) en estos eventos:

- Un admin responde una PQR.
- Cambia el estado de un pago (confirmado/rechazado/cancelado).
- Cambia el estado de una reserva.

La lógica de creación está centralizada en
`app/Services/NotificacionService.php` para no repetir código en cada
controlador.

---

## Buenas prácticas aplicadas en el proyecto

- **Form Requests** (`app/Http/Requests/`) para separar la validación
  de la lógica de negocio en los controladores.
- **Transacciones de base de datos** (`DB::transaction`) con bloqueo de
  fila (`lockForUpdate`) en flujos críticos como inscripciones y pagos,
  para evitar condiciones de carrera (dos personas tomando el último
  cupo al mismo tiempo, por ejemplo).
- **Filtrado por propietario**: un usuario normal solo puede ver sus
  propias inscripciones, reservas y PQR; solo el rol admin
  correspondiente ve todo.
- **Middleware de roles reutilizable** (`role.context`), soporta un rol
  único o varios separados por `|`.

---

## Pruebas con Postman

1. Corre `POST /api/login` con el usuario sembrado por
   `AdminUserSeeder` para obtener un token.
2. En Postman, configura la autorización de tu colección como
   **Bearer Token** usando una variable `{{token}}`.
3. En la pestaña **Tests** del request de login, guarda el token
   automáticamente:

```javascript
const data = pm.response.json();
pm.environment.set("token", data.token);
```

---

## Comandos útiles

```bash
# Limpiar cachés después de cambios en rutas/config
php artisan route:clear
php artisan config:clear

# Ver todas las rutas registradas
php artisan route:list --path=api

# Resetear la base de datos y volver a sembrarla
php artisan migrate:fresh
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=AdminUserSeeder
```
