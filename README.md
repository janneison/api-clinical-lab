# API Clinical Lab

Módulo de laboratorio clínico en PHP 8.2 + MySQL con arquitectura hexagonal y Slim 4 como capa HTTP.

## Estructura

```
public/          ← DocumentRoot del hosting (solo esta carpeta es pública)
  index.php      ← Punto de entrada
  .htaccess      ← Rewrite rules para Slim
src/
  Application/   ← Casos de uso y DTOs
  Domain/        ← Entidades, interfaces de repositorios
  Infrastructure/← PDO, HTTP client, controladores, middleware
config/
  .env           ← Variables de entorno (NO subir a git)
  .env.example   ← Plantilla
docs/
  schema.sql     ← Tablas MySQL
```

## Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/orders` | Crear orden de laboratorio |
| GET | `/orders/{id}` | Consultar orden |
| POST | `/orders/{id}/send` | Enviar al laboratorio externo |
| POST | `/results` | Registrar resultado |

Todos los endpoints requieren el header `X-API-KEY`.

## Instalación local (con devcontainer)

```bash
# Abrir en VS Code → "Reopen in Container"
composer install
cp config/.env.example config/.env
# Editar config/.env con tus credenciales
mysql -u$DB_USERNAME -p$DB_PASSWORD $DB_NAME < docs/schema.sql
php -S localhost:8080 -t public
```

## Despliegue en cPanel

1. Subir todo el proyecto **excepto** `vendor/` y `config/.env`
2. En cPanel → **Terminal** o **SSH**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Crear `config/.env` en el servidor con las credenciales reales
4. En cPanel → **Dominios** → apuntar el `Document Root` a la carpeta `public/`
5. Verificar que `mod_rewrite` esté activo (el `.htaccess` ya está configurado)
6. Crear la base de datos en cPanel → **MySQL Databases** y ejecutar `docs/schema.sql`

## Variables de entorno (`config/.env`)

```env
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=clinical_lab
DB_USERNAME=api_user
DB_PASSWORD=change_me

EXTERNAL_LAB_BASE_URL=https://aliado-lab.local
EXTERNAL_LAB_API_KEY=generated_api_key
EXTERNAL_LAB_JWT_SECRET=change_this_secret
```
