# Modelagem do Banco de Dados

Resumo da modelagem do banco de dados para o sistema,
incluindo as principais entidades, seus atributos e os relacionamentos entre elas.

Para mais detalhes sobre o projeto, consulte o [README.md](../README.md).

Para visualizar os dados iniciais, consulte a
[Planilha Teste Prático - Dimensa](./Arquivos%20do%20Teste/Planilha%20Teste%20Pratico%20-%20Dimensa%20(27-03).xlsx).

---

## Visão Geral dos Dados da Planilha

Os dados a serem importados incluem:

- **valor_requerido**: O montante total que o cliente solicitou inicialmente.

- **valor_desembolso**: O valor líquido que foi (ou será) efetivamente entregue ao cliente.

- **total_juros**: O valor em moeda referente aos juros totais da operação.

- **taxa_juros** (%): A taxa percentual de juros aplicada.

- **taxa_multa** / **taxa_mora**: Percentuais aplicados em caso de atraso no 
  pagamento das parcelas.

- **status_id**: Identificador numérico do estágio atual da operação.

- **data_criacao**: Data em que a proposta foi gerada no sistema.

- **data_pagamento**: Data em que o dinheiro foi enviado ao cliente 
  (fica vazio até a conclusão do fluxo).

- **produto**: Tipo de crédito ("CONSIGNADO" ou "NAO_CONSIGNADO").

- **conveniada_id**: Identificador da empresa ou órgão parceiro vinculado àquela operação.

- **quantidade_parcelas**: O prazo total da operação (em meses).

- **data_primeiro_vencimento**: Data em que a primeira parcela deve ser paga pelo cliente.

- **valor_parcela**: O valor fixo de cada prestação mensal.

- **quantidade_parcelas_pagas**: Indica quantas parcelas o cliente já quitou até o momento.

- **cpf**: Cadastro de Pessoa Física do proponente (armazenado como texto/varchar).

- **nome**: Nome completo do cliente.

- **dt_nasc**: Data de nascimento.

- **sexo**: Gênero do cliente (M/F).

- **email**: Endereço eletrônico para contato e login.

---

## Nomenclatura

Para seguir as convenções mais usuais de nomenclatura em bancos de dados relacionais,
(que para SQL não há um consenso unânime) e para combinar com a convenção
internacional do Laravel (e PSRs): será adotado o inglês como idioma 
para os nomes de tabelas e colunas utilizando snake_case.

Documentação pertinente para convenção de nomenclatura de Bancos de Dados: 
[Convenção de Nomenclatura](https://gist.github.com/thiamsantos/654ec002f04c86d53611923a8b4c3a65).

---

## Normalização dos Dados

### Campos `status`, `gender` e `product_type`

Pela estrutura do teste ser rígida e os dados serem fornecidos 
em um formato específico que não irá se alterar, será evitado: o uso de 
`lookup tables` e `enums do MySQL` para os campos `status`, `gender` e `product_type`,
optando por armazenar os valores diretamente como texto (varchar) na tabela de operações.
Esta decisão é justificada pela simplicidade do modelo 
e pela baixa probabilidade de mudanças frequentes nesses campos, 
além de evitar joins desnecessários que poderiam impactar a 
performance em consultas frequentes.

Os campos relacionados a tipos, serão mapeados como Enums na regra de negócio (no PHP)
para garantir a validação e a integridade dos dados. 
Se for necessário o uso de lookup tables no futuro, 
a migração seria relativamente simples (pois as regras estarão centralizadas).

> Disclaimer 1: Além dos problemas citados, utilizar `lookup tables` 
  iria adicionar uma complexidade extra em cada nova consulta e
  pode esbarrar em problemas de `N+1` 
  (caso a consulta não esteja bem otimizada e organizada) devido à abstração do ORM.

> Disclaimer 2: Se fosse utilizado o tipo `ENUM` do MySQL, a adição de novos 
  valores exigiria uma alteração na estrutura da tabela, 
  o que pode ser problemático em ambientes de produção, principalmente em tabelas com
  muitos registros, o que poderia deixar o Banco de Dados fora do ar por minutos.

> Disclaimer 3: O campo `gender` é um caso específico que deveria ser normalizado 
  em um cenário ideal para atender a diversidade de identidades de gênero.
> - **PL 585/2024**: Combate à discriminação algorítmica de gênero.
  Embora o projeto não seja diretamente sobre a inclusão de gênero em 
  sistemas informatizados, ele abre precedente para que os sistemas sejam mais 
  "flexíveis" e mais transparentes em relação a como lidam com a
  diversidade de identidades de gênero.
>
> 
> - **PL 5253/2020**: Propõe alteração na Lei de Registros Públicos para permitir
  que pessoas não binárias possam registrar seu gênero como tal em documentos oficiais.
  Logo, se for aprovado, isso forçaria a adaptação de todos os sistemas que lidam ou
  consomem bases de dados governamentais/que dependam de dados como o CPF.
  Além disso, também abre precedente para que os sistemas se adequem a 
  pluralidade de identidades de gênero.

### Tabela `operations`

A tabela `operations` poderia ter uma "denormalização" intencional,
onde os campos `installments_count`, `paid_installments_count` e `installment_value`
são armazenados diretamente para facilitar consultas frequentes e evitar cálculos
repetitivos, mesmo que isso possa levar a uma redundância de dados.

Outra abordagem poderia ser criar uma `view` ou uma `materialized view` que já 
traga esses dados pré-calculados, mas isso pode adicionar complexidade 
e overhead de manutenção, especialmente se a base de dados crescer significativamente.

Ainda há a opção de criar uma tabela de `operation_summaries` que armazena 
esses dados pré-calculados, mas isso exigiria uma lógica adicional para manter 
os dados sincronizados
entre as tabelas `operations` e `installments`, o que pode ser complexo e propenso a erros.
(Podendo ser necessário o uso de triggers e uma camada de reconciliação para 
garantir a consistência dos dados).

> A camada de conciliação poderia ser um job periódico ou artisan command 
  que valida e corrige divergências (audit/sanity check).

Como o sistema está sendo iniciado do zero e não há dados de métricas
ou histórico de performance, será optado por não realizá-lá agora
(Mas isso pode ser reavaliado no futuro, 
caso seja necessário otimizar consultas específicas - algo que não será
difícil de implementar).

---

## Descrição das Entidades (MySQL Like)

### 1. Clients (Clientes)

**Colunas:**
- `id`: PK, BigInt, Auto Increment
- `name`: Varchar(255), NOT NULL
- `cpf`: Varchar(11), Unique, NOT NULL
- `birth_date`: Date, NOT NULL
- `gender`: Varchar(20), NOT NULL, Default='prefer_not_to_say' ¹
- `email`: Varchar(255), Unique, NOT NULL
- `created_at`: Timestamp, Default=CURRENT_TIMESTAMP
- `updated_at`: Timestamp, Default=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

**Índices:**
- `UNIQUE KEY idx_clients_cpf (cpf)`
- `UNIQUE KEY idx_clients_email (email)`

**Constraints de Validação (CHECK):**
- `CONSTRAINT chk_gender CHECK (gender IN ('male', 'female', 'other', 'prefer_not_to_say'))`
- `CONSTRAINT chk_cpf_length CHECK (CHAR_LENGTH(cpf) = 11)`
- `CONSTRAINT chk_email_format CHECK (email LIKE '%@%.%')`

### 2. Agreements (Conveniadas)

**Colunas:**
- `id`: PK, BigInt, Auto Increment
- `name`: Varchar(255), NOT NULL
- `created_at`: Timestamp, Default=CURRENT_TIMESTAMP
- `updated_at`: Timestamp, Default=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

**Índices:**
- `KEY idx_agreements_name (name)`

### 3. Operations (Operações)

**Colunas:**
- `id`: PK, BigInt, Auto Increment
- `client_id`: FK, BigInt, NOT NULL
- `agreement_id`: FK, BigInt, NOT NULL
- `requested_value`: Decimal(15,2), NOT NULL
- `disbursement_value`: Decimal(15,2), NOT NULL
- `total_interest`: Decimal(15,2), NOT NULL
- `late_fee_rate`: Decimal(5,2), NOT NULL
- `late_interest_rate`: Decimal(5,2), NOT NULL
- `installments_count`: Int, NOT NULL
- `status`: Varchar(30), Default='draft', NOT NULL ²
- `product_type`: Varchar(20), NOT NULL ³
- `first_due_date`: Date, NOT NULL
- `proposal_created_date`: Date, NOT NULL
- `payment_date`: Timestamp, Nullable
- `created_at`: Timestamp, Default=CURRENT_TIMESTAMP
- `updated_at`: Timestamp, Default=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

**Índices:**
- `KEY idx_operations_status (status)`
- `KEY idx_operations_product_type (product_type)`
- `KEY idx_operations_agreement_id (agreement_id)`
- `KEY idx_operations_client_id (client_id)`
- `KEY idx_operations_filters (status, product_type, agreement_id, id)` ← Índice composto (covering index)
- `KEY idx_operations_status_created (status, proposal_created_date)`
- `KEY idx_operations_payment_date (payment_date)` ← Partial index (WHERE payment_date IS NOT NULL)

**Constraints de Validação (CHECK):**
- `CONSTRAINT chk_status CHECK (status IN ('draft', 'pre_analysis', 'under_review', 'awaiting_signature', 'signed', 'approved', 'canceled', 'disbursed'))`
- `CONSTRAINT chk_product_type CHECK (product_type IN ('payroll_loan', 'personal_loan'))`
- `CONSTRAINT chk_requested_value CHECK (requested_value > 0)`
- `CONSTRAINT chk_disbursement_value CHECK (disbursement_value >= 0)`
- `CONSTRAINT chk_total_interest CHECK (total_interest >= 0)`
- `CONSTRAINT chk_late_fee_rate CHECK (late_fee_rate >= 0)`
- `CONSTRAINT chk_late_interest_rate CHECK (late_interest_rate >= 0)`
- `CONSTRAINT chk_installments_count CHECK (installments_count > 0)`
- `CONSTRAINT chk_payment_date_logic CHECK ((status = 'disbursed' AND payment_date IS NOT NULL) OR (status != 'disbursed' AND payment_date IS NULL))`

**Foreign Keys:**
- `CONSTRAINT fk_operations_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE CASCADE`
- `CONSTRAINT fk_operations_agreement_id FOREIGN KEY (agreement_id) REFERENCES agreements(id) ON DELETE RESTRICT ON UPDATE CASCADE`

### 4. Installments (Parcelas)

**Colunas:**
- `id`: PK, BigInt, Auto Increment
- `operation_id`: FK, BigInt, NOT NULL
- `installment_number`: Int, NOT NULL
- `due_date`: Date, NOT NULL
- `value`: Decimal(15,2), NOT NULL
- `paid`: Boolean, Default=false, NOT NULL
- `paid_at`: Timestamp, Nullable
- `paid_by_user_id`: FK, BigInt, Nullable
- `created_at`: Timestamp, Default=CURRENT_TIMESTAMP
- `updated_at`: Timestamp, Default=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

**Índices:**
- `KEY idx_installments_operation_id (operation_id)`
- `KEY idx_installments_operation_paid (operation_id, paid)` ← Índice composto (otimiza contagem de parcelas pagas)
- `KEY idx_installments_due_date (due_date)`
- `KEY idx_installments_operation_due (operation_id, due_date, paid)` ← Índice composto (suporta cálculo de VP)
- `UNIQUE KEY unique_installment_number (operation_id, installment_number)`

**Constraints de Validação (CHECK):**
- `CONSTRAINT chk_installment_number CHECK (installment_number > 0)`
- `CONSTRAINT chk_installment_value CHECK (value > 0)`
- `CONSTRAINT chk_installment_paid_consistency CHECK ((paid = TRUE AND paid_at IS NOT NULL) OR (paid = FALSE AND paid_at IS NULL))`

**Foreign Keys:**
- `CONSTRAINT fk_installments_operation_id FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT fk_installments_paid_by_user_id FOREIGN KEY (paid_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE`

### 5. StatusHistory (Histórico de Status)

**Colunas:**
- `id`: PK, BigInt, Auto Increment
- `operation_id`: FK, BigInt, NOT NULL
- `previous_status`: Varchar(30), Nullable ²
- `new_status`: Varchar(30), NOT NULL ²
- `changed_by_user_id`: FK, BigInt, NOT NULL
- `notes`: Text, Nullable
- `changed_at`: Timestamp, Default=CURRENT_TIMESTAMP, NOT NULL

**Índices:**
- `KEY idx_status_histories_operation_id (operation_id)`
- `KEY idx_status_histories_operation_changed_at (operation_id, changed_at DESC)` ← Índice composto (suporta busca + ordenação cronológica)

**Constraints de Validação (CHECK):**
- `CONSTRAINT chk_status_history_values CHECK (previous_status IN ('draft', 'pre_analysis', 'under_review', 'awaiting_signature', 'signed', 'approved', 'canceled', 'disbursed', NULL) AND new_status IN ('draft', 'pre_analysis', 'under_review', 'awaiting_signature', 'signed', 'approved', 'canceled', 'disbursed'))`
- `CONSTRAINT chk_status_not_equal CHECK (previous_status IS NULL OR previous_status != new_status)`

**Foreign Keys:**
- `CONSTRAINT fk_status_histories_operation_id FOREIGN KEY (operation_id) REFERENCES operations(id) ON DELETE CASCADE ON UPDATE CASCADE`
- `CONSTRAINT fk_status_histories_user_id FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE`

### 6. Users (Usuários)

**Colunas:**
- `id`: PK, BigInt, Auto Increment
- `name`: Varchar(255), NOT NULL
- `email`: Varchar(255), Unique, NOT NULL
- `username`: Varchar(50), Unique, NOT NULL
- `password`: Varchar(255), NOT NULL
- `created_at`: Timestamp, Default=CURRENT_TIMESTAMP
- `updated_at`: Timestamp, Default=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

**Índices:**
- `UNIQUE KEY idx_users_email (email)`
- `UNIQUE KEY idx_users_username (username)`

---

## Notas Importantes

### Índices Compostos
Os índices compostos foram estrategicamente projetados para:
- **`operations` (status, product_type, agreement_id, id)**: Covering index que otimiza filtros combinados em RF03 (listagem) e RF04 (relatórios)
- **`installments` (operation_id, paid)**: Otimiza contagem de parcelas pagas (reconciliação e verificação)
- **`installments` (operation_id, due_date, paid)**: Suporta cálculo de valor presente e detecção de atrasos
- **`status_histories` (operation_id, changed_at DESC)**: Otimiza recuperação do histórico cronológico

### Índices Parciais
- **`payment_date`**: Reduzem tamanho e melhoram cache, já que a maioria das operações tem `payment_date = NULL`

### Foreign Keys - Estratégia de Deleção
- **`operations` → `clients` / `agreements`**: `ON DELETE RESTRICT` → Impede exclusão de clientes/conveniadas com operações ativas (integridade crítica)
- **`installments` → `operations`**: `ON DELETE CASCADE` → Parcelas são deletadas com a operação (dados dependentes)
- **`installments` → `users`**: `ON DELETE SET NULL` → Histórico de pagamento persiste mesmo se usuário for removido
- **`status_histories` → `operations`**: `ON DELETE CASCADE` → Histórico é deletado com a operação
- **`status_histories` → `users`**: `ON DELETE RESTRICT` → Impede exclusão de usuários com histórico de alterações (auditoria)

> Casos como a deleção de um cliente ou conveniada com operações ativas, 
  ou a deleção de um usuário com histórico de alterações,
  são considerados cenários críticos que podem comprometer a integridade dos
  dados e a rastreabilidade das operações.
>
> Além disso, a deleção pode ser interpretada como uma "migração" de bancos de dados, 
  onde os dados são movidos para uma tabela de "arquivamento" ou "log" em vez 
  de serem realmente deletados, para preservar o histórico de operações
  (para a conformidade com a LGPD, os dados pessoais podem ser anonimizados 
  ou pseudonimizados, mas a estrutura de dados e o histórico de operações devem ser 
  mantidos para auditoria e conformidade).

### Constraints de Validação
Todos os constraints `CHECK` foram implementados no nível de banco de dados para:
- Garantir integridade mesmo em atualizações diretas SQL
- Evitar estados inválidos na aplicação
- Fornecer feedback imediato em caso de violação

> Elas não são necessariamente obrigatórias, mas são uma camada extra de segurança 
  e integridade dos dados, especialmente em um cenário onde múltiplas
  interfaces ou integrações podem acessar o banco de dados.

### Glossário:
- PK: Primary Key (Chave Primária)
- FK: Foreign Key (Chave Estrangeira)
- ¹ - Valores aceitos para o campo `gender`:
    - `male` - Masculino
    - `female` - Feminino
    - `other` - Outro
    - `prefer_not_to_say` - Prefiro não informar/dizer
- ² - Valores aceitos para os campos `status`, `previous_status` e `new_status`:
    - `draft` - Rascunho
    - `pre_analysis` - Pré-Análise
    - `under_review` - Em Análise
    - `awaiting_signature` - Aguardando Assinatura
    - `signed` - Assinatura Concluída
    - `approved` - Aprovada
    - `canceled` - Cancelada
    - `disbursed`: Pago ao Cliente
- ³ - Valores aceitos para o campo `product_type`:
    - `payroll_loan` - Consignado
    - `personal_loan` - Não Consignado

Para mais informações sobre o `status`: [RF05: Análise de Operação](../docs/levantamento-de-requisitos.md#status-possíveis-fluxo-sequencial).

Para consultar a definição de cada `product_type`: [RF05: Análise de Operação](../docs/levantamento-de-requisitos.md#tipos-de-produto)

---

## Relacionamentos

- Um `cliente` pode ter várias `operações` → `(1:N)`
- Uma `operação` pertence a um `cliente` → `(N:1)`
- Uma `operação` pertence a uma `conveniada` → `(N:1)`
- Uma conveniada pode ter várias `operações` → `(1:N)`
- Uma `operação` pode ter várias `parcelas` → `(1:N)`
- Uma parcela pertence a uma `operação` → `(N:1)`
- Uma `operação` pode ter várias entradas no `histórico de status` → `(1:N)`
- Um `histórico de status` pertence a uma `operação` → `(N:1)`
- Um `histórico de status` é associado a um usuário que realizou a alteração → `(N:1)`
- Um `usuário` pode realizar várias alterações no `histórico de status` → `(1:N)`
