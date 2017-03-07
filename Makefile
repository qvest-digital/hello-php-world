SHELL:=/bin/mksh

AUTOLDR_DIRS+=	common
AUTOLDR_DIRS+=	www

all: syntaxcheck var/autoldr.php var/version.php

clean:
	rm -f var/autoldr.php var/version.php var/*~ www/artifact-version

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
	php -l $@~
	echo 'Hello-PHP-World (hello-php-world)' $$(dpkg-parsechangelog -n1 | \
	    sed -n '/^Version: /s///p') >www/artifact-version
	mv -f $@~ $@

var/autoldr.php:
	set -e; set -o pipefail; set -A dirs; ndirs=0; \
	    for dir in ${AUTOLDR_DIRS}; do \
		dirs[ndirs++]=$$PWD/$$dir; \
	    done; \
	    printf '%s\n' '}BEGIN' $$'\t___CLASSLIST___' '}END' | \
	    phpab -n --indent $$'\t' -b "$$PWD" -t php://stdin "$${dirs[@]}" | \
	    sed --posix -e '1,/^}BEGIN/d' -e '/^}END/,$$d' | \
	    printf '%s\n' '<''?php' '$$classlist = array(' "$$(cat)" ');' >$@~
	php -l $@~
	mv -f $@~ $@

.PHONY: all clean syntaxcheck
