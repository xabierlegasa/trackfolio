.PHONY: help clear-cache cc restart restart-app docker-up docker-down migrate migrate-refresh

# Default target
help:
	@echo "Available commands:"
	@echo "  make clear-cache  - Clear Laravel caches and restart container"
	@echo "  make cc           - Alias for clear-cache"
	@echo "  make restart      - Restart all Docker containers"
	@echo "  make restart-app  - Restart only the application container"
	@echo "  make docker-up    - Start Docker containers"
	@echo "  make docker-down  - Stop Docker containers"
	@echo "  make migrate      - Run database migrations"
	@echo "  make migrate-refresh - Rollback last migration and migrate again"

# Clear Laravel caches and restart container
clear-cache cc:
	@echo "Clearing Laravel caches..."
	cd infra && docker-compose exec app php artisan route:clear
	cd infra && docker-compose exec app php artisan config:clear
	cd infra && docker-compose exec app php artisan cache:clear
	cd infra && docker-compose exec app composer dump-autoload
	@echo "Restarting application container..."
	cd infra && docker-compose restart app
	@echo "Done!"

# Restart all Docker containers
restart:
	cd infra && docker-compose restart

# Restart only the application container
restart-app:
	cd infra && docker-compose restart app

# Start Docker containers
docker-up:
	cd infra && docker-compose up -d

# Stop Docker containers
docker-down:
	cd infra && docker-compose down

# Run database migrations
migrate:
	cd infra && docker-compose exec app php artisan migrate

# Rollback last migration and migrate again
migrate-refresh:
	@echo "Rolling back last migration..."
	cd infra && docker-compose exec app php artisan migrate:rollback --step=1
	@echo "Running migrations..."
	cd infra && docker-compose exec app php artisan migrate
	@echo "Done!"

