SHELL = /bin/bash

# Main file(s) to install (the PHP frontend)
PTARGETS= webgit.php

# Directory containing all git repositories
REPODIR = $(HOME)/gitrepos

# SUID git binary copy settings:
# - Owner (repo owner)
XTRGOWN = pi
# - Permissions (setuid, user-exec, group-exec, group-read)
XTRGPRM = 4610
# - Source git binary
XTRGSRC = /usr/bin/git
# - Group (webserver group)
XTRGGRP = www-data
# - Target location for the copy
XTARGET = $(REPODIR)/webgit

# Web root for PHP and CSS
PBINDIR = /data/www
PSTYDIR = $(PBINDIR)/webgit-style

# Target owner/group for installed files
POWNER  = www-data
PGROUP  = www-data

# Stylesheets to install (without .css)
PSTYLES	= dark-theme light-theme webgit-layout

# Main install target: install PHP, layout, and special git binary
install: php layout suidbin

devinstall: php layout

# Install PHP and CSS files if changed
# php: 
	@for n in $(PTARGETS);\
	do \
	sudo diff -q $$n $(PBINDIR)/$$n > /dev/null;\
	if [ "$$?" != "0" ];then \
      echo sudo installing in $(PBINDIR): $$n;\
	   sudo install -o $(POWNER) -g $(PGROUP) -m 500 -t $(PBINDIR) $$n;\
	fi;\
	done;\
	for n in $(PSTYLES);\
	do \
	sudo diff -q $$n.css $(PSTYDIR)/$$n.css > /dev/null;\
	if [ "$$?" != "0" ];then \
      echo sudo installing in $(PSTYDIR): $$n.css;\
	   sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PSTYDIR) $$n.css;\
	fi;\
	done;

# Install only the main layout CSS if changed
layout: webgit-layout.css
	@sudo diff -q webgit-layout.css $(PSTYDIR)/webgit-layout.css > /dev/null; \
	if [ "$$?" != "0" ];then \
    echo sudo installing in $(PSTYDIR)/webgit-style: style webgit-layout.css;\
		sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PBINDIR)/webgit-style  webgit-layout.css; \
	fi;

# Copy and set permissions for a setuid git binary for safe web use
suidbin: $(XTRGSRC)
	@if [ -e $(XTARGET) ]; then \
		sudo diff -q $(XTARGET) $(XTRGSRC) > /dev/null; \
	else \
		false; \
	fi; \
	if [ $$? != 0 ]; then \
		echo "Creating setuid $(XTRGOWN) binary of $(XTRGSRC) as $(XTARGET)"; \
		sudo cp $(XTRGSRC) $(XTARGET); \
		sudo chown $(XTRGOWN):$(XTRGGRP) $(XTARGET); \
		sudo chmod $(XTRGPRM) $(XTARGET); \
	fi;

.PHONY: install devinstall php layout suidbin
