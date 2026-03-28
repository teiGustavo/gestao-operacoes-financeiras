# Levantamento de Requisitos

Este é um levantamento de requisitos construído com base no 
PDF de descrição do teste técnico.

Nota-se que nem todos os detalhes estão presentes no PDF, 
então algumas suposições foram feitas por mim, para nortear e embasar
a implementação do projeto.

Para mais detalhes sobre o projeto, consulte o [README.md](../README.md).

---

### 🔵 Requisitos Funcionais

#### RF01 - Sistema de Autenticação (Login)
**Descrição:** O sistema deve permitir que usuários realizem login com suas credenciais para acessar as funcionalidades de gestão.

**Critérios de Aceitação:**
- O usuário deve poder acessar o sistema informando login (email ou usuário) e senha
- O sistema deve validar as credenciais informadas
- Permitir acesso em caso de sucesso na autenticação
- Exibir mensagem de erro em caso de falha na autenticação
- Usuários não autenticados não podem acessar as telas do sistema
- Implementar proteção de rotas e sessões

#### RF02 - Importação de Dados em Lote
**Descrição:** O sistema deve importar dados de uma planilha Excel contendo informações de clientes, operações, conveniadas e parcelas.

**Dados a serem importados:**

**Dados do Cliente:**
- Nome
- CPF (formato varchar)
- Data de nascimento
- Sexo
- Email

**Dados da Operação:**
- Código da operação (gerado automaticamente pelo sistema)
- Valor requerido
- Valor de desembolso
- Valor total do Juros
- Status
- Taxa da Operação (taxa_juros)
- Taxa Mora
- Taxa da Multa
- Data de criação
- Data de pagamento
- Produto (Consignado / Não consignado)
- Código da conveniada

**Dados da Conveniada:**
- Código
- Nome (10 conveniadas disponíveis):
    1. Prefeitura de Leopoldina
    2. Prefeitura de Cataguases
    3. Prefeitura de Ponte Nova
    4. Prefeitura de Ubá
    5. Prefeitura de Muriaé
    6. Exército de Leopoldina
    7. Exército de Cataguases
    8. Governo de SP
    9. Prefeitura de Goiânia
    10. Prefeitura de São Paulo

**Dados das Parcelas:**
- Número da parcela
- Data de vencimento
- Valor da parcela (Float)
- Status (Pago ou Pendente)

**Regras de Importação:**
- Importar todos os registros para o banco de dados
- Cada linha da planilha representa uma operação completa
- Gerar automaticamente campos não presentes na planilha
- Cada parcela deve ter intervalo de 1 mês (30 dias) em relação à parcela anterior
- Suportar importação de até 50 mil registros

#### RF03 - Esteira de Operações (Listagem e Filtros)
**Descrição:** O sistema deve exibir uma lista de operações com capacidade de filtros combinados.

**Campos da Listagem:**
- Código da operação
- Nome do cliente
- CPF
- Valor da operação
- Status
- Produto
- Conveniada

**Filtros Disponíveis (podem ser combinados):**
- Status
- Operação
- Produto
- Conveniada

**Critérios de Aceitação:**
- Exibir listagem paginada de operações
- Permitir aplicação de múltiplos filtros simultaneamente
- Manter performance mesmo com grande volume de dados
- Interface responsiva e intuitiva

#### RF04 - Geração de Relatórios
**Descrição:** O sistema deve gerar relatórios em formato CSV ou Excel respeitando os filtros aplicados.

**Campos do Relatório:**
- Código da operação
- Nome do cliente
- CPF
- Valor da operação
- Status
- Produto
- Conveniada
- **Valor Presente** (calculado conforme a data de exportação)

**Cálculo do Valor Presente:**

**Para parcelas em atraso:**
    
$$
VP = V \times \left(1 + m + (j \times d)\right) + V \times \left(\left(1 + i\right)^{\frac{d}{30}} - 1\right)
$$


**Para parcelas adiantadas:**

$$
VP = \dfrac{V}{\left(1 + i\right)^{\frac{d}{30}}}
$$

**Onde:**
$$
\begin{flalign*}
& \mathrm{VP} = \text{Valor Presente} & \\
& \mathrm{V}  = \text{Valor da Parcela} & \\
& \mathrm{m}  = \text{Multa} & \\
& \mathrm{j}  = \text{Juros Mora} & \\
& \mathrm{i}  = \text{Taxa da Operação} & \\
& \mathrm{d}  = \text{Dias (atraso/adiantamento)} &
\end{flalign*}
$$

**Regras do Relatório:**
- Deve respeitar os filtros aplicados na tela
- Formato: CSV ou Excel
- Deve suportar grande volume de dados
- Não deve comprometer a performance da aplicação
- Geração assíncrona recomendada para grandes volumes

#### RF05 - Análise de Operação (Detalhes e Alteração de Status)
**Descrição:** O sistema deve permitir visualizar detalhes de uma operação e atualizar seu status conforme regras de negócio.

**Status Possíveis (fluxo sequencial):**
1. DIGITANDO
2. PRÉ-ANÁLISE
3. EM ANÁLISE
4. PARA ASSINATURA
5. ASSINATURA CONCLUÍDA
6. APROVADA
7. CANCELADA
8. PAGO AO CLIENTE

**Critérios de Aceitação:**
- Permitir acesso ao detalhe de uma operação a partir da listagem
- Permitir alteração de status conforme regras estabelecidas
- Exibir todos os dados da operação, cliente e parcelas
- Interface clara para mudança de status

**Regras de Alteração de Status:**
1. Uma operação só pode ser marcada como **PAGO AO CLIENTE** se:
    - Estiver com status **APROVADA**
    - Já tiver passado por **ASSINATURA CONCLUÍDA**

2. Após status **PAGO AO CLIENTE**:
    - A data de pagamento da tabela de operação deve ser atualizada automaticamente
    - O status não pode mais ser alterado (operação finalizada)

3. Toda alteração de status deve:
    - Ser registrada em log/histórico
    - Incluir: usuário responsável, data/hora, status anterior e novo status

#### RF06 - Histórico de Alterações
**Descrição:** O sistema deve manter registro de todas as alterações de status das operações.

**Informações do Log:**
- ID da operação
- Status anterior
- Status novo
- Usuário que realizou a alteração
- Data e hora da alteração
- Observações (opcional)

---

### 🔴 Requisitos Não Funcionais

#### RNF01 - Performance
**Descrição:** O sistema deve manter performance adequada mesmo com alto volume de dados.

**Critérios:**
- Importação de 50 mil registros deve ser concluída em tempo aceitável (< 5 minutos)
- Listagem de operações deve carregar em menos de 2 segundos
- Filtros devem responder em menos de 1 segundo
- Utilizar índices adequados no banco de dados
- Implementar paginação para grandes volumes
- Cache de consultas frequentes quando aplicável
- Processamento assíncrono para tarefas pesadas (importação, geração de relatórios)

#### RNF02 - Escalabilidade
**Descrição:** A arquitetura deve suportar crescimento de dados e usuários.

**Critérios:**
- Estrutura de banco de dados normalizada
- Uso de filas para processamento assíncrono (jobs)
- Possibilidade de otimização de queries
- Código modular e extensível

#### RNF03 - Segurança
**Descrição:** O sistema deve proteger dados sensíveis e controlar acesso.

**Critérios:**
- Autenticação obrigatória para todas as funcionalidades
- Senhas devem ser armazenadas com hash seguro (bcrypt/argon2)
- Proteção contra SQL Injection (uso de ORM/Prepared Statements)
- Proteção contra CSRF (Cross-Site Request Forgery)
- Validação de entrada de dados
- Sanitização de dados de saída
- Controle de sessão seguro
- HTTPS em produção (recomendado)

#### RNF04 - Usabilidade
**Descrição:** Interface intuitiva e responsiva.

**Critérios:**
- Design responsivo (funciona em desktop, tablet e mobile)
- Feedback visual para ações do usuário
- Mensagens de erro claras e objetivas
- Interface consistente em todas as telas
- Utilização de framework CSS (Bootstrap ou equivalente)

#### RNF05 - Manutenibilidade
**Descrição:** Código limpo e bem documentado.

**Critérios:**
- Código seguindo padrões PSR (PHP Standard Recommendations)
- Arquitetura MVC bem definida
- Comentários em lógicas complexas
- Nomenclatura clara de variáveis e métodos
- Separação de responsabilidades (Services, Repositories, Controllers)

#### RNF06 - Confiabilidade
**Descrição:** O sistema deve ser estável e confiável.

**Critérios:**
- Tratamento adequado de exceções
- Logs de erros e ações críticas
- Validação de dados em todas as camadas
- Transações de banco de dados para operações críticas
- Rollback em caso de falha na importação

#### RNF07 - Compatibilidade
**Descrição:** Compatibilidade com tecnologias especificadas.

**Critérios:**
- PHP 8 ou superior
- Laravel 12 ou superior
- MySQL
- JavaScript
- Bootstrap ou equivalente
- Compatível com ambientes Xampp, Docker ou equivalentes

#### RNF08 - Modelagem de Dados
**Descrição:** Estrutura de banco de dados eficiente e bem modelada.

**Critérios:**
- Normalização adequada
- Relacionamentos bem definidos (One-to-Many, Many-to-Many)
- Índices em campos de busca e foreign keys
- Constraints de integridade referencial
- Soft deletes para dados importantes
- Timestamps para auditoria

---

## 📌 Observações Importantes

1. **CPF:** Atualmente definido como VARCHAR, pode ser integrado e armazenado no banco nesse formato
2. **Campos Gerados:** Campos não presentes na planilha devem ser gerados automaticamente pelo sistema conforme regras de negócio
3. **Intervalo de Parcelas:** Cada parcela deve ter intervalo de 1 mês (30 dias) em relação à parcela anterior
4. **Tolerância de Cálculo:** Caso os valores de Valor Presente não coincidam exatamente, não há problema; o objetivo é validar a estrutura e implementação do cálculo
