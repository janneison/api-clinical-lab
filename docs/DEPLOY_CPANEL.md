# Despliegue en cPanel — API Clinical Lab (vía FTP)

## Requisitos previos

Confirmá en tu cPanel que tenés disponible:

- **PHP 8.2** o superior (en *Select PHP Version*)
- **Extensiones activas:** `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `gd`
- **MySQL** ya configurado con la BD y los scripts ejecutados
- **Cliente FTP:** FileZilla (recomendado) o similar
- **Composer** instalado en tu máquina local

---

## Paso 1 — Preparar los archivos localmente

En tu máquina, desde la raíz del proyecto, ejecutá:

```bash
composer install --no-dev --optimize-autoloader
```

Esto genera la carpeta `vendor/` con todas las dependencias de producción.
**No subas el proyecto sin este paso** — sin `vendor/` la API no funciona.

---

## Paso 2 — Ajustar rutas en `public/index.php` antes de subir

El `index.php` usa rutas relativas que funcionan en Docker pero no en cPanel.
Editá el archivo `public/index.php` localmente y actualizá estas dos líneas:

**Línea del autoload:**
```php
// Antes
require __DIR__ . '/../vendor/autoload.php';

// Después
require '/home/tuusuario/api-clinical-lab/vendor/autoload.php';
```

**Línea del .env:**
```php
// Antes
$envFile = __DIR__ . '/../config/.env';

// Después
$envFile = '/home/tuusuario/api-clinical-lab/config/.env';
```

> Reemplazá `tuusuario` por tu usuario real de cPanel.  
> Si no sabés tu usuario, lo encontrás en cPanel → arriba a la derecha donde dice *"Hola, tuusuario"*.

---

## Paso 3 — Configurar el `.env` de producción

Editá el archivo `config/.env` localmente con los datos reales del servidor:

```ini
APP_DEBUG=false

# En cPanel el host de MySQL es localhost
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tuusuario_clinical_lab
DB_USERNAME=tuusuario_api_user
DB_PASSWORD=tu_password_real

# Secreto JWT — mínimo 32 caracteres aleatorios
# Podés generar uno en: https://generate-secret.vercel.app/32
JWT_SECRET=cambia_esto_por_un_secreto_largo_y_seguro_2024

# Integración con laboratorio externo
EXTERNAL_LAB_BASE_URL=https://lab-externo.com
EXTERNAL_LAB_API_KEY=tu_api_key
EXTERNAL_LAB_JWT_SECRET=otro_secreto_minimo_32_chars

# Correo SMTP
MAIL_HOST=mail.tudominio.com
MAIL_PORT=587
MAIL_USERNAME=lab@tudominio.com
MAIL_PASSWORD=tu_password_correo
MAIL_FROM_NAME="Laboratorio Clínico"
```

> En cPanel el nombre de la BD y el usuario MySQL llevan el prefijo de tu cuenta,
> por ejemplo: `tuusuario_clinical_lab` y `tuusuario_api_user`.

---

## Paso 4 — Estructura de carpetas a crear en el servidor

Antes de subir archivos, creá esta estructura usando el **File Manager de cPanel**
o directamente desde FileZilla:

```
/home/tuusuario/
├── public_html/                  ← ya existe (document root)
│
└── api-clinical-lab/             ← creá esta carpeta manualmente
    ├── src/
    ├── config/
    ├── vendor/
    └── storage/
        ├── firmas/
        ├── logos/
        └── pdfs/
```

**Cómo crear las carpetas en FileZilla:**
1. Conectate al servidor FTP
2. En el panel derecho (servidor) navegá a `/home/tuusuario/`
3. Clic derecho → *Create directory* → `api-clinical-lab`
4. Entrá a `api-clinical-lab` y creá las subcarpetas: `src`, `config`, `vendor`, `storage`
5. Entrá a `storage` y creá: `firmas`, `logos`, `pdfs`

---

## Paso 5 — Subir archivos con FileZilla

Abrí FileZilla y conectate con tus credenciales FTP de cPanel.

### 5a — Subir el código fuente (carpeta `src/`)

| Origen (tu PC) | Destino (servidor) |
|---|---|
| `src/` | `/home/tuusuario/api-clinical-lab/src/` |

### 5b — Subir las dependencias (carpeta `vendor/`)

| Origen (tu PC) | Destino (servidor) |
|---|---|
| `vendor/` | `/home/tuusuario/api-clinical-lab/vendor/` |

> ⚠️ La carpeta `vendor/` puede tener miles de archivos y tardar varios minutos.
> FileZilla puede procesar transferencias en cola — dejala correr.

### 5c — Subir la configuración

| Origen (tu PC) | Destino (servidor) |
|---|---|
| `config/.env` | `/home/tuusuario/api-clinical-lab/config/.env` |

### 5d — Subir los archivos públicos

| Origen (tu PC) | Destino (servidor) |
|---|---|
| `public/index.php` | `/home/tuusuario/public_html/index.php` |
| `public/.htaccess` | `/home/tuusuario/public_html/.htaccess` |

> Si `public_html` ya tiene un `index.php` del hosting, hacé una copia antes de reemplazarlo.

---

## Paso 6 — Verificar el `.htaccess`

El archivo `public_html/.htaccess` debe tener exactamente este contenido:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

Si la API corre en un **subdirectorio** (ej: `tudominio.com/api/`), agregá `RewriteBase`:

```apache
RewriteEngine On
RewriteBase /api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

---

## Paso 7 — Seleccionar PHP 8.2 en cPanel

1. cPanel → **Select PHP Version**
2. Seleccioná `PHP 8.2` o superior
3. Activá las extensiones requeridas:
   - `pdo`
   - `pdo_mysql`
   - `mbstring`
   - `json`
   - `openssl`
   - `fileinfo`
   - `gd`
4. Guardá los cambios

---

## Paso 8 — Verificar permisos de las carpetas de storage

En cPanel → **File Manager**:

1. Navegá a `/home/tuusuario/api-clinical-lab/storage/`
2. Seleccioná las carpetas `firmas`, `logos`, `pdfs`
3. Clic derecho → *Change Permissions*
4. Asegurate de que tengan permisos **755**

---

## Paso 9 — Verificar que funciona

### Prueba 1 — Endpoint público (sin token)

Abrí en el navegador o Postman:

```
GET https://tudominio.com/auth/password-policy
```

Respuesta esperada:
```json
{
  "policy": "Mínimo 8 caracteres...",
  "minLength": 8,
  "requireUppercase": true
}
```

### Prueba 2 — Login

```http
POST https://tudominio.com/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "Admin1234!"
}
```

Respuesta esperada `200 OK` con el token JWT.

Si devuelve `500`, revisá: cPanel → **Logs** → **Error Logs**.

---

## Resumen visual de la estructura final

```
/home/tuusuario/
│
├── public_html/                      ← accesible desde internet
│   ├── index.php                     ← entry point (rutas apuntan a api-clinical-lab/)
│   └── .htaccess                     ← redirige todo a index.php
│
└── api-clinical-lab/                 ← NO accesible desde internet
    ├── src/                          ← código fuente PHP
    ├── config/
    │   └── .env                      ← credenciales (NUNCA en public_html)
    ├── vendor/                       ← dependencias composer
    └── storage/
        ├── firmas/                   ← firmas digitales de bacteriólogos
        ├── logos/                    ← logos de aliados
        └── pdfs/                     ← informes PDF generados
```

---

## Problemas comunes

| Error | Causa probable | Solución |
|---|---|---|
| `500 Internal Server Error` | Rutas incorrectas en `index.php` | Verificar las rutas absolutas al `autoload.php` y al `.env` en el paso 2 |
| `404` en todas las rutas | mod_rewrite no activo o `.htaccess` ignorado | Verificar que el `.htaccess` se subió correctamente a `public_html/` |
| `SQLSTATE: connection refused` | `DB_HOST` incorrecto | En cPanel el host es `localhost`, no el nombre del contenedor Docker |
| `SQLSTATE: table not found` | Scripts SQL no ejecutados en la BD del hosting | Correr `dbprod.sql` en la BD configurada en cPanel |
| `JWT_SECRET too short` | Secret menor a 32 caracteres | Generarlo en https://generate-secret.vercel.app/32 |
| `Permission denied` al subir logo/firma | Permisos incorrectos en storage | Cambiar a `755` desde File Manager → Change Permissions |
| PDF no se genera | Extensión `gd` no activa | Activarla en *Select PHP Version* → Extensions |
| Correo no se envía | Credenciales SMTP incorrectas | Verificar `MAIL_*` en `.env`; para Gmail usar App Password |
| FileZilla se cuelga subiendo `vendor/` | Demasiados archivos en cola | Subir en partes o aumentar el límite de conexiones simultáneas en FileZilla |

---

## Notas de seguridad

- Nunca subas el archivo `.env` a `public_html/` ni lo incluyas en el repositorio Git
- Cambiá el `JWT_SECRET` por uno único para producción
- Asegurate de que `APP_DEBUG=false` en producción para no exponer stack traces en los errores
- El usuario de BD debe tener solo los permisos necesarios: `SELECT`, `INSERT`, `UPDATE`, `DELETE` — sin `DROP` ni `CREATE`
