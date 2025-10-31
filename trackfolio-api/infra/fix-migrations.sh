#!/bin/bash
echo "🔧 Verificando y ejecutando migraciones..."

cd /home/xabierlegasa/xabi/code/trackfolio/trackfolio-api/infra

echo "1. Verificando conexión a base de datos..."
docker-compose exec -T app php artisan db:show || {
    echo "❌ No se puede conectar a la base de datos"
    exit 1
}

echo "2. Verificando estado de migraciones..."
docker-compose exec -T app php artisan migrate:status || echo "No hay migraciones ejecutadas"

echo "3. Ejecutando migraciones..."
docker-compose exec -T app php artisan migrate --force

echo "✅ Migraciones completadas"

