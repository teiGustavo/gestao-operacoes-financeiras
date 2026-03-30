# Gerenciamento de Fila (Imports e Relatórios)

Este documento cobre operação da fila (worker, monitoramento e troubleshooting).
Bootstrap geral e uso do projeto ficam no `README.md`.

O projeto usa fila em banco (`QUEUE_CONNECTION=database`) e o serviço `imports-worker` no `docker-compose` para processar jobs assíncronos de:

- importação CSV de operações;
- exportação CSV de relatórios.

## Como a importação paralela funciona

- O job orquestrador (`ProcessOperationCsvImportJob`) valida o cabeçalho e divide o arquivo em chunks com tamanho dinâmico.
- O tamanho de chunk é calculado automaticamente por execução com base em `IMPORT_WORKERS`.
- Os chunks são registrados em `operation_import_run_chunks` com:
  - `start_line_number`
  - `end_line_number`
  - `start_byte_offset`
- Cada worker (`ProcessOperationCsvImportChunkJob`) processa somente seu range.
- O `start_byte_offset` permite `fseek` direto no trecho do worker, evitando releitura do CSV desde o início em chunks tardios.
- O job `FinalizeOperationCsvImportRunJob` agrega métricas/erros dos chunks e conclui a execução.
- Em falha infraestrutural de chunk (ex.: erro de banco), o sistema registra auditoria em `operation_import_run_errors` com metadados do chunk e marca linhas de staging pendentes/validadas como `failed`.

## Como a exportação paralela funciona

- O job orquestrador (`ProcessOperationCsvExportJob`) monta o plano de chunks com tamanho dinâmico.
- O tamanho de chunk considera `IMPORT_WORKERS` para distribuir os registros exportáveis por worker.
- Cada chunk é processado por `ProcessOperationCsvExportChunkJob`, gerando um arquivo parcial.
- O `FinalizeOperationCsvExportRunJob` consolida os arquivos parciais em um único CSV final e conclui a execução.
- O run final agrega métricas (`query`, `write`, `merge`, `total`) para acompanhamento de performance.

## Por que usar um serviço separado no docker-compose?

Sim, é necessário para o fluxo assíncrono ficar estável no ambiente local:

- o container `app` roda `php artisan serve` e não deve acumular outro processo de longa duração;
- o worker precisa reiniciar automaticamente em caso de falha (`restart: unless-stopped`);
- separar `app` e `imports-worker` facilita logs, operação e troubleshooting;
- o serviço `imports-worker` pode ser escalado via `Makefile` com `IMPORT_WORKERS`.

> Regra de consistência: `IMPORT_WORKERS` no `.env` deve ser igual ao número de réplicas ativas de `imports-worker`.

## Comandos e responsabilidades

| Comando                           | O que faz                                         |
|-----------------------------------|---------------------------------------------------|
| `make queue-worker-start`         | Sobe `mysql` e escala `imports-worker` (padrão `IMPORT_WORKERS=4`) |
| `make queue-worker-stop`          | Para os containers do serviço `imports-worker`    |
| `make queue-worker-status`        | Exibe status dos containers do serviço `imports-worker` |
| `make queue-monitor`              | Mostra logs em tempo real do serviço `imports-worker` |
| `make queue-work-imports`         | Processa a fila no `app` (modo manual/bloqueante) |
| `make artisan CMD='queue:failed'` | Lista jobs falhados                               |
| `make report-status-latest`       | Exibe status da exportação de relatório mais recente |
| `make report-status RUN_ID='12'`  | Exibe status de uma execução específica de relatório |

```bash
make queue-worker-start
make queue-worker-start IMPORT_WORKERS=8
make queue-monitor
make queue-worker-status
make queue-worker-stop
```

> Exemplo de comando gerado pelo `Makefile`: `IMPORT_WORKERS=8 docker compose up -d app mysql imports-worker --scale imports-worker=8`.

> Mantenha os dois lados com o mesmo número: `IMPORT_WORKERS` (config do app/chunk plan) e `--scale imports-worker` (concorrência real de processamento).

## Fluxo recomendado (contínuo)

### Importação CSV

```bash
make queue-worker-start
make import FILE='/caminho/arquivo.csv' REQUESTED_BY_USER_ID='1'
make import-status-latest
```

Para ajustar a concorrência dos workers em importações grandes:

```bash
make queue-worker-start IMPORT_WORKERS=8
```

> Ao alterar para `8`, atualize também o `.env` para `IMPORT_WORKERS=8` antes de iniciar/reiniciar os workers.

> Dica: escale gradualmente (ex.: 4 → 8 → 12) e monitore `mysql`/CPU/IO para achar o ponto de melhor throughput.

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

Se houver divergência entre `.env` e quantidade de réplicas (ex.: `.env=5` e `imports-worker=10`), alinhe e reinicie:

```bash
make queue-worker-start IMPORT_WORKERS=8
make artisan CMD='queue:restart --no-interaction'
make artisan CMD='config:show imports.parallel_workers'
make queue-worker-status
```

Se houver jobs falhados e você quiser limpar tudo:

```bash
make artisan CMD='queue:flush'
```

Para tentar concluir uma importação que falhou por job na fila (ex.: deadlock após esgotar tentativas), reprocese os jobs falhados:

```bash
make artisan CMD='queue:retry all'
make import-status RUN_ID='12'
```

Se a execução já estiver marcada como `failed` e finalizada, esse retry não reabre o run; nesse caso, reenvie o CSV com `make import FILE='/caminho/arquivo.csv'`.

## Referências

- `docker-compose.yml`
- `Makefile`
- `app/Infrastructure/Import/Jobs/ProcessOperationCsvImportJob.php`
- `app/Infrastructure/Import/Jobs/ProcessOperationCsvImportChunkJob.php`
- `app/Infrastructure/Import/Jobs/FinalizeOperationCsvImportRunJob.php`
- `app/Infrastructure/Report/Jobs/ProcessOperationCsvExportJob.php`

