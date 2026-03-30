# Sistema de Processamento e Gestão de Operações Financeiras

>**DISCLAIMER**: Todas as decisões foram tomadas de forma consciente e com base em 
  análises técnicas, mas o código não deve ser considerado como um produto finalizado
  ou pronto para produção. 
  O objetivo principal deste projeto é demonstrar habilidades técnicas, sendo assim
  algumas escolhas foram feitas visando a clareza e a demonstração de boas práticas,
  mesmo que isso não seja possível em todas as situações cotidianas (devido à prazo, 
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

> Os comandos de `composer install` e `npm install` são opcionais na primeira execução, 
pois o `Dockerfile` já se encarrega de instalar as dependências no boot.

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
- `make import FILE='/caminho/arquivo.csv'`: Enfileira a importação CSV assíncrona
- `make import-run FILE='/caminho/arquivo.csv'`: Enfileira, processa a fila até esvaziar e exibe o último status
- `make import-status RUN_ID='1'`: Exibe status de uma execução de importação
- `make import-status-latest`: Exibe status da execução de importação mais recente
- `make queue-work-imports`: Processa a fila de importação (`imports`)

> Para mais detalhes, consulte o arquivo [Makefile](./Makefile) no repositório.

### Equivalência dos comandos `Makefile` (caso o `make` não estiver disponível):

- `make up`: `docker compose up -d`
- `make down`: `docker compose down`
- `make artisan CMD='...'`: `docker compose exec app php artisan ...`
- `make composer CMD='...'`: `docker compose exec app composer ...`
- `make npm CMD='...'`: `docker compose exec app npm ...`

### Importação CSV assíncrona (fila)

#### Opção rápida (one-shot):

- `make import-run FILE='/caminho/arquivo.csv'`

#### Opção detalhada (passo a passo, no fluxo operacional recomendado):
1. Enfileire a importação (validação de cabeçalho ocorre de forma síncrona):
   - `make import FILE='/caminho/arquivo.csv'`
2. Inicie o worker da fila para processar os jobs:
   - `make queue-work-imports`
3. Consulte o status da execução com o `run_id` retornado no passo 1:
   - `make import-status RUN_ID='1'`
4. Opcional: consulte o status do último run sem informar ID:
   - `make import-status-latest`

##### Equivalentes via Artisan:

- `php artisan operations:import /caminho/arquivo.csv`
- `php artisan queue:work --queue=imports,default --tries=3 --timeout=120`
- `php artisan operations:import:status 1`
- `php artisan operations:import:status-latest`

##### Equivalente one-shot via Artisan:

```bash
php artisan operations:import /caminho/arquivo.csv
php artisan queue:work --queue=imports,default --tries=3 --timeout=120 --stop-when-empty
php artisan operations:import:status-latest
```

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

## 📝 Licença

Este projeto é parte de um processo seletivo para a vaga de 
`Analista I de Desenvolvimento de Software`. 

O código é fornecido apenas para fins de avaliação técnica e não deve ser utilizado 
para outros propósitos sem autorização prévia.

Todos os direitos reservados à `Dimensa`.
