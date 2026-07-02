# Custom Metadata — OMP plugin

[![OMP](https://img.shields.io/badge/OMP-3.4-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.0.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

A generic plugin for **Open Monograph Press (OMP)** that lets you add configurable extra
metadata fields to the publication **Metadata** tab. The values are persisted in the
publication schema and are available in the book theme via
`$publication->getData('key')` or `$publication->getLocalizedData('key')`.

> Developed by **[OJSBR](https://ojsbr.com.br)**.

## Compatibility / branches

| OMP version | Branch | Plugin release |
|-------------|--------|----------------|
| OMP 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) *(default)* | 1.0.0.0 |

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the
   folder into `plugins/generic/` so you get `plugins/generic/customMetadata/`.
2. Enable **Custom Metadata** under the *Generic* plugins list.

## Configuration

Open the plugin **Settings** and declare one field per line, in the format:

```
key | Label | type | multilingual
```

- **key** — letters, numbers and underscore only. This is the name used to read the
  value in the theme, e.g. `$publication->getData('printIsbn')`.
- **Label** — the label shown on the Metadata tab.
- **type** — `text` (default) or `textarea`.
- **multilingual** — `1` or `yes` for multilingual fields; empty otherwise.
- Lines starting with `#` are ignored.

Example:

```
printIsbn | Print ISBN | text
collection | Collection / Series | text | 1
```

## Contributing

Issues and pull requests are welcome.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE).

---

## 🇧🇷 Português

Plugin genérico para o **Open Monograph Press (OMP)** que permite adicionar campos de
metadados extras configuráveis à aba **Metadados** da publicação. Os valores são
persistidos no schema da publicação e ficam disponíveis no tema do livro via
`$publication->getData('chave')` ou `$publication->getLocalizedData('chave')`.

> Desenvolvido pela **[OJSBR](https://ojsbr.com.br)**.

### Configuração

Nas **Configurações** do plugin, declare um campo por linha no formato:

```
chave | Rótulo | tipo | multilingue
```

- **chave** — apenas letras, números e underscore; é o nome usado para ler o valor no
  tema, ex.: `$publication->getData('printIsbn')`.
- **Rótulo** — o texto exibido na aba Metadados.
- **tipo** — `text` (padrão) ou `textarea`.
- **multilingue** — `1` ou `yes` para campos multilíngues; vazio caso contrário.
- Linhas iniciadas por `#` são ignoradas.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE).
