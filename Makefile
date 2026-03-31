export MAKEFLAGS='--silent --environment-override'

ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))

.ONESHELL:

.PHONY: build
build:
	if [ ! -f $(ROOT)/env.yaml ]; then
		echo "Error: env.yaml not found."
		echo "Run 'make php' to build PHP, then 'make env' to generate env.yaml."
		exit 1
	fi

	cd $(ROOT)
	while IFS= read -r line; do
		key="$${line%%:*}"
		value="$${line#*: \"}"
		value="$${value%\"}"
		[ -n "$$key" ] && export "$$key=$$value"
	done < env.yaml
	go build -tags "nowatcher" -o dist/frankenstate .
	echo "Built dist/frankenstate"

.PHONY: run
run: build
	cd $(ROOT)
	while IFS= read -r line; do
		key="$${line%%:*}"
		value="$${line#*: \"}"
		value="$${value%\"}"
		[ -n "$$key" ] && export "$$key=$$value"
	done < env.yaml
	FRANKENSTATE_DOC_ROOT=examples dist/frankenstate

.PHONY: clean
clean:
	rm -f dist/frankenstate

# PHP build targets (delegated to build/php/Makefile)
.PHONY: php
php:
	$(MAKE) -f $(ROOT)/build/php/Makefile download build

.PHONY: env
env:
	$(MAKE) -f $(ROOT)/build/php/Makefile env

.PHONY: php-clean
php-clean:
	$(MAKE) -f $(ROOT)/build/php/Makefile clean=1 clean

.PHONY: tidy
tidy:
	cd $(ROOT) && go mod tidy
