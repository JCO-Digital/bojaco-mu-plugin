dist/bojaco.php: index.php
	php tools/merge.php index.php dist/bojaco.php

PHONY: clean
clean:
	rm -f dist/bojaco.php

PHONY: build
build: clean dist/bojaco.php
