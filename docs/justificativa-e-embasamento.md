# Justificativa Técnica e Embasamento das Decisões

Aqui está a justificativa técnica das decisões tomadas 
durante o desenvolvimento do projeto.

Para mais detalhes sobre o projeto, consulte o [README.md](../README.md).

Para visualizar os dados iniciais, consulte a
[Planilha Teste Prático - Dimensa](./Arquivos%20do%20Teste/Planilha%20Teste%20Pratico%20-%20Dimensa%20(27-03).xlsx).

---

## Escolhas Gerais

#### PHP 8.5: 
Foi escolhido pelas recentes melhorias na linguagem, segurança aprimorada, pipe operator `|>` e por ser a versão mais nova lançada. 
Mesmo sendo a versão estável mais nova, ela já tem um tempo considerável de maturidade,
pois foi lançada em Novembro/2025 e já possui patches de correção
(o que a comunidade gosta de esperar antes de adotar uma nova versão).

#### Filament: 
Escolhido para o otimizar o tempo e focar em performance/modelagem.

#### Tailwind CSS:
Para possibilitar o desenvolvimento mais otimizado e `mobile-first` 
(o `Bootstrap` também permite, mas não tem o mesmo objetivo, 
tendo foco principal em components `copy & use`).

#### Arquitetura: 
Mesmo que o Laravel tenha uma estrutura rígida de pastas,
o projeto foi organizado visando o desacoplamento, a manutenibilidade e a escalabilidade,
seguindo os princípios da `Clean Architecture` e alguns princípios
extraídos do `Domain-Driven Design` (como imutabilidade, objetos de valor e 
entidades de domínio / domínio rico).

> A estrutura padrão do Laravel ainda foi mantida,
  tendo apenas algumas pastas/camadas adicionais, 
  evitando quebrar a convenção do framework e, principalmente
  a compatibilidade com o ecossistema Laravel.

#### Filosofia das Movimentações/Operações: 
Em um cenário real,
as movimentações/operacões financeiras são tratadas como eventos imutáveis, ou seja,
uma vez que uma movimentação é registrada, ela não deve ser alterada,
mas sim, criar uma nova movimentação para refletir qualquer mudança ou correção necessária.
Por exemplo:
- Um saldo em uma conta bancária é atualizado por meio de movimentações,
onde o saldo é calculado a partir do histórico de movimentações, não sendo apenas
um campo atualizado diretamente 
(o que torna o saldo completamente auditável e rastreável).
- Um cancelamento ou estorno de uma operação financeira é registrado 
como uma nova movimentação, em vez de alterar a movimentação original,
o que mantém a integridade do histórico financeiro e permite uma trilha de auditoria clara.

Como o teste não especifica a necessidade de modificar os dados das operações
(somente seu status),
as operações serão consideradas imutáveis, mas não será implementada uma lógica 
de movimentações incrementais 
(pois seria necessária uma política de audit/sanit check).


#### Arredondamento:
Quando for necessário algum arredondamento, será adotado o `Arredondamento Bancário`
(_Regra do Par_ / _Round Half to Even_) descrito na norma **NBR 5891:2014** e
**Anexo B da ISO 80000-1**.

---

### Problema 01 - Representação de valores monetários

Mesmo que o teste não obrigue tratar os valores monetários com precisão,
este é um ponto crítico para o projeto, pois os cálculos financeiros exigem precisão
e o uso de `float` e/ou `double` pode levar a erros de arredondamento e imprecisão, 
o que é inaceitável em contextos financeiros.

Por padrão, o float/double não é seguro para cálculos financeiros.

Ao nível de banco de dados, o tipo `Decimal` é recomendado para valores monetários, 
pois evita problemas de precisão, logo, ele foi escolhido para os campos 
de valores monetários.

Ao nível de código/regras de negócio, 
temos as seguintes opções para lidar com valores monetários:

- Criar uma classe personalizada para representação Decimal:
  - Pode ser uma solução que transforme os valores em centavos (inteiros) para evitar 
  problemas de precisão, mas isso gera a necessidade de converter os valores 
  para exibição e pode complicar os cálculos.
  
  - Também existe a possibilidade de representar os valores como strings, 
  mas que pode complicar os cálculos e a manipulação dos dados (igual a anterior),
  tendo possibilidade de erros de formatação e conversão.
    
- Utilizar uma biblioteca de terceiros, como:
  - `Brick/Math`: biblioteca que permite cálculos precisos,
    que agora tem uma sintaxe mais verbosa comparada à nova sintaxe da `BCMath`,
    sendo melhor utilizada para casos de cálculos científicos.

  - `Brick/Money`: biblioteca de terceiros que suporta internacionalização de moedas,
    obriga a utilização de um método de arredondamento,
    impede cálculos entre duas moedas diferentes, ...

- Utilizar a classe nativa do PHP (`\BCMath\Number`), para cálculo preciso e performático 
  com nova sintaxe a partir do PHP 8.4 e imutável por padrão.
  - Seu tradeoff, é depender de habilitar a `extensão pecl bcmath`, 
    mas como é uma extensão amplamente utilizada e disponível na maioria dos ambientes PHP,
    isso não é um grande problema. 
  - Além disso, é garantido que em uma versão futura 
    do PHP a `BCMath` será atualizada em conjunto, pois é parte do core da linguagem. <br>
    A alteração está descrita na [RFC: Support object type in BCMath](https://wiki.php.net/rfc/support_object_type_in_bcmath) 
    e na [RFC: Fix up BCMath Number Class / Change GMP bool cast behavior](https://wiki.php.net/rfc/fix_up_bcmath_number_class).


Escolha Utilizada: `\BCMath\Number`.

Por ser nativa, imutável, pela sintaxe ter melhorado no PHP 8.4 
(suportando operações matemáticas com operadores aritméticos diretamente) e,
por ser extremamente performático (pois é implementada em C) 
e agora ser "quase intercambiável" com a `Brick/Money` 
(para migrar em caso de necessidade futura)
devido ao "compartilhamento" de seus Enums internos
(a biblioteca por ser amplamente utilizada, serviu de base para a melhoria na API da
`BCMath`).

---

### Problema 02 - Processamento de arquivos Excel/CSV

#### Problema 02.1 - Estrutura do processamento

O processamento de arquivos Excel/CSV é uma funcionalidade crítica para o projeto,
pois é necessário importar um grande volume de dados (até 50 mil registros)
de forma eficiente. 

Estimativa rápida do custo em RAM para importar os registros do CSV fornecido:

- O limite de memória padrão do PHP é de 128MB. 

- O arquivo CSV possui 50.000 linhas e 20 colunas:
    - Ocupa 5MB de espaço em disco.
    - Totaliza 1.000.000 de células (50.000 linhas x 20 colunas).

- Cada array PHP possui um custo fixo de memória, além do custo de armazenar os dados:
    - Estrutura do Array da Linha: ~76 bytes (overhead de tabela hash).
    - 20 Células (zvals): 20 x 32 bytes fixos = 640 bytes.
    - Conteúdo: Assumindo uma média 20 caracteres por célula
      - 20 caracteres x 1 byte por caractere = 20 bytes por célula.
      - string de 20 bytes no PHP = ~40 bytes.
      - 20 células/colunas x 40 bytes = 800 bytes.
    - Total por Linha: 76 bytes (array) + 640 bytes (zvals) + 800 bytes (conteúdo) 
      = ~1.516 bytes.
    - Total para 50.000 linhas: 1.516 bytes x 50.000 = ~75.800.000 bytes (~72.3 MB).

Sendo assim, é possível inferir que o consumo estará na ordem de grandeza de 50MB a 100MB
aproximadamente.

Logo, o CSV inteiro pode ser carregado na memória, 
mas isso não deixa margem para outras operações
(o que pode levar a erros de falta de memória)
e, se hover várias importações simultâneas, isso pode se tornar um grande problema.

Pensando nisso, devemos adotar uma abordagem de processamento em lote (batch processing) 
ou streaming, transformando a solução anterior de `O(N)` para `O(1)` 
em termos de uso de memória. 
Aliado a isso, também podemos processar os dados de forma assíncrona, 
utilizando filas e jobs do Laravel, para evitar que o processo de importação 
bloqueie a aplicação, para melhorar a experiência do usuário e 
para permitir a escalabilidade do sistema.

(Para fins de comparação, o processamento em lote tende a ficar na ordem de 5MB a 10MB,
dependendo do tamanho do lote escolhido).

Fontes utilizadas para os cálculos e estimativas de memória:
- [Memory usage of PHP arrays](https://www.php.net/manual/en/language.types.array.php#language.types.array.memory)
- [PHP Array Benchmark](https://lukasrotermund.de/posts/php-array-object-benchmarking/#:~:text=PHP%20in%20general%20has%20a,struct%20looks%20simplified%20like%20this:)

#### Problema 02.2 - Abordagem de processamento em lote (batch processing) ou streaming

Para o processamento em lote, podemos utilizar as abordagens:

- A combinação de `fopen()` e `fgetcsv()` do PHP,
  que lê o arquivo linha por linha, evitando carregar o arquivo inteiro na memória.
  - Vantagens: Simplicidade, baixo consumo de memória, fácil de implementar.
  - Desvantagens: Código sujo 
    (regra de negócio misturada com a lógica de leitura do arquivo), 
    difícil de testar, acoplamento entre leitura e processamento.

- Geradores do PHP (`yield`) + abordagem anterior, 
  que permitem criar um iterador personalizado para ler o arquivo em lotes.
  - Vantagens: 
    - Código mais limpo e organizado
    - Separação de responsabilidades
    - Fácil de testar 
    - Baixo consumo de memória
  - Desvantagens: 
    - Requer conhecimento prévio sobre generators 
    - Leve overhead comparado à abordagem direta somente com `fopen()` e `fgetcsv()`

- `Laravel Excel`, que é uma biblioteca de terceiros amplamente utilizada 
  para importação/exportação de arquivos Excel/CSV.
  - Vantagens: 
    - Abstração de baixo nível
    - Fácil de usar
    - Suporte a diversos formatos
  - Desvantagens: 
    - Dependência de terceiros
    - Pode ser mais pesado do que uma solução personalizada
    - Cria uma árvore de dependências no projeto (muitas dependências indiretas)
    - Funciona em cima do `PhpSpreadsheet`, que é uma biblioteca de terceiros para 
      manipulação de arquivos Excel/CSV, 
      o que pode levar a problemas de performance e consumo de memória 
      (e ela teve um histórico de problemas relacionados a isso, 
      como também, problemas de segurança, relacionadas a ataques de 
      Cross-Site Scripting (XSS) e XML External Entity (XXE)
    - Curva de aprendizado maior
  
  - `Spatie/Simple-Excel`, que é uma biblioteca de terceiros mais leve e focada 
    em performance para importação/exportação de arquivos Excel/CSV.
    - Vantagens: 
      - Foco em performance
      - Fácil de usar
      - Suporte a diversos formatos
      - Utiliza yield internamente para otimizar o consumo de memória
      - Não tem grandes dependências indiretas
    - Desvantagens: 
      - Dependência de terceiros
      - Menos recursos avançados, comparado ao `Laravel Excel`
      - Pode ser mais difícil de encontrar soluções para problemas específicos 
        devido à menor comunidade

  - `League/CSV`, que é uma biblioteca de terceiros focada exclusivamente em CSV.
    - Vantagens: 
      - Fácil de usar
      - Utiliza streaming para otimizar o consumo de memória
    - Desvantagens: 
      - Dependência de terceiros
      - Não suporta arquivos Excel (apenas CSV)
      - Menos recursos avançados, comparado ao `Laravel Excel`
      - Pode ser mais difícil de encontrar soluções para problemas específicos
      devido à menor comunidade

  - Conversão prévia de arquivos Excel para CSV utilizando `ssconvert` ou `xlsx2csv`
    + alguma das abordagens de processamento em lote 
    ou streaming para ler o CSV convertido.
    - Vantagens: 
      - Permite utilizar uma abordagem personalizada para ler o CSV convertido
      - Pode ser mais leve do que utilizar uma biblioteca de terceiros para Excel
      - É extremamente performático, pois a conversão é feita por 
        ferramentas otimizadas para isso
      - Permite dividir o processo em etapas, melhorando a organização do código,
        eficiência, debug e profiling e permite escalar de formas diferentes a
        conversão e o processamento dos dados.
    - Desvantagens: 
      - Requer a instalação de ferramentas adicionais no ambiente de produção
      - Adiciona complexidade ao processo de importação

Escolha Utilizada: `Spatie/Simple-Excel` combinada com `Fila e/ou Laravel Jobs`.
Devido ao seu foco em performance, 
facilidade de uso,
por utilizar `yield` internamente para otimizar o consumo de memória
e por não ter grandes dependências indiretas.

---

### Problema 03 - Abordagem de processamento assíncrono

Para o processamento assíncrono, utilizaremos o sistema de filas e jobs do Laravel.

Dentre os drivers de filas disponíveis, podemos escolher entre:

- `Database`: Utiliza o banco de dados para armazenar as filas.
  - Vantagens: 
    - Simplicidade 
    - Fácil de configurar
    - Não requer dependências adicionais
    - É persistente, ou seja, as filas não são perdidas em caso de falha do servidor 
      (o que também permite auditabilidade mais fácil)
    
  - Desvantagens: 
    - Pode ser mais lento do que outros drivers
    - Requer uma alternativa para escalar horizontalmente
    - Usa de `LOCK` para evitar race conditions 
      (o que pode levar a gargalos em cenários de alta concorrência)

- `Redis`: Utiliza o Redis para armazenar as filas.
  - Vantagens: 
    - Alta performance
    - Não depende do banco de dados principal

  - Desvantagens:
    - Requer a instalação e configuração do Redis
    - As filas são perdidas em caso de falha do servidor, 
      a menos que o Redis esteja configurado para persistência (AOF, RDB ou RDB + AOF)

Escolha Utilizada: `Database`. 
Por ser simples de configurar, 
não requerer dependências adicionais e ser persistente por natureza.

> NOTA: Para este volume de 50 mil registros, optei pelo MySQL pela 
  simplicidade e persistência nativa. 
  Contudo, para escalas maiores, a arquitetura está pronta para migrar 
  para Redis com (RDB + AOF, por exemplo), 
  garantindo latência sub-milissegundo sem sacrificar a durabilidade dos jobs 
  (e sem ter problemas de gargalho no banco em um cenário de concorrência massiva).
