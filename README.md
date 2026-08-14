# Trackfolio



cd trackfolio-api/infra
docker compose up
# First time (or after Dockerfile/infra changes):
# docker compose up --build

# API: http://localhost:8080
# RabbitMQ Management UI: http://localhost:15672 (trackfolio / secret)

http://localhost:15672/


cd trackfolio-front
npm run dev
http://localhost:3000/login
