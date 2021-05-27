# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.

# Variables that can be overridden by env variables
VERSION?=$(shell sed -ne 's/^\s*<version>\(.*\)<\/version>/\1/p' appinfo/info.xml)
OCC?=php ../../occ
NPM?=npm
COMMIT_IMAGE?=nextcloud-collectives:latest
LATEST_IMAGE?=nextcloud-collectives:latest

app_name=$(notdir $(CURDIR))
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build
build_tools_dir=$(build_dir)/tools
source_dir=$(build_dir)/source
release_dir=$(build_dir)/release
cert_dir=$(HOME)/.nextcloud/certificates
composer=$(shell which composer 2> /dev/null)
translationtool_url=https://github.com/nextcloud/docker-ci/raw/master/translations/translationtool/translationtool.phar

all: dev-setup lint build test

dev-setup: distclean composer npm-init translationtool

build-dev: build-js

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_dir)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_dir)
	php $(build_tools_dir)/composer.phar install --prefer-dist
else
	composer install --prefer-dist
endif

npm-init:
	$(NPM) ci

node_modules: package.json package-lock.json
	$(NPM) install

# Installs nextclouds translation tool
translationtool:
ifeq (, $(wildcard $(build_tools_dir)/translationtool.phar))
	mkdir -p $(build_tools_dir)
	curl $(translationtool_url) --silent --location --output $(build_tools_dir)/translationtool.phar
endif

# Linting
lint:
	$(NPM) run lint

# Building
build-js:
	$(NPM) run dev

build-js-production:
	$(NPM) run build

# Builds translation template from source code and update
po: clean
	php $(build_tools_dir)/translationtool.phar create-pot-files
	sed -i 's/^#: .*\/collectives/#: \/collectives/' $(CURDIR)/translationfiles/templates/collectives.pot
	for pofile in $(CURDIR)/translationfiles/*/collectives.po; do \
		msgmerge --backup=none --update "$$pofile" translationfiles/templates/collectives.pot; \
	done

l10n: po
	php $(build_tools_dir)/translationtool.phar convert-po-files

# Update psalm baseline
psalm-baseline:
	$(CURDIR)/vendor/bin/psalm.phar --set-baseline=tests/psalm-baseline.xml
	$(CURDIR)/vendor/bin/psalm.phar --update-baseline

text-app-includes:
	for n in `cat .files_from_text`; do cp ../../apps/text/$$n $$n ; done

# Testing
test: php-test test-js

php-test: php-unit-test php-integration-test

php-unit-test:
	$(CURDIR)/vendor/bin/phpunit --configuration phpunit.xml

php-integration-test:
	$(CURDIR)/vendor/bin/behat --config=tests/Integration/config/behat.yml

test-js: node_modules
	$(NPM) test

test-cypress:
	cd cypress && ./runLocal.sh run

test-cypress-watch:
	cd cypress && ./runLocal.sh open

# Cleaning
clean:
	rm -rf js/*
	rm -rf $(release_dir)/collectives

# Same as clean but also removes dependencies installed by composer and npm
distclean: clean
	rm -rf $(build_tools_dir)
	rm -rf $(source_dir)
	rm -rf node_modules
	rm -rf vendor

# Builds the source and release package
dist:
	make source
	make release

# Builds the source package
source:
	mkdir -p $(source_dir)
	rsync -a --delete --delete-excluded \
		--exclude=".git*" \
		--exclude=".php_cs.cache" \
		--exclude="build" \
		--exclude="cypress/screenshots" \
		--exclude="cypress/videos" \
		--exclude="js/*" \
		--exclude="node_modules" \
		--exclude="vendor" \
	$(project_dir) $(source_dir)/
	tar -czf $(source_dir)/$(app_name)-$(VERSION).tar.gz \
		-C $(source_dir) $(app_name)

js/collectives.js:
	$(NPM) run build

# Builds the source package for the app store
release: js/collectives.js
	mkdir -p $(release_dir)
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
	$(project_dir) $(release_dir)/
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing code…"; \
		$(OCC) integrity:sign-app --privateKey="$(cert_dir)/$(app_name).key" \
			--certificate="$(cert_dir)/$(app_name).crt" \
			--path="$(release_dir)/$(app_name)"; \
	fi
	tar -czf $(release_dir)/$(app_name)-$(VERSION).tar.gz \
		-C $(release_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing release tarball…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key \
			$(release_dir)/$(app_name)-$(VERSION).tar.gz | openssl base64; \
	fi
	rm -rf $(release_dir)/collectives
