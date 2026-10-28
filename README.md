# Custom Metadata — OJS and OMP plugin

[![OJS](https://img.shields.io/badge/OJS-3.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![OMP](https://img.shields.io/badge/OMP-3.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.1.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS and OMP 3.5](https://github.com/OJSBR/customMetadata/releases/download/1.1.0.0/customMetadata-1.1.0.0.tar.gz) · [OMP 3.4](https://github.com/OJSBR/customMetadata/releases/download/1.0.1.2-omp3.4/customMetadata-1.0.1.2-omp3.4.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **Open Journal Systems (OJS)** and **Open Monograph Press (OMP)** that lets
you add configurable extra metadata fields to the publication **Metadata** tab. Each field is
editable by the authors too, or by the editors only. The values are persisted in the publication
schema and are available in the theme via `$publication->getData('key')` or
`$publication->getLocalizedData('key')`.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| Application | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x and OMP 3.5.x | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.1.0.0 |
| OMP 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.1.2-omp3.4 |

One package serves OJS and OMP 3.5: the plugin only uses what both share (the publication
schema and the Metadata form of the PKP library). Both branches follow PKP issue #11793: the
schema/form hooks are always registered and the `getEnabled()` check runs inside the callbacks,
so the publication schema is extended on every request and the SchemaDAO save does not silently
drop the custom fields.

> **New in 1.1.0.0.** OJS 3.5 support, and a fifth column that keeps a field for the editors.
> Existing definitions keep working unchanged: a line without the fifth column stays editable by
> everyone who can edit the publication, as before.
>
> **Upgrade from 1.0.0.x.** A field whose key already exists in the publication (for example
> `title`) was left out of the schema but still shown on the Metadata tab, as a plain text field
> in place of the native one, and a repeated key showed two fields. 1.0.1.0 ignores both lines.
> Saving the settings requires a POST with the form's CSRF token. 1.0.1.1 restores the accents of
> the Brazilian Portuguese translation.

## The problem

Journals and presses often need a few extra fields on an article or a book — a print ISBN, a
collection, a funding note, an internal classification — that OJS and OMP do not have. Adding
them usually means a custom plugin per field, or a template change the next upgrade overwrites.
And some of those fields are the editors' business, not the authors'.

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder
   into `plugins/generic/` so you get `plugins/generic/customMetadata/`.
2. Enable **Custom Metadata** under the *Generic* plugins list.

## Configuration

Open the plugin **Settings** and declare one field per line, in the format:

```
key | Label | type | multilingual | who edits
```

- **key** — letters, numbers and underscore only; the name used in the theme, e.g.
  `$publication->getData('printIsbn')`.
- **Label** — the label shown on the Metadata tab.
- **type** — `text` (default) or `textarea`.
- **multilingual** — `1` or `yes` for multilingual fields; empty otherwise.
- **who edits** — `editors` (or `editores`) for a field only the editors can change; empty for a
  field the authors can edit too.
- Lines starting with `#` are ignored, and so is a line whose key already exists in the
  publication (such as `title` or `abstract`) or repeats an earlier key.

Example:

```
printIsbn | Print ISBN | text
collection | Collection / Series | text | 1
fundingNote | Funding note | textarea | | editors
```

Values are stored as plain text and have no validation; print them in a theme with
`|escape`.

### Fields kept for the editors

Who counts as an editor of a submission: a site administrator, a journal or press manager, and
anybody assigned to the submission in a role other than author (an editor, a section or series
editor, an assistant). Everybody else who may edit the publication — the author while submitting,
or an author an editor allowed to change the metadata — gets:

- on the **Metadata** tab, the field as read-only text, with its value in every language and a
  note that only the editors can change it;
- in the **submission wizard**, nothing: the core shows the custom fields in the "For the
  Editors" step when the journal or press asks the authors for other metadata there, and a field
  kept for the editors is always left out of it for an author;
- from the **publication API**, an error for any change to the field. Hiding the field is not
  enough, since the API accepts every key of the schema from whoever may edit the publication;
  a form that sends the value back unchanged is accepted.

## Tests

- **PHPUnit** (`tests/*Test.php`, on `PKP\tests\PKPTestCase`): the plugin classes against the
  installed PKP, the plugin found by PKP's plugin registry, parsing of the field definitions and
  of the fifth column, the schema never losing a native property, the form an editor and an author
  get (editable, read-only with the value escaped, left out of the wizard), the change detection
  (a value sent back unchanged is no change), the site level without settings, the template (a
  form posted with a CSRF token) and the 38 translations. From the OJS or OMP root:

  ```bash
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/customMetadata/tests"
  ```

- **Cypress** (`cypress/tests/functional/CustomMetadata.cy.js`, run by
  [pkp-github-actions](https://github.com/pkp/pkp-github-actions) on OJS and OMP, MySQL and
  PostgreSQL, on every push): enables the plugin, saves the definitions, shows on the Metadata tab
  only the new, distinct keys, saves a value and reads it back. Then an author makes a submission
  of their own: their field is saved, the editors' field comes read-only on the Metadata form of a
  submission of theirs and the API refuses a change to it; the editor fills it; the author sees
  the value, can send it back unchanged but not change it; and, as a counter-check, without the
  fifth column the same change goes through. Everything is put back at the end. Parameters:
  `contextPath`, `adminUser`, `adminPassword`, `authorUser`, `authorPassword`,
  `authorSubmittedTitle`, `submissionId` (defaults from PKP's data set).
- Verified on OJS 3.5.0.5 and OMP 3.5.0.5 with real users on their databases: an author, an
  assigned section editor, a section editor not assigned and an administrator, values read back
  from `publication_settings`.

Tests are kept in the repository and are not part of the release package.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**.

## AI use

Generative AI (Claude, by Anthropic) was used to write and run tests, improve the code and bring
it in line with PKP standards. Every change is reviewed and tested by OJSBR, which is responsible
for the published releases.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OJS or OMP version
you are working against. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin genérico para o **Open Journal Systems (OJS)** e o **Open Monograph Press (OMP)** que
permite adicionar campos de metadados extras configuráveis à aba **Metadados** da publicação.
Cada campo pode ser editável também pelos autores ou somente pelos editores. Os valores são
persistidos no schema da publicação e ficam disponíveis no tema via
`$publication->getData('chave')` ou `$publication->getLocalizedData('chave')`.

**⬇️ Pacote de instalação:** [OJS e OMP 3.5](https://github.com/OJSBR/customMetadata/releases/download/1.1.0.0/customMetadata-1.1.0.0.tar.gz) · [OMP 3.4](https://github.com/OJSBR/customMetadata/releases/download/1.0.1.2-omp3.4/customMetadata-1.0.1.2-omp3.4.tar.gz).

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com).** Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### Compatibilidade e branches

| Aplicação | Branch | Release do plugin |
|-----------|--------|-------------------|
| OJS 3.5.x e OMP 3.5.x | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.1.0.0 |
| OMP 3.4.x | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.1.2-omp3.4 |

Um pacote só atende OJS e OMP 3.5: o plugin usa apenas o que os dois compartilham (o schema da
publicação e o formulário de Metadados da biblioteca da PKP). As duas branches seguem a issue
#11793 da PKP: os hooks de schema/formulário são sempre registrados e o `getEnabled()` é checado
dentro dos callbacks, para que o schema da publicação seja estendido em todo request e o
SchemaDAO não descarte os campos.

> **Novo na 1.1.0.0.** Suporte ao OJS 3.5 e uma quinta coluna que reserva o campo aos editores.
> As definições existentes continuam valendo sem mudança: linha sem a quinta coluna segue
> editável por todos que podem editar a publicação, como antes.
>
> **Atualização a partir da 1.0.0.x.** Um campo cuja chave já existe na publicação (por exemplo
> `title`) ficava fora do schema, mas aparecia na aba Metadados como campo de texto simples no
> lugar do nativo, e uma chave repetida mostrava dois campos. A 1.0.1.0 ignora as duas linhas.
> Salvar as configurações exige POST com o token CSRF do formulário. A 1.0.1.1 devolve os acentos
> da tradução em português do Brasil.

### O problema

Revistas e editoras muitas vezes precisam de alguns campos a mais no artigo ou no livro — ISBN
impresso, coleção, nota de financiamento, uma classificação interna — que o OJS e o OMP não têm.
Acrescentá-los costuma exigir um plugin por campo ou uma mudança de template que a próxima
atualização apaga. E alguns desses campos são assunto dos editores, não dos autores.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a pasta em
`plugins/generic/` (ficando `plugins/generic/customMetadata/`). Depois ative o **Metadados
Personalizados** na lista de plugins *Genéricos*.

### Configuração

Nas **Configurações** do plugin, declare um campo por linha no formato
`chave | Rótulo | tipo | multilíngue | quem edita`:

- a **chave** aceita apenas letras, números e sublinhado e é o nome usado para ler o valor no tema;
- o **tipo** pode ser `text` (padrão) ou `textarea`;
- use `1`/`sim` na quarta coluna para campos multilíngues;
- escreva `editores` (ou `editors`) na quinta coluna para um campo que só os editores podem
  alterar; deixe em branco para que os autores também possam editá-lo.

Linhas iniciadas por `#` são ignoradas, assim como a linha cuja chave já existe na publicação
(como `title` ou `abstract`) ou repete uma chave anterior. Os valores são texto sem validação; no
tema, imprima com `|escape`.

```
isbnImpresso | ISBN impresso | text
colecao | Coleção | text | 1
notaFinanciamento | Nota de financiamento | textarea | | editores
```

#### Campos reservados aos editores

Conta como editor da submissão: o administrador do site, o gestor da revista ou editora e quem
estiver designado na submissão com papel que não seja de autor (editor, editor de seção ou de
série, assistente). Qualquer outra pessoa que possa editar a publicação — o autor durante a
submissão, ou o autor a quem o editor liberou alterar os metadados — recebe:

- na aba **Metadados**, o campo somente para leitura, com o valor em cada idioma e o aviso de que
  só os editores podem alterá-lo;
- no **assistente de submissão**, nada: o núcleo mostra os campos personalizados no passo "Para
  os editores" quando a revista ou editora pede outros metadados ali, e o campo reservado aos
  editores sempre fica fora dele para o autor;
- da **API da publicação**, um erro para qualquer alteração do campo. Esconder o campo não basta,
  porque a API aceita qualquer chave do schema de quem pode editar a publicação; o formulário que
  devolve o valor sem mudança é aceito.

### Testes

PHPUnit em `tests/` (sobre `PKP\tests\PKPTestCase`) e Cypress em `cypress/tests/functional/`
(rodado pelo [pkp-github-actions](https://github.com/pkp/pkp-github-actions) no OJS e no OMP, em
MySQL e PostgreSQL, a cada push), com os comandos da seção em inglês. A suíte cobre as classes
contra o PKP instalado, a leitura das definições e da quinta coluna, o schema sem perder
propriedade nativa, o formulário do editor e do autor (editável, somente leitura com o valor
escapado, fora do assistente), a detecção de mudança, o nível do site sem configurações, o
template e as 38 traduções. O Cypress faz o caminho do autor numa submissão própria — o campo
dele é gravado, o dos editores vem somente leitura e a API recusa a alteração, o editor preenche,
o autor vê o valor e pode devolvê-lo sem mudança, mas não alterá-lo — e a contraprova: sem a
quinta coluna, a mesma alteração passa. Verificado no OJS 3.5.0.5 e no OMP 3.5.0.5 com usuários
reais nos bancos (autor, editor de seção designado, editor de seção sem designação e
administrador), com os valores relidos de `publication_settings`.

Os testes ficam no repositório e não fazem parte do pacote da release.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin autoral.
- Distribuído sob a **GNU GPL v3**.

### Uso de IA

Foi usada IA generativa (Claude, da Anthropic) para escrever e rodar testes, melhorar o código e
alinhá-lo aos padrões da PKP. Toda mudança é revisada e testada pela OJSBR, que responde pelas
releases publicadas.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
