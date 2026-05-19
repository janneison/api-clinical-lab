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
  "password": "admin123"
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
    "aliados": []
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
  "iss": "api-clinical-lab",
  "iat": 1714204800,
  "exp": 1714208400
}
```

---

## Roles y permisos

| Rol | Descripción |
|---|---|
| `admin` | Acceso total. Puede registrar usuarios y ver todas las órdenes sin restricción de aliado. |
| `lab_operator` | Operador interno. Crea y envía órdenes, registra resultados, lista todas las órdenes. |
| `aliado_operator` | Operador de laboratorio aliado externo. Consulta y lista solo las órdenes de sus aliados, registra resultados. |
| `viewer` | Solo lectura. Lista y consulta las órdenes de sus aliados. No puede crear ni modificar. |

### Matriz de acceso por endpoint

| Endpoint | admin | lab_operator | aliado_operator | viewer |
|---|:---:|:---:|:---:|:---:|
| `POST /auth/login` | ✅ | ✅ | ✅ | ✅ |
| `GET /auth/me` | ✅ | ✅ | ✅ | ✅ |
| `POST /auth/register` | ✅ | ❌ | ❌ | ❌ |
| `GET /health-centers` | ✅ | ✅ | ✅ | ✅ |
| `POST /health-centers` | ✅ | ❌ | ❌ | ❌ |
| `PUT /health-centers/{id}` | ✅ | ❌ | ❌ | ❌ |
| `POST /health-centers/{id}/aliados/{aliadoId}` | ✅ | ❌ | ❌ | ❌ |
| `DELETE /health-centers/{id}/aliados/{aliadoId}` | ✅ | ❌ | ❌ | ❌ |
| `GET /aliados` | ✅ | ✅ | ✅ | ✅ |
| `GET /aliados/{id}` | ✅ | ✅ | ✅ | ✅ |
| `PUT /aliados/{id}` | ✅ | ❌ | ❌ | ❌ |
| `POST /aliados/{id}/logo` | ✅ | ❌ | ❌ | ❌ |
| `GET /aliados/{id}/bacteriologos` | ✅ | ✅ | ✅ | ✅ |
| `POST /aliados/{id}/bacteriologos` | ✅ | ✅ | ❌ | ❌ |
| `GET /bacteriologos/{id}` | ✅ | ✅ | ✅ | ✅ |
| `PUT /bacteriologos/{id}` | ✅ | ✅ | ❌ | ❌ |
| `DELETE /bacteriologos/{id}` | ✅ | ✅ | ❌ | ❌ |
| `POST /bacteriologos/{id}/firma` | ✅ | ✅ | ❌ | ❌ |
| `POST /aliados/{id}/logo` | ✅ | ❌ | ❌ | ❌ |
| `GET /patients` | ✅ | ✅ | ❌ | ❌ |
| `POST /patients` | ✅ | ✅ | ❌ | ❌ |
| `GET /patients/{id}` | ✅ | ✅ | ❌ | ❌ |
| `PUT /patients/{id}` | ✅ | ✅ | ❌ | ❌ |
| `GET /medicos` | ✅ | ✅ | ✅ | ✅ |
| `POST /medicos` | ✅ | ✅ | ❌ | ❌ |
| `GET /medicos/{id}` | ✅ | ✅ | ✅ | ✅ |
| `PUT /medicos/{id}` | ✅ | ✅ | ❌ | ❌ |
| `DELETE /medicos/{id}` | ✅ | ✅ | ❌ | ❌ |
| `GET /orders` | ✅ | ✅ | ✅ * | ✅ * |
| `POST /orders` | ✅ | ✅ | ❌ | ❌ |
| `GET /orders/{id}` | ✅ | ✅ | ✅ | ✅ |
| `POST /orders/{id}/send` | ✅ | ✅ | ❌ | ❌ |
| `GET /orders/{id}/results` | ✅ | ✅ | ✅ | ✅ |
| `GET /orders/{id}/results/pdf` | ✅ | ✅ | ✅ | ✅ |
| `POST /orders/{id}/results/attach-pdf` | ✅ | ✅ | ✅ | ❌ |
| `POST /orders/{id}/results/send-email` | ✅ | ✅ | ✅ | ❌ |
| `POST /results` | ✅ | ✅ | ✅ | ❌ |
| `GET /aliados/{id}/orders/pending` | ✅ | ✅ | ✅ * | ❌ |
| `POST /aliados/{id}/orders/mark-sent` | ✅ | ✅ | ❌ | ❌ |
| `GET /exam-types` | ✅ | ✅ | ✅ | ✅ |
| `POST /exam-types` | ✅ | ❌ | ❌ | ❌ |
| `PUT /exam-types/{cups}` | ✅ | ❌ | ❌ | ❌ |
| `GET /exam-types/{cups}/parameters` | ✅ | ✅ | ✅ | ✅ |
| `POST /exam-types/{cups}/parameters` | ✅ | ❌ | ❌ | ❌ |
| `PUT /exam-types/{cups}/parameters/{id}` | ✅ | ❌ | ❌ | ❌ |
| `DELETE /exam-types/{cups}/parameters/{id}` | ✅ | ❌ | ❌ | ❌ |
| `GET /exam-types/{cups}/parameters/{id}/ranges` | ✅ | ✅ | ✅ | ✅ |
| `POST /exam-types/{cups}/parameters/{id}/ranges` | ✅ | ❌ | ❌ | ❌ |
| `PUT /exam-types/{cups}/parameters/{id}/ranges/{rangeId}` | ✅ | ❌ | ❌ | ❌ |
| `DELETE /exam-types/{cups}/parameters/{id}/ranges/{rangeId}` | ✅ | ❌ | ❌ | ❌ |

> \* `aliado_operator` y `viewer` solo ven las órdenes de los aliados asignados a su usuario.

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
  "password": "aliado_norte123"
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
    "aliados": ["ALIADO-001"]
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

Retorna el perfil del usuario autenticado extraído del JWT.

**Roles:** todos.

**Respuesta `200 OK`:**

```json
{
  "id": 3,
  "username": "aliado_norte",
  "role": "aliado_operator",
  "aliados": ["ALIADO-001"]
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
| `password` | string | ✅ | Contraseña en texto plano |
| `role` | string | ✅ | Uno de: `admin`, `lab_operator`, `aliado_operator`, `viewer` |
| `aliados` | string[] | ❌ | IDs de aliados asignados al usuario |

**Ejemplo:**

```json
{
  "username": "nuevo_operador",
  "email": "nuevo@clinicallab.local",
  "password": "segura1234",
  "role": "aliado_operator",
  "aliados": ["ALIADO-001", "ALIADO-002"]
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

#### `GET /aliados/{id}`

Retorna el perfil completo de un aliado.

**Roles:** todos.

**Path param:** `id` — ID del aliado (ej: `ALIADO-001`).

**Respuesta `200 OK`:**
```json
{
  "id": "ALIADO-001",
  "nombre": "Laboratorio Clínico Norte",
  "nit": "900123456-7",
  "direccion": "Calle 100 # 15-20, Bogotá",
  "email": "contacto@labnorte.com",
  "logoPath": "/storage/logos/aliado-001_logo.png",
  "activo": true
}
```

`404 Not Found`
```json
{ "error": "Aliado no encontrado: ALIADO-999" }
```

---

#### `PUT /aliados/{id}`

Actualiza el perfil de un aliado (nombre, NIT, dirección, email, estado activo). El logotipo se actualiza por endpoint separado.

**Roles:** `admin`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `nombre` | string | ✅ | Nombre del aliado |
| `nit` | string | ❌ | NIT o número de identificación tributaria |
| `direccion` | string | ❌ | Dirección física |
| `email` | string | ❌ | Correo de contacto |
| `activo` | bool | ❌ | Estado activo (default: mantiene el actual) |

**Ejemplo:**
```json
{
  "nombre": "Laboratorio Clínico Norte S.A.",
  "nit": "900123456-7",
  "direccion": "Calle 100 # 15-20, Bogotá",
  "email": "contacto@labnorte.com",
  "activo": true
}
```

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

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `logo` | file | ✅ | Imagen PNG o JPG, máx 2 MB |

**Respuesta `200 OK`:**
```json
{
  "message": "Logo actualizado correctamente",
  "logoPath": "/storage/logos/aliado-001_logo.png"
}
```

`422 Unprocessable Entity`
```json
{ "error": "Formato no permitido. Use PNG o JPG" }
{ "error": "El archivo supera el tamaño máximo de 2 MB" }
{ "error": "Se requiere el campo \"logo\" como archivo multipart" }
```

---

### Bacteriólogos

Catálogo de bacteriólogos asociados a un aliado. Cada bacteriólogo tiene número de tarjeta profesional, universidad de egreso y firma digital (PNG/JPG) que aparece en el PDF del informe de resultados.

---

#### `GET /aliados/{aliadoId}/bacteriologos`

Lista los bacteriólogos activos de un aliado.

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
| `tipoDocumento` | string | ✅ | Tipo de documento (`CC`, `TP`, etc.) |
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

`422 Unprocessable Entity`
```json
{ "error": "Ya existe un bacteriólogo con ese documento" }
```

---

#### `GET /bacteriologos/{id}`

Retorna el detalle de un bacteriólogo.

**Roles:** todos.

**Respuesta `200 OK`:** mismo formato que el listado.

`404 Not Found`
```json
{ "error": "Bacteriólogo no encontrado: 99" }
```

---

#### `PUT /bacteriologos/{id}`

Actualiza los datos de un bacteriólogo.

**Roles:** `admin`, `lab_operator`.

**Request body:** mismos campos que `POST /aliados/{aliadoId}/bacteriologos` más `activo`.

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

Sube o reemplaza la firma digital del bacteriólogo. La firma aparece en el PDF del informe de resultados.

**Roles:** `admin`, `lab_operator`.

**Content-Type:** `multipart/form-data`

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `firma` | file | ✅ | Imagen PNG o JPG, máx 2 MB |

**Respuesta `200 OK`:**
```json
{
  "message": "Firma actualizada correctamente",
  "firmaPath": "/storage/firmas/firma_bact_1.png"
}
```

`422 Unprocessable Entity`
```json
{ "error": "Formato no permitido. Use PNG o JPG" }
{ "error": "El archivo supera el tamaño máximo de 2 MB" }
```

---

### Centros de salud

Catálogo de centros de salud con su relación a aliados. Un aliado puede atender múltiples centros y un centro puede estar asociado a múltiples aliados.

---

#### `GET /health-centers`

Lista los centros de salud activos.

**Roles:** todos.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `activo` | `0`\|`1` | `0` incluye también inactivos (default: `1`) |
| `aliado_id` | string | Filtra solo los centros asociados a ese aliado |

**Ejemplos:**
```
GET /health-centers
GET /health-centers?aliado_id=ALIADO-001
GET /health-centers?activo=0
```

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

**Ejemplo:**
```json
{
  "nombre": "Hospital Universitario",
  "ciudad": "Barranquilla",
  "direccion": "Calle 30 # 45-10",
  "telefono": "605-3601234"
}
```

**Respuesta `201 Created`:**
```json
{ "id": 5, "message": "Centro de salud creado" }
```

---

#### `PUT /health-centers/{id}`

Actualiza un centro de salud existente.

**Roles:** `admin`.

**Request body:** mismos campos que `POST /health-centers`.

**Respuesta `200 OK`:**
```json
{ "message": "Centro de salud actualizado" }
```

`404 Not Found`
```json
{ "error": "Centro de salud no encontrado: 99" }
```

---

#### `POST /health-centers/{id}/aliados/{aliadoId}`

Asocia un aliado a un centro de salud.

**Roles:** `admin`.

**Sin body.**

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

Los pacientes se crean automáticamente al registrar una orden si no existen (identificados por `tipoDeDocumento` + `identificacion`). Si ya existen, se reutilizan sin modificar sus datos. También pueden crearse y editarse directamente desde este endpoint.

---

#### `GET /patients`

Lista pacientes con búsqueda opcional por nombre o identificación.

**Roles:** `admin`, `lab_operator`.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `q` | string | Búsqueda parcial por nombre o identificación |
| `page` | int | Número de página (default: `1`) |
| `limit` | int | Resultados por página, máximo `100` (default: `20`) |

**Ejemplos:**
```
GET /patients
GET /patients?q=Carlos
GET /patients?q=1020304050
```

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

Crea un paciente directamente (sin necesidad de crear una orden).

**Roles:** `admin`, `lab_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipoDocumento` | string | ✅ | Tipo de documento (`CC`, `TI`, `PA`, `CE`, etc.) |
| `identificacion` | string | ✅ | Número de documento |
| `nombre` | string | ✅ | Nombre completo |
| `sexo` | string | ✅ | `M` o `F` |
| `fechaNacimiento` | string | ✅ | Formato `YYYY-MM-DD` |
| `email` | string | ❌ | Correo electrónico (requerido para el portal de pacientes) |
| `telefono` | string | ❌ | Teléfono de contacto |

**Ejemplo:**
```json
{
  "tipoDocumento": "CC",
  "identificacion": "1020304050",
  "nombre": "Carlos Andrés Pérez López",
  "sexo": "M",
  "fechaNacimiento": "1985-03-15",
  "email": "carlos.perez@correo.com",
  "telefono": "3001234567"
}
```

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

`422 Unprocessable Entity`
```json
{ "error": "Campo requerido: nombre" }
{ "error": "Ya existe un paciente con ese tipo y número de documento" }
{ "error": "fechaNacimiento debe tener formato YYYY-MM-DD" }
{ "error": "sexo debe ser M o F" }
```

---

#### `GET /patients/{id}`

Retorna el detalle de un paciente con el historial de sus órdenes.

**Roles:** `admin`, `lab_operator`.

**Path param:** `id` — ID numérico del paciente.

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

`404 Not Found`
```json
{ "error": "Paciente no encontrado: 99" }
```

---

#### `PUT /patients/{id}`

Actualiza los datos de un paciente existente. Solo se modifican los campos enviados.

**Roles:** `admin`, `lab_operator`.

**Path param:** `id` — ID numérico del paciente.

**Request body** (todos opcionales — solo se actualizan los campos enviados):

| Campo | Tipo | Descripción |
|---|---|---|
| `tipoDocumento` | string | Tipo de documento |
| `identificacion` | string | Número de documento |
| `nombre` | string | Nombre completo |
| `sexo` | string | `M` o `F` |
| `fechaNacimiento` | string | Formato `YYYY-MM-DD` |
| `email` | string | Correo electrónico (enviar `""` para borrar) |
| `telefono` | string | Teléfono (enviar `""` para borrar) |

**Ejemplo:**
```json
{
  "email": "nuevo@correo.com",
  "telefono": "3109876543"
}
```

**Respuesta `200 OK`:** mismo formato que `GET /patients/{id}` (sin el historial de órdenes).

`404 Not Found`
```json
{ "error": "Paciente no encontrado: 99" }
```

`422 Unprocessable Entity`
```json
{ "error": "Ya existe otro paciente con ese tipo y número de documento" }
{ "error": "fechaNacimiento debe tener formato YYYY-MM-DD" }
{ "error": "sexo debe ser M o F" }
```

---

### Médicos

Catálogo de médicos que ordenan exámenes. Pueden vincularse opcionalmente a un usuario del sistema. Al crear una orden se puede referenciar el médico por `medicoId` o por documento (`tipoDocumentoMedico` + `identificacionMedico`).

---

#### `GET /medicos`

Lista médicos con búsqueda opcional.

**Roles:** todos.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `q` | string | Búsqueda parcial por nombre o identificación |
| `activo` | `0`\|`1` | `0` incluye también inactivos (default: `1`) |

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
| `tipoDocumento` | string | ✅ | Tipo de documento (`CC`, `TP`, etc.) |
| `identificacion` | string | ✅ | Número de documento |
| `nombre` | string | ✅ | Nombre completo |
| `especialidad` | string | ❌ | Especialidad médica |
| `registroMedico` | string | ❌ | Número de registro profesional |
| `userId` | int | ❌ | ID de usuario del sistema a vincular (único por médico) |

**Ejemplo:**
```json
{
  "tipoDocumento": "CC",
  "identificacion": "12345678",
  "nombre": "Dr. Juan Rodríguez",
  "especialidad": "Medicina General",
  "registroMedico": "RM-12345",
  "userId": 3
}
```

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

`422 Unprocessable Entity`
```json
{ "error": "Ya existe un médico con ese documento" }
{ "error": "Usuario no encontrado: 99" }
{ "error": "Ese usuario ya tiene un médico asociado" }
```

---

#### `GET /medicos/{id}`

Retorna el detalle de un médico.

**Roles:** todos.

`404 Not Found`
```json
{ "error": "Médico no encontrado: 99" }
```

---

#### `PUT /medicos/{id}`

Actualiza los datos de un médico. Solo se modifican los campos enviados.

**Roles:** `admin`, `lab_operator`.

**Request body:** mismos campos que `POST /medicos` (todos opcionales).

**Respuesta `200 OK`:** mismo formato que `GET /medicos/{id}`.

---

#### `DELETE /medicos/{id}`

Desactiva un médico (soft delete).

**Roles:** `admin`, `lab_operator`.

**Respuesta `200 OK`:**
```json
{ "message": "Médico desactivado" }
```

---

### Órdenes de laboratorio

---

#### `GET /orders`

Lista órdenes con filtros opcionales y paginación. Los resultados se ordenan por `fecha_orden` descendente (más recientes primero).

**Roles:** todos (con restricción de aliado para `aliado_operator` y `viewer`).

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `estado` | string | Filtra por estado: `pending`, `sent` o `completed` |
| `fecha_desde` | string | Fecha mínima de la orden. Formato `YYYY-MM-DD` |
| `fecha_hasta` | string | Fecha máxima de la orden. Formato `YYYY-MM-DD` |
| `cups` | string | Filtra órdenes que contengan ese código CUPS en sus detalles |
| `page` | int | Número de página (default: `1`) |
| `limit` | int | Resultados por página, máximo `100` (default: `20`) |

**Ejemplos:**

```
GET /orders
GET /orders?estado=pending
GET /orders?fecha_desde=2025-04-01&fecha_hasta=2025-04-30
GET /orders?cups=903820
GET /orders?estado=completed&cups=904855&page=1&limit=10
GET /orders?fecha_desde=2025-04-15&estado=sent
```

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

`422 Unprocessable Entity`
```json
{ "error": "Estado inválido. Valores permitidos: pending, sent, completed" }
{ "error": "fecha_desde debe tener formato YYYY-MM-DD" }
{ "error": "fecha_hasta debe tener formato YYYY-MM-DD" }
```

---

#### `POST /orders`

Crea una nueva orden de laboratorio con sus exámenes.

**Roles:** `admin`, `lab_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `idSolicitudKey` | string | ✅ | Identificador único de la solicitud |
| `idAdmision` | string | ✅ | ID de admisión del paciente |
| `idAtencion` | string | ❌ | ID de atención |
| `tipoDeDocumento` | string | ✅ | Tipo de documento (`CC`, `TI`, `PA`, `CE`, etc.) |
| `identificacion` | string | ✅ | Número de documento del paciente |
| `nombreDelPaciente` | string | ✅ | Nombre completo |
| `sexo` | string | ✅ | `M` o `F` |
| `fechaDeNacimiento` | string | ✅ | Formato `YYYY-MM-DD` |
| `centroDeSalud` | string | ✅ | Nombre del centro de salud |
| `fechaDeLaOrden` | string | ✅ | Formato `YYYY-MM-DD HH:MM:SS` |
| `medicoQueOrdena` | string | ✅ | Nombre del médico |
| `numeroDeAutorizacion` | string | ❌ | Número de autorización del seguro |
| `idAliado` | string | ❌ | ID del laboratorio aliado destino |
| `healthCenterId` | int | ❌ | ID del centro de salud del catálogo. Si se omite, se busca por nombre en `centroDeSalud` |
| `medicoId` | int | ❌ | ID del médico del catálogo. Tiene prioridad sobre `tipoDocumentoMedico` + `identificacionMedico` |
| `tipoDocumentoMedico` | string | ❌ | Tipo de documento del médico (alternativa a `medicoId`) |
| `identificacionMedico` | string | ❌ | Número de documento del médico (alternativa a `medicoId`) |
| `porcEjecucion` | string | ❌ | Porcentaje inicial (default `"0"`) |
| `detalles` | array | ✅ | Mínimo 1 examen (ver estructura abajo) |

**Estructura de cada detalle:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `cups` | string | ✅ | Código CUPS del examen |
| `nombreDelLaboratorio` | string | ✅ | Nombre del examen |
| `fechaTomaMuestra` | string\|null | ❌ | Formato `YYYY-MM-DD HH:MM:SS` |
| `metodo` | string\|null | ❌ | Método de análisis |
| `reactivo` | string\|null | ❌ | Reactivo utilizado |
| `invima` | string\|null | ❌ | Registro INVIMA |
| `estadoDelResultado` | string\|null | ❌ | Estado del resultado |
| `fechaResultado` | string\|null | ❌ | Fecha del resultado |
| `tipoIdentificacionDelBacteriologo` | string\|null | ❌ | Tipo de doc del bacteriólogo |
| `identificacionDelBacteriologo` | string\|null | ❌ | Documento del bacteriólogo |

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
  "detalles": [
    {
      "cups": "903820",
      "nombreDelLaboratorio": "Hemograma Completo"
    },
    {
      "cups": "904010",
      "nombreDelLaboratorio": "Glucosa en Ayunas"
    }
  ]
}
```

**Respuesta `201 Created`:**

```json
{
  "idSolicitudKey": "SOL-2025-0099",
  "estadoDeLaOrden": "pending",
  "porcEjecucion": 0,
  "detalles": 2
}
```

---

#### `GET /orders/{id}`

Retorna una orden con todos sus detalles.

**Roles:** `admin`, `lab_operator`, `aliado_operator`, `viewer`.

**Path param:** `id` — valor de `idSolicitudKey`.

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

`404 Not Found`
```json
{ "error": "Orden no encontrada" }
```

**Estados posibles de `estadoDeLaOrden`:**

| Estado | Descripción |
|---|---|
| `pending` | Orden creada, pendiente de envío al laboratorio externo |
| `sent` | Enviada al laboratorio externo, esperando resultados |
| `completed` | Al menos un resultado registrado (`porcEjecucion = 100`) |

---

#### `POST /orders/{id}/send`

Envía la orden al laboratorio aliado externo mediante HTTP con autenticación JWT + API Key.

**Roles:** `admin`, `lab_operator`.

**Path param:** `id` — valor de `idSolicitudKey`.

**Sin body.**

**Respuesta `200 OK`:**

```json
{
  "idSolicitudKey": "SOL-2025-0004",
  "estadoDeLaOrden": "sent",
  "fechaEnvio": "2025-04-28T09:30:00+00:00"
}
```

`404 Not Found`
```json
{ "error": "Orden no encontrada" }
```

`502 Bad Gateway`
```json
{ "error": "Fallo al enviar orden: Connection timed out" }
```

---

### Servicios por aliado

Endpoints especializados para operar sobre las órdenes de un aliado específico sin necesidad de filtros adicionales.

---

#### `GET /aliados/{aliadoId}/orders/pending`

Retorna todas las órdenes en estado `pending` para el aliado indicado, ordenadas por `fecha_orden` descendente.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Path param:** `aliadoId` — ID del aliado (ej: `ALIADO-001`).

**Sin query params ni body.**

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

`404 Not Found`
```json
{ "error": "Aliado no encontrado: ALIADO-999" }
```

---

#### `POST /aliados/{aliadoId}/orders/mark-sent`

Marca como `sent` una lista de órdenes del aliado. Procesa cada orden de forma independiente:

- Si la orden **no existe** → se omite con razón `Orden no encontrada`
- Si la orden **no pertenece al aliado** → se omite con razón `La orden no pertenece al aliado`
- Si la orden **no está en `pending`** → se omite con razón `Estado inválido: <estado> (se requiere pending)`
- Si cumple todas las condiciones → se actualiza a `sent` y se registra `fecha_envio`

**Roles:** `admin`, `lab_operator`.

**Path param:** `aliadoId` — ID del aliado.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `orders` | string[] | ✅ | Lista de `idSolicitudKey` a marcar como enviadas |

**Ejemplo:**

```json
{
  "orders": ["SOL-2025-0007", "SOL-2025-0011", "SOL-2025-0099"]
}
```

**Respuesta `200 OK`:**

```json
{
  "aliadoId": "ALIADO-001",
  "totalRecibidas": 3,
  "totalActualizadas": 1,
  "totalOmitidas": 2,
  "actualizadas": ["SOL-2025-0007"],
  "omitidas": {
    "SOL-2025-0011": "La orden no pertenece al aliado",
    "SOL-2025-0099": "Orden no encontrada"
  }
}
```

`404 Not Found`
```json
{ "error": "Aliado no encontrado: ALIADO-999" }
```

`422 Unprocessable Entity`
```json
{ "error": "El campo \"orders\" es requerido y debe ser un array de idSolicitudKey" }
{ "error": "El array \"orders\" no contiene identificadores válidos" }
```

---

### Resultados de laboratorio

---

#### `POST /results`

Registra el resultado de un examen. Al guardar, la orden asociada actualiza su `porcEjecucion` a `100` y pasa a estado `completed`.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `idSolicitudKey` | string | ✅ | ID de la orden asociada |
| `cups` | string | ✅ | Código CUPS del examen |
| `values` | object | ✅ | Objeto con los valores del resultado. Debe incluir la clave `resultado` |
| `values.resultado` | string | ✅ | Valor principal del resultado (ej: `"Normal"`, `"Positivo"`) |
| `attachmentPath` | string\|null | ❌ | Ruta al archivo adjunto (PDF, imagen, etc.) |
| `bacteriologoId` | int | ❌ | ID del bacteriólogo que procesó el examen |

**Ejemplo:**

```json
{
  "idSolicitudKey": "SOL-2025-0004",
  "cups": "903820",
  "values": {
    "resultado": "Normal",
    "leucocitos":  "7.2 10³/µL",
    "eritrocitos": "4.8 10⁶/µL",
    "hemoglobina": "14.5 g/dL",
    "hematocrito": "43.2 %",
    "plaquetas":   "250 10³/µL"
  },
  "attachmentPath": null
}
```

Para exámenes parametrizados, los valores pueden incluir una clave `reactivo` por parámetro:

```json
{
  "idSolicitudKey": "SOL-2025-0007",
  "cups": "903820",
  "values": {
    "hb": { "valor": "14.5", "reactivo": "Sysmex XN-1000" },
    "wbc": "7.2"
  }
}
```

Ejemplo para examen de tipo booleano:

```json
{
  "idSolicitudKey": "SOL-2025-0007",
  "cups": "904500",
  "values": {
    "pcr_cualitativa": "reactivo"
  }
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

`404 Not Found`
```json
{ "error": "Orden no encontrada" }
```

`422 Unprocessable Entity`
```json
{ "error": "El resultado debe incluir un valor de resultado principal." }
{ "error": "Campo requerido faltante: cups" }
```

---

#### `GET /orders/{id}/results`

Retorna los resultados estructurados de una orden con los valores tipados y sus flags de interpretación clínica. Para exámenes sin parámetros configurados devuelve el JSON libre original.

**Roles:** todos.

**Path param:** `id` — valor de `idSolicitudKey`.

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
        "universidad": "Universidad Nacional de Colombia",
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
          "etiquetaBooleano": null,
          "flag": "normal"
        },
        {
          "codigo": "hb",
          "nombre": "Hemoglobina",
          "tipoResultado": "numerico",
          "valorNumerico": 14.5,
          "valorTexto": null,
          "valorBooleano": null,
          "reactivo": "Sysmex XN-1000",
          "unidad": "g/dL",
          "valorMinRef": 13.5,
          "valorMaxRef": 17.5,
          "etiquetaBooleano": null,
          "flag": "normal"
        }
      ],
      "receivedAt": "2025-04-10 14:05:00"
    }
  ]
}
```

**Flags posibles:**

| Flag | Descripción |
|---|---|
| `normal` | Dentro del rango de referencia |
| `alto` | Por encima del rango máximo |
| `bajo` | Por debajo del rango mínimo |
| `critico` | Valor crítico (>130% del máximo o <70% del mínimo) |
| `indeterminado` | Sin rango de referencia definido o valor no numérico |

---

#### `POST /orders/{id}/results/attach-pdf`

Adjunta un PDF externo como el informe oficial de la orden. Una vez adjuntado, este PDF se usa en lugar del generado automáticamente al descargar o enviar por correo.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Content-Type:** `multipart/form-data`

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `pdf` | file | ✅ | Archivo PDF, máximo 20 MB |

**Respuesta `200 OK`:**
```json
{
  "message": "PDF adjuntado correctamente. Se usará como informe oficial.",
  "idSolicitudKey": "SOL-2025-0001",
  "attachmentPath": "/storage/pdfs/resultado_SOL-2025-0001_adjunto.pdf"
}
```

`422 Unprocessable Entity`
```json
{ "error": "Solo se aceptan archivos PDF" }
{ "error": "El archivo supera el tamaño máximo de 20 MB" }
{ "error": "La orden no tiene resultados registrados" }
```

---

#### `GET /orders/{id}/results/pdf`

Devuelve el PDF del informe de resultados. Prioridad de resolución:

1. **PDF adjunto manualmente** (`POST /orders/{id}/results/attach-pdf`) — si existe y el archivo está en disco, se devuelve directamente sin regenerar
2. **PDF generado automáticamente** — se genera a partir de los resultados estructurados

**Roles:** todos.

**Path param:** `id` — valor de `idSolicitudKey`.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `regenerate` | `1` | Fuerza la regeneración aunque exista un PDF adjunto |

**Ejemplos:**
```
GET /orders/SOL-2025-0001/results/pdf
GET /orders/SOL-2025-0001/results/pdf?regenerate=1
```

**Respuesta `200 OK`:**
- `Content-Type: application/pdf`
- `Content-Disposition: attachment; filename="resultado_SOL-2025-0001.pdf"`
- Cuerpo: bytes del PDF

**Contenido del PDF generado automáticamente:**
- Encabezado con logo, nombre, NIT y dirección del aliado
- Datos del paciente (nombre, documento, sexo, edad, fecha de nacimiento)
- Datos de la orden (N° solicitud, fecha, médico, centro de salud)
- Tabla de resultados por examen con valores, unidades, rangos de referencia y flag con color (verde=normal, amarillo=alto/bajo, rojo=crítico)
- Pie de página con fecha de generación

`404 Not Found`
```json
{ "error": "Orden no encontrada" }
```

`422 Unprocessable Entity`
```json
{ "error": "La orden no tiene resultados registrados" }
{ "error": "No se encontró el paciente asociado a la orden" }
```

---

#### `POST /orders/{id}/results/send-email`

Genera el PDF (si no existe) y lo envía por correo electrónico al paciente o a la dirección indicada.

**Roles:** `admin`, `lab_operator`, `aliado_operator`.

**Path param:** `id` — valor de `idSolicitudKey`.

**Request body (todo opcional):**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `email` | string | ❌ | Destinatario. Si se omite, usa `patients.email`. Si el paciente no tiene email y no se pasa este campo → error 422 |
| `mensaje` | string | ❌ | Texto personalizado para el cuerpo del correo |

**Ejemplo:**
```json
{
  "email": "otro@correo.com",
  "mensaje": "Estimado paciente, adjunto sus resultados de laboratorio."
}
```

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

`404 Not Found`
```json
{ "error": "Orden no encontrada" }
```

`422 Unprocessable Entity`
```json
{ "error": "El paciente no tiene email registrado. Proporcione un email en el body." }
{ "error": "Email inválido: correo_invalido" }
```

---

### Catálogo de exámenes

Permite configurar los tipos de examen y sus parámetros de referencia. Los parámetros se usan para validar y calcular flags automáticamente al registrar resultados.

---

#### `GET /exam-types`

Lista todos los tipos de examen activos del catálogo.

**Roles:** todos.

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `activo` | `0` \| `1` | `0` incluye también los inactivos (default: `1`) |

**Respuesta `200 OK`:**

```json
[
  { "cups": "903820", "nombre": "Hemograma Completo", "descripcion": "CBC...", "activo": true },
  { "cups": "904010", "nombre": "Glucosa en Ayunas",  "descripcion": null,    "activo": true }
]
```

---

#### `POST /exam-types`

Crea un nuevo tipo de examen.

**Roles:** `admin`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `cups` | string | ✅ | Código CUPS único |
| `nombre` | string | ✅ | Nombre del examen |
| `descripcion` | string | ❌ | Descripción opcional |
| `activo` | bool | ❌ | Default `true` |

**Respuesta `201 Created`:**
```json
{ "message": "Tipo de examen creado" }
```

`422 Unprocessable Entity`
```json
{ "error": "Ya existe un tipo de examen con CUPS: 903820" }
```

---

#### `PUT /exam-types/{cups}`

Actualiza nombre, descripción o estado activo de un tipo de examen.

**Roles:** `admin`.

**Request body:** mismos campos que `POST /exam-types` excepto `cups`.

**Respuesta `200 OK`:**
```json
{ "message": "Tipo de examen actualizado" }
```

`404 Not Found`
```json
{ "error": "Tipo de examen no encontrado: 904999" }
```

---

#### `GET /exam-types/{cups}/parameters`

Lista los parámetros de referencia de un tipo de examen.

**Roles:** todos.

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
    "sexo": "*",
    "edadMin": null,
    "edadMax": null,
    "obligatorio": true,
    "orden": 1,
    "activo": true,
    "tipoResultado": "numerico",
    "etiquetaBooleano": null
  }
]
```

---

#### `POST /exam-types/{cups}/parameters`

Agrega un parámetro de referencia al tipo de examen.

**Roles:** `admin`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `codigo` | string | ✅ | Clave del parámetro en `values` (ej: `wbc`, `hb`) |
| `nombre` | string | ✅ | Nombre legible |
| `unidad` | string | ❌ | Unidad de medida (ej: `g/dL`, `%`) |
| `valorMinRef` | float | ❌ | Límite inferior del rango normal |
| `valorMaxRef` | float | ❌ | Límite superior del rango normal |
| `sexo` | string | ❌ | `M`, `F` o `*` para ambos (default: `*`) |
| `edadMin` | int | ❌ | Edad mínima en años (null = sin restricción) |
| `edadMax` | int | ❌ | Edad máxima en años (null = sin restricción) |
| `obligatorio` | bool | ❌ | Si es requerido al registrar resultado (default: `false`) |
| `orden` | int | ❌ | Posición en la presentación (default: `0`) |
| `tipoResultado` | string | ❌ | Tipo de valor: `numerico`, `texto`, `booleano` (default: `numerico`) |
| `etiquetaBooleano` | string | ❌ | Requerido si `tipoResultado=booleano`. Valores: `normal_alto`, `positivo_negativo`, `reactivo_no_reactivo` |

**Respuesta `201 Created`:**
```json
{ "id": 25, "message": "Parámetro creado" }
```

---

#### `PUT /exam-types/{cups}/parameters/{id}`

Actualiza un parámetro existente.

**Roles:** `admin`.

**Request body:** mismos campos que `POST /exam-types/{cups}/parameters`.

**Respuesta `200 OK`:**
```json
{ "message": "Parámetro actualizado" }
```

`404 Not Found`
```json
{ "error": "Parámetro no encontrado: 999" }
```

---

#### `DELETE /exam-types/{cups}/parameters/{id}`

Desactiva un parámetro (soft delete — no se elimina físicamente).

**Roles:** `admin`.

**Respuesta `200 OK`:**
```json
{ "message": "Parámetro desactivado" }
```

`404 Not Found`
```json
{ "error": "Parámetro no encontrado: 999" }
```

---

#### `GET /exam-types/{cups}/parameters/{parameterId}/ranges`

Lista los rangos de referencia por reactivo de un parámetro numérico.

**Roles:** todos.

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

Agrega un rango de referencia específico para un reactivo. Solo aplica a parámetros de tipo `numerico`.

**Roles:** `admin`.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `reactivo` | string | ✅ | Nombre del reactivo |
| `valorMinRef` | float | ❌ | Límite inferior del rango normal |
| `valorMaxRef` | float | ❌ | Límite superior del rango normal |
| `sexo` | string | ❌ | `M`, `F` o `*` para ambos (default: `*`) |
| `edadMin` | int | ❌ | Edad mínima en años (null = sin restricción) |
| `edadMax` | int | ❌ | Edad máxima en años (null = sin restricción) |

**Respuesta `201 Created`:**
```json
{ "id": 1, "message": "Rango creado" }
```

`422 Unprocessable Entity`
```json
{ "error": "Los rangos por reactivo solo aplican a parámetros de tipo numérico." }
```

---

#### `PUT /exam-types/{cups}/parameters/{parameterId}/ranges/{rangeId}`

Actualiza un rango existente.

**Roles:** `admin`.

**Request body:** mismos campos que `POST /exam-types/{cups}/parameters/{parameterId}/ranges`.

**Respuesta `200 OK`:**
```json
{ "message": "Rango actualizado" }
```

`404 Not Found`
```json
{ "error": "Rango no encontrado: 99" }
```

---

#### `DELETE /exam-types/{cups}/parameters/{parameterId}/ranges/{rangeId}`

Desactiva un rango (soft delete).

**Roles:** `admin`.

**Respuesta `200 OK`:**
```json
{ "message": "Rango desactivado" }
```

---

### Portal de pacientes

Acceso passwordless para pacientes. Usa un flujo OTP de dos pasos que emite un **JWT de paciente** independiente del JWT de staff. El JWT de paciente tiene issuer `api-clinical-lab-patient` y validez de **1 hora**.

> Ninguno de estos endpoints requiere el JWT de staff ni la API Key. Los endpoints de resultados requieren el JWT de paciente en el header `Authorization: Bearer <patient-jwt>`.

---

#### `POST /patient-portal/request-access`

El paciente solicita un código OTP de 6 dígitos. El sistema lo envía al email registrado en la tabla `patients`.

**Pública** — no requiere token.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipoDocumento` | string | ✅ | Tipo de documento (`CC`, `TI`, `PA`, `CE`, etc.) |
| `identificacion` | string | ✅ | Número de documento |

**Ejemplo:**

```json
{
  "tipoDocumento": "CC",
  "identificacion": "1020304050"
}
```

**Respuesta `200 OK`** (siempre, para no revelar si el paciente existe):

```json
{
  "message": "Si el documento está registrado, recibirás un código en tu correo."
}
```

`422 Unprocessable Entity`
```json
{ "error": "Los campos tipoDocumento e identificacion son requeridos" }
```

> El código OTP tiene validez de **15 minutos**. Si se solicita uno nuevo, el anterior queda invalidado automáticamente.

---

#### `POST /patient-portal/verify`

El paciente envía el código OTP recibido. Si es válido, el sistema retorna un JWT de paciente.

**Pública** — no requiere token.

**Request body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipoDocumento` | string | ✅ | Tipo de documento |
| `identificacion` | string | ✅ | Número de documento |
| `codigo` | string | ✅ | Código OTP de 6 dígitos recibido por email |

**Ejemplo:**

```json
{
  "tipoDocumento": "CC",
  "identificacion": "1020304050",
  "codigo": "847291"
}
```

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

`401 Unauthorized`
```json
{ "error": "Código inválido o expirado." }
```

`422 Unprocessable Entity`
```json
{ "error": "Los campos tipoDocumento, identificacion y codigo son requeridos" }
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

Retorna todas las órdenes en estado `completada` del paciente autenticado.

**Requiere:** `Authorization: Bearer <patient-jwt>`

**Sin parámetros.**

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
      "estadoDeLaOrden": "completada",
      "centroDeSalud": "Clínica Norte S.A.",
      "medicoQueOrdena": "Dr. Juan Rodríguez"
    },
    {
      "idSolicitudKey": "SOL-2025-0003",
      "fechaDeLaOrden": "2025-04-22 09:00:00",
      "estadoDeLaOrden": "completada",
      "centroDeSalud": "Hospital Central",
      "medicoQueOrdena": "Dr. Pedro Sánchez"
    }
  ],
  "total": 2
}
```

`401 Unauthorized`
```json
{ "error": "Token de paciente ausente" }
{ "error": "Token de paciente inválido o expirado: ..." }
```

---

#### `GET /patient-portal/results/{idSolicitudKey}/pdf`

Descarga el PDF de resultados de una orden específica del paciente autenticado. Verifica que la orden pertenezca al paciente antes de servir el archivo.

**Requiere:** `Authorization: Bearer <patient-jwt>`

**Path param:** `idSolicitudKey` — identificador de la orden (ej: `SOL-2025-0001`).

**Respuesta `200 OK`:**
- `Content-Type: application/pdf`
- `Content-Disposition: attachment; filename="resultado_SOL-2025-0001.pdf"`
- Cuerpo: bytes del PDF

`401 Unauthorized`
```json
{ "error": "Token de paciente ausente" }
```

`403 Forbidden`
```json
{ "error": "No tienes acceso a esta orden" }
```

`404 Not Found`
```json
{ "error": "Orden no encontrada" }
```

`422 Unprocessable Entity`
```json
{ "error": "Los resultados de esta orden aún no están disponibles" }
```

---

## Modelos de datos

### Esquema de base de datos

```
roles             → id, name
aliados           → id, nombre, nit, direccion, email, logo_path, activo, created_at
users             → id, username, email, password_hash, role_id, activo, created_at, updated_at
user_aliado       → user_id, aliado_id  (N:M)

bacteriologos     → id (PK), aliado_id (FK), tipo_documento, identificacion (UNIQUE),
                    nombre, tarjeta_profesional, universidad, firma_path, activo,
                    created_at, updated_at

health_centers       → id (PK), nombre, ciudad, direccion, telefono, activo, created_at, updated_at
aliado_health_center → aliado_id (FK), health_center_id (FK)  (N:M)

patients          → id (PK), tipo_documento, identificacion (UNIQUE con tipo_doc),
                    nombre, sexo, fecha_nacimiento, email, telefono, created_at, updated_at

lab_orders        → id_solicitud_key (PK), id_admision, id_atencion, tipo_documento,
                    identificacion, nombre_paciente, sexo, fecha_nacimiento,
                    centro_salud, fecha_orden, medico_ordena, numero_autorizacion,
                    id_aliado, fecha_envio, porc_ejecucion, estado_orden,
                    patient_id (FK → patients), health_center_id (FK → health_centers),
                    medico_id (FK → medicos, nullable),
                    created_at, updated_at

lab_order_details → id (PK), id_solicitud_key (FK), id_admision, cups,
                    nombre_laboratorio, fecha_toma_muestra, metodo, reactivo,
                    invima, estado_resultado, fecha_resultado,
                    tipo_id_bacteriologo, id_bacteriologo, created_at

lab_results       → id (PK), id_solicitud_key (FK), cups, values_json,
                    attachment_path, bacteriologo_id (FK → bacteriologos),
                    received_at, created_at

medicos           → id (PK), tipo_documento, identificacion (UNIQUE con tipo_doc),
                    nombre, especialidad, registro_medico,
                    user_id (FK → users, UNIQUE, nullable),
                    activo, created_at, updated_at

exam_types        → cups (PK), nombre, descripcion, activo, created_at, updated_at

exam_parameters   → id (PK), cups (FK), codigo, nombre, unidad,
                    valor_min_ref, valor_max_ref, tipo_resultado, etiqueta_booleano,
                    sexo, edad_min, edad_max, obligatorio, orden, activo,
                    created_at, updated_at

exam_parameter_ranges → id (PK), parameter_id (FK), reactivo, valor_min_ref, valor_max_ref,
                        sexo, edad_min, edad_max, activo, created_at, updated_at

lab_result_values → id (PK), lab_result_id (FK), parameter_id (FK),
                    valor_numerico, valor_texto, valor_booleano, reactivo, flag, created_at

result_email_log  → id (PK), id_solicitud_key (FK), email_destino,
                    estado (enviado|error), error_mensaje, enviado_at

patient_access_tokens → id (PK), patient_id (FK → patients), codigo_hash,
                        expires_at, usado, created_at
```

Scripts DDL: `docs/schema.sql`, `docs/schema_auth.sql`, `database/schema_exam_catalog.sql`, `database/schema_health_centers.sql`, `database/schema_param_ranges.sql`, `database/schema_aliado_profile.sql`, `database/schema_pdf_email.sql`, `database/schema_bacteriologos.sql`.

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
2.  POST /auth/register                                 → (admin) crear usuarios del sistema
3.  PUT  /aliados/{id}                                  → (admin) completar perfil del aliado (NIT, email, dirección)
4.  POST /aliados/{id}/logo                             → (admin) subir logotipo del aliado
5.  POST /aliados/{id}/bacteriologos                    → (admin/lab) registrar bacteriólogos del aliado
6.  POST /bacteriologos/{id}/firma                      → (admin/lab) subir firma digital del bacteriólogo
7.  POST /medicos                                       → (admin/lab) registrar médico en el catálogo
8.  POST /health-centers                                → (admin) crear centros de salud
9.  POST /health-centers/{id}/aliados/{aliadoId}        → (admin) asociar aliado a centro
10. POST /orders                                        → crear orden con medicoId o tipoDocumentoMedico+identificacionMedico
11. GET  /patients?q=<nombre>                           → buscar paciente creado automáticamente
12. GET  /orders?estado=pending                         → listar órdenes pendientes (general)
13. GET  /aliados/{id}/orders/pending                   → listar pendientes de un aliado específico
14. GET  /orders/{id}                                   → ver detalle de una orden (incluye medicoId)
15. POST /orders/{id}/send                              → enviar una orden al laboratorio externo
16. POST /aliados/{id}/orders/mark-sent                 → marcar múltiples órdenes como enviadas
17. GET  /orders?estado=sent                            → verificar órdenes enviadas
18. POST /results                                       → registrar resultado con bacteriologoId
19. GET  /orders/{id}/results                           → ver resultados estructurados con flags y bacteriólogo
20. GET  /orders/{id}/results/pdf                       → descargar PDF con firma del bacteriólogo
21. POST /orders/{id}/results/send-email                → enviar informe PDF por correo al paciente
22. GET  /orders?estado=completed                       → confirmar órdenes completadas

--- Flujo portal de pacientes (passwordless) ---
23. POST /patient-portal/request-access                 → paciente solicita código OTP (enviado al email)
24. POST /patient-portal/verify                         → paciente verifica OTP → recibe JWT de paciente (1 h)
25. GET  /patient-portal/results                        → paciente consulta sus órdenes completadas
26. GET  /patient-portal/results/{idSolicitudKey}/pdf   → paciente descarga PDF de una orden
```

---

## Configuración de correo

Para habilitar el envío de resultados por email (`POST /orders/{id}/results/send-email`) se requieren las siguientes variables de entorno:

| Variable | Descripción | Ejemplo |
|---|---|---|
| `MAIL_HOST` | Servidor SMTP | `smtp.gmail.com` |
| `MAIL_PORT` | Puerto SMTP (`587` = TLS, `465` = SSL) | `587` |
| `MAIL_USERNAME` | Usuario SMTP (correo remitente) | `lab@clinica.com` |
| `MAIL_PASSWORD` | Contraseña o App Password | `xxxx xxxx xxxx xxxx` |
| `MAIL_FROM_NAME` | Nombre del remitente | `Laboratorio Clínico` |

Agregar al `docker-compose.yml` en la sección `environment` del servicio `app`:

```yaml
MAIL_HOST: smtp.gmail.com
MAIL_PORT: 587
MAIL_USERNAME: lab@clinica.com
MAIL_PASSWORD: tu_app_password
MAIL_FROM_NAME: "Laboratorio Clínico"
```

> Para Gmail se recomienda usar una **App Password** (contraseña de aplicación) en lugar de la contraseña de la cuenta. Se genera en: Cuenta Google → Seguridad → Verificación en dos pasos → Contraseñas de aplicaciones.

---

## Datos de prueba

El script `database/seed.php` carga usuarios, aliados y órdenes de prueba.

```bash
docker compose exec app php /app/database/seed.php
```

### Usuarios

| Usuario | Contraseña | Rol | Aliados asignados |
|---|---|---|---|
| `admin` | `admin123` | `admin` | — (ve todo) |
| `lab_op` | `lab_op123` | `lab_operator` | — (ve todo) |
| `aliado_norte` | `aliado_norte123` | `aliado_operator` | ALIADO-001 |
| `aliado_sur` | `aliado_sur123` | `aliado_operator` | ALIADO-002 |
| `viewer` | `viewer123` | `viewer` | — |

### Aliados

| ID | Nombre |
|---|---|
| `ALIADO-001` | Laboratorio Clínico Norte |
| `ALIADO-002` | Laboratorio Clínico Sur |

### Órdenes de prueba

| ID | Estado | Resultados | Aliado | Visible por |
|---|---|:---:|---|---|
| `SOL-2025-0001` | `completed` | ✅ Hemograma + Glucosa | ALIADO-001 | admin, aliado_norte |
| `SOL-2025-0003` | `completed` | ✅ Creatinina + Urea | ALIADO-001 | admin, aliado_norte |
| `SOL-2025-0006` | `completed` | ✅ TSH + T4 Libre | ALIADO-001 | admin, aliado_norte |
| `SOL-2025-0004` | `sent` | ❌ | ALIADO-001 | admin, aliado_norte |
| `SOL-2025-0007` | `pending` | ❌ | ALIADO-001 | admin, aliado_norte |
| `SOL-2025-0002` | `completed` | ✅ Perfil Lipídico | ALIADO-002 | admin, aliado_sur |
| `SOL-2025-0008` | `completed` | ✅ Parcial de Orina | ALIADO-002 | admin, aliado_sur |
| `SOL-2025-0009` | `completed` | ✅ HbA1c + Glucosa | ALIADO-002 | admin, aliado_sur |
| `SOL-2025-0010` | `completed` | ✅ PCR + Perfil Lipídico | ALIADO-002 | admin, aliado_sur |
| `SOL-2025-0005` | `sent` | ❌ | ALIADO-002 | admin, aliado_sur |
| `SOL-2025-0011` | `pending` | ❌ | ALIADO-002 | admin, aliado_sur |
| `SOL-2025-0012` | `pending` | ❌ | ALIADO-002 | admin, aliado_sur |

---

## Errores comunes

| Situación | Código | Mensaje |
|---|---|---|
| Token ausente | `401` | `Token de autorización ausente` |
| Token expirado o inválido | `401` | `Token inválido o expirado: ...` |
| Rol insuficiente | `403` | `No tienes permisos para esta acción` |
| Credenciales incorrectas | `401` | `Credenciales inválidas` |
| Campo requerido faltante | `422` | `Campo requerido faltante: <campo>` |
| Username duplicado | `422` | `El username ya está en uso` |
| Email duplicado | `422` | `El email ya está en uso` |
| Rol inválido al registrar | `422` | `Rol inválido: <rol>` |
| Centro de salud no encontrado | `404` | `Centro de salud no encontrado: <id>` |
| Paciente no encontrado | `404` | `Paciente no encontrado: <id>` |
| Aliado no encontrado | `404` | `Aliado no encontrado: <id>` |
| Orden no encontrada | `404` | `Orden no encontrada` |
| Estado de filtro inválido | `422` | `Estado inválido. Valores permitidos: pending, sent, completed` |
| Formato de fecha inválido | `422` | `fecha_desde debe tener formato YYYY-MM-DD` |
| Sin campo `resultado` | `422` | `El resultado debe incluir un valor de resultado principal.` |
| Parámetros obligatorios faltantes | `422` | `Faltan parámetros obligatorios: hb (Hemoglobina), ...` |
| Array `orders` ausente o inválido | `422` | `El campo "orders" es requerido y debe ser un array de idSolicitudKey` |
| Array `orders` vacío | `422` | `El array "orders" no contiene identificadores válidos` |
| Tipo de examen duplicado | `422` | `Ya existe un tipo de examen con CUPS: <cups>` |
| Tipo de examen no encontrado | `404` | `Tipo de examen no encontrado: <cups>` |
| Parámetro no encontrado | `404` | `Parámetro no encontrado: <id>` |
| Rango no encontrado | `404` | `Rango no encontrado: <id>` |
| Sexo inválido en parámetro | `422` | `Valor de sexo inválido: 'X'. Use 'M', 'F' o '*'.` |
| Tipo resultado inválido | `422` | `Tipo de resultado inválido: 'x'. Use: numerico, texto, booleano` |
| Etiqueta booleano requerida | `422` | `Para tipo 'booleano' se requiere etiquetaBooleano...` |
| Rango solo para numérico | `422` | `Los rangos por reactivo solo aplican a parámetros de tipo numérico.` |
| Bacteriólogo no encontrado | `404` | `Bacteriólogo no encontrado: <id>` |
| Bacteriólogo duplicado | `422` | `Ya existe un bacteriólogo con ese documento` |
| Logo formato inválido | `422` | `Formato no permitido. Use PNG o JPG` |
| Logo tamaño excedido | `422` | `El archivo supera el tamaño máximo de 2 MB` |
| Sin resultados para PDF | `422` | `La orden no tiene resultados registrados` |
| Paciente sin email | `422` | `El paciente no tiene email registrado. Proporcione un email en el body.` |
| Email inválido | `422` | `Email inválido: <email>` |
| Error envío correo | `500` | `Error al enviar el correo: <detalle>` |
| Lab externo no responde | `502` | `Fallo al enviar orden: <detalle>` |
| OTP inválido o expirado | `401` | `Código inválido o expirado.` |
| Médico no encontrado | `422` | `Médico no encontrado o inactivo: <id>` |
| Médico no encontrado por doc | `422` | `Médico no encontrado con documento CC <id>` |
| Médico duplicado | `422` | `Ya existe un médico con ese documento` |
| Usuario ya vinculado a médico | `422` | `Ese usuario ya tiene un médico asociado` || Token de paciente ausente | `401` | `Token de paciente ausente` |
| Token de paciente inválido | `401` | `Token de paciente inválido o expirado: ...` |
| Orden no pertenece al paciente | `403` | `No tienes acceso a esta orden` |
| Orden no completada (portal) | `422` | `Los resultados de esta orden aún no están disponibles` |
