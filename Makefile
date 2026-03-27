CONTAINER_TOOL ?= docker
COMPOSE_CMD ?= $(CONTAINER_TOOL) compose
APP_SERVICE ?= app
CMD ?=
ARGS ?=

.PHONY: help up down restart build ps logs shell artisan migrate test pint composer

help: ## Lista os comandos disponíveis
	@awk 'BEGIN {FS = ":.*##"; printf "Comandos:\n"} /^[a-zA-Z_-]+:.*##/ {printf "  %-12s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

up: ## Sobe os containers em background
	$(COMPOSE_CMD) up -d --build

down: ## Para e remove os containers
	$(COMPOSE_CMD) down

restart: ## Reinicia os containers
	$(COMPOSE_CMD) restart

build: ## Rebuild da imagem da aplicação
	$(COMPOSE_CMD) build app

ps: ## Mostra status dos serviços
	$(COMPOSE_CMD) ps

logs: ## Mostra logs (use ARGS='-f app')
	$(COMPOSE_CMD) logs $(ARGS)

shell: ## Abre shell no container app
	$(COMPOSE_CMD) exec $(APP_SERVICE) sh

artisan: ## Executa Artisan (use CMD='about')
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan $(CMD)

migrate: ## Executa migrações
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan migrate

test: ## Executa testes (use ARGS='--filter=NomeDoTeste')
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan test --compact $(ARGS)

pint: ## Formata código com Pint
	$(COMPOSE_CMD) exec $(APP_SERVICE) vendor/bin/pint --dirty --format agent

composer: ## Executa Composer (use CMD='require pacote')
	$(COMPOSE_CMD) exec $(APP_SERVICE) composer $(CMD)

npm: ## Executa npm (use CMD='install')
	$(COMPOSE_CMD) exec $(APP_SERVICE) npm $(CMD)
