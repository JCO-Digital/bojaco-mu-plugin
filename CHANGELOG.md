# Changelog

### 1.0.4 (2026-04-28)

#### Bug Fixes

- api: allow CORS OPTIONS requests and add coding standards config (799f447)

### v1.0.3 (2026-04-28)

#### Bug Fixes

- api: restrict user endpoints via rest_pre_dispatch (69d5555)

### v1.0.2 (2026-04-28)

#### Maintenance

- config: enable version synchronization for index.php (063d66f)
- version: bump plugin version to 1.0.1 (693bef7)

### v1.0.1 (2026-04-28)

#### Bug Fixes

- config: replace const with define for constant declaration (7a6c2ff)
- mu-plugin: correct misspelled constant BOJACO_MU_PLUGIN_DISABLED_MODULES (a2ffcea)

## v1.0.0 (2026-04-22)

#### Features

- index: allow disabling modules via filter and skip loading rest-api (853cb3d)
- project: update README and disable unauthenticated users REST API endpoint (0b210e4)
- plugin: add initial MU plugin, composer, Makefile, and merge tool (01e6077)

#### Continuous Integration

- release: update release workflow and Makefile build target (c1c7ee4)
- github: add release workflow to build binaries and publish artifacts on tag push (fa755f6)

#### Maintenance

- plugin: bump plugin version to 1.0.0 (dddca8f)
- release: remove composer.json, add foonver.toml and version.txt, update release workflow (9f04147)
- modules: rename rest-api module to user-rest-api and update loader (b69934a)

### Misc
- Unset all /wp/v2/users endpoints (9134d41)
- first commit (afe4add)

