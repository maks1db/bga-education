# Deploy uses password auth via .vscode/sftp.json (read by misc/bga_sftp.py).
# Alternative once the SSH key is registered in BGA Studio: scp -i ~/.ssh/id_rsa_bga -P 2022 -o IdentitiesOnly=yes
BGA_PY         := $(HOME)/.bga-tools/venv/bin/python
BGA_SFTP       := misc/bga_sftp.py
BGA_REMOTE_DIR := /krestiki

# Config files: keep the extension the scaffold shipped (.jsonc here)
DEPLOY_ROOT   := dbmodel.sql $(wildcard gameinfos.jsonc gameinfos.inc.php stats.json stats.jsonc gameoptions.json gameoptions.jsonc gamepreferences.json gamepreferences.jsonc)
DEPLOY_PHP    := modules/php/Game.php $(wildcard modules/php/material.inc.php)
DEPLOY_STATES := $(wildcard modules/php/States/*.php)
DEPLOY_JS     := $(wildcard modules/js/*.js)

check:
	@for f in $(DEPLOY_PHP) $(DEPLOY_STATES); do php -l "$$f" || exit 1; done
	@echo "✓ PHP OK"

deploy: check
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_ROOT) krestiki.css $(BGA_REMOTE_DIR)/
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_PHP) $(BGA_REMOTE_DIR)/modules/php/
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_STATES) $(BGA_REMOTE_DIR)/modules/php/States/
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_JS) $(BGA_REMOTE_DIR)/modules/js/
	$(BGA_PY) $(BGA_SFTP) put img/* $(BGA_REMOTE_DIR)/img/
	@echo "✓ Deployed"
