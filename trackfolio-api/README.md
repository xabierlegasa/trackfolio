# Trackfolio API

API REST construida con Laravel 12 para Trackfolio. Esta es una API-only application sin frontend, diseñada para ser consumida por una SPA (Single Page Application).

## Características

- **Laravel 12** con PHP 8.4
- **API REST** sin frontend
- **MySQL 8.0** como base de datos
- **Redis** para cache y sesiones
- **Docker** para desarrollo y testing
- **CORS** configurado para SPA
- **Respuestas JSON** para todas las rutas API

## Requisitos

- Docker y Docker Compose
- Git

## Configuración Inicial

### 1. Clonar y configurar entorno

```bash
# Clonar el repositorio (si aplica)
git clone <repository-url>
cd trackfolio-api

# Copiar archivo de entorno
cp .env.example .env
```

El archivo `.env.example` ya contiene configuración compatible con Docker:
- `DB_CONNECTION=mysql`
- `DB_HOST=db` (nombre del servicio Docker)
- `REDIS_HOST=redis` (nombre del servicio Docker)
- `APP_URL=http://localhost:8080`

**Nota:** Si ejecutas sin Docker, actualiza:
- `DB_HOST=127.0.0.1` o `DB_HOST=localhost`
- `REDIS_HOST=127.0.0.1` o `REDIS_HOST=localhost`

### 2. Iniciar con Docker

```bash
cd infra
docker-compose up -d --build
```

### 3. Instalar dependencias

```bash
docker-compose exec app composer install
```

### 4. Generar clave de aplicación

```bash
docker-compose exec app php artisan key:generate
```

### 5. Configurar permisos de almacenamiento

```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### 6. Ejecutar migraciones

```bash
docker-compose exec app php artisan migrate
```

### 7. Acceder a la API

- **API:** http://localhost:8080
- **Base de datos:** localhost:3306
- **Redis:** localhost:6379

## Servicios Docker

La configuración Docker incluye los siguientes servicios:

- **app** - PHP 8.4-FPM con aplicación Laravel
- **nginx** - Servidor web (puerto 8080)
- **db** - MySQL 8.0 (puerto 3306)
- **redis** - Cache y almacenamiento de sesiones (puerto 6379)

## Comandos Útiles

### Docker

#### Iniciar contenedores:
```bash
cd infra
docker-compose up -d
```

#### Detener contenedores:
```bash
docker-compose down
```

#### Ver logs:
```bash
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f db
docker-compose logs -f redis
```

#### Reconstruir contenedores:
```bash
docker-compose down
docker-compose up -d --build
```

### Laravel (dentro de Docker)

#### Ejecutar comandos Artisan:
```bash
docker-compose exec app php artisan <comando>
```

#### Ejecutar Composer:
```bash
docker-compose exec app composer <comando>
```

#### Acceder al shell del contenedor:
```bash
docker-compose exec app sh
```

#### Ejecutar migraciones:
```bash
docker-compose exec app php artisan migrate
```

#### Ejecutar tests:
```bash
docker-compose exec app php artisan test
```

#### Limpiar cachés:
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

## Pruebas del Sistema Docker

### Verificación Rápida

1. **Verificar estado de contenedores:**
   ```bash
   cd infra
   docker-compose ps
   ```
   Todos los servicios deben mostrar estado "Up".

2. **Probar conexión a base de datos:**
   ```bash
   docker-compose exec db mysql -u trackfolio -psecret -e "SELECT VERSION();"
   ```

3. **Probar conexión a Redis:**
   ```bash
   docker-compose exec redis redis-cli ping
   ```
   Debe responder: `PONG`

4. **Probar endpoint de API:**
   ```bash
   curl http://localhost:8080
   ```
   Debe devolver JSON con información de la API.

5. **Probar health check:**
   ```bash
   curl http://localhost:8080/up
   ```
   Debe devolver: `{"status":"ok"}`

### Script de Pruebas Completo

```bash
#!/bin/bash
cd infra

echo "🔍 Verificando configuración Docker..."

echo "1. Estado de contenedores:"
docker-compose ps

echo -e "\n2. Probando conexión a base de datos:"
docker-compose exec -T db mysql -u trackfolio -psecret -e "SELECT 'Database OK' AS status;" 2>&1 | grep -q "Database OK" && echo "✅ Conexión exitosa" || echo "❌ Fallo en conexión"

echo -e "\n3. Probando conexión a Redis:"
docker-compose exec -T redis redis-cli ping | grep -q "PONG" && echo "✅ Conexión exitosa" || echo "❌ Fallo en conexión"

echo -e "\n4. Probando endpoint de API:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080)
if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ API respondiendo (HTTP $HTTP_CODE)"
else
    echo "❌ API devolvió HTTP $HTTP_CODE"
fi

echo -e "\n5. Probando health check:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/up)
if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Health check exitoso (HTTP $HTTP_CODE)"
else
    echo "❌ Health check falló (HTTP $HTTP_CODE)"
fi

echo -e "\n✅ Pruebas completadas!"
```

## Acceso a Servicios

### Base de Datos MySQL

- **Host:** localhost (desde máquina host) o `db` (desde contenedores)
- **Puerto:** 3306
- **Base de datos:** trackfolio (por defecto)
- **Usuario:** trackfolio (por defecto)
- **Contraseña:** secret (por defecto)
- **Root Password:** root (por defecto)

Conectar con cliente MySQL:
```bash
mysql -h 127.0.0.1 -P 3306 -u trackfolio -p trackfolio
```

### Redis

- **Host:** localhost (desde máquina host) o `redis` (desde contenedores)
- **Puerto:** 6379
- **Sin contraseña** por defecto

Probar conexión:
```bash
redis-cli -h localhost -p 6379
```

## Configuración de Entorno

El archivo `.env.example` en el directorio raíz contiene todas las variables de entorno necesarias pre-configuradas para Docker:

- `DB_CONNECTION=mysql` - Base de datos MySQL
- `DB_HOST=db` - Nombre del servicio Docker (usar `127.0.0.1` o `localhost` para desarrollo local)
- `REDIS_HOST=redis` - Nombre del servicio Docker (usar `127.0.0.1` o `localhost` para desarrollo local)
- `APP_URL=http://localhost:8080` - URL de la API

### CORS

La configuración CORS está pre-configurada para permitir solicitudes desde:
- `http://localhost:3000` (React/Vite default)
- `http://localhost:5173` (Vite default)
- `http://127.0.0.1:3000`
- `http://127.0.0.1:5173`

Puedes personalizar los orígenes permitidos en `.env`:
```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://tudominio.com
```

## Estructura del Proyecto

```
trackfolio-api/
├── app/                 # Lógica de la aplicación
├── bootstrap/           # Archivos de arranque
├── config/              # Archivos de configuración
├── database/            # Migraciones y seeders
├── infra/               # Configuración Docker
│   ├── docker-compose.yml
│   ├── Dockerfile
│   ├── nginx/
│   └── mysql/
├── public/              # Punto de entrada público
├── resources/           # Recursos (vacío - API only)
├── routes/              # Definición de rutas
│   ├── api.php         # Rutas API
│   └── web.php         # Rutas web (JSON response)
├── storage/             # Almacenamiento de archivos
└── tests/               # Tests automatizados
```

## Solución de Problemas

### Problemas de Permisos

Si encuentras problemas de permisos con archivos:
```bash
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 775 /var/www/storage
```

### Problemas de Conexión a Base de Datos

- Verifica que el servicio de base de datos esté saludable:
  ```bash
  docker-compose ps
  ```
- Espera unos segundos a que la base de datos esté lista antes de ejecutar migraciones
- Verifica que `.env` tenga `DB_HOST=db` (cuando uses Docker)
- Revisa los logs: `docker-compose logs db`

### Conflictos de Puertos

Si los puertos 8080, 3306, o 6379 están en uso, modifica `infra/docker-compose.yml`:
```yaml
ports:
  - "8081:80"  # Cambiar 8080 a 8081
```

### API devuelve 502 Bad Gateway

- Revisa logs del contenedor app: `docker-compose logs app`
- Verifica que PHP-FPM esté ejecutándose: `docker-compose exec app php-fpm -v`

### Contenedores no inician

- Revisa qué está mal: `docker-compose logs`
- Reconstruye desde cero:
  ```bash
  docker-compose down
  docker-compose up -d --build
  ```

## Desarrollo

### Ejecutar sin Docker

Si prefieres ejecutar sin Docker, necesitarás:

1. PHP 8.4 con extensiones: pdo_mysql, mbstring, redis, gd, opcache
2. MySQL 8.0
3. Redis 7+
4. Composer

Actualiza `.env`:
- `DB_HOST=127.0.0.1`
- `REDIS_HOST=127.0.0.1`

Luego ejecuta:
```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

## Consideraciones para Producción

⚠️ **Advertencia:** Esta configuración está diseñada para desarrollo. Para producción:

1. Usar contraseñas más seguras para base de datos
2. Establecer `APP_DEBUG=false`
3. Configurar SSL/TLS apropiado
4. Usar configuraciones específicas del entorno
5. Establecer estrategias de backup adecuadas
6. Configurar reglas de firewall apropiadas
7. Usar Docker secrets para datos sensibles
8. Habilitar autenticación Redis si es necesario
9. Optimizar configuración de PHP (opcache)
10. Configurar rate limiting
11. Implementar logging y monitoreo

## Licencia

Este proyecto está bajo la licencia MIT.
