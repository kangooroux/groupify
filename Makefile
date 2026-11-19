DC = docker compose

.PHONY: up down bash composer migrate

up:
	$(DC) up --detach --build
	@echo "\nApp running at http://localhost:$$($(DC) port nginx 80 | cut -d: -f2)\n"

down:
	$(DC) down

bash:
	$(DC) exec php sh

composer:
	$(DC) exec php composer $(cmd)

migrate:
	$(DC) exec php php bin/console doctrine:migrations:migrate --no-interaction
