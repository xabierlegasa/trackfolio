#!/bin/bash
echo "🔧 Corrigiendo credenciales de base de datos..."

cd /home/xabierlegasa/xabi/code/trackfolio/trackfolio-api/infra

echo "1. Deteniendo contenedores..."
docker-compose down

echo "2. Eliminando volumen de base de datos (se perderán los datos)..."
docker volume rm trackfolio-api_db_data 2>/dev/null || true

echo "3. Recreando contenedores con credenciales correctas..."
docker-compose up -d --build

echo "4. Esperando a que MySQL esté listo..."
sleep 10

echo "✅ Base de datos recreada con credenciales correctas"
echo ""
echo "Ahora puedes ejecutar:"
echo "  docker-compose exec app php artisan migrate"

