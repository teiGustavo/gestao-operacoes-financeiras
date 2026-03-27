# Justificativa Técnica e Embasamento das Decisões

Aqui está a justificativa técnica das decisões tomadas 
durante o desenvolvimento do projeto.

Para mais detalhes sobre o repositório, consulte o [README.md](./README.md).

---

`PHP 8.5`: Foi escolhido pelas recentes melhorias na linguagem, segurança aprimorada, pipe operator `|>` e por ser a versão mais nova lançada. 
Mesmo sendo a versão estável mais nova, ela já tem um tempo considerável de maturidade,
pois foi lançada em Novembro/2025 e já possui patches de correção
(o que a comunidade gosta de esperar antes de adotar uma nova versão).

`Filament`: Escolhido para o otimizar o tempo e focar em performance/modelagem.

`Tailwind CSS`: Para possibilitar o desenvolvimento mais otimizado e `mobile-first` 
(o `Bootstrap` também permite, mas não tem o mesmo objetivo, 
tendo foco principal em components `copy & use`).

Quando for necessário algum arredondamento, será adotado o `Arredondamento Bancário`
(_Regra do Par_ / _Round Half to Even_) descrito na norma **NBR 5891:2014** e
**Anexo B da ISO 80000-1**.

---

### Problema 01 - Float/double para valores monetários

Por padrão, o float/double não é seguro para cálculos financeiros.

Ao nível de banco de dados, o tipo `Decimal` é recomendado para valores monetários, 
pois evita problemas de precisão, logo, ele foi escolhido para os campos 
de valores monetários.

Ao nível de código, a escolha foi entre utilizar uma biblioteca de terceiros
(já consolidada e amplamente utilizada) ou utilizar a classe nativa do PHP.

Opções Consideradas:

- `\BCMath\Number`: para calculo preciso e performático 
com nova sintaxe a partir do PHP 8.4 (depende de habilitar a `extensão pecl bcmath`).

- `Brick/Math`: biblioteca de terceiros que permite cálculos precisos,
que agora tem uma sintaxe mais verbosa comparada à nova sintaxe da `BCMath`, 
sendo melhor utilizada para casos de cálculos científicos.

- `Brick/Money`: biblioteca de terceiros que suporta internacionalização de moedas, 
obriga a utilização de um método de arredondamento, 
impede cálculos entre duas moedas diferentes, ...

Escolha Utilizada: `\BCMath\Number`.

Por ser nativa, pela sintaxe ter melhorado no PHP 8.4 
(suportando operações matemáticas com operadores aritméticos diretamente) e,
por ser extremamente performático e agora "quase intercambiável" 
com a `Brick/Money` (para migrar em caso de necessidade futura)
devido ao "compartilhamento" de seus Enums.
