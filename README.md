# Sistema de Processamento e Gestão de Operações Financeiras

>**DISCLAIMER**: Todas as decisões foram tomadas de forma consciente e com base em 
  análises técnicas, mas o código não deve ser considerado como um produto finalizado
  ou pronto para produção. 
  O objetivo principal deste projeto é demonstrar habilidades técnicas, sendo assim
  algumas escolhas foram feitas visando a clareza e a demonstração de boas práticas,
  mesmo que isso não seja possível em todas as situações cotidianas (seja por: prazo, 
  escopo, padrões já estabelecidos, base legada e etc).
>  
> **Resumindo: O teste foi tratado como prova de fogo, a fim de demonstrar as minhas
  capacidades técnicas, o que não torna minhas decisões menos acertadas. Para mais 
  detalhes, verifique o arquivo de [Justificativa e Embasamento](./docs/justificativa-e-embasamento.md).** 

---

## 📋 Sobre o Projeto

Sistema para processamento e gestão de
operações financeiras a partir de um arquivo contendo alto volume de
dados.

Este projeto foi desenvolvido como parte de um processo seletivo para a vaga de 
`Analista I de Desenvolvimento de Software - PHP Laravel` na [Dimensa](https://dimensa.com).

Descrição da Vaga: [Pessoa Analista de Desenvolvimento PHP Laravel Júnior - Dimensa](https://www.linkedin.com/jobs/view/4375245140)

Descrição do Teste Técnico: [Teste Técnico - Analista I de Desenvolvimento de Software - Dimensa](./docs/Arquivos%20do%20Teste/Processo%20seletivo%20-%20Dev%20I.docx.pdf).

---

## 🏗️ Estrutura do Repositório

Além das pastas comuns do Laravel, o repositório inclui:

- `docker/`: Configurações e arquivos relacionados ao Docker
- `docs/`: Documentação do projeto (levantamento de requisitos, justificativas técnicas, etc.)
- `Makefile`: Script para facilitar a configuração do ambiente
- `mise.toml`: Configurações do gerenciador de versões `Mise`
- `README.md`: Documentação principal do projeto

A pasta `app/` contém os diretórios padrão + separação de camadas, com a motivação descrita na seção de justificativas técnicas. Os diretórios adicionais são:
- `app/Domain/`: Contém as entidades de domínio e regras de negócio
- `app/Application/`: Contém os casos de uso e serviços de aplicação
- `app/Infrastructure/`: Contém as implementações de acesso a dados e outras integrações

Na camada HTTP, o projeto também adota uma separação explícita de apresentação de dados usando `Presenter/ViewModel` (extensão do MVC inspirada em MVVM), mantendo controllers como orquestradores e views focadas em renderização.

---

## 🎯 Objetivos do Sistema

1. Importar grande volume de dados financeiros
2. Estruturar informações no banco de dados
3. Permitir consulta e gestão de operações
4. Emitir relatórios customizados

---

## 🛠️ Stack Tecnológica Utilizada

- **Aplicação/Backend:** PHP 8.5 com Laravel 13
- **Frontend:** Livewire, Alpine.js, Tailwind CSS e Vite
- **Banco de Dados:** MySQL 8.4
- **Ambiente:** Docker

> Para o gerenciamento de bibliotecas do PHP, foi utilizado o `Composer`. <br>
> Para o gerenciamento de bibliotecas do frontend, foi utilizado o `npm` (Node.js 24). <br>
> Para o gerenciamento das linguagens de programação fora do `Docker` (para garantir a velocidade em análises estáticas, por exemplo), foi utilizado o `Mise` (sucessor do antigo `asdf`).

---

## 📝 Como Iniciar o Projeto

Um arquivo `Makefile` foi criado para facilitar a configuração do ambiente. 

### Siga os passos abaixo:

1. Clone o repositório
2. Navegue até a pasta do projeto
3. Configure o arquivo `.env` com as credenciais do banco de dados (as credenciais são compartilhadas com o Docker).
4. Execute o comando `make up` para configurar e subir o ambiente Docker.
5. Execute `make composer CMD='install'` para instalar as dependências do PHP/Laravel.
6. Execute `make npm CMD='install'` para instalar as dependências do Node.js.
7. Execute `make artisan CMD='key:generate'` para gerar a chave de aplicação do Laravel.
8. Execute `make artisan CMD='migrate'` para criar as tabelas no banco de dados.

> Os comandos de `composer install` e `npm install` são necessários para preparar o ambiente local.

### Glossário dos comandos do `Makefile`:

- `make up`: Configura e sobe o ambiente Docker
- `make down`: Para e remove os containers Docker
- `make restart`: Reinicia os containers
- `make build`: Rebuild da imagem da aplicação
- `make ps`: Mostra status dos serviços
- `make logs ARGS='-f app'`: Mostra logs dos serviços
- `make shell`: Abre shell no container `app`
- `make artisan CMD='...'`: Executa comandos Artisan do Laravel dentro do container
- `make migrate`: Executa migrações
- `make test ARGS='--filter=NomeDoTeste'`: Executa os testes com `php artisan test --compact`
- `make pint`: Formata o código com Pint
- `make composer CMD='...'`: Executa comandos do Composer dentro do container
- `make npm CMD='...'`: Executa comandos do npm dentro do container
- `make import FILE='/caminho/arquivo.csv' REQUESTED_BY_USER_ID='1'`: Enfileira a importação CSV assíncrona (opcionalmente vinculada a um usuário solicitante)
- `make import-run FILE='/caminho/arquivo.csv' REQUESTED_BY_USER_ID='1'`: Fluxo one-shot (enfileira, processa fila no `app` até esvaziar e exibe o último status)
- `make import-status RUN_ID='1'`: Exibe status de uma execução de importação
- `make import-status-latest`: Exibe status da execução de importação mais recente
- `make report-status RUN_ID='1'`: Exibe status de uma execução de relatório
- `make report-status-latest`: Exibe status da execução de relatório mais recente
- `make queue-work-imports`: Processa a fila `imports` no container `app` (manual/bloqueante)
- `make queue-worker-start`: Sobe `mysql` e escala o serviço `imports-worker` (padrão: `IMPORT_WORKERS=4`)
- `make queue-worker-stop`: Para os containers do serviço `imports-worker`
- `make queue-worker-status`: Mostra status dos containers do serviço `imports-worker`
- `make queue-monitor`: Mostra logs em tempo real do serviço `imports-worker`

> Para mais detalhes, consulte o arquivo [Makefile](./Makefile) no repositório.

### Equivalência dos comandos `Makefile` (caso o `make` não estiver disponível):

- `make up`: `docker compose up -d`
- `make down`: `docker compose down`
- `make artisan CMD='...'`: `docker compose exec app php artisan ...`
- `make composer CMD='...'`: `docker compose exec app composer ...`
- `make npm CMD='...'`: `docker compose exec app npm ...`

### Importação CSV assíncrona (fila)

Resumo da implementação atual (performance):

- A importação é orquestrada por execução (`operation_import_runs`) e dividida em chunks dinâmicos.
- O tamanho de cada chunk é calculado automaticamente por execução com base em `IMPORT_WORKERS`.
- Cada chunk é processado por um job dedicado em paralelo (`ProcessOperationCsvImportChunkJob`).
- O orquestrador persiste `start_line_number`, `end_line_number` e `start_byte_offset` por chunk (`operation_import_run_chunks`).
- Cada worker faz `seek` direto para o início do seu trecho no arquivo, evitando releitura completa do CSV nos chunks tardios.

Fluxo recomendado (worker dedicado):

```bash
make queue-worker-start
make import FILE='/caminho/arquivo.csv' REQUESTED_BY_USER_ID='1'
make import-status-latest
```

Para aumentar/reduzir concorrência dos workers:

```bash
make queue-worker-start IMPORT_WORKERS=8
```

> Quanto maior `IMPORT_WORKERS`, menor tende a ser o chunk por worker (limitado por CPU/IO/DB).

Fluxo one-shot (execução única/manual):

```bash
make import-run FILE='/caminho/arquivo.csv' REQUESTED_BY_USER_ID='1'
```

> Se `requested-by-user-id` for informado, o usuário solicitante recebe notificação no canal `database` ao término da importação.

### Exportação CSV assíncrona (fila)

Fluxo recomendado (worker dedicado):

```bash
make queue-worker-start
# acione "Exportar CSV" na esteira (/operations)
make report-status-latest
```

Para consultar uma execução específica:

```bash
make report-status RUN_ID='12'
```

---

## 🔄 Gerenciamento de Fila

O processamento contínuo da fila `imports` é feito pelo serviço `imports-worker` no `docker-compose`.
O número de réplicas desse serviço é controlado via `IMPORT_WORKERS` no `Makefile` (padrão: `4`).

Comandos operacionais:

```bash
make queue-worker-start
make queue-worker-start IMPORT_WORKERS=8
make queue-monitor
make queue-worker-status
make queue-worker-stop
```

> O `imports-worker` aguarda o `mysql` ficar saudável antes de iniciar.

## 📦 Limites de Upload (UI/API)

Para suportar uploads de CSV maiores no ambiente Docker local, o container PHP aplica:

- `post_max_size=32M`
- `upload_max_filesize=32M`

Arquivo de configuração: `docker/php/uploads.ini`.

Detalhes operacionais e troubleshooting em [Gerenciamento de Fila](docs/queue-management.md).

---

## 📊 Levantamento de Requisitos

O levantamento de requisitos detalhado
(incluindo requisitos funcionais e não funcionais) 
pode ser encontrado no arquivo:
[Levantamento de Requisitos](docs/levantamento-de-requisitos.md).

---

## ⚖️ Decisões Técnicas e Justificativas

As decisões técnicas tomadas durante o desenvolvimento do projeto,
bem como as justificativas para escolhas importantes,
podem ser encontradas no arquivo:
[Justificativa Técnica e Embasamento das Decisões](docs/justificativa-e-embasamento.md).

---

## 📐 Modelagem do Banco de Dados

A modelagem do banco de dados, incluindo as principais entidades, 
seus atributos e os relacionamentos entre elas, pode ser encontrada no arquivo:
[Modelagem do Banco de Dados](docs/modelagem.md).

---

## 🎯 Critérios de Avaliação

O teste será avaliado principalmente nos seguintes aspectos:

1. **Estrutura da solução** - Arquitetura e organização do código
2. **Performance** - Capacidade de lidar com alto volume de dados
3. **Modelagem de dados** - Qualidade do design do banco de dados
4. **Clareza nas decisões técnicas** - Documentação e justificativa das escolhas

---

## 🎯 Melhorias Futuras e Limitações da Solução

#### Com mais tempo, as seguintes melhorias poderiam ser implementadas:

- Usar UUID ou ULID para `external_id` (tornando o id externo seguro para exposição pública)

- Padronizar todos os erros em inglês e fazer internacionalização para as mensagens serem retornadas para o usuário em português

- Delegar o salvamento do histórico para uma fila/job

- Adicionar um "sininho" ou um toast na tela do usuário no exato momento em que o Job terminar (sem que ele precise dar F5, o que precisaria de `WebSocket`).

- Separar rotas de API (que retornam JSON) das rotas que servem views (HTML), para evitar confusão e manter uma estrutura mais clara.

- Separar fila exports da fila imports se para isolamento operacional.
  
- Melhorar exportação das tabelas de parcelas e a qualidade geral do relatório.

- Traduzir campos exportados no csv.

- Incluir arquivo csv com os cabeçalhos
  esperados, disponível para download, para o usuário tratar os dados antes de submeter para a aplicação

#### Limitações e "Problemas":

- Os fluxos estão acoplados, onde em um cenário de escala, é mais interessante haver uma comunicação por eventos (filosofia `Event Driven`).

- O histórico de jobs não é salvo para auditoria.
 
- As importações não são idempotentes (se o mesmo arquivo for importado mais de uma vez, ele irá criar registros duplicados).

- Somente as tabelas com entidades de domínio possuem checks ao nível de banco de dados.

- Os testes não rodam em um banco separado, o que gera efeitos colaterais no banco padrão.

- As dependências do frontend não estão utilizando o NPM + Vite (simplicidade).

- Separação pouco delimitada entre os idiomas

- A importação permite apenas csv (consulte o arquivo de embasamento técnico para mais detalhes sobre essa decisão).

- Rotas que servem JSON não seguem formatação clara e estruturada de retorno

---

## 📝 Licença

Este projeto é parte de um processo seletivo para a vaga de 
`Analista I de Desenvolvimento de Software`. 

O código é fornecido apenas para fins de avaliação técnica e não deve ser utilizado 
para outros propósitos sem autorização prévia.

Todos os direitos reservados à `Dimensa`.
