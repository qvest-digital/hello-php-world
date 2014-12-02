SHELL:=/bin/mksh

all: syntaxcheck var/version.php

clean:
	rm -f var/version.php var/version.php~

syntaxcheck:
	@echo Running syntax checks, please verify output manually.
	rv=0; find . -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

var/version.php: debian/changelog
	printf '%s\n' '<?php' '' \
	    '/* THIS FILE IS AUTOMATICALLY GENERATED, DO NOT EDIT! */' '' \
	    "define('HPW_VERSION', '$$(dpkg-parsechangelog -n1 | \
	    sed -n '/^Version: /s///p')');" >$@~
	mv -f $@~ $@

.PHONY: all clean syntaxcheck
