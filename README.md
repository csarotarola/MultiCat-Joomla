# Otarola Joomla Multi-Category

Otarola Joomla Multi-Category is a system plugin for Joomla 4, Joomla 5, and Joomla 6 that allows content editors to assign multiple categories to a single article. Articles keep their primary category for routing and SEO, but can also appear in additional category listings across the frontend.

## Features

- Adds an **Additional Categories** fieldset with checkbox-based multi-select to the article edit form.
- Stores relationships in the bridge table `#__content_multicat`.
- Extends Joomla's native article listings (component views and article-based modules) so that articles show up when matched through their primary or additional categories.
- Optional debug logging to the Joomla log for troubleshooting.
- Fully translated into English (en-GB) with multi-language ready structure.

## Requirements

- Joomla 4.4+, 5.x, or 6.x.
- PHP 8.1 or newer (matching the core Joomla requirement).

## Installation

1. Download or build the installation ZIP (see below for build instructions).
2. Install the plugin through **Extensions → Manage → Install**.
3. Enable the **System - Otarola Multi-Category** plugin.
4. (Optional) Enable debug logging within the plugin settings if you need verbose diagnostics.

### Building the installation ZIP

From the repository root run:

```bash
zip -r otarola_multicat.zip plugins/system/otarola_multicat README.md CHANGELOG.md LICENSE
```

Upload the resulting ZIP file through the Joomla installer.

## Usage

1. Edit any article and expand the **Additional Categories** fieldset.
2. Select zero or more extra categories using the checkbox list.
3. Save the article. The plugin writes the selections into `#__content_multicat`.
4. Frontend article listings now include the article whenever either the primary category or one of the additional categories match the listing criteria. URLs and breadcrumbs continue to use the primary category.

## Logging

Set **Enable debug logging** to **Yes** inside the plugin configuration to send verbose messages to the Joomla log (`administrator/logs/otarola_multicat.php`).

## Development

The codebase follows PSR-12 coding standards and leverages strict typing where practical. Helper classes encapsulate database access and model overrides to keep the plugin maintainable.

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
