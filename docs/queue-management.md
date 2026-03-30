# Gerenciamento de Fila (Imports e Relatórios)

Este documento cobre operação da fila (worker, monitoramento e troubleshooting).
Bootstrap geral e uso do projeto ficam no `README.md`.

O projeto usa fila em banco (`QUEUE_CONNECTION=database`) e o serviço `imports-worker` no `docker-compose` para processar jobs assíncronos de:

- importação CSV de operações;
- exportação CSV de relatórios.

## Por que usar um serviço separado no docker-compose?

Sim, é necessário para o fluxo assíncrono ficar estável no ambiente local:

- o container `app` roda `php artisan serve` e não deve acumular outro processo de longa duração;
- o worker precisa reiniciar automaticamente em caso de falha (`restart: unless-stopped`);
- separar `app` e `imports-worker` facilita logs, operação e troubleshooting.

## Comandos e responsabilidades

| Comando                           | O que faz                                         |
|-----------------------------------|---------------------------------------------------|
| `make queue-worker-start`         | Sobe `mysql` e `imports-worker` em background     |
| `make queue-worker-stop`          | Para o container `imports-worker`                 |
| `make queue-worker-status`        | Exibe status do container `imports-worker`        |
| `make queue-monitor`              | Mostra logs em tempo real do `imports-worker`     |
| `make queue-work-imports`         | Processa a fila no `app` (modo manual/bloqueante) |
| `make artisan CMD='queue:failed'` | Lista jobs falhados                               |
| `make report-status-latest`       | Exibe status da exportação de relatório mais recente |
| `make report-status RUN_ID='12'`  | Exibe status de uma execução específica de relatório |

```bash
make queue-worker-start
make queue-monitor
make queue-worker-status
make queue-worker-stop
```

## Fluxo recomendado (contínuo)

### Importação CSV

```bash
make queue-worker-start
make import FILE='/caminho/arquivo.csv' REQUESTED_BY_USER_ID='1'
make import-status-latest
```

### Exportação CSV de relatório

1. Acione "Exportar CSV" na tela da esteira (`/operations`) com ou sem filtros.
2. O sistema enfileira a exportação e cria uma execução em `operation_report_runs`.
3. Após concluir, o usuário recebe notificação em `database`.
4. O download fica disponível na própria esteira (painel de "Últimos relatórios").

Para consultar o último status via CLI:

```bash
make report-status-latest
```

Para consultar uma execução específica:

```bash
make report-status RUN_ID='12'
```

Para execução única (sem manter worker dedicado):

```bash
make import-run FILE='/caminho/arquivo.csv' REQUESTED_BY_USER_ID='1'
```

Use `make queue-work-imports` apenas para execução manual/bloqueante no container `app`.

## Troubleshooting

```bash
make queue-monitor
make queue-worker-status
make artisan CMD='queue:failed'
```

Se o log do worker mostrar `SQLSTATE[HY000] [2002] Connection refused`:

```bash
make queue-worker-stop
make queue-worker-start
make queue-monitor
```

Esse erro normalmente acontece quando o worker sobe antes do MySQL aceitar conexoes. O `docker-compose` foi ajustado para aguardar `mysql` saudavel antes de iniciar `imports-worker`.

Também mantenha `DB_QUEUE_RETRY_AFTER` maior que o `timeout` do job. Neste projeto: `timeout=120` e `DB_QUEUE_RETRY_AFTER=150`.

Se houver jobs falhados e você quiser limpar tudo:

```bash
make artisan CMD='queue:flush'
```

## Referências

- `docker-compose.yml`
- `Makefile`
- `app/Infrastructure/Import/Jobs/ProcessOperationCsvImportJob.php`
- `app/Infrastructure/Report/Jobs/ProcessOperationCsvExportJob.php`

