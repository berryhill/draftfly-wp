.PHONY: help release release-patch release-minor release-major version tag push current-version

# Colors for output
CYAN := \033[0;36m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

# Plugin file
PLUGIN_FILE := draftfly.php

# Get current version from plugin file
CURRENT_VERSION := $(shell grep -oP "Version:\s*\K[0-9]+\.[0-9]+\.[0-9]+" $(PLUGIN_FILE))

help: ## Show this help message
	@echo "$(CYAN)DraftFly WordPress Plugin - Release Management$(NC)"
	@echo ""
	@echo "$(GREEN)Available commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(GREEN)Current version:$(NC) $(CYAN)$(CURRENT_VERSION)$(NC)"

current-version: ## Display current plugin version
	@echo "$(GREEN)Current version:$(NC) $(CYAN)$(CURRENT_VERSION)$(NC)"

release: ## Interactive release - prompts for version number
	@echo "$(CYAN)Current version: $(CURRENT_VERSION)$(NC)"
	@read -p "Enter new version (e.g., 1.2.3): " NEW_VERSION; \
	if [ -z "$$NEW_VERSION" ]; then \
		echo "$(RED)Error: Version cannot be empty$(NC)"; \
		exit 1; \
	fi; \
	echo "$(YELLOW)Creating release v$$NEW_VERSION...$(NC)"; \
	$(MAKE) _do_release VERSION=$$NEW_VERSION

release-patch: ## Bump patch version (1.0.0 -> 1.0.1)
	@$(eval MAJOR := $(shell echo $(CURRENT_VERSION) | cut -d. -f1))
	@$(eval MINOR := $(shell echo $(CURRENT_VERSION) | cut -d. -f2))
	@$(eval PATCH := $(shell echo $(CURRENT_VERSION) | cut -d. -f3))
	@$(eval NEW_PATCH := $(shell echo $$(($(PATCH) + 1))))
	@$(eval NEW_VERSION := $(MAJOR).$(MINOR).$(NEW_PATCH))
	@echo "$(YELLOW)Bumping patch version: $(CURRENT_VERSION) -> $(NEW_VERSION)$(NC)"
	@$(MAKE) _do_release VERSION=$(NEW_VERSION)

release-minor: ## Bump minor version (1.0.0 -> 1.1.0)
	@$(eval MAJOR := $(shell echo $(CURRENT_VERSION) | cut -d. -f1))
	@$(eval MINOR := $(shell echo $(CURRENT_VERSION) | cut -d. -f2))
	@$(eval NEW_MINOR := $(shell echo $$(($(MINOR) + 1))))
	@$(eval NEW_VERSION := $(MAJOR).$(NEW_MINOR).0)
	@echo "$(YELLOW)Bumping minor version: $(CURRENT_VERSION) -> $(NEW_VERSION)$(NC)"
	@$(MAKE) _do_release VERSION=$(NEW_VERSION)

release-major: ## Bump major version (1.0.0 -> 2.0.0)
	@$(eval MAJOR := $(shell echo $(CURRENT_VERSION) | cut -d. -f1))
	@$(eval NEW_MAJOR := $(shell echo $$(($(MAJOR) + 1))))
	@$(eval NEW_VERSION := $(NEW_MAJOR).0.0)
	@echo "$(YELLOW)Bumping major version: $(CURRENT_VERSION) -> $(NEW_VERSION)$(NC)"
	@$(MAKE) _do_release VERSION=$(NEW_VERSION)

_do_release:
	@if [ -z "$(VERSION)" ]; then \
		echo "$(RED)Error: VERSION is required$(NC)"; \
		exit 1; \
	fi
	@echo "$(CYAN)Step 1/6: Checking git status...$(NC)"
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "$(RED)Error: Working directory is not clean. Commit or stash changes first.$(NC)"; \
		git status --short; \
		exit 1; \
	fi
	@echo "$(GREEN)✓ Working directory is clean$(NC)"

	@echo "$(CYAN)Step 2/6: Updating version in $(PLUGIN_FILE)...$(NC)"
	@sed -i "s/Version: $(CURRENT_VERSION)/Version: $(VERSION)/" $(PLUGIN_FILE)
	@sed -i "s/define( 'DRAFTFLY_VERSION', '$(CURRENT_VERSION)' );/define( 'DRAFTFLY_VERSION', '$(VERSION)' );/" $(PLUGIN_FILE)
	@echo "$(GREEN)✓ Updated version to $(VERSION)$(NC)"

	@echo "$(CYAN)Step 3/6: Updating version in README.txt...$(NC)"
	@sed -i "s/Stable tag: $(CURRENT_VERSION)/Stable tag: $(VERSION)/" README.txt
	@echo "$(GREEN)✓ Updated README.txt$(NC)"

	@echo "$(CYAN)Step 4/6: Committing changes...$(NC)"
	@git add $(PLUGIN_FILE) README.txt
	@git commit -m "Bump version to $(VERSION)"
	@echo "$(GREEN)✓ Changes committed$(NC)"

	@echo "$(CYAN)Step 5/6: Creating git tag v$(VERSION)...$(NC)"
	@git tag -a v$(VERSION) -m "Release version $(VERSION)"
	@echo "$(GREEN)✓ Tag created$(NC)"

	@echo "$(CYAN)Step 6/6: Pushing to remote...$(NC)"
	@git push origin main
	@git push origin v$(VERSION)
	@echo "$(GREEN)✓ Pushed to remote$(NC)"

	@echo ""
	@echo "$(GREEN)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo "$(GREEN)✓ Release v$(VERSION) completed successfully!$(NC)"
	@echo "$(GREEN)━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━$(NC)"
	@echo ""
	@echo "$(CYAN)GitHub Action is now building the release...$(NC)"
	@echo "$(YELLOW)Check: https://github.com/yourusername/draftfly-wp/actions$(NC)"

version: current-version ## Alias for current-version
