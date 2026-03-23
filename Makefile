DC = docker compose

.PHONY: up down bash composer migrate

up:
	$(DC) up --detach --build

down:
	$(DC) down

bash:
	$(DC) exec php sh

composer:
	$(DC) exec php composer $(cmd)

migrate:
	$(DC) exec php php bin/console doctrine:migrations:migrate --no-interaction
