# API Clinical Lab

Módulo de laboratorio clínico basado en PHP 8.2 y MySQL siguiendo arquitectura hexagonal (dominio, aplicación y puertos/adaptadores). Incluye autenticación segura y consideraciones OWASP para el flujo completo de órdenes y resultados.

## Componentes de dominio
- **LabOrder** y **LabOrderDetail** representan la solicitud y sus exámenes.
- **LabResult** almacena valores estructurados y adjuntos.
- **Repositorios de dominio** definen contratos para persistencia y consumo de APIs.

## Casos de uso
- **CreateLabOrderUseCase**: crea la orden y sus detalles a partir de DTOs validados.
- **SendLabOrderUseCase**: envía la orden al laboratorio aliado vía API segura (API Key + JWT de corta duración) y marca fecha de envío/estado.
- **ValidateAndStoreResultUseCase**: valida estructura mínima del resultado, persiste valores y actualiza el progreso al 100%.

## Adaptadores de infraestructura
- **PDO (MySQL)** para persistencia (`MySqlLabOrderRepository`, `MySqlLabOrderDetailRepository`, `MySqlLabResultRepository`). Se usan *prepared statements* y JSON para resultados.
- **ExternalLabApiClient**: cliente HTTP que firma la petición con API Key y JWT (HS256) y envía el payload JSON al endpoint `/orders`.
- **PdoConnectionFactory**: crea conexiones tomando las variables de entorno (`config/.env.example`).

## Seguridad y OWASP
- Uso de JWT efímero (5 minutos) y API Key almacenada de forma protegida.
- TLS obligatorio (`CURLOPT_SSL_VERIFYPEER=true`).
- Preparación de sentencias para prevenir inyección SQL y codificación JSON estricta.
- Loggear y auditar solicitudes/respuestas en los adaptadores según el despliegue del gateway.

## Esquema de base de datos
Consulta `docs/schema.sql` para crear tablas de usuarios, órdenes, detalles y resultados. Incluye claves foráneas, control de versiones mediante `updated_at` y columnas para adjuntos/JSON.

## Ejemplo de inicialización
```bash
cp config/.env.example .env
composer install
# Crear tablas
mysql -u$DB_USERNAME -p$DB_PASSWORD $DB_NAME < docs/schema.sql
```

## Próximos pasos sugeridos
- Exponer controladores HTTP (framework al gusto) que instancien los casos de uso con dependencias reales.
- Añadir colas para reintentos automáticos y un listener de webhook `/results` para el aliado.
- Implementar validaciones adicionales de datos clínicos y conversión de unidades en el dominio.
