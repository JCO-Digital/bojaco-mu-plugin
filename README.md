# Bojaco MU Plugin

A custom WordPress Must-Use (MU) plugin development environment featuring an automated build process to merge multiple modules into a single production-ready file.

## Features

- **Modular Development**: Write clean, separated PHP modules in the `modules/` directory.
- **Auto-Merging**: A custom PHP tool (`tools/merge.php`) that resolves `require_once` statements and flattens your code into a single file for deployment.
- **GitHub Actions Integration**: Automated releases that build and attach the merged plugin binary to GitHub tags.
- **Makefile Driven**: Simple build commands to manage the development lifecycle.

## Project Structure

```text
.
├── dist/               # Compiled/merged plugin (ignored by git)
├── modules/            # Plugin modules and features
├── tools/              # Build tools and scripts
│   └── merge.php       # PHP script to flatten dependencies
├── index.php           # Plugin entry point
├── Makefile            # Build automation
└── composer.json       # Project dependencies and metadata
```

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- Make

### Development

1.  Add your logic into the `modules/` directory.
2.  Include your modules in the root `index.php` using standard `require_once` statements:
    ```php
    require_once 'modules/my-feature.php';
    ```

### Building for Production

To generate the single-file version of the plugin, run:

```bash
make build
```

The compiled plugin will be available at `dist/bojaco.php`. This is the file you should upload to the `wp-content/mu-plugins/` directory on your WordPress server.

## Automated Releases

This project is configured with a GitHub Action (`.github/workflows/release.yml`). When you push a new tag following the `v*` pattern (e.g., `v1.0.0`), the workflow will:

1.  Set up a PHP environment.
2.  Run the build process via `make`.
3.  Create a new GitHub Release.
4.  Attach the compiled `dist/bojaco.php` as a release asset.

## Tooling Details

### merge.php

The `tools/merge.php` script is a token-based PHP flattener. It recursively finds `require_once` calls, reads the target file, strips the opening/closing PHP tags, and injects the content into the main file. It also handles basic path resolution using `__DIR__` or `dirname(__FILE__)`.

## License

This project is licensed under the GPL-2.0-or-later License.
