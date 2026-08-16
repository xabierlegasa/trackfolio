# Trackfolio

## Local development

```bash
cd infra/local
docker compose up
# First time (or after Dockerfile/infra changes):
# docker compose up --build

# API: http://localhost:8080
# RabbitMQ Management UI: http://localhost:15672 (trackfolio / secret)
```

```bash
cd trackfolio-front
npm run dev
# http://localhost:3000/login
```

## Production deploy (Synology via Ansistrano)

See [`deployment/`](deployment/) — run from your laptop:

```bash
cd deployment
ansible-playbook -i inventory.ini deploy.yml
```
