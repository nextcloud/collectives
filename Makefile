# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.

# Variables that can be overridden by env variables
VERSION?=$(shell sed -ne 's/^\s*<version>\(.*\)<\/version>/\1/p' appinfo/info.xml)
OCC?=php ../../occ
NPM?=npm

# Internal variables
APP_NAME=$(notdir $(CURDIR))
PROJECT_DIR=$(CURDIR)/../$(APP_NAME)
BUILD_DIR=$(CURDIR)/build
BUILD_TOOLS_DIR=$(BUILD_DIR)/tools
RELEASE_DIR=$(BUILD_DIR)/release
CERT_DIR=$(HOME)/.nextcloud/certificates

# Meta targets
all: setup-dev lint build test

setup-dev: composer-install node-modules

# Install build tools
composer:
ifeq (, $(wildcard $(BUILD_TOOLS_DIR)/composer.phar))
	mkdir -p $(BUILD_TOOLS_DIR)
	cd $(BUILD_TOOLS_DIR) && curl -sS https://getcomposer.org/installer | php
endif

translationtool:
ifeq (, $(wildcard $(BUILD_TOOLS_DIR)/translationtool.phar))
	mkdir -p $(BUILD_TOOLS_DIR)
	curl https://github.com/nextcloud/docker-ci/raw/master/translations/translationtool/translationtool.phar \
		--silent --location --output $(BUILD_TOOLS_DIR)/translationtool.phar
endif

# Install dependencies
node-modules:
	$(NPM) install

composer-install: composer
	php $(BUILD_TOOLS_DIR)/composer.phar install --prefer-dist

# Clean build artifacts
clean:
	rm -rf js/*
	rm -rf $(RELEASE_DIR)/collectives

# Also remove build tools and dependencies
distclean: clean
	rm -rf $(BUILD_TOOLS_DIR)
	rm -rf node_modules
	rm -rf vendor

# Lint
lint: lint-js

lint-js:
	$(NPM) run lint

# Build
build: build-js-dev

build-js-dev:
	$(NPM) run dev

build-js-production:
	$(NPM) run build

# Testing
test: test-php test-js

test-php: test-php-unit test-php-integration

test-php-unit:
	$(CURDIR)/vendor/bin/phpunit --configuration phpunit.xml

test-php-integration:
	$(CURDIR)/vendor/bin/behat --config=tests/Integration/config/behat.yml

test-js:
	$(NPM) test

test-js-cypress:
	cd cypress && ./runLocal.sh run

test-js-cypress-watch:
	cd cypress && ./runLocal.sh open

# Development

# Build and update translation template from source code
po: translationtool clean
	php $(BUILD_TOOLS_DIR)/translationtool.phar create-pot-files
	sed -i 's/^#: .*\/collectives/#: \/collectives/' $(CURDIR)/translationfiles/templates/collectives.pot
	for pofile in $(CURDIR)/translationfiles/*/collectives.po; do \
		msgmerge --backup=none --update "$$pofile" translationfiles/templates/collectives.pot; \
	done

# Update l10n files from translation templates
l10n: po
	php $(BUILD_TOOLS_DIR)/translationtool.phar convert-po-files

# Update psalm baseline
php-psalm-baseline:
	$(CURDIR)/vendor/bin/psalm.phar --set-baseline=tests/psalm-baseline.xml
	$(CURDIR)/vendor/bin/psalm.phar --update-baseline

text-app-includes:
	for n in `cat .files_from_text`; do cp ../../apps/text/$$n $$n ; done

# Build a release package
build: node-modules build-js-production
	mkdir -p $(RELEASE_DIR)
	rsync -a --delete --delete-excluded \
		--exclude=".[a-z]*" \
		--exclude="Makefile" \
		--exclude="Dockerfile" \
		--exclude="babel.config.js" \
		--exclude="build" \
		--exclude="composer.*" \
		--exclude="cypress" \
		--exclude="cypress.json" \
		--exclude="docs" \
		--exclude="jest.config.json" \
		--exclude="node_modules" \
		--exclude="package-lock.json" \
		--exclude="package.json" \
		--exclude="phpunit.xml" \
		--exclude="psalm.xml" \
		--exclude="src" \
		--exclude="tests" \
		--exclude="vendor" \
		--exclude="webpack.*" \
	$(PROJECT_DIR) $(RELEASE_DIR)/
	@if [ -f $(CERT_DIR)/$(APP_NAME).key ]; then \
		echo "Signing code…"; \
		$(OCC) integrity:sign-app --privateKey="$(CERT_DIR)/$(APP_NAME).key" \
			--certificate="$(CERT_DIR)/$(APP_NAME).crt" \
			--path="$(RELEASE_DIR)/$(APP_NAME)"; \
	fi
	tar -czf $(RELEASE_DIR)/$(APP_NAME)-$(VERSION).tar.gz \
		-C $(RELEASE_DIR) $(APP_NAME)
	# Sign the release tarball
	@if [ -f $(CERT_DIR)/$(APP_NAME).key ]; then \
		echo "Signing release tarball…"; \
		openssl dgst -sha512 -sign $(CERT_DIR)/$(APP_NAME).key \
			$(RELEASE_DIR)/$(APP_NAME)-$(VERSION).tar.gz | openssl base64; \
	fi
	rm -rf $(RELEASE_DIR)/collectives

.PHONY: all setup-dev composer translationtool node-modules composer-install clean distclean lint lint-js build build-js-dev build-js-production test test-php test-php-unit test-php-integration test-js test-js-cypress test-js-cypress-watch po l10n php-psalm-baseline text-app-includes build
