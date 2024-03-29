SHELL:=/bin/mksh
BINFILES:='\.(png|jpe?g|gif|deb|rpm|vpp|rtf)$$'

all:

include HPWname.inc

AUTOLDR_DIRS+=	common
AUTOLDR_DIRS+=	www

DBC:=$(basename $(notdir $(wildcard dbconfig/install/*.in)))
ifneq (*,${DBC})
define dbsystem =
D$1_IN:=dbconfig/install/$1.in
D$1_OUT:=dbconfig/install/$1
D$1_UP:=$$(wildcard dbconfig/upgrade/$1/*)
$${D$1_OUT}: $${D$1_IN} $${D$1_UP}
	@ln -sfT . dbconfig/data
	@for rev in $$$$(dbc_share=$$$$PWD/dbconfig; dbc_basepackage=.; \
	    dbc_dbtype=$1; dbc_oldversion='0~~~~'; \
	    . /usr/share/dbconfig-common/dpkg/postinst; \
	    _dbc_find_upgrades); do \
		printf '%s\n' '' "-- revision $$$$rev" ''; \
		cat dbconfig/upgrade/$1/$$$$rev; \
	done | cat $${D$1_IN} - | \
	    sed -e '/^BEGIN;$$$$/d' -e '/^COMMIT;$$$$/d' | \
	    if [[ $1 = pgsql ]]; then \
		echo 'BEGIN;'; \
		cat; \
		echo 'COMMIT;'; \
	    else \
		cat; \
	    fi >$$@~
	mv -f $$@~ $$@
	echo created $$@
endef

$(foreach db,${DBC},$(eval $(call dbsystem,${db})))
DBC_OUT=$(foreach db,${DBC},${D${db}_OUT})
CLEANFILES+=${DBC_OUT} dbconfig/data

dbc-generated: ${DBC_OUT}
	@rm -f dbconfig/data
else
dbc-generated:
	# no dbconfig-common files to frobnicate
endif

metacheck: generated
	@echo Checking that all files are in UTF-8
	find * -type f -print0 | grep -zEv ${BINFILES} | xargs -0 -- isutf8
	@echo Checking for CVS/git conflict markers
	find * -type f -print0 | grep -zEv ${BINFILES} | xargs -0r mksh -c \
	    'grep -El "^[<>=]{7}( |\$$)" "$$@"; test $$? -eq 1' \
	    hpw-metacheck-helper-cvs
	@echo Ensuring there is no whitespace or CR at end of lines
	find * -type f -print0 | grep -zEv ${BINFILES} | xargs -0r mksh -c \
	    'pcregrep -l $$'\''[\t\x0B-\x0D ]$$'\'' "$$@"; test $$? -eq 1' \
	    hpw-metacheck-helper-eol
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
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck5: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php5 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck70: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.0 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck71: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.1 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck72: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.2 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck73: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.3 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck74: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.4 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck80: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php8.0 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck81: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php8.1 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck82: generated
	@echo Running syntax checks, please verify output manually.
	rv=0; find * -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php8.2 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
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
	    "define('HPW_VERSION', '$$(dpkg-parsechangelog -n1 | \
	    sed -n '/^Version: /s///p')');" >$@~
	php -l $@~
	echo '${HPW_nameword} (${HPW_package})' $$(dpkg-parsechangelog -n1 | \
	    sed -n '/^Version: /s///p') >www/artifact-version
	mv -f $@~ $@

GENERATED+=var/README
var/README: var/.README.in
	sed 's@HPW_package@${HPW_package}g' var/.README.in >$@

GENERATED+=var/AUTOLDR.php
var/AUTOLDR.php:
ifeq (true,${HPW_autoload})
	set -e; set -o pipefail; set -A dirs; ndirs=0; \
	    for dir in ${AUTOLDR_DIRS}; do \
		dirs[ndirs++]=$$PWD/$$dir; \
	    done; \
	    printf '%s\n' '}BEGIN' $$'\t___CLASSLIST___' '}END' | \
	    phpab -n --indent $$'\t' -b "$$PWD" -t php://stdin \
	    --blacklist ErrorException "$${dirs[@]}" | \
	    sed --posix -e '1,/^}BEGIN/d' -e '/^}END/,$$d' | \
	    printf '%s\n' '<''?php' '$$classlist = array(' "$$(cat)" ');' >$@~
else
	printf '%s\n' '<''?php' '// autoloader was disabled' >$@~
endif
	php -l $@~
	mv -f $@~ $@

CLEANFILES+=common/*~ dbconfig/install/*~ var/*~
all: metacheck syntaxcheck dbc-generated generated

CLEANFILES+=${GENERATED}
generated: ${GENERATED}

check: lcheck pcheck

pcheck: generated
	# run tests with phpunit
	cd tests && \
	    if phpunit --do-not-cache-result --help >/dev/null 2>&1; then \
		exec phpunit --do-not-cache-result .; \
	    else \
		exec phpunit .; \
	fi

lcheck: generated
	# run tests without phpunit
	php tests/fakeunit.php

lcheck5: generated
	php5 tests/fakeunit.php

lcheck70: generated
	php7.0 tests/fakeunit.php

lcheck71: generated
	php7.1 tests/fakeunit.php

lcheck72: generated
	php7.2 tests/fakeunit.php

lcheck73: generated
	php7.3 tests/fakeunit.php

lcheck74: generated
	php7.4 tests/fakeunit.php

lcheck80: generated
	php8.0 tests/fakeunit.php

lcheck81: generated
	php8.1 tests/fakeunit.php

lcheck82: generated
	php8.2 tests/fakeunit.php

clean:
	rm -f ${CLEANFILES}

.PHONY: all generated check lcheck pcheck clean metacheck syntaxcheck
.PHONY: lcheck5 lcheck70 lcheck71 lcheck72 lcheck73 lcheck74 lcheck80
.PHONY: lcheck81 lcheck82
.PHONY: syntaxcheck5 syntaxcheck70 syntaxcheck71 syntaxcheck72 syntaxcheck73
.PHONY: syntaxcheck74 syntaxcheck80 syntaxcheck81 syntaxcheck82
.PHONY: dbc-generated
