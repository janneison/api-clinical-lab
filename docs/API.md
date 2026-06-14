# API Clinical Lab — Documentación

## Índice

- [Descripción general](#descripción-general)
- [Autenticación](#autenticación)
- [Roles y permisos](#roles-y-permisos)
- [Códigos de respuesta](#códigos-de-respuesta)
- [Endpoints](#endpoints)
  - [Auth](#auth)
  - [Aliados](#aliados)
  - [Bacteriólogos](#bacteriólogos)
  - [Centros de salud](#centros-de-salud)
  - [Pacientes](#pacientes)
  - [Médicos](#médicos)
  - [Órdenes de laboratorio](#órdenes-de-laboratorio)
  - [Servicios por aliado](#servicios-por-aliado)
  - [Resultados de laboratorio](#resultados-de-laboratorio)
  - [Catálogo de exámenes](#catálogo-de-exámenes)
  - [Portal de pacientes](#portal-de-pacientes)
- [Configuración de correo](#configuración-de-correo)
- [Modelos de datos](#modelos-de-datos)
- [Flujo completo](#flujo-completo)
- [Datos de prueba](#datos-de-prueba)
- [Errores comunes](#errores-comunes)

---

## Descripción general

API REST para gestión de órdenes y resultados de laboratorio clínico. Construida con PHP 8.2 + Slim 4, arquitectura hexagonal.

**Base URL:** `http://localhost:8080` (desarrollo)

**Content-Type:** `application/json` en todos los requests con body.

---

## Autenticación

La API usa **JWT (JSON Web Token)** con algoritmo HS256. El token tiene una validez de **1 hora**.

### Obtener token

```http
POST /auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "Admin1234!"
}
```

Respuesta:

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@clinicallab.local",
    "role": "admin",
    "aliados": [],
    "health_centers": [],
    "permissions": {
      "canRegisterUsers": true,
      "canCreateOrder": true,
      "canViewOrders": true,
      ...
    }
  }
}
```

### Usar el token

Incluir en el header de cada request protegido:

```
Authorization: Bearer <token>
```

### Payload del JWT

```json
{
  "sub": 3,
  "username": "aliado_norte",
  "role": "aliado_operator",
  "aliados": ["ALIADO-001"],
  "health_centers": [],
  "iss": "api-clinical-lab",
  "iat": 1714204800,
  "exp": 1714208400
}
```

Para el rol `medico`, `health_centers` contiene los IDs de los centros de salud asignados y `aliados` estará vacío:

```json
{
  "sub": 7,
  "username": "dr_ramirez",
  "role": "medico",
  "aliados": [],
  "health_centers": [1, 3],
  "iss": "api-clinical-lab",
  "iat": 1714204800,
  "exp": 1714208400
}
```

---

## Roles y permisos

| Rol | Descripción |
|---|---|
| `admin` | Acceso total. Puede registrar usuarios y ver todas las órdenes sin restricción. |
| `lab_operator` | Operador interno. Crea y envía órdenes, registra resultados, lista todas las órdenes. |
| `aliado_operator` | Operador de laboratorio aliado externo. Solo ve las órdenes de sus aliados asignados. |
| `viewer` | Solo lectura. Lista y consulta las órdenes de sus aliados asignados. |
| `medico` | Médico ordenante. Solo ve las órdenes de los centros de salud asignados a su usuario. |

### Matriz de acceso por endpoint

| Endpoint | admin | lab_operator | aliado_operator | viewer | medico |
|---|:---:|:---:|:---:|:---:|:---:|
| `POST /auth/login` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `GET /auth/me` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /auth/register` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `GET /health-centers` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /health-centers` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `PUT /health-centers/{id}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `POST /health-centers/{id}/aliados/{aliadoId}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `DELETE /health-centers/{id}/aliados/{aliadoId}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `GET /aliados` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /aliados` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `GET /aliados/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `PUT /aliados/{id}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `POST /aliados/{id}/logo` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `GET /aliados/{id}/bacteriologos` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /aliados/{id}/bacteriologos` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /bacteriologos/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `PUT /bacteriologos/{id}` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `DELETE /bacteriologos/{id}` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `POST /bacteriologos/{id}/firma` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /patients` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `POST /patients` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /patients/{id}` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `PUT /patients/{id}` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /medicos` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /medicos` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /medicos/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `PUT /medicos/{id}` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `DELETE /medicos/{id}` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /orders` | ✅ | ✅ | ✅ † | ✅ † | ✅ ‡ |
| `POST /orders` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /orders/{id}` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /orders/{id}/send` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /orders/{id}/results` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `GET /orders/{id}/results/pdf` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /orders/{id}/results/attach-pdf` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `POST /orders/{id}/results/send-email` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `POST /results` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `GET /aliados/{id}/orders/pending` | ✅ | ✅ | ✅ † | ❌ | ❌ |
| `POST /aliados/{id}/orders/mark-sent` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `GET /exam-types` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /exam-types` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `PUT /exam-types/{cups}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `GET /exam-types/{cups}/parameters` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /exam-types/{cups}/parameters` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `PUT /exam-types/{cups}/parameters/{id}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `DELETE /exam-types/{cups}/parameters/{id}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `GET /exam-types/{cups}/parameters/{id}/ranges` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `POST /exam-types/{cups}/parameters/{id}/ranges` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `PUT /exam-types/{cups}/parameters/{id}/ranges/{rangeId}` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `DELETE /exam-types/{cups}/parameters/{id}/ranges/{rangeId}` | ✅ | ❌ | ❌ | ❌ | ❌ |

> † `aliado_operator` y `viewer` solo ven las órdenes de los aliados asignados a su usuario.  
> ‡ `medico` solo ve las órdenes de los centros de salud asignados a su usuario (`health_centers` en el JWT).

**Portal de pacientes** — autenticación independiente con JWT de paciente (sin contraseña):

| Endpoint | Autenticación |
|---|---|
| `POST /patient-portal/request-access` | Pública |
| `POST /patient-portal/verify` | Pública |
| `GET /patient-portal/results` | JWT de paciente |
| `GET /patient-portal/results/{idSolicitudKey}/pdf` | JWT de paciente |

---

## Códigos de respuesta

| Código | Significado |
|---|---|
| `200` | OK |
| `201` | Recurso creado |
| `401` | No autenticado (token ausente o inválido) |
| `403` | Sin permisos (rol insuficiente) |
| `404` | Recurso no encontrado |
| `422` | Error de validación |
| `500` | Error interno del servidor |
| `502` | Fallo al contactar el laboratorio externo |

---

## Endpoints

### Auth

---

#### `POST /auth/login`

Autentica un usuario y retorna un JWT.

**Pública** — no requiere token.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `username` | string | ✅ | Nombre de usuario |
| `password` | string | ✅ | Contraseña en texto plano |

**Ejemplo:**

```json
{
  "username": "aliado_norte",
  "password": "Aliado_norte1!"
}
```

**Respuestas:**

`200 OK`
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 3,
    "username": "aliado_norte",
    "email": "aliado_norte@clinicallab.local",
    "role": "aliado_operator",
    "aliados": ["ALIADO-001"],
    "health_centers": [],
    "permissions": {
      "canRegisterUsers": false,
      "canCreateOrder": false,
      "canViewOrders": true,
      "canStoreResult": true,
      "canSendResultEmail": true
    }
  }
}
```

`401 Unauthorized`
```json
{ "error": "Credenciales inválidas" }
```

`422 Unprocessable Entity`
```json
{ "error": "username y password son requeridos" }
```

---

#### `GET /auth/me`

Retorna el perfil del usuario autenticado extraído del JWT, incluyendo sus permisos por rol.

**Roles:** todos.

**Respuesta `200 OK`:**

```json
{
  "id": 3,
  "username": "aliado_norte",
  "role": "aliado_operator",
  "aliados": ["ALIADO-001"],
  "health_centers": [],
  "permissions": {
    "canRegisterUsers": false,
    "canEditAliado": false,
    "canCreateOrder": false,
    "canViewOrders": true,
    "canStoreResult": true,
    "canAttachPdf": true,
    "canSendResultEmail": true,
    "canEditExamCatalog": false
  }
}
```

Para el rol `medico`:

```json
{
  "id": 7,
  "username": "dr_ramirez",
  "role": "medico",
  "aliados": [],
  "health_centers": [1, 3],
  "permissions": {
    "canViewOrders": true,
    "canCreateOrder": false,
    "canStoreResult": false
  }
}
```

---

#### `POST /auth/register`

Crea un nuevo usuario en el sistema.

**Roles:** `admin` únicamente.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `username` | string | ✅ | Nombre de usuario único |
| `email` | string | ✅ | Email único |
| `password` | string | ✅ | Contraseña (mínimo 8 caracteres, mayúscula, minúscula, número y carácter especial) |
| `role` | string | ✅ | Uno de: `admin`, `lab_operator`, `aliado_operator`, `viewer`, `medico` |
| `aliados` | string[] | ❌ | IDs de aliados asignados (aplica a `aliado_operator` y `viewer`) |
| `health_centers` | int[] | ❌ | IDs de centros de salud asignados (aplica principalmente a `medico`) |

**Ejemplo — aliado operator:**

```json
{
  "username": "nuevo_operador",
  "email": "nuevo@clinicallab.local",
  "password": "Segura1234!",
  "role": "aliado_operator",
  "aliados": ["ALIADO-001", "ALIADO-002"]
}
```

**Ejemplo — médico:**

```json
{
  "username": "dr_ramirez",
  "email": "ramirez@clinica.com",
  "password": "Medico2024!",
  "role": "medico",
  "health_centers": [1, 3]
}
```

**Respuestas:**

`201 Created`
```json
{ "id": 6, "message": "Usuario creado" }
```

`422 Unprocessable Entity`
```json
{ "error": "El username ya está en uso" }
{ "error": "El email ya está en uso" }
{ "error": "Rol inválido: superuser" }
{ "error": "Aliado no encontrado: ALIADO-999" }
```

`403 Forbidden`
```json
{ "error": "No tienes permisos para esta acción" }
```

---

#### `GET /auth/password-policy`

Devuelve la descripción de la política de contraseñas.

**Pública** — no requiere token.

**Respuesta `200 OK`:**

```json
{
  "policy": "Mínimo 8 caracteres, al menos una mayúscula, una minúscula, un número y un carácter especial",
  "minLength": 8,
  "requireUppercase": true,
  "requireLowercase": true,
  "requireDigit": true,
  "requireSpecial": true,
  "allowedSpecial": "!@#$%^&*()_+-=[]{}|;':\",./<>?"
}
```

---

#### `POST /auth/password-reset/request`

Solicita un enlace de recuperación de contraseña. Siempre responde `200` para no revelar si el email existe.

**Pública** — no requiere token.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `email` | string | ✅ | Email del usuario |

**Respuesta `200 OK`:**

```json
{
  "message": "Si el correo está registrado, recibirás un enlace de recuperación en los próximos minutos."
}
```

---

#### `POST /auth/password-reset/confirm`

Confirma el restablecimiento de contraseña con el token recibido por email.

**Pública** — no requiere token.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `token` | string | ✅ | Token de recuperación recibido por email |
| `password` | string | ✅ | Nueva contraseña (cumple la política) |

**Respuesta `200 OK`:**

```json
{ "message": "Contraseña actualizada correctamente. Ya puedes iniciar sesión." }
```

`422 Unprocessable Entity`
```json
{ "error": "Token inválido o expirado" }
{ "error": "Los campos token y password son requeridos" }
```

---

### Aliados

Gestión del perfil completo de los aliados (laboratorios externos). Incluye NIT, dirección, email y logotipo.

---

#### `GET /aliados`

Lista todos los aliados con su perfil completo.

**Roles:** todos.

**Respuesta `200 OK`:**
```json
[
  {
    "id": "ALIADO-001",
    "nombre": "Laboratorio Clínico Norte",
    "nit": "900123456-7",
    "direccion": "Calle 100 # 15-20, Bogotá",
    "email": "contacto@labnorte.com",
    "logoPath": "/storage/logos/aliado-001_logo.png",
    "activo": true
  }
]
```

---

#### `POST /aliados`

Crea un nuevo aliado.

**Roles:** `admin`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `id` | string | ✅ | Identificador único del aliado (ej: `ALIADO-003`) |
| `nombre` | string | ✅ | Nombre del aliado |
| `nit` | string | ❌ | NIT o número de identificación tributaria |
| `direccion` | string | ❌ | Dirección física |
| `email` | string | ❌ | Correo de contacto |
| `activo` | bool | ❌ | Default `true` |

**Respuesta `201 Created`:**
```json
{ "id": "ALIADO-003", "message": "Aliado creado" }
```

---

#### `GET /aliados/{id}`

Retorna el perfil completo de un aliado.

**Roles:** todos.

**Respuesta `200 OK`:** mismo formato que el listado.

`404 Not Found`
```json
{ "error": "Aliado no encontrado: ALIADO-999" }
```

---

#### `PUT /aliados/{id}`

Actualiza el perfil de un aliado.

**Roles:** `admin`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `nombre` | string | ✅ | Nombre del aliado |
| `nit` | string | ❌ | NIT |
| `direccion` | string | ❌ | Dirección física |
| `email` | string | ❌ | Correo de contacto |
| `activo` | bool | ❌ | Estado activo |

**Respuesta `200 OK`:**
```json
{
  "message": "Aliado actualizado",
  "aliado": {
    "id": "ALIADO-001",
    "nombre": "Laboratorio Clínico Norte S.A.",
    "nit": "900123456-7",
    "direccion": "Calle 100 # 15-20, Bogotá",
    "email": "contacto@labnorte.com",
    "logoPath": null,
    "activo": true
  }
}
```

---

#### `POST /aliados/{id}/logo`

Sube o reemplaza el logotipo del aliado. Acepta PNG o JPG, máximo 2 MB.

**Roles:** `admin`.

**Content-Type:** `multipart/form-data`

| Campo | Tipo | Requerido |
|---|---|:---:|
| `logo` | file (PNG/JPG) | ✅ |

**Respuesta `200 OK`:**
```json
{
  "message": "Logo actualizado correctamente",
  "logoPath": "/storage/logos/aliado-001_logo.png"
}
```

---

### Bacteriólogos

---

#### `GET /aliados/{aliadoId}/bacteriologos`

Lista los bacteriólogos de un aliado.

**Roles:** todos.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `activo` | `0`\|`1` | `0` incluye también inactivos (default: `1`) |

**Respuesta `200 OK`:**
```json
[
  {
    "id": 1,
    "aliadoId": "ALIADO-001",
    "tipoDocumento": "CC",
    "identificacion": "52001234",
    "nombre": "Dra. María González",
    "tarjetaProfesional": "TP-12345",
    "universidad": "Universidad Nacional de Colombia",
    "firmaPath": "/storage/firmas/firma_bact_1.png",
    "activo": true
  }
]
```

---

#### `POST /aliados/{aliadoId}/bacteriologos`

Crea un nuevo bacteriólogo asociado al aliado.

**Roles:** `admin`, `lab_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipoDocumento` | string | ✅ | `CC`, `TP`, etc. |
| `identificacion` | string | ✅ | Número de documento |
| `nombre` | string | ✅ | Nombre completo |
| `tarjetaProfesional` | string | ❌ | Número de tarjeta profesional |
| `universidad` | string | ❌ | Universidad de egreso |

**Respuesta `201 Created`:**
```json
{
  "id": 1,
  "aliadoId": "ALIADO-001",
  "tipoDocumento": "CC",
  "identificacion": "52001234",
  "nombre": "Dra. María González",
  "tarjetaProfesional": "TP-12345",
  "universidad": "Universidad Nacional de Colombia",
  "firmaPath": null,
  "activo": true
}
```

---

#### `GET /bacteriologos/{id}`

Detalle de un bacteriólogo.

**Roles:** todos.

---

#### `PUT /bacteriologos/{id}`

Actualiza los datos de un bacteriólogo.

**Roles:** `admin`, `lab_operator`.

**Request body:** mismos campos que `POST` más `activo` (bool).

**Respuesta `200 OK`:**
```json
{ "message": "Bacteriólogo actualizado" }
```

---

#### `DELETE /bacteriologos/{id}`

Desactiva un bacteriólogo (soft delete).

**Roles:** `admin`, `lab_operator`.

**Respuesta `200 OK`:**
```json
{ "message": "Bacteriólogo desactivado" }
```

---

#### `POST /bacteriologos/{id}/firma`

Sube o reemplaza la firma digital del bacteriólogo. Aparece en el PDF del informe.

**Roles:** `admin`, `lab_operator`.

**Content-Type:** `multipart/form-data`

| Campo | Tipo | Requerido |
|---|---|:---:|
| `firma` | file (PNG/JPG, máx 2 MB) | ✅ |

**Respuesta `200 OK`:**
```json
{
  "message": "Firma actualizada correctamente",
  "firmaPath": "/storage/firmas/firma_bact_1.png"
}
```

---

### Centros de salud

---

#### `GET /health-centers`

Lista los centros de salud.

**Roles:** todos.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `activo` | `0`\|`1` | `0` incluye inactivos (default: `1`) |
| `aliado_id` | string | Filtra solo los centros del aliado indicado |

**Respuesta `200 OK`:**
```json
[
  {
    "id": 1,
    "nombre": "Clínica Norte S.A.",
    "ciudad": "Bogotá",
    "direccion": "Calle 100 # 15-20",
    "telefono": "601-7001000",
    "activo": true
  }
]
```

---

#### `POST /health-centers`

Crea un nuevo centro de salud.

**Roles:** `admin`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `nombre` | string | ✅ | Nombre del centro |
| `ciudad` | string | ❌ | Ciudad |
| `direccion` | string | ❌ | Dirección física |
| `telefono` | string | ❌ | Teléfono de contacto |
| `activo` | bool | ❌ | Default `true` |

**Respuesta `201 Created`:**
```json
{ "id": 5, "message": "Centro de salud creado" }
```

---

#### `PUT /health-centers/{id}`

Actualiza un centro de salud.

**Roles:** `admin`.

**Request body:** mismos campos que `POST /health-centers`.

**Respuesta `200 OK`:**
```json
{ "message": "Centro de salud actualizado" }
```

---

#### `POST /health-centers/{id}/aliados/{aliadoId}`

Asocia un aliado a un centro de salud.

**Roles:** `admin`. Sin body.

**Respuesta `200 OK`:**
```json
{ "message": "Aliado asociado al centro de salud" }
```

---

#### `DELETE /health-centers/{id}/aliados/{aliadoId}`

Desasocia un aliado de un centro de salud.

**Roles:** `admin`.

**Respuesta `200 OK`:**
```json
{ "message": "Aliado desasociado del centro de salud" }
```

---

### Pacientes

Los pacientes se crean automáticamente al registrar una orden (identificados por `tipoDeDocumento` + `identificacion`). También pueden gestionarse directamente desde estos endpoints.

---

#### `GET /patients`

Lista pacientes con búsqueda opcional.

**Roles:** `admin`, `lab_operator`.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `q` | string | Búsqueda parcial por nombre o identificación |
| `page` | int | Número de página (default: `1`) |
| `limit` | int | Resultados por página, máximo `100` (default: `20`) |

**Respuesta `200 OK`:**
```json
{
  "data": [
    {
      "id": 1,
      "tipoDocumento": "CC",
      "identificacion": "1020304050",
      "nombre": "Carlos Andrés Pérez López",
      "sexo": "M",
      "fechaNacimiento": "1985-03-15",
      "email": "carlos.perez@correo.com",
      "telefono": "3001234567"
    }
  ],
  "pagination": {
    "total": 8,
    "page": 1,
    "limit": 20,
    "total_pages": 1
  }
}
```

---

#### `POST /patients`

Crea un paciente directamente.

**Roles:** `admin`, `lab_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipoDocumento` | string | ✅ | `CC`, `TI`, `PA`, `CE`, etc. |
| `identificacion` | string | ✅ | Número de documento |
| `nombre` | string | ✅ | Nombre completo |
| `sexo` | string | ✅ | `M` o `F` |
| `fechaNacimiento` | string | ✅ | Formato `YYYY-MM-DD` |
| `email` | string | ❌ | Correo (requerido para portal de pacientes) |
| `telefono` | string | ❌ | Teléfono de contacto |

**Respuesta `201 Created`:**
```json
{
  "id": 1,
  "tipoDocumento": "CC",
  "identificacion": "1020304050",
  "nombre": "Carlos Andrés Pérez López",
  "sexo": "M",
  "fechaNacimiento": "1985-03-15",
  "email": "carlos.perez@correo.com",
  "telefono": "3001234567"
}
```

---

#### `GET /patients/{id}`

Detalle de un paciente con historial de órdenes.

**Roles:** `admin`, `lab_operator`.

**Respuesta `200 OK`:**
```json
{
  "id": 1,
  "tipoDocumento": "CC",
  "identificacion": "1020304050",
  "nombre": "Carlos Andrés Pérez López",
  "sexo": "M",
  "fechaNacimiento": "1985-03-15",
  "email": "carlos.perez@correo.com",
  "telefono": "3001234567",
  "totalOrdenes": 2,
  "ordenes": [
    {
      "idSolicitudKey": "SOL-2025-0001",
      "fechaDeLaOrden": "2025-04-10 08:30:00",
      "estadoDeLaOrden": "completed",
      "idAliado": "ALIADO-001",
      "centroDeSalud": "Clínica Norte S.A."
    }
  ]
}
```

---

#### `PUT /patients/{id}`

Actualiza los datos de un paciente. Solo se modifican los campos enviados.

**Roles:** `admin`, `lab_operator`.

**Request body:** todos los campos son opcionales (mismos que `POST`). Enviar `""` para borrar `email` o `telefono`.

**Respuesta `200 OK`:** mismo formato que `GET /patients/{id}` sin el historial.

---

### Médicos

Catálogo de médicos que ordenan exámenes. Pueden vincularse opcionalmente a un usuario del sistema con rol `medico`.

---

#### `GET /medicos`

Lista médicos.

**Roles:** todos.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `q` | string | Búsqueda parcial por nombre o identificación |
| `activo` | `0`\|`1` | `0` incluye inactivos (default: `1`) |

**Respuesta `200 OK`:**
```json
[
  {
    "id": 1,
    "tipoDocumento": "CC",
    "identificacion": "12345678",
    "nombre": "Dr. Juan Rodríguez",
    "especialidad": "Medicina General",
    "registroMedico": "RM-12345",
    "userId": 3,
    "activo": true
  }
]
```

---

#### `POST /medicos`

Crea un nuevo médico.

**Roles:** `admin`, `lab_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipoDocumento` | string | ✅ | Tipo de documento |
| `identificacion` | string | ✅ | Número de documento |
| `nombre` | string | ✅ | Nombre completo |
| `especialidad` | string | ❌ | Especialidad médica |
| `registroMedico` | string | ❌ | Número de registro profesional |
| `userId` | int | ❌ | ID de usuario del sistema a vincular (único por médico, rol `medico`) |

**Respuesta `201 Created`:**
```json
{
  "id": 1,
  "tipoDocumento": "CC",
  "identificacion": "12345678",
  "nombre": "Dr. Juan Rodríguez",
  "especialidad": "Medicina General",
  "registroMedico": "RM-12345",
  "userId": 3,
  "activo": true
}
```

---

#### `GET /medicos/{id}`

Detalle de un médico. **Roles:** todos.

---

#### `PUT /medicos/{id}`

Actualiza un médico. **Roles:** `admin`, `lab_operator`.

**Request body:** mismos campos que `POST` (todos opcionales).

**Respuesta `200 OK`:** mismo formato que `GET /medicos/{id}`.

---

#### `DELETE /medicos/{id}`

Desactiva un médico (soft delete). **Roles:** `admin`, `lab_operator`.

**Respuesta `200 OK`:**
```json
{ "message": "Médico desactivado" }
```

---

### Órdenes de laboratorio

---

#### `GET /orders`

Lista órdenes con filtros opcionales y paginación, ordenadas por `fecha_orden` descendente.

**Roles:** todos.  
**Restricciones automáticas:**
- `aliado_operator` / `viewer` → solo órdenes de sus aliados asignados
- `medico` → solo órdenes de sus centros de salud asignados (`health_centers` del JWT)
- `admin` / `lab_operator` → sin restricción

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `estado` | string | `pending`, `sent` o `completed` |
| `fecha_desde` | string | Formato `YYYY-MM-DD` |
| `fecha_hasta` | string | Formato `YYYY-MM-DD` |
| `cups` | string | Filtra órdenes que contengan ese código CUPS |
| `page` | int | Default: `1` |
| `limit` | int | Máximo `100` (default: `20`) |

**Respuesta `200 OK`:**

```json
{
  "data": [
    {
      "idSolicitudKey": "SOL-2025-0003",
      "idAdmision": "ADM-10003",
      "nombreDelPaciente": "Luis Eduardo Torres Vargas",
      "identificacion": "3344556677",
      "tipoDocumento": "CC",
      "sexo": "M",
      "centroDeSalud": "Hospital Central",
      "medicoQueOrdena": "Dr. Pedro Sánchez",
      "idAliado": "ALIADO-001",
      "fechaDeLaOrden": "2025-04-20 07:00:00",
      "fechaEnvio": "2025-04-20 07:45:00",
      "estadoDeLaOrden": "completed",
      "porcEjecucion": 100
    }
  ],
  "pagination": {
    "total": 12,
    "page": 1,
    "limit": 20,
    "total_pages": 1
  }
}
```

---

#### `POST /orders`

Crea una nueva orden de laboratorio.

**Roles:** `admin`, `lab_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `idSolicitudKey` | string | ✅ | Identificador único de la solicitud |
| `idAdmision` | string | ✅ | ID de admisión |
| `idAtencion` | string | ❌ | ID de atención |
| `tipoDeDocumento` | string | ✅ | `CC`, `TI`, `PA`, `CE`, etc. |
| `identificacion` | string | ✅ | Número de documento del paciente |
| `nombreDelPaciente` | string | ✅ | Nombre completo |
| `sexo` | string | ✅ | `M` o `F` |
| `fechaDeNacimiento` | string | ✅ | `YYYY-MM-DD` |
| `centroDeSalud` | string | ✅ | Nombre del centro de salud |
| `fechaDeLaOrden` | string | ✅ | `YYYY-MM-DD HH:MM:SS` |
| `medicoQueOrdena` | string | ✅ | Nombre del médico ordenante |
| `numeroDeAutorizacion` | string | ❌ | Número de autorización del seguro |
| `idAliado` | string | ❌ | ID del laboratorio aliado destino |
| `healthCenterId` | int | ❌ | ID del centro de salud del catálogo (prioridad sobre nombre en `centroDeSalud`) |
| `medicoId` | int | ❌ | ID del médico del catálogo (prioridad sobre documento) |
| `tipoDocumentoMedico` | string | ❌ | Tipo de documento del médico (alternativa a `medicoId`) |
| `identificacionMedico` | string | ❌ | Número de documento del médico (alternativa a `medicoId`) |
| `porcEjecucion` | string | ❌ | Porcentaje inicial (default `"0"`) |
| `detalles` | array | ✅ | Mínimo 1 examen |

**Estructura de cada detalle:**

| Campo | Tipo | Requerido |
|---|---|:---:|
| `cups` | string | ✅ |
| `nombreDelLaboratorio` | string | ✅ |
| `fechaTomaMuestra` | string\|null | ❌ |
| `metodo` | string\|null | ❌ |
| `reactivo` | string\|null | ❌ |
| `invima` | string\|null | ❌ |
| `estadoDelResultado` | string\|null | ❌ |
| `fechaResultado` | string\|null | ❌ |
| `tipoIdentificacionDelBacteriologo` | string\|null | ❌ |
| `identificacionDelBacteriologo` | string\|null | ❌ |

**Ejemplo:**

```json
{
  "idSolicitudKey": "SOL-2025-0099",
  "idAdmision": "ADM-10099",
  "tipoDeDocumento": "CC",
  "identificacion": "1234567890",
  "nombreDelPaciente": "Ana Lucía Bermúdez",
  "sexo": "F",
  "fechaDeNacimiento": "1992-05-20",
  "centroDeSalud": "Clínica Norte S.A.",
  "fechaDeLaOrden": "2025-05-02 09:00:00",
  "medicoQueOrdena": "Dr. Juan Rodríguez",
  "idAliado": "ALIADO-001",
  "healthCenterId": 1,
  "medicoId": 1,
  "detalles": [
    { "cups": "903820", "nombreDelLaboratorio": "Hemograma Completo" },
    { "cups": "904010", "nombreDelLaboratorio": "Glucosa en Ayunas" }
  ]
}
```

**Respuesta `201 Created`:**

```json
{
  "idSolicitudKey": "SOL-2025-0099",
  "estadoDeLaOrden": "pending",
  "porcEjecucion": 0,
  "medicoId": 1,
  "detalles": 2
}
```

---

#### `GET /orders/{id}`

Retorna una orden con todos sus detalles.

**Roles:** todos.

**Respuesta `200 OK`:**

```json
{
  "idSolicitudKey": "SOL-2025-0001",
  "idAdmision": "ADM-10001",
  "nombreDelPaciente": "Carlos Andrés Pérez López",
  "estadoDeLaOrden": "completed",
  "porcEjecucion": 100,
  "fechaEnvio": "2025-04-10 09:00:00",
  "medicoId": 1,
  "medicoQueOrdena": "Dr. Juan Rodríguez",
  "detalles": [
    {
      "cups": "903820",
      "nombreDelLaboratorio": "Hemograma Completo",
      "fechaTomaMuestra": "2025-04-10 09:15:00",
      "metodo": "Automatizado",
      "reactivo": "Sysmex XN-1000",
      "invima": "INVIMA2020M-0001234",
      "estadoDelResultado": "FINAL",
      "fechaResultado": "2025-04-10 14:00:00",
      "tipoIdentificacionDelBacteriologo": "CC",
      "identificacionDelBacteriologo": "52001234"
    }
  ]
}
```

**Estados posibles:**

| Estado | Descripción |
|---|---|
| `pending` | Creada, pendiente de envío |
| `sent` | Enviada al laboratorio externo |
| `completed` | Al menos un resultado registrado |

---

#### `POST /orders/{id}/send`

Envía la orden al laboratorio aliado externo.

**Roles:** `admin`, `lab_operator`.

**Sin body.**

**Respuesta `200 OK`:**

```json
{
  "idSolicitudKey": "SOL-2025-0004",
  "estadoDeLaOrden": "sent",
  "fechaEnvio": "2025-04-28T09:30:00+00:00"
}
```

---

### Servicios por aliado

---

#### `GET /aliados/{aliadoId}/orders/pending`

Retorna todas las órdenes en estado `pending` del aliado, ordenadas por `fecha_orden` descendente.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Respuesta `200 OK`:**

```json
{
  "aliadoId": "ALIADO-001",
  "total": 2,
  "orders": [
    {
      "idSolicitudKey": "SOL-2025-0007",
      "idAdmision": "ADM-10007",
      "nombreDelPaciente": "Andrés Felipe Vargas Ospina",
      "identificacion": "4455667788",
      "tipoDocumento": "CC",
      "centroDeSalud": "Hospital Central",
      "medicoQueOrdena": "Dr. Pedro Sánchez",
      "fechaDeLaOrden": "2025-05-01 07:30:00",
      "estadoDeLaOrden": "pending"
    }
  ]
}
```

---

#### `POST /aliados/{aliadoId}/orders/mark-sent`

Marca como `sent` una lista de órdenes del aliado.

**Roles:** `admin`, `lab_operator`.

**Request body:**

| Campo | Tipo | Requerido |
|---|---|:---:|
| `orders` | string[] | ✅ |

**Ejemplo:**
```json
{ "orders": ["SOL-2025-0007", "SOL-2025-0011"] }
```

**Respuesta `200 OK`:**

```json
{
  "aliadoId": "ALIADO-001",
  "totalRecibidas": 2,
  "totalActualizadas": 1,
  "totalOmitidas": 1,
  "actualizadas": ["SOL-2025-0007"],
  "omitidas": {
    "SOL-2025-0011": "La orden no pertenece al aliado"
  }
}
```

---

### Resultados de laboratorio

---

#### `POST /results`

Registra el resultado de un examen. La orden pasa a estado `completed` con `porcEjecucion = 100`.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `idSolicitudKey` | string | ✅ | ID de la orden |
| `cups` | string | ✅ | Código CUPS del examen |
| `values` | object | ✅ | Valores del resultado. Debe incluir clave `resultado` |
| `values.resultado` | string | ✅ | Valor principal |
| `attachmentPath` | string\|null | ❌ | Ruta a archivo adjunto |
| `bacteriologoId` | int | ❌ | ID del bacteriólogo que procesó el examen |

**Ejemplo — resultado numérico:**
```json
{
  "idSolicitudKey": "SOL-2025-0004",
  "cups": "903820",
  "values": {
    "resultado": "Normal",
    "leucocitos": "7.2 10³/µL",
    "hemoglobina": "14.5 g/dL",
    "hematocrito": "43.2 %"
  }
}
```

**Ejemplo — resultado booleano:**
```json
{
  "idSolicitudKey": "SOL-2025-0007",
  "cups": "904500",
  "values": { "pcr_cualitativa": "reactivo" }
}
```

**Respuesta `201 Created`:**
```json
{
  "idSolicitudKey": "SOL-2025-0004",
  "cups": "903820",
  "message": "Resultado registrado correctamente"
}
```

---

#### `GET /orders/{id}/results`

Retorna los resultados estructurados de una orden con flags de interpretación clínica.

**Roles:** todos.

**Respuesta `200 OK`:**

```json
{
  "idSolicitudKey": "SOL-2025-0001",
  "resultados": [
    {
      "labResultId": 1,
      "cups": "903820",
      "bacteriologo": {
        "id": 1,
        "nombre": "Dra. María González",
        "tipoDocumento": "CC",
        "identificacion": "52001234",
        "tarjetaProfesional": "TP-12345",
        "firmaPath": "/storage/firmas/firma_bact_1.png"
      },
      "valuesJson": { "hb": "14.5", "wbc": "7.2" },
      "valoresEstructurados": [
        {
          "codigo": "wbc",
          "nombre": "Leucocitos",
          "tipoResultado": "numerico",
          "valorNumerico": 7.2,
          "valorTexto": null,
          "valorBooleano": null,
          "reactivo": null,
          "unidad": "10³/µL",
          "valorMinRef": 4.5,
          "valorMaxRef": 11.0,
          "flag": "normal"
        }
      ],
      "receivedAt": "2025-04-10 14:05:00"
    }
  ]
}
```

**Flags posibles:** `normal`, `alto`, `bajo`, `critico`, `indeterminado`, `positivo`, `negativo`, `reactivo`, `no_reactivo`.

---

#### `POST /orders/{id}/results/attach-pdf`

Adjunta un PDF externo como informe oficial de la orden.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Content-Type:** `multipart/form-data`

| Campo | Tipo | Requerido |
|---|---|:---:|
| `pdf` | file (PDF, máx 20 MB) | ✅ |

**Respuesta `200 OK`:**
```json
{
  "message": "PDF adjuntado correctamente. Se usará como informe oficial.",
  "idSolicitudKey": "SOL-2025-0001",
  "attachmentPath": "/storage/pdfs/resultado_SOL-2025-0001_adjunto.pdf"
}
```

---

#### `GET /orders/{id}/results/pdf`

Descarga el PDF del informe de resultados. Prioriza el PDF adjuntado manualmente; si no existe, genera uno automáticamente.

**Roles:** todos.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `regenerate` | `1` | Fuerza la regeneración aunque exista PDF adjunto |

**Respuesta `200 OK`:**
- `Content-Type: application/pdf`
- `Content-Disposition: attachment; filename="resultado_SOL-2025-0001.pdf"`

---

#### `POST /orders/{id}/results/send-email`

Genera el PDF (si no existe) y lo envía por correo al paciente o a la dirección indicada.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Request body (todo opcional):**

| Campo | Tipo | Descripción |
|---|---|---|
| `email` | string | Destinatario. Si se omite usa `patients.email` |
| `mensaje` | string | Texto personalizado para el cuerpo del correo |

**Respuesta `200 OK`:**
```json
{
  "idSolicitudKey": "SOL-2025-0001",
  "emailDestino": "carlos.perez@correo.com",
  "pdfPath": "/storage/pdfs/resultado_SOL-2025-0001.pdf",
  "estado": "enviado",
  "message": "Correo enviado correctamente"
}
```

---

### Catálogo de exámenes

---

#### `GET /exam-types`

Lista todos los tipos de examen.

**Roles:** todos.

**Query params:** `activo` (`0`|`1`, default `1`).

**Respuesta `200 OK`:**
```json
[
  { "cups": "903820", "nombre": "Hemograma Completo", "descripcion": "CBC", "activo": true },
  { "cups": "904010", "nombre": "Glucosa en Ayunas",  "descripcion": null,  "activo": true }
]
```

---

#### `POST /exam-types`

Crea un tipo de examen. **Roles:** `admin`.

| Campo | Tipo | Requerido |
|---|---|:---:|
| `cups` | string | ✅ |
| `nombre` | string | ✅ |
| `descripcion` | string | ❌ |
| `activo` | bool | ❌ |

**Respuesta `201 Created`:**
```json
{ "message": "Tipo de examen creado" }
```

---

#### `PUT /exam-types/{cups}`

Actualiza un tipo de examen. **Roles:** `admin`.

---

#### `GET /exam-types/{cups}/parameters`

Lista los parámetros de referencia de un examen. **Roles:** todos.

**Respuesta `200 OK`:**
```json
[
  {
    "id": 1,
    "codigo": "wbc",
    "nombre": "Leucocitos",
    "unidad": "10³/µL",
    "valorMinRef": 4.5,
    "valorMaxRef": 11.0,
    "tipoResultado": "numerico",
    "etiquetaBooleano": null,
    "sexo": "*",
    "edadMin": null,
    "edadMax": null,
    "obligatorio": true,
    "orden": 1,
    "activo": true
  }
]
```

---

#### `POST /exam-types/{cups}/parameters`

Agrega un parámetro al tipo de examen. **Roles:** `admin`.

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `codigo` | string | ✅ | Clave en `values` (ej: `wbc`) |
| `nombre` | string | ✅ | Nombre legible |
| `unidad` | string | ❌ | Unidad de medida |
| `valorMinRef` | float | ❌ | Límite inferior del rango normal |
| `valorMaxRef` | float | ❌ | Límite superior del rango normal |
| `sexo` | string | ❌ | `M`, `F` o `*` (default: `*`) |
| `edadMin` | int | ❌ | Edad mínima en años |
| `edadMax` | int | ❌ | Edad máxima en años |
| `obligatorio` | bool | ❌ | Default: `false` |
| `orden` | int | ❌ | Posición en la presentación (default: `0`) |
| `tipoResultado` | string | ❌ | `numerico`, `texto` o `booleano` (default: `numerico`) |
| `etiquetaBooleano` | string | ❌ | Requerido si `tipoResultado=booleano`: `normal_alto`, `positivo_negativo`, `reactivo_no_reactivo` |

**Respuesta `201 Created`:**
```json
{ "id": 25, "message": "Parámetro creado" }
```

---

#### `PUT /exam-types/{cups}/parameters/{id}`

Actualiza un parámetro. **Roles:** `admin`.

---

#### `DELETE /exam-types/{cups}/parameters/{id}`

Desactiva un parámetro (soft delete). **Roles:** `admin`.

---

#### `GET /exam-types/{cups}/parameters/{parameterId}/ranges`

Lista los rangos por reactivo de un parámetro. **Roles:** todos.

**Respuesta `200 OK`:**
```json
[
  {
    "id": 1,
    "reactivo": "Sysmex XN-1000",
    "valorMinRef": 13.5,
    "valorMaxRef": 17.5,
    "sexo": "M",
    "edadMin": null,
    "edadMax": null,
    "activo": true
  }
]
```

---

#### `POST /exam-types/{cups}/parameters/{parameterId}/ranges`

Agrega un rango por reactivo (solo para parámetros `numerico`). **Roles:** `admin`.

| Campo | Tipo | Requerido |
|---|---|:---:|
| `reactivo` | string | ✅ |
| `valorMinRef` | float | ❌ |
| `valorMaxRef` | float | ❌ |
| `sexo` | string | ❌ |
| `edadMin` | int | ❌ |
| `edadMax` | int | ❌ |

**Respuesta `201 Created`:**
```json
{ "id": 1, "message": "Rango creado" }
```

---

#### `PUT /exam-types/{cups}/parameters/{parameterId}/ranges/{rangeId}`

Actualiza un rango. **Roles:** `admin`.

---

#### `DELETE /exam-types/{cups}/parameters/{parameterId}/ranges/{rangeId}`

Desactiva un rango. **Roles:** `admin`.

---

### Portal de pacientes

Acceso passwordless para pacientes mediante OTP de dos pasos. El JWT de paciente tiene issuer `api-clinical-lab-patient` y validez de **1 hora**. No requiere JWT de staff.

---

#### `POST /patient-portal/request-access`

El paciente solicita un código OTP de 6 dígitos al email registrado.

**Pública.**

**Request body:**

| Campo | Tipo | Requerido |
|---|---|:---:|
| `tipoDocumento` | string | ✅ |
| `identificacion` | string | ✅ |

**Respuesta `200 OK`** (siempre, para no revelar si el paciente existe):
```json
{ "message": "Si el documento está registrado, recibirás un código en tu correo." }
```

> El OTP tiene validez de **15 minutos**. Al solicitar uno nuevo, el anterior queda invalidado.

---

#### `POST /patient-portal/verify`

El paciente valida el OTP y recibe un JWT de paciente.

**Pública.**

**Request body:**

| Campo | Tipo | Requerido |
|---|---|:---:|
| `tipoDocumento` | string | ✅ |
| `identificacion` | string | ✅ |
| `codigo` | string | ✅ |

**Respuesta `200 OK`:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "patient": {
    "id": 1,
    "nombre": "Carlos Andrés Pérez López",
    "tipoDocumento": "CC",
    "identificacion": "1020304050"
  },
  "expiresIn": 3600
}
```

### Payload del JWT de paciente

```json
{
  "iss": "api-clinical-lab-patient",
  "sub": 1,
  "nombre": "Carlos Andrés Pérez López",
  "tipoDocumento": "CC",
  "identificacion": "1020304050",
  "iat": 1714204800,
  "exp": 1714208400
}
```

---

#### `GET /patient-portal/results`

Retorna todas las órdenes completadas del paciente autenticado.

**Requiere:** `Authorization: Bearer <patient-jwt>`

**Respuesta `200 OK`:**
```json
{
  "patient": {
    "nombre": "Carlos Andrés Pérez López",
    "tipoDocumento": "CC",
    "identificacion": "1020304050"
  },
  "ordenes": [
    {
      "idSolicitudKey": "SOL-2025-0001",
      "fechaDeLaOrden": "2025-04-10 08:30:00",
      "estadoDeLaOrden": "completed",
      "centroDeSalud": "Clínica Norte S.A.",
      "medicoQueOrdena": "Dr. Juan Rodríguez"
    }
  ],
  "total": 1
}
```

---

#### `GET /patient-portal/results/{idSolicitudKey}/pdf`

Descarga el PDF de una orden específica del paciente. Verifica que la orden le pertenezca.

**Requiere:** `Authorization: Bearer <patient-jwt>`

**Respuesta `200 OK`:** `Content-Type: application/pdf`

---

## Modelos de datos

### Esquema de base de datos

```
roles                 → id, name
aliados               → id, nombre, nit, direccion, email, logo_path, activo, created_at
users                 → id, username, email, password_hash, role_id, activo,
                        failed_login_attempts, locked_until,
                        password_reset_token, password_reset_expires,
                        created_at, updated_at
user_aliado           → user_id, aliado_id  (N:M)
user_health_center    → user_id, health_center_id  (N:M — asignación de centros a usuarios, rol medico)

bacteriologos         → id, aliado_id (FK), tipo_documento, identificacion (UNIQUE),
                        nombre, tarjeta_profesional, universidad, firma_path, activo,
                        created_at, updated_at

health_centers        → id, nombre, ciudad, direccion, telefono, activo, created_at, updated_at
aliado_health_center  → aliado_id (FK), health_center_id (FK)  (N:M)

patients              → id, tipo_documento, identificacion (UNIQUE con tipo_doc),
                        nombre, sexo, fecha_nacimiento, email, telefono, created_at, updated_at

medicos               → id, tipo_documento, identificacion (UNIQUE con tipo_doc),
                        nombre, especialidad, registro_medico,
                        user_id (FK → users, UNIQUE, nullable),
                        activo, created_at, updated_at

lab_orders            → id_solicitud_key (PK), id_admision, id_atencion, tipo_documento,
                        identificacion, nombre_paciente, sexo, fecha_nacimiento,
                        centro_salud, fecha_orden, medico_ordena, numero_autorizacion,
                        id_aliado, fecha_envio, porc_ejecucion, estado_orden,
                        patient_id (FK → patients),
                        health_center_id (FK → health_centers),
                        medico_id (FK → medicos, nullable),
                        created_at, updated_at

lab_order_details     → id, id_solicitud_key (FK), id_admision, cups,
                        nombre_laboratorio, fecha_toma_muestra, metodo, reactivo,
                        invima, estado_resultado, fecha_resultado,
                        tipo_id_bacteriologo, id_bacteriologo, created_at

lab_results           → id, id_solicitud_key (FK), cups, values_json,
                        attachment_path, bacteriologo_id (FK → bacteriologos),
                        received_at, created_at

exam_types            → cups (PK), nombre, descripcion, activo, created_at, updated_at

exam_parameters       → id, cups (FK), codigo, nombre, unidad,
                        valor_min_ref, valor_max_ref,
                        tipo_resultado (numerico|texto|booleano),
                        etiqueta_booleano, comentario,
                        sexo, edad_min, edad_max, obligatorio, orden, activo,
                        created_at, updated_at

exam_parameter_ranges → id, parameter_id (FK), reactivo, valor_min_ref, valor_max_ref,
                        sexo, edad_min, edad_max, activo, created_at, updated_at

lab_result_values     → id, lab_result_id (FK), parameter_id (FK),
                        valor_numerico, valor_texto, valor_booleano, reactivo, flag,
                        created_at

result_email_log      → id, id_solicitud_key (FK), email_destino,
                        estado (enviado|error), error_mensaje, enviado_at

patient_access_tokens → id, patient_id (FK → patients), codigo_hash,
                        expires_at, usado, created_at

antibiogramas         → id, lab_result_id (FK), bacteria_aislada, gram,
                        tiempo_incubacion, gram_orina, observaciones, created_at, updated_at

antibiograma_items    → id, antibiograma_id (FK), antibiotico, cim,
                        sensibilidad (S|I|R), metodo, created_at
```

Script DDL consolidado: `database/dbprod.sql`

### Ciclo de vida de una orden

```
[POST /orders]                              → estado: pending
[POST /orders/{id}/send]                    → estado: sent
[POST /aliados/{id}/orders/mark-sent]       → estado: sent  (bulk, por aliado)
[POST /results]                             → estado: completed  (porc_ejecucion = 100)
```

---

## Flujo completo

```
1.  POST /auth/login                                    → obtener JWT
2.  GET  /auth/password-policy                          → consultar requisitos de contraseña
3.  POST /auth/register                                 → (admin) crear usuarios del sistema
                                                          • role: medico + health_centers: [1,3]
                                                          • role: aliado_operator + aliados: ["ALIADO-001"]
4.  PUT  /aliados/{id}                                  → (admin) completar perfil del aliado
5.  POST /aliados/{id}/logo                             → (admin) subir logotipo del aliado
6.  POST /aliados/{id}/bacteriologos                    → (admin/lab) registrar bacteriólogos
7.  POST /bacteriologos/{id}/firma                      → (admin/lab) subir firma digital
8.  POST /medicos                                       → (admin/lab) registrar médico (opcional: userId)
9.  POST /health-centers                                → (admin) crear centros de salud
10. POST /health-centers/{id}/aliados/{aliadoId}        → (admin) asociar aliado a centro
11. POST /orders                                        → crear orden (healthCenterId, medicoId opcionales)
12. GET  /patients?q=<nombre>                           → buscar paciente creado automáticamente
13. GET  /orders?estado=pending                         → listar órdenes pendientes
        [medico] → solo ve órdenes de sus health_centers
        [aliado_operator] → solo ve órdenes de sus aliados
14. GET  /aliados/{id}/orders/pending                   → pendientes de un aliado específico
15. GET  /orders/{id}                                   → ver detalle (incluye medicoId)
16. POST /orders/{id}/send                              → enviar al laboratorio externo
17. POST /aliados/{id}/orders/mark-sent                 → marcar múltiples como enviadas
18. POST /results                                       → registrar resultado (bacteriologoId opcional)
19. GET  /orders/{id}/results                           → resultados estructurados con flags
20. GET  /orders/{id}/results/pdf                       → descargar PDF con firma del bacteriólogo
21. POST /orders/{id}/results/send-email                → enviar informe PDF por correo
22. GET  /orders?estado=completed                       → confirmar órdenes completadas

--- Flujo portal de pacientes (passwordless) ---
23. POST /patient-portal/request-access                 → solicitar OTP
24. POST /patient-portal/verify                         → verificar OTP → JWT de paciente
25. GET  /patient-portal/results                        → paciente consulta sus órdenes completadas
26. GET  /patient-portal/results/{idSolicitudKey}/pdf   → paciente descarga PDF

--- Recuperación de contraseña ---
27. POST /auth/password-reset/request                   → solicitar enlace de recuperación
28. POST /auth/password-reset/confirm                   → confirmar nueva contraseña con token
```

---

## Configuración de correo

Para habilitar el envío de resultados por email se requieren estas variables de entorno:

| Variable | Descripción | Ejemplo |
|---|---|---|
| `MAIL_HOST` | Servidor SMTP | `smtp.gmail.com` |
| `MAIL_PORT` | Puerto SMTP | `587` |
| `MAIL_USERNAME` | Usuario SMTP (correo remitente) | `lab@clinica.com` |
| `MAIL_PASSWORD` | Contraseña o App Password | `xxxx xxxx xxxx xxxx` |
| `MAIL_FROM_NAME` | Nombre del remitente | `Laboratorio Clínico` |

```yaml
# docker-compose.yml → services.app.environment
MAIL_HOST: smtp.gmail.com
MAIL_PORT: 587
MAIL_USERNAME: lab@clinica.com
MAIL_PASSWORD: tu_app_password
MAIL_FROM_NAME: "Laboratorio Clínico"
```

> Para Gmail usa una **App Password** (Cuenta Google → Seguridad → Verificación en dos pasos → Contraseñas de aplicaciones).

---

## Datos de prueba

```bash
docker compose exec app php /app/database/seed.php
```

### Usuarios

| Usuario | Contraseña | Rol | Asignación |
|---|---|---|---|
| `admin` | `Admin1234!` | `admin` | — (ve todo) |
| `lab_op` | `Lab_op123!` | `lab_operator` | — (ve todo) |
| `aliado_norte` | `Aliado_norte1!` | `aliado_operator` | ALIADO-001 |
| `aliado_sur` | `Aliado_sur1!` | `aliado_operator` | ALIADO-002 |
| `viewer` | `Viewer123!` | `viewer` | — |

> Para crear un usuario médico de prueba:
> ```json
> POST /auth/register
> { "username": "dr_demo", "email": "dr@demo.com", "password": "Medico2024!", "role": "medico", "health_centers": [1] }
> ```

### Aliados

| ID | Nombre |
|---|---|
| `ALIADO-001` | Laboratorio Clínico Norte |
| `ALIADO-002` | Laboratorio Clínico Sur |

### Órdenes de prueba

| ID | Estado | Resultados | Aliado |
|---|---|:---:|---|
| `SOL-2025-0001` | `completed` | ✅ Hemograma + Glucosa | ALIADO-001 |
| `SOL-2025-0003` | `completed` | ✅ Creatinina + Urea | ALIADO-001 |
| `SOL-2025-0006` | `completed` | ✅ TSH + T4 Libre | ALIADO-001 |
| `SOL-2025-0004` | `sent` | ❌ | ALIADO-001 |
| `SOL-2025-0007` | `pending` | ❌ | ALIADO-001 |
| `SOL-2025-0002` | `completed` | ✅ Perfil Lipídico | ALIADO-002 |
| `SOL-2025-0008` | `completed` | ✅ Parcial de Orina | ALIADO-002 |
| `SOL-2025-0009` | `completed` | ✅ HbA1c + Glucosa | ALIADO-002 |
| `SOL-2025-0010` | `completed` | ✅ PCR + Perfil Lipídico | ALIADO-002 |
| `SOL-2025-0005` | `sent` | ❌ | ALIADO-002 |
| `SOL-2025-0011` | `pending` | ❌ | ALIADO-002 |
| `SOL-2025-0012` | `pending` | ❌ | ALIADO-002 |

---

## Errores comunes

| Situación | Código | Mensaje |
|---|---|---|
| Token ausente | `401` | `Token de autorización ausente` |
| Token expirado o inválido | `401` | `Token inválido o expirado: ...` |
| Cuenta bloqueada | `401` | `Cuenta bloqueada por múltiples intentos fallidos...` |
| Rol insuficiente | `403` | `No tienes permisos para esta acción` |
| Credenciales incorrectas | `401` | `Credenciales inválidas` |
| Campo requerido faltante | `422` | `Campo requerido: <campo>` |
| Username duplicado | `422` | `El username ya está en uso` |
| Email duplicado | `422` | `El email ya está en uso` |
| Rol inválido al registrar | `422` | `Rol inválido: <rol>` |
| Aliado no encontrado | `404` | `Aliado no encontrado: <id>` |
| Centro de salud no encontrado | `404` | `Centro de salud no encontrado: <id>` |
| Paciente no encontrado | `404` | `Paciente no encontrado: <id>` |
| Orden no encontrada | `404` | `Orden no encontrada` |
| Estado de filtro inválido | `422` | `Estado inválido. Valores permitidos: pending, sent, completed` |
| Formato de fecha inválido | `422` | `fecha_desde debe tener formato YYYY-MM-DD` |
| Sin campo `resultado` | `422` | `El resultado debe incluir un valor de resultado principal.` |
| Parámetros obligatorios faltantes | `422` | `Faltan parámetros obligatorios: hb (Hemoglobina), ...` |
| Array `orders` ausente | `422` | `El campo "orders" es requerido y debe ser un array de idSolicitudKey` |
| Tipo de examen duplicado | `422` | `Ya existe un tipo de examen con CUPS: <cups>` |
| Tipo de examen no encontrado | `404` | `Tipo de examen no encontrado: <cups>` |
| Parámetro no encontrado | `404` | `Parámetro no encontrado: <id>` |
| Rango no encontrado | `404` | `Rango no encontrado: <id>` |
| Bacteriólogo no encontrado | `404` | `Bacteriólogo no encontrado: <id>` |
| Bacteriólogo duplicado | `422` | `Ya existe un bacteriólogo con ese documento` |
| Logo / firma formato inválido | `422` | `Formato no permitido. Use PNG o JPG` |
| Logo / firma tamaño excedido | `422` | `El archivo supera el tamaño máximo de 2 MB` |
| Sin resultados para PDF | `422` | `La orden no tiene resultados registrados` |
| Paciente sin email | `422` | `El paciente no tiene email registrado. Proporcione un email en el body.` |
| Email inválido | `422` | `Email inválido: <email>` |
| Error envío correo | `500` | `Error al enviar el correo: <detalle>` |
| Lab externo no responde | `502` | `Fallo al enviar orden: <detalle>` |
| Token reset inválido | `422` | `Token inválido o expirado` |
| OTP inválido o expirado | `401` | `Código inválido o expirado.` |
| Token de paciente ausente | `401` | `Token de paciente ausente` |
| Token de paciente inválido | `401` | `Token de paciente inválido o expirado: ...` |
| Orden no pertenece al paciente | `403` | `No tienes acceso a esta orden` |
| Orden no completada (portal) | `422` | `Los resultados de esta orden aún no están disponibles` |
| Médico no encontrado | `422` | `Médico no encontrado o inactivo: <id>` |
| Médico duplicado | `422` | `Ya existe un médico con ese documento` |
| Usuario ya vinculado a médico | `422` | `Ese usuario ya tiene un médico asociado` |
| Rango solo para numérico | `422` | `Los rangos por reactivo solo aplican a parámetros de tipo numérico.` |
| Tipo resultado inválido | `422` | `Tipo de resultado inválido: 'x'. Use: numerico, texto, booleano` |
