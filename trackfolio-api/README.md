# Trackfolio API

REST API built with Laravel 12 for Trackfolio. This is an API-only application without frontend, designed to be consumed by a SPA (Single Page Application).


## Usefull commands

### Start Docker
```bash
cd infra
docker-compose up --build
# api should be available here: http://localhost:8080/
```


### Run migrations

```bash
cd infra
docker-compose exec app php artisan migrate
```

### Clea route and config cache
```bash
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan config:clear  
```

## Features

- **Laravel 12** with PHP 8.4
- **REST API** without frontend
- **MySQL 8.0** as database
- **Redis** for cache and sessions
- **Docker** for development and testing
- **CORS** configured for SPA
- **JSON responses** for all API routes

## Requirements

- Docker and Docker Compose
- Git

## Initial Setup

### 1. Clone and configure environment

```bash
# Clone the repository (if applicable)
git clone <repository-url>
cd trackfolio-api

# Copy environment file
cp .env.example .env
```

The `.env.example` file already contains Docker-compatible configuration:
- `DB_CONNECTION=mysql`
- `DB_HOST=db` (Docker service name)
- `REDIS_HOST=redis` (Docker service name)
- `APP_URL=http://localhost:8080`

**Note:** If running without Docker, update:
- `DB_HOST=127.0.0.1` or `DB_HOST=localhost`
- `REDIS_HOST=127.0.0.1` or `REDIS_HOST=localhost`

### 2. Start with Docker

```bash
cd infra
docker-compose up -d --build
```

### 3. Install dependencies

```bash
docker-compose exec app composer install
```

### 4. Generate application key

```bash
docker-compose exec app php artisan key:generate
```

### 5. Set up storage permissions

```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### 6. Run migrations

```bash
docker-compose exec app php artisan migrate
```

### 7. Access the API

- **API:** http://localhost:8080
- **Database:** localhost:3306
- **Redis:** localhost:6379

## Docker Services

The Docker configuration includes the following services:

- **app** - PHP 8.4-FPM with Laravel application
- **nginx** - Web server (port 8080)
- **db** - MySQL 8.0 (port 3306)
- **redis** - Cache and session storage (port 6379)

## Useful Commands

### Docker

#### Start containers:
```bash
cd infra
docker-compose up -d
```

#### Stop containers:
```bash
docker-compose down
```

#### View logs:
```bash
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f db
docker-compose logs -f redis
```

#### Rebuild containers:
```bash
docker-compose down
docker-compose up -d --build
```

### Laravel (inside Docker)

#### Run Artisan commands:
```bash
docker-compose exec app php artisan <command>
```

#### Run Composer:
```bash
docker-compose exec app composer <command>
```

#### Access container shell:
```bash
docker-compose exec app sh
```

#### Run migrations:
```bash
docker-compose exec app php artisan migrate
```

#### Run tests:
```bash
docker-compose exec app php artisan test
```

#### Clear caches:
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

## Docker System Testing

### Quick Verification

1. **Check container status:**
   ```bash
   cd infra
   docker-compose ps
   ```
   All services should show "Up" status.

2. **Test database connection:**
   ```bash
   docker-compose exec db mysql -u trackfolio -proot -e "SELECT VERSION();"
   ```

3. **Test Redis connection:**
   ```bash
   docker-compose exec redis redis-cli ping
   ```
   Should respond: `PONG`

4. **Test API endpoint:**
   ```bash
   curl http://localhost:8080
   ```
   Should return JSON with API information.

5. **Test health check:**
   ```bash
   curl http://localhost:8080/up
   ```
   Should return: `{"status":"ok"}`

### Complete Testing Script

```bash
#!/bin/bash
cd infra

echo "Verifying Docker configuration..."

echo "1. Container status:"
docker-compose ps

echo -e "\n2. Testing database connection:"
docker-compose exec -T db mysql -u trackfolio -proot -e "SELECT 'Database OK' AS status;" 2>&1 | grep -q "Database OK" && echo "Connection successful" || echo "Connection failed"

echo -e "\n3. Testing Redis connection:"
docker-compose exec -T redis redis-cli ping | grep -q "PONG" && echo "Connection successful" || echo "Connection failed"

echo -e "\n4. Testing API endpoint:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080)
if [ "$HTTP_CODE" == "200" ]; then
    echo "API responding (HTTP $HTTP_CODE)"
else
    echo "API returned HTTP $HTTP_CODE"
fi

echo -e "\n5. Testing health check:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/up)
if [ "$HTTP_CODE" == "200" ]; then
    echo "Health check passed (HTTP $HTTP_CODE)"
else
    echo "Health check failed (HTTP $HTTP_CODE)"
fi

echo -e "\nAll tests completed!"
```

## Service Access

### MySQL Database

- **Host:** localhost (from host machine) or `db` (from containers)
- **Port:** 3306
- **Database:** trackfolio (default)
- **Username:** trackfolio (default)
- **Password:** root (default)
- **Root Password:** root (default)

Connect with MySQL client:
```bash
mysql -h 127.0.0.1 -P 3306 -u trackfolio -p trackfolio
```

### Redis

- **Host:** localhost (from host machine) or `redis` (from containers)
- **Port:** 6379
- **No password** by default

Test connection:
```bash
redis-cli -h localhost -p 6379
```

## Environment Configuration

The `.env.example` file in the root directory contains all necessary environment variables pre-configured for Docker:

- `DB_CONNECTION=mysql` - MySQL database
- `DB_HOST=db` - Docker service name (use `127.0.0.1` or `localhost` for local development)
- `REDIS_HOST=redis` - Docker service name (use `127.0.0.1` or `localhost` for local development)
- `APP_URL=http://localhost:8080` - API URL

### CORS

CORS configuration is pre-configured to allow requests from:
- `http://localhost:3000` (React/Vite default)
- `http://localhost:5173` (Vite default)
- `http://127.0.0.1:3000`
- `http://127.0.0.1:5173`

You can customize allowed origins in `.env`:
```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://yourdomain.com
```

## Project Structure

```
trackfolio-api/
├── app/                 # Application logic
├── bootstrap/           # Bootstrap files
├── config/             # Configuration files
├── database/           # Migrations and seeders
├── infra/              # Docker configuration
│   ├── docker-compose.yml
│   ├── Dockerfile
│   ├── nginx/
│   └── mysql/
├── public/             # Public entry point
├── resources/          # Resources (empty - API only)
├── routes/            # Route definitions
│   ├── api.php        # API routes
│   └── web.php        # Web routes (JSON response)
├── storage/            # File storage
└── tests/             # Automated tests
```

## Troubleshooting

### Permission Issues

If you encounter permission issues with files:
```bash
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 775 /var/www/storage
```

### Database Connection Issues

- Verify that the database service is healthy:
  ```bash
  docker-compose ps
  ```
- Wait a few seconds for the database to be ready before running migrations
- Verify that `.env` has `DB_HOST=db` (when using Docker)
- Check logs: `docker-compose logs db`

### Port Conflicts

If ports 8080, 3306, or 6379 are already in use, modify `infra/docker-compose.yml`:
```yaml
ports:
  - "8081:80"  # Change 8080 to 8081
```

### API returns 502 Bad Gateway

- Check app container logs: `docker-compose logs app`
- Verify PHP-FPM is running: `docker-compose exec app php-fpm -v`

### Containers won't start

- Check what's wrong: `docker-compose logs`
- Rebuild from scratch:
  ```bash
  docker-compose down
  docker-compose up -d --build
  ```

## Development

### Running without Docker

If you prefer to run without Docker, you'll need:

1. PHP 8.4 with extensions: pdo_mysql, mbstring, redis, gd, opcache
2. MySQL 8.0
3. Redis 7+
4. Composer

Update `.env`:
- `DB_HOST=127.0.0.1`
- `REDIS_HOST=127.0.0.1`

Then run:
```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

## Production Considerations

**Warning:** This configuration is designed for development. For production:

1. Use stronger database passwords
2. Set `APP_DEBUG=false`
3. Configure proper SSL/TLS
4. Use environment-specific configurations
5. Set up proper backup strategies
6. Configure proper firewall rules
7. Use Docker secrets for sensitive data
8. Enable Redis authentication if needed
9. Optimize PHP configuration (opcache)
10. Configure rate limiting
11. Implement logging and monitoring

## License

This project is licensed under the MIT license.
