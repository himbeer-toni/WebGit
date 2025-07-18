SHELL = /bin/bash
# Command := $(firstword $(MAKECMDGOALS))
gitTarget := $(firstword $(MAKECMDGOALS))
cmdArg1 := $(word 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

# Targets
PHONY = install devinstall php layout suidbin rebrand phpadapt readme fontdata

# Product name - can be changed using make rebrand
PRODUCT = GitPeek

# Main file(s) to install (the PHP frontend)
PTARGETS= $(PRODUCT).php

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
XTARGET = $(REPODIR)/git4$(PRODUCT)

# Web root for PHP and CSS
PBINDIR = /data/www
PSTYDIR = $(PBINDIR)/$(PRODUCT)-style

# Target owner/group for installed files
POWNER  = www-data
PGROUP  = www-data

# Stylesheets to install
PSTYLES	= Dark-theme.css Light-theme.css layout.css
PSTYLES = layout.css $(wildcard *-theme.css)

# Font list
FONTLIST=fontdata.txt

# Main install target: install PHP, layout, and special git binary

install: devinstall suidbin

devinstall: fontdata php layout readme

# Adapt whatever $repoRoot is defined in the PHP
# to reflect this Makefile's $(REPODIR)
# (so much for consistent naming ;-)
#sed -i.bak -E "s#($$repoRoot = ).*;(.*)#\1$(REPODIR);\2#" GitPeek.php
phpadapt:
	@# If someone can tell me how match ^$repoRoot
	@# without getting in deep yoghurt with
	@# dollar quoting, please drop me a note
	@# Here I'm matching ^.{1}repoRoot which includes
	@# any ONE character instead of the dollar.
	@# That works because the code does not
	@# contain another matching line
	@sed -i.bak -E "s#(^.{1}repoRoot = ).*;(.*)#\1'$(REPODIR)';\2#" $(PRODUCT).php

# This creates a README.m4 with the actual $(PRODUCT)
# in the text. 
# Must be called whenever a README.in is edited
readme: README.md

README.md: README.in
	@sed -e 's/PRODUCT/$(PRODUCT)/g' README.in > README.md
	@git commit -m "new version generated - README.in changed" README.md

php: phpadapt
	@for n in $(PTARGETS);\
	do \
	sudo diff -q $$n $(PBINDIR)/$$n > /dev/null;\
	if [ "$$?" != "0" ];then \
     echo installing in $(PBINDIR): $$n;\
	   sudo install -o $(POWNER) -g $(PGROUP) -m 500 -t $(PBINDIR) $$n;\
	fi;\
	done;\

# Install font list
#  create fontdata.txt out of 
#   fontdata.default,fontdata.local and fontspec.txt 
#   (the last derived from fontbunny/Fonts+/Embed CSS)
fontdata: $(FONTLIST) fontdata.local fontdata.default fontspec.txt
	@./newFontdata 
	@sudo diff -q $(FONTLIST) $(PSTYDIR)/$(FONTLIST) > /dev/null; \
	if [ "$$?" != "0" ];then \
    echo installing in $(PSTYDIR)/$(PRODUCT)-style: style $(FONTLIST); \
		sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PBINDIR)/$(PRODUCT)-style  $(FONTLIST); \
	fi; \

# Copy and set permissions for a setuid git binary for safe web use
# Install only the main layout CSS if changed
layout: $(PSTYLES)
	@for n in $(PSTYLES);\
	do \
	sudo diff -q $$n $(PSTYDIR)/$$n > /dev/null;\
	if [ "$$?" != "0" ];then \
     echo installing in $(PSTYDIR): $$n;\
	   sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PSTYDIR) $$n;\
	fi;\
	done;


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
	fi;\

rebrand: readme
	@prvprd=$$(awk '/^PRODUCT = *(.+)/{print $$3}' Makefile); \
	[ -z "$(cmdArg1)" ] && echo "fatal: cannot rebrand without a new name use \"make rebrand <new-name>\"" && exit 7; \
	[ ! -e $$prvprd.php ] && echo "fatal: cannot rebrand absent $$prvprd.php - check variable \$$(PRODUCT) in Makefile" && exit 8; \
	echo "rebranding: $$prvprd -> $(cmdArg1)"; \
	mv $(REPODIR)/git4$(PRODUCT) $(REPODIR)/git4$(cmdArg1) && \
	sudo mv $(PSTYDIR)/ $(PBINDIR)/$(cmdArg1)-style/ && \
	mv $$prvprd.php $(cmdArg1).php && \
	sed -i.bak -e 's/^PRODUCT =.*/PRODUCT = $(cmdArg1)/' Makefile && \
	echo "done. To install the new files in your webserver run \"make\"."; \

# Rebrand also requires ourself to be updated

# Catch all and ignore undefined targets
# to enable "make rebrand <new-name>"
%:
	@if [[ " $(PHONY) " =~ " $(gitTarget) " ]]; then \
		true; \
	else \
		echo "P:$(PHONY):"; \
		echo "t:$(gitTarget):"; \
		echo "make: *** no rule to create „$(gitTarget)“.  End."; \
		exit 2; \
	fi; \

#	echo "make: *** no rule to create \„$(gitTarget)\“.  End.

.PHONY: $(PHONY)
