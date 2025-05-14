SHELL := /bin/bash

RUN_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
YARN=./bin/yarn

config:
	@if [ ! -f '.env' ]; then \
		cp .env.example .env; \
		sed -i.bak "s?PROJECT_ROOT=.*?PROJECT_ROOT=$$(pwd)?" .env && rm .env.bak; \
	fi; \
	set -o allexport; source .env; set +o allexport; \
	if [ ! -f '.yarnrc.yml' ]; then \
		cp .yarnrc.yml.template .yarnrc.yml; \
	fi; \
	sed -i.bak "s/__FORTAWESOME_TOKEN__/$${FORTAWESOME_TOKEN}/" .yarnrc.yml && rm .yarnrc.yml.bak;


.build:
	$(YARN) run build

$(eval $(subst $$, $$$$, $(RUN_ARGS)):;@:)
