# SPDX-FileCopyrightText: 2020-2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Variables that can be overridden by env variables
VERSION?=$(shell sed -ne 's/^\s*<version>\(.*\)<\/version>/\1/p' appinfo/info.xml)
GIT_TAG?=v$(VERSION)
OCC?=php ../../occ
NPM?=npm
PRERELEASE?=0

# Release variables
VERSION_CHANGELOG:=$(shell sed -ne 's/^\#\#\s\([0-9\.]\+-*\w*\)\s-\s.*$$/\1/p' CHANGELOG.md | head -n1 )

# Upgrade: once we have git >= 2.22 everywhere we can use the more
# readable GIT_BRANCH:=$(shell git branch --show-current)
GIT_BRANCH:=$(shell git rev-parse --abbrev-ref HEAD)
GIT_REMOTE:=$(shell git config --get "branch.${GIT_BRANCH}.remote")

# Internal variables
APP_NAME:=$(notdir $(CURDIR))
PROJECT_DIR:=$(CURDIR)/../$(APP_NAME)
BUILD_DIR:=$(CURDIR)/build
BUILD_TOOLS_DIR:=$(BUILD_DIR)/tools
RELEASE_DIR:=$(BUILD_DIR)/release
CERT_DIR:=$(HOME)/.nextcloud/certificates

# So far just for removing releases again
NEXTCLOUD_APPSTORE_API_URL:=https://apps.nextcloud.com/api/v1/apps

GITHUB_PROJECT_URL:=https://github.com/nextcloud/$(APP_NAME)

# Meta targets
all: setup-dev lint build test

setup-dev: composer-install node-modules

# Install build tools
composer: $(BUILD_TOOLS_DIR)/composer.phar

$(BUILD_TOOLS_DIR)/composer.phar:
	mkdir -p $(BUILD_TOOLS_DIR)
	cd $(BUILD_TOOLS_DIR) && curl -sS https://getcomposer.org/installer | php

$(BUILD_TOOLS_DIR)/info.xsd:
	mkdir -p $(BUILD_TOOLS_DIR)
	curl https://apps.nextcloud.com/schema/apps/info.xsd \
	--silent --location --output $(BUILD_TOOLS_DIR)/info.xsd

# Install dependencies
node-modules:
	$(NPM) ci

composer-install: composer
	php $(BUILD_TOOLS_DIR)/composer.phar install --prefer-dist

composer-install-no-dev: composer
	php $(BUILD_TOOLS_DIR)/composer.phar install --prefer-dist --no-dev

# Clean build artifacts
clean:
	rm -rf js/*
	rm -rf $(RELEASE_DIR)/$(APP_NAME)

# Also remove build tools and dependencies
distclean: clean
	rm -rf $(BUILD_TOOLS_DIR)
	rm -rf node_modules
	rm -rf vendor vendor-bin/*/vendor

# Lint
lint: lint-js lint-appinfo

lint-js:
	$(NPM) run lint

lint-appinfo: $(BUILD_TOOLS_DIR)/info.xsd
	xmllint appinfo/info.xml --noout \
		--schema $(BUILD_TOOLS_DIR)/info.xsd

# Testing
test: test-php test-js

test-php: test-php-unit test-php-integration

test-php-unit:
	$(CURDIR)/vendor/bin/phpunit --configuration tests/phpunit.xml

test-php-integration:
	$(CURDIR)/vendor/bin/behat --config=tests/Integration/config/behat.yml

test-js:
	$(NPM) test

test-js-cypress:
	cd cypress && ./runLocal.sh run

test-js-cypress-watch:
	cd cypress && ./runLocal.sh open

# Development

# Update psalm baseline
php-psalm-baseline:
	$(CURDIR)/vendor/bin/psalm.phar --set-baseline=tests/psalm-baseline.xml lib/
	$(CURDIR)/vendor/bin/psalm.phar --update-baseline lib/

text-app-includes:
	for n in `cat .files_from_text`; do cp ../../apps/text/$$n $$n ; done


# Build
build-js-dev:
	$(NPM) run dev

build-js-production:
	$(NPM) run build

# Build a release package
build: node-modules build-js-production composer-install-no-dev
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "Git repo not clean!"; \
		exit 1; \
	fi
	mkdir -p $(RELEASE_DIR)
	rsync -a --delete --delete-excluded \
		--exclude="$(APP_NAME)/.**" \
		--exclude="$(APP_NAME)/Makefile" \
		--exclude="$(APP_NAME)/TODO*" \
		--exclude="$(APP_NAME)/babel.config.js" \
		--exclude="$(APP_NAME)/build" \
		--exclude="$(APP_NAME)/composer.*" \
		--exclude="$(APP_NAME)/cypress" \
		--exclude="$(APP_NAME)/cypress.config.js" \
		--exclude="$(APP_NAME)/docs" \
		--exclude="$(APP_NAME)/eslint.config.*" \
		--exclude="$(APP_NAME)/jsconfig.json" \
		--exclude="$(APP_NAME)/node_modules" \
		--exclude="$(APP_NAME)/package-lock.json" \
		--exclude="$(APP_NAME)/package.json" \
		--exclude="$(APP_NAME)/psalm.xml" \
		--exclude="$(APP_NAME)/rector.php" \
		--exclude="$(APP_NAME)/renovate.json" \
		--exclude="$(APP_NAME)/src" \
		--exclude="$(APP_NAME)/stylelint.config.js" \
		--exclude="$(APP_NAME)/tests" \
		--exclude="$(APP_NAME)/tsconfig.json" \
		--exclude="$(APP_NAME)/vendor-bin" \
		--exclude="$(APP_NAME)/vite.*" \
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
	rm -rf $(RELEASE_DIR)/$(APP_NAME)

release-checks:
ifneq ($(VERSION),$(VERSION_CHANGELOG))
	$(error Version missmatch between `appinfo/info.xml`: $(VERSION) and `CHANGELOG.md`: $(VERSION_CHANGELOG))
endif
	@if git tag | grep -qFx $(GIT_TAG); then \
		echo "Git tag already exists!"; \
		echo "Delete it with 'git tag -d $(GIT_TAG)'"; \
		exit 1; \
	fi
	@if git ls-remote --tags $(GIT_REMOTE) "refs/tags/$(GIT_TAG)" | grep $(GIT_TAG); then \
		echo "Git tag already exists on remote $(GIT_REMOTE)!"; \
		echo "Delete it with 'git push $(GIT_REMOTE) :$(GIT_TAG)'"; \
		exit 1; \
	fi

release: release-github release-appstore

# Publish the release on Github
release-github: release-checks lint-appinfo distclean build
	# Git tag and push
	git tag $(GIT_TAG) -m "Version $(VERSION)" && git push $(GIT_REMOTE) $(GIT_TAG)

	# Publish the release on Github
ifeq ($(PRERELEASE),1)
	gh release create --prerelease --title "$(GIT_TAG)" $(GIT_TAG) ./build/release/$(APP_NAME)-$(VERSION).tar.gz
else
	gh release create --title "$(GIT_TAG)" $(GIT_TAG) ./build/release/$(APP_NAME)-$(VERSION).tar.gz
endif

	@echo "URL to release tarball (for app store): $(GITHUB_PROJECT_URL)/releases/download/$(GIT_TAG)/$(APP_NAME)-$(VERSION).tar.gz"

# Publish the release on appstore
release-appstore:
ifndef NEXTCLOUD_PASSWORD
	  $(error Missing $$NEXTCLOUD_PASSWORD)
endif
	@if [ -f $(CERT_DIR)/$(APP_NAME).key ]; then \
		echo 'Publishing $(APP_NAME)-$(VERSION) to the app store'; \
		curl -s -X POST $(NEXTCLOUD_APPSTORE_API_URL)/releases \
			-H 'Content-Type: application/json' \
			-d '{"download":"$(GITHUB_PROJECT_URL)/releases/download/$(GIT_TAG)/$(APP_NAME)-$(VERSION).tar.gz", "signature":"$(shell openssl dgst -sha512 -sign $(CERT_DIR)/$(APP_NAME).key \
					$(RELEASE_DIR)/$(APP_NAME)-$(VERSION).tar.gz | openssl base64)"}' \
			-u 'collectivecloud:$(NEXTCLOUD_PASSWORD)'; \
	fi

delete-release: delete-release-from-github delete-release-from-appstore

delete-release-from-github:
ifndef RELEASE_NAME
	  $(error Please specify the release to remove with $$RELEASE_NAME)
endif
	echo 'Removing release from Github.'
	gh release delete 'v$(RELEASE_NAME)' --cleanup-tag --yes

delete-release-from-appstore:
ifndef RELEASE_NAME
	  $(error Please specify the release to remove with $$RELEASE_NAME)
endif
ifndef NEXTCLOUD_PASSWORD
	  $(error Missing $$NEXTCLOUD_PASSWORD)
endif
	echo 'Removing release from nextcloud app store.'
	curl -s -X DELETE $(NEXTCLOUD_APPSTORE_API_URL)/collectives/releases/$(RELEASE_NAME) \
		-u 'collectivecloud:$(NEXTCLOUD_PASSWORD)'

.PHONY: all setup-dev composer node-modules composer-install composer-install-no-dev clean distclean lint lint-js lint-appinfo build build-js-dev build-js-production test test-php test-php-unit test-php-integration test-js test-js-cypress test-js-cypress-watch po php-psalm-baseline text-app-includes release release-github release-appstore delete-release delete-release-from-github delete-release-from-appstore
