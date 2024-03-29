BINFILES='\.(png|jpe?g|gif|deb|rpm|vpp|rtf)$$'

all:
	# only some functions supported

.include "${.CURDIR}/HPWname.inc"

metacheck: generated
.ifdef have_isutf8
	@echo Checking that all files are in UTF-8
	find * -type f -print0 | grep -zEv ${BINFILES} | xargs -0 -- isutf8
.endif
	@echo Checking for CVS/git conflict markers
	find * -type f -print0 | grep -zEv ${BINFILES} | xargs -0r mksh -c \
	    'grep -El "^[<>=]{7}( |\$$)" "$$@"; test $$? -eq 1' \
	    hpw-metacheck-helper-cvs
.ifdef have_pcregrep
	@echo Ensuring there is no whitespace or CR at end of lines
	find * -type f -print0 | grep -zEv ${BINFILES} | xargs -0r mksh -c \
	    'pcregrep -l $$'\''[\t\x0B-\x0D ]$$'\'' "$$@"; test $$? -eq 1' \
	    hpw-metacheck-helper-eol
.endif
	@echo Checking for empty lines or missing newline at EOF
	rv=0; find * -type f -print0 | grep -zEv ${BINFILES} |& \
	    while IFS= read -p -d '' -r name; do \
		if [[ -n "$$(tail -c -1 "$$name")" ]]; then \
			rv=1; \
			print -r -- "$$name: no newline at EOF"; \
		fi; \
		if [[ -s $$name && -z "$$(tail -n 1 "$$name")" ]]; then \
			rv=1; \
			print -r -- "$$name: empty line at EOF"; \
		fi; \
	done; exit $$rv
	@echo All done.

syntaxcheck: generated
	@echo Running syntax checks, please verify output manually.
	set +e; rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

GENERATED+=common/VERSION.php
CLEANFILES+=www/artifact-version
common/VERSION.php: debian/changelog
	printf '%s\n' '<?php' '' \
	    '/* THIS FILE IS AUTOMATICALLY GENERATED, DO NOT EDIT! */' '' \
	    "define('HPW_NAMEWRD', '${HPW_nameword}');" \
	    "define('HPW_PACKAGE', '${HPW_package}');" \
	    "define('HPW_AUTOLDR', ${HPW_autoload});" \
	    "define('HPW_VERSION', '$$(echo \
	    30~dummy)');" >$@~
	php -l $@~
	echo '${HPW_nameword} (${HPW_package})' $$(echo \
	    30~dummy) >www/artifact-version
	mv -f $@~ $@

GENERATED+=var/AUTOLDR.php
var/AUTOLDR.php:
	printf '%s\n' '<''?php' '$$classlist = array(' \
	    "'HpwWeb' => '/common/hpw.php'" ');' >$@~
	php -l $@~
	mv -f $@~ $@

CLEANFILES+=common/*~ dbconfig/install/*~ var/*~

CLEANFILES+=${GENERATED}
generated: ${GENERATED}

lcheck: generated
	# run tests without phpunit
	php tests/fakeunit.php

clean:
	rm -f ${CLEANFILES}

.PHONY: all generated lcheck clean metacheck syntaxcheck
