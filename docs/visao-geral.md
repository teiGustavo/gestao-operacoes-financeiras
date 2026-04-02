# Visão geral da aplicação

Resumo do fluxo principal e pontos de integração entre frontend, controllers, jobs e persistência.

---

## Fluxo principal (Visão geral)
- Usuário autenticado acessa `GET /operations` (definido em `routes/web.php`).
- `OperationListController` busca:
    - lista de operações;
    - últimos runs de importação/exportação.
- View principal: `resources/views/operations/index.blade.php` — centraliza:
    - filtros;
    - importar CSV;
    - exportar CSV;
    - atualizar status dos runs;
    - download de relatórios prontos.
- Importação/exportação são assíncronas via fila (jobs em `app/Infrastructure/Import/Jobs/*` e `app/Infrastructure/Report/Jobs/*`).
- Status e histórico são persistidos em entidades/coleções como `OperationImportRun` e `OperationReportRun`.
- Frontend consulta `GET /operations/runs-status` (controlador `OperationRunsStatusController`) para atualizar painéis de progresso.
- Ao finalizar, o usuário recebe notificação persistida em banco (`OperationImportFinishedNotification`, `OperationReportFinishedNotification`).

---

## Importação

Entrada web
- Endpoint: `POST /operations/import/csv` (controlador `OperationCsvImportController`).
- Fluxo inicial:
    - salva arquivo;
    - valida cabeçalho via `OperationCsvImporter::ensureHeaderIsValid()`;
    - cria `OperationImportRun` com status `pending`;
    - dispara `ProcessOperationCsvImportJob`.

`ProcessOperationCsvImportJob`
- Faz claim do run (de `pending` para `processing`).
- Gera plano de chunks (`buildChunkPlan`).
- Cria `OperationImportRunChunk` para cada chunk.
- Dispara `ProcessOperationCsvImportChunkJob` para cada chunk.
- Agenda `FinalizeOperationCsvImportRunJob`.

Processamento de cada chunk
- Chamada: `OperationCsvImporter::importWithSummary(...)`.
- Validação linha a linha.
- Persistência das linhas válidas via `OperationImportRowPersister` (inserção com queries MySQL).
- Registra rejeitadas em `OperationImportRunError` e `OperationImportStagingRow`.
- Salva métricas/resumo no chunk (contagens, erros, tempo).

Finalização (`FinalizeOperationCsvImportRunJob`)
- Consolida dados dos chunks.
- Define status final do run:
    - `completed` / `completed_with_errors` / `failed`.
- Grava métricas agregadas.
- Notifica o usuário solicitante (persistência da notificação).

---

## Exportação

Entrada web
- Endpoint: `GET /operations/report/csv` (controlador `OperationReportCsvExportController`).
- Fluxo inicial:
    - valida filtros;
    - cria `OperationReportRun` com status `pending`;
    - dispara `ProcessOperationCsvExportJob`.

`ProcessOperationCsvExportJob`
- Faz claim do run (de `pending` para `processing`).
- Monta plano de chunks via `OperationCsvReportGenerator::buildChunkPlan`.
- Cria `OperationReportRunChunk` para cada chunk.
- Dispara `ProcessOperationCsvExportChunkJob` por chunk.
- Agenda `FinalizeOperationCsvExportRunJob`.

Processamento de cada chunk
- Usa `OperationCsvReportGenerator::generateChunk(...)`.
- Executa consultas filtradas (por exemplo `OperationReportCsvQuery`).
- Calcula valores de domínio (ex.: valor presente) usando serviços de domínio.
- Escreve CSV parcial em `storage` (arquivo por chunk).

Finalização (`FinalizeOperationCsvExportRunJob`)
- Valida término de todos os chunks.
- Se sucesso, faz merge dos CSVs (`mergeChunkFiles`) em arquivo final.
- Atualiza `OperationReportRun` para `completed` e grava `output_file_path`.
- Notifica o usuário solicitante (persistência da notificação).

Download
- Endpoint: `GET /operations/report/csv/download/{operationReportRun}` (`OperationReportCsvDownloadController`).
- Autorização por `requested_by_user_id` antes do download.

---

## Observações e responsabilidades
- Import/export são orquestrados por jobs e chunks; cada job registra seu resumo e erros.
- Notificações são gravadas em banco e associadas ao run e ao usuário solicitante.
- Frontend recupera status via `GET /operations/runs-status` para atualização em tempo próximo a real.
- Persistência de runs, chunks, erros e staging rows é crítica para auditoria, reprocessamento e relatórios.
