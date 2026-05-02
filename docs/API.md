# API Clinical Lab — Documentación

## Índice

- [Descripción general](#descripción-general)
- [Autenticación](#autenticación)
- [Roles y permisos](#roles-y-permisos)
- [Códigos de respuesta](#códigos-de-respuesta)
- [Endpoints](#endpoints)
  - [Auth](#auth)
  - [Órdenes de laboratorio](#órdenes-de-laboratorio)
  - [Resultados de laboratorio](#resultados-de-laboratorio)
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
| `GET /orders` | ✅ | ✅ | ✅ * | ✅ * |
| `POST /orders` | ✅ | ✅ | ❌ | ❌ |
| `GET /orders/{id}` | ✅ | ✅ | ✅ | ✅ |
| `POST /orders/{id}/send` | ✅ | ✅ | ❌ | ❌ |
| `POST /results` | ✅ | ✅ | ✅ | ❌ |

> \* `aliado_operator` y `viewer` solo ven las órdenes de los aliados asignados a su usuario.

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
  "detalles": [
    {
      "cups": "903820",
      "nombreDelLaboratorio": "Hemograma Completo",
      "estadoDelResultado": "FINAL",
      "fechaResultado": "2025-04-10 14:00:00"
    },
    {
      "cups": "904010",
      "nombreDelLaboratorio": "Glucosa en Ayunas",
      "estadoDelResultado": "FINAL",
      "fechaResultado": "2025-04-10 13:30:00"
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

## Modelos de datos

### Esquema de base de datos

```
roles             → id, name
aliados           → id, nombre, activo, created_at
users             → id, username, email, password_hash, role_id, activo, created_at, updated_at
user_aliado       → user_id, aliado_id  (N:M)

lab_orders        → id_solicitud_key (PK), id_admision, id_atencion, tipo_documento,
                    identificacion, nombre_paciente, sexo, fecha_nacimiento,
                    centro_salud, fecha_orden, medico_ordena, numero_autorizacion,
                    id_aliado, fecha_envio, porc_ejecucion, estado_orden,
                    created_at, updated_at

lab_order_details → id (PK), id_solicitud_key (FK), id_admision, cups,
                    nombre_laboratorio, fecha_toma_muestra, metodo, reactivo,
                    invima, estado_resultado, fecha_resultado,
                    tipo_id_bacteriologo, id_bacteriologo, created_at

lab_results       → id (PK), id_solicitud_key (FK), cups, values_json,
                    attachment_path, received_at, created_at
```

Ver `docs/schema.sql` y `docs/schema_auth.sql` para el DDL completo.

### Ciclo de vida de una orden

```
[POST /orders]          → estado: pending
[POST /orders/{id}/send] → estado: sent
[POST /results]          → estado: completed  (porc_ejecucion = 100)
```

---

## Flujo completo

```
1. POST /auth/login              → obtener JWT
2. POST /auth/register           → (admin) crear usuarios del sistema
3. POST /orders                  → crear orden con exámenes
4. GET  /orders?estado=pending   → listar órdenes pendientes
5. GET  /orders/{id}             → ver detalle de una orden
6. POST /orders/{id}/send        → enviar al laboratorio externo
7. GET  /orders?estado=sent      → verificar órdenes enviadas
8. POST /results                 → registrar resultado recibido
9. GET  /orders?estado=completed → confirmar órdenes completadas
```

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
| Aliado no encontrado | `422` | `Aliado no encontrado: <id>` |
| Orden no encontrada | `404` | `Orden no encontrada` |
| Estado de filtro inválido | `422` | `Estado inválido. Valores permitidos: pending, sent, completed` |
| Formato de fecha inválido | `422` | `fecha_desde debe tener formato YYYY-MM-DD` |
| Sin campo `resultado` | `422` | `El resultado debe incluir un valor de resultado principal.` |
| Lab externo no responde | `502` | `Fallo al enviar orden: <detalle>` |
