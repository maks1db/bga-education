BGA_USER   := maksskv
BGA_HOST   := 1.studio.boardgamearena.com
BGA_PORT   := 2022
BGA_GAME   := krestiki
BGA_REMOTE := $(BGA_GAME)
BGA_KEY    := $(HOME)/.ssh/id_rsa_bga
BGA_SCP    := scp -i $(BGA_KEY) -P $(BGA_PORT) -o IdentitiesOnly=yes

# Config files: keep the extension the scaffold shipped (.jsonc here)
DEPLOY_ROOT   := dbmodel.sql $(wildcard gameinfos.jsonc gameinfos.inc.php stats.json stats.jsonc gameoptions.json gameoptions.jsonc gamepreferences.json gamepreferences.jsonc)
DEPLOY_PHP    := modules/php/Game.php $(wildcard modules/php/material.inc.php)
DEPLOY_STATES := $(wildcard modules/php/States/*.php)
DEPLOY_JS     := $(wildcard modules/js/*.js)

check:
	@for f in $(DEPLOY_PHP) $(DEPLOY_STATES); do php -l "$$f" || exit 1; done
	@echo "✓ PHP OK"

deploy: check
	$(BGA_SCP) $(DEPLOY_ROOT) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/
	$(BGA_SCP) krestiki.css $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/krestiki.css
	$(BGA_SCP) $(DEPLOY_PHP) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/modules/php/
	$(BGA_SCP) $(DEPLOY_STATES) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/modules/php/States/
	$(BGA_SCP) $(DEPLOY_JS) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/modules/js/
	$(BGA_SCP) img/* $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/img/
	@echo "✓ Deployed"
