CONTAINER_TOOL ?= docker
COMPOSE_CMD ?= $(CONTAINER_TOOL) compose
APP_SERVICE ?= app
CMD ?=
ARGS ?=
FILE ?=
RUN_ID ?=
IMPORT_WORKERS ?= 4
REQUESTED_BY_USER_ID ?=
REQUESTED_BY_USER_ID_OPTION := $(if $(REQUESTED_BY_USER_ID),--requested-by-user-id=$(REQUESTED_BY_USER_ID),)

.PHONY: help up down restart build ps logs shell artisan migrate test pint composer npm import import-run import-status import-status-latest report-status report-status-latest queue-work-imports queue-worker-start queue-worker-stop queue-worker-status queue-monitor

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

import: ## Enfileira importacao CSV (opcional: REQUESTED_BY_USER_ID='1')
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan operations:import $(FILE) $(REQUESTED_BY_USER_ID_OPTION)

import-run: ## Fluxo one-shot: enfileira CSV, processa fila no app ate esvaziar e mostra ultimo status
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan operations:import $(FILE) $(REQUESTED_BY_USER_ID_OPTION)
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan queue:work --queue=imports,default --tries=3 --timeout=120 --stop-when-empty
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan operations:import:status-latest

import-status: ## Exibe status da importacao (use RUN_ID='1')
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan operations:import:status $(RUN_ID)

import-status-latest: ## Exibe status da importacao mais recente
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan operations:import:status-latest

report-status: ## Exibe status do relatorio (use RUN_ID='1')
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan operations:report:status $(RUN_ID)

report-status-latest: ## Exibe status do relatorio mais recente
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan operations:report:status-latest

queue-work-imports: ## Processa fila imports no container app (manual/bloqueante)
	$(COMPOSE_CMD) exec $(APP_SERVICE) php artisan queue:work --queue=imports,default --tries=3 --timeout=120

queue-worker-start: ## Sobe mysql e escala imports-worker (use IMPORT_WORKERS='6')
	$(COMPOSE_CMD) up -d mysql imports-worker --scale imports-worker=$(IMPORT_WORKERS)

queue-worker-stop: ## Para os servicos dedicados imports-worker
	$(COMPOSE_CMD) stop imports-worker

queue-worker-status: ## Exibe status dos containers imports-worker
	$(COMPOSE_CMD) ps imports-worker

queue-monitor: ## Exibe logs em tempo real dos imports-workers
	$(COMPOSE_CMD) logs -f imports-worker

