---
inclusion: always
---

# Docker Conventions

## Stack Architecture

This project runs on Docker with compose overrides:
- `docker-compose.yml` — base services (nginx, php, mysql, composer, npm, artisan)
- `docker-compose.dev.yml` — development: volume mounts, exposed ports, hot-reload
- `docker-compose.prod.yml` — production: optimized builds, no source mounts

## Container Names

All containers are prefixed with the `COMPOSE_PROJECT_NAME` from `docker/environments/config.env`:
- `docker_laravel_nginx`
- `docker_laravel_php`
- `docker_laravel_mysql`

When this project matures, update `COMPOSE_PROJECT_NAME` to `budget_tracker`.

## Makefile as Interface

All Docker operations go through the Makefile. Never run raw `docker compose` commands in documentation or scripts.

Key commands:
- `make up` / `make down` — start/stop
- `make build` / `make rebuild` — build images
- `make shell` — enter PHP container
- `make artisan cmd="..."` — run artisan commands
- `make composer cmd="..."` — run composer commands
- `make npm cmd="..."` — run npm commands
- `make fresh` — drop all tables, re-migrate + seed

When adding new services or commands, follow these patterns:
- Add `##@` section markers for help grouping
- Add `##` inline comments for `make help` output
- Use `$(DOCKER_COMPOSE)` variable, never hardcode `docker compose`

## Non-Root Containers

All containers run as non-root users. PHP container uses `HOST_UID` and `HOST_GID` args to align file permissions with the host. Never add `user: root` to services.

## Volumes

- `mysql_data` — database persistence (survives `make down`, cleared by `make down-v`)
- `vendor_data` — composer dependencies (shared between php, artisan, composer containers)
- `node_modules_data` — npm dependencies
- `nginx_logs` — dev only, for debugging

## Environment Files

- `docker/environments/config.env` — Docker-level config (ports, versions, paths)
- `docker/environments/dev.env` — MySQL credentials and app secrets for development
- `src/.env` — Laravel application config (copied from `.env.example` on setup)

## Adding New Services

When adding services (e.g., Redis, queue worker, scheduler):
1. Add to `docker-compose.yml` (base config, no ports/volumes)
2. Add dev-specific config to `docker-compose.dev.yml`
3. Add prod-specific config to `docker-compose.prod.yml`
4. Add relevant Makefile targets
5. Update `config.env` if new version variables are needed

## Queue and Scheduler

The queue worker and scheduler are pre-configured but commented out in `docker-compose.yml`. Uncomment them when the project needs background job processing (e.g., installment generation, CSV exports).
