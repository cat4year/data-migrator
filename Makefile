init: docker-down docker-pull docker-build docker-up
up: docker-up
build: docker-build
down: docker-down
restart: docker-down docker-up
php: docker-php

include .env

docker-up:
	docker compose up -d

docker-down:
	docker compose down --remove-orphans

docker-pull:
	docker compose pull

docker-build:
	COMPOSE_BAKE=true docker compose build --no-cache

docker-php:
	docker exec -it ${DOCKER_PROJECT_NAME}-php bash
