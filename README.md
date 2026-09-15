# Custom Metadata — OMP plugin

[![OMP](https://img.shields.io/badge/OMP-3.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.0.1.2-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OMP 3.5](https://github.com/OJSBR/customMetadata/releases/download/1.0.1.2/customMetadata-1.0.1.2.tar.gz) · [OMP 3.4](https://github.com/OJSBR/customMetadata/releases/download/1.0.1.2-omp3.4/customMetadata-1.0.1.2-omp3.4.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **Open Monograph Press (OMP)** that lets you add configurable extra
metadata fields to the publication **Metadata** tab. The values are persisted in the
publication schema and are available in the book theme via `$publication->getData('key')`
or `$publication->getLocalizedData('key')`.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OMP version | Branch | Plugin release |
|-------------|--------|----------------|
| OMP 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.1.2 |
| OMP 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.1.2-omp3.4 |

Both branches share the same feature set and follow PKP issue #11793:
the schema/form hooks are always registered and the `getEnabled()` check runs inside the
callbacks, so the publication schema is extended on every request and the SchemaDAO save
does not silently drop the custom fields.

> **Upgrade from 1.0.0.x.** A field whose key already exists in the publication (for example
> `title`) was left out of the schema but still shown on the Metadata tab, as a plain text field
> in place of the native one, and a repeated key showed two fields. 1.0.1.0 ignores both lines.
> Saving the settings now requires a POST with the form's CSRF token. 1.0.1.1 restores the accents of
> the Brazilian Portuguese translation.

## The problem

Presses often need a few extra fields on a book — a print ISBN, a collection, a funding note —
that OMP does not have. Adding them usually means a custom plugin per field, or a template
change the next upgrade overwrites.

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder
   into `plugins/generic/` so you get `plugins/generic/customMetadata/`.
2. Enable **Custom Metadata** under the *Generic* plugins list.

## Configuration

Open the plugin **Settings** and declare one field per line, in the format:

```
key | Label | type | multilingual
```

- **key** — letters, numbers and underscore only; the name used in the theme, e.g.
  `$publication->getData('printIsbn')`.
- **Label** — the label shown on the Metadata tab.
- **type** — `text` (default) or `textarea`.
- **multilingual** — `1` or `yes` for multilingual fields; empty otherwise.
- Lines starting with `#` are ignored, and so is a line whose key already exists in the
  publication (such as `title` or `abstract`) or repeats an earlier key.

Example:

```
printIsbn | Print ISBN | text
collection | Collection / Series | text | 1
```

Values are stored as plain text and have no validation; print them in a theme with
`|escape`.

## Tests

- **PHPUnit** (`tests/*Test.php`, on `PKP\tests\PKPTestCase`): the plugin classes against the
  installed PKP, the plugin found by PKP's plugin registry, parsing of the field definitions, the
  schema never losing a native property, the site level without settings, the template (a form
  posted with a CSRF token) and the 38 translations. From the OMP root:

  ```bash
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/customMetadata/tests"
  ```

- **Cypress** (`cypress/tests/functional/CustomMetadata.cy.js`, run by
  [pkp-github-actions](https://github.com/pkp/pkp-github-actions) on OMP on every push): enables
  the plugin, saves the definitions from the plugin settings, shows on the publication Metadata tab
  only the new, distinct keys, saves a value there and reads it back (it fails with the schema hook
  off), and puts the definitions and the value back. Parameters: `contextPath`, `adminUser`,
  `adminPassword`, `submissionId` (an unpublished submission; default 1, as in PKP's data set).
- Verified on OMP 3.5.0.5 and 3.4.0.10.

Tests are kept in the repository and are not part of the release package.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**.

## AI use

Generative AI (Claude, by Anthropic) was used to write and run tests, improve the code and bring
it in line with PKP standards. Every change is reviewed and tested by OJSBR, which is responsible
for the published releases.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OMP version you
are working against. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin genérico para o **Open Monograph Press (OMP)** que permite adicionar campos de
metadados extras configuráveis à aba **Metadados** da publicação. Os valores são persistidos
no schema da publicação e ficam disponíveis no tema do livro via
`$publication->getData('chave')` ou `$publication->getLocalizedData('chave')`.

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com).** Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### Compatibilidade e branches

| Versão do OMP | Branch | Release do plugin |
|---------------|--------|-------------------|
| OMP 3.5.x     | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.0.1.2 |
| OMP 3.4.x     | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.1.2-omp3.4 |

As duas branches seguem a issue #11793 da PKP: os hooks de schema/formulário são sempre
registrados e o `getEnabled()` é checado dentro dos callbacks, para que o schema da
publicação seja estendido em todo request e o SchemaDAO não descarte os campos.

> **Atualização a partir da 1.0.0.x.** Um campo cuja chave já existe na publicação (por exemplo
> `title`) ficava fora do schema, mas aparecia na aba Metadados como campo de texto simples no
> lugar do nativo, e uma chave repetida mostrava dois campos. A 1.0.1.0 ignora as duas linhas.
> Salvar as configurações agora exige POST com o token CSRF do formulário. A 1.0.1.1 devolve os
> acentos da tradução em português do Brasil.

### O problema

Editoras muitas vezes precisam de alguns campos a mais no livro — ISBN impresso, coleção, nota
de financiamento — que o OMP não tem. Acrescentá-los costuma exigir um plugin por campo ou uma
mudança de template que a próxima atualização apaga.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a pasta em
`plugins/generic/` (ficando `plugins/generic/customMetadata/`). Depois ative o **Custom Metadata**
na lista de plugins *Genéricos*.

### Configuração

Nas **Configurações** do plugin, declare um campo por linha no formato
`chave | Rótulo | tipo | multilingue`. A **chave** aceita apenas letras, números e
underscore e é o nome usado para ler o valor no tema; o **tipo** pode ser `text` (padrão) ou
`textarea`; use `1`/`yes` para campos multilíngues. Linhas iniciadas por `#` são ignoradas, assim
como a linha cuja chave já existe na publicação (como `title` ou `abstract`) ou repete uma chave
anterior. Os valores são texto sem validação; no tema, imprima com `|escape`.

### Testes

PHPUnit em `tests/` (sobre `PKP\tests\PKPTestCase`) e Cypress em `cypress/tests/functional/`
(rodado pelo [pkp-github-actions](https://github.com/pkp/pkp-github-actions) no OMP a cada push),
com os comandos da seção em inglês. A suíte cobre as classes contra o PKP instalado, o plugin
encontrado pelo registro de plugins, a leitura das definições, o schema sem perder propriedade
nativa, o nível do site sem configurações, o template e as 38 traduções; o Cypress salva as
definições, confere na aba Metadados só as chaves novas e distintas e grava e relê um valor,
devolvendo tudo como estava. Verificado no OMP 3.5.0.5 e 3.4.0.10.

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
