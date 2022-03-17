SHELL:=/bin/mksh

all: syntaxcheck
clean:

include HPWname.inc

DIRS:=/usr/share/${HPW_package} /var/lib/${HPW_package}

syntaxcheck:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck5:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php5 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck70:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.0 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck71:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.1 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck72:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.2 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck73:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.3 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck74:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php7.4 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck80:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php8.0 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

syntaxcheck81:
	@echo Running syntax checks, please verify output manually.
	rv=0; find ${DIRS} -name '*.php' -print0 |& while IFS= read -p -d '' -r; do \
		php8.1 -l "$$REPLY" | grep -v '^No syntax errors detected in '; \
		(( PIPESTATUS[0] )) && rv=1; \
	done; exit $$rv
	@echo done.

.PHONY: all clean syntaxcheck
.PHONY: syntaxcheck5 syntaxcheck70 syntaxcheck71 syntaxcheck72 syntaxcheck73
.PHONY: syntaxcheck74 syntaxcheck80 syntaxcheck81
