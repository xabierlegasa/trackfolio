# Trackfolio deployment (Ansistrano → Synology NAS)

## One-time setup

### Laptop

```bash
ansible-galaxy role install ansistrano.deploy
ansible-galaxy collection install community.docker  # optional; playbook uses docker compose CLI
```

### NAS (`192.168.1.136`)

- Enable SSH; user `xabi` can run `docker` / `docker compose`
- Create deploy root: `mkdir -p /volume1/docker/trackfolio`
- Ensure the NAS can `git clone https://192.168.1.149/trackfolio.git` (adjust URL in `deploy.yml` if needed)

### Secrets

Edit `group_vars/nas.yml` (or pass `-e`) with real `vault_trackfolio_*` values before the first deploy.
On first deploy, `shared/trackfolio-api/.env` is created from `templates/.env.j2` only if it does not exist (`force: false`).

Generate an app key locally if needed:

```bash
cd trackfolio-api && php artisan key:generate --show
```

## Deploy

```bash
cd deployment
ansible-playbook -i inventory.ini deploy.yml
```

After a successful deploy:

- API: http://192.168.1.136:8080
- Front: http://192.168.1.136:3080
- RabbitMQ UI: http://192.168.1.136:15672
