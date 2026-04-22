# Find all PHP files except those in dist or vendor
PHP_FILES := $(shell find modules -name "*.php")

dist/bojaco.php: index.php $(PHP_FILES)
	@mkdir -p dist
	php tools/merge.php index.php dist/bojaco.php

PHONY: clean
clean:
	rm -f dist/bojaco.php

PHONY: build
build: clean dist/bojaco.php
