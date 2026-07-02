# Custom Metadata — OMP plugin (OMP 3.4 branch)

[![OMP](https://img.shields.io/badge/OMP-3.4-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.0.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

> **This is the `stable-3_4_0` branch (OMP 3.4).** For OMP 3.5 use the
> [`stable-3_5_0`](../../tree/stable-3_5_0) branch.

A generic plugin for **Open Monograph Press (OMP)** that lets you add configurable extra
metadata fields to the publication **Metadata** tab. The values are persisted in the
publication schema and are available in the book theme via `$publication->getData('key')`
or `$publication->getLocalizedData('key')`.

> **Developed and maintained by [OJSBR](https://ojsbr.com.br).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OMP version | Branch | Plugin release |
|-------------|--------|----------------|
| OMP 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.0.0 |
| OMP 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) *(this branch)* | 1.0.0.0 |

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
- Lines starting with `#` are ignored.

Example:

```
printIsbn | Print ISBN | text
collection | Collection / Series | text | 1
```

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com.br) — original plugin.
- Distributed under the **GNU GPL v3**.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OMP version you
are working against.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

> **Esta é a branch `stable-3_4_0` (OMP 3.4).** Para OMP 3.5 use a branch
> [`stable-3_5_0`](../../tree/stable-3_5_0).

Plugin genérico para o **Open Monograph Press (OMP)** que permite adicionar campos de
metadados extras configuráveis à aba **Metadados** da publicação. Os valores são persistidos
no schema da publicação e ficam disponíveis no tema do livro via
`$publication->getData('chave')` ou `$publication->getLocalizedData('chave')`.

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com.br).** Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### Configuração

Nas **Configurações** do plugin, declare um campo por linha no formato
`chave | Rótulo | tipo | multilingue`. A **chave** aceita apenas letras, números e
underscore e é o nome usado para ler o valor no tema; o **tipo** pode ser `text` (padrão) ou
`textarea`; use `1`/`yes` para campos multilíngues. Linhas iniciadas por `#` são ignoradas.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com.br) — plugin autoral.
- Distribuído sob a **GNU GPL v3**.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
