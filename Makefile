SHELL = /bin/bash
# Command := $(firstword $(MAKECMDGOALS))
gitTarget := $(firstword $(MAKECMDGOALS))
cmdArg1 := $(word 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

# Targets
PHONY = install devinstall php layout suidbin rebrand phpadapt readme fontdata demo $(PFNTPHP) htmldoc

# Product name - can be changed using make rebrand
PRODUCT = GitPeek

# Main file(s) to install (the PHP frontend)
PTARGETS= $(PRODUCT).php

# PHP to include fonts
PFNTPHP = $(PRODUCT)-fontlink.php

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

# Docs in HTML
DOCSRC = README.html
DOCTRG = $(PSTYDIR)/$(DOCSRC)
DOCCSSSRC = markdown.css
DOCCSSTRG = $(PSTYDIR)/$(DOCCSSSRC)

# Target owner/group for installed files
POWNER  = www-data
PGROUP  = www-data

# Stylesheets (and  other stuff) to install
PSTYLES = layout.css $(wildcard *-theme.css) $(wildcard *-logo.svg) exdiff.jpg

# Font list
FONTLIST=fontdata.txt

# Main install target: install PHP, layout, and special git binary

install: devinstall suidbin

devinstall: styledir fontdata readme php layout

styledir:
	@if sudo [ ! -d $(PSTYDIR) ]; \
		then \
		sudo mkdir -p $(PSTYDIR) && \
		sudo chown $(POWNER):$(PGROUP) $(PSTYDIR) && \
		sudo chmod 500 $(PSTYDIR) && \
		echo "$(PSTYDIR) created"; \
	fi; \

rmdemo:
	rm -rf demo/

demo: rmdemo
	mkdir demo
	find . -maxdepth 1 -type f -exec cp {} demo/ \;   
	mv demo/GitPeek.php demo/GitPeekDemo.php
# REPODIR = $(HOME)/gitrepos
	sed -e 's/^PRODUCT =.*/PRODUCT = GitPeekDemo/' -e 's@^REPODIR =.*@REPODIR = $(HOME)/gitrepos/Toni@' Makefile > demo/Makefile
	cd demo;make mkdemostage2

mkdemostage2: README.md styledir fontdata php layout suidbin
	@echo stage 2 done

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
readme: README.md htmldoc

htmldoc: DOCCSSTRG DOCTRG

DOCCSSTRG: $(DOCCSSSRC)
		@./sudiffif $(DOCCSSTRG) $(DOCCSSSRC); \
			if [ $$? != 0 ]; then \
				trgdir=$$(dirname $(DOCCSSTRG)); \
				sudo install -o $(POWNER) -g $(PGROUP) -m 500 -t $$trgdir $(DOCCSSSRC); \
			fi; \
	
DOCTRG: $(DOCSRC)
		@./sudiffif $(DOCTRG) $(DOCSRC); \
				trgdir=$$(dirname $(DOCTRG)); \
				sudo install -o $(POWNER) -g $(PGROUP) -m 500 -t $$trgdir $(DOCSRC); \
	
README.html: README.md
	@pandoc -t html5 -f markdown --metadata title="About $(PRODUCT)" -s README.md -o README-tmp.html && \
		echo '<link rel="stylesheet" href="markdown.css" charset="utf-8">' > README.html && \
		echo "<link rel=\"stylesheet\" href=\"https://fonts.bunny.net/css?family='ABeeZee:400|Abyssinica+SIL:400|M+PLUS+1+Code:400'\">" >> README.html && \
		cat README-tmp.html >> README.html && \
		rm -f README-tmp.html

README.md: README.in.md
	@sed -e 's/PRODUCT/$(PRODUCT)/g' README.in.md > README.md
	@git commit -m "new version generated - README.in.md changed" README.md

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
#   and fontload.txt (loaded, but not selectable)
#   (the last derived from fontbunny/Fonts+/Embed CSS)
#   also update fontlink.php if needed
#			sudo diff -q $(FONTLIST) $(PSTYDIR)/$(FONTLIST) > /dev/null; 
fontdata: $(FONTLIST) fontdata.local fontdata.default fontspec.txt fontload.txt
	@./newFontdata; \
		sts=$$?; \
		if [ $$sts -ge 10 ]; then \
			if [ $$? != 0 ];then \
				echo installing in $(PSTYDIR): $(FONTLIST); \
				sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PSTYDIR) $(FONTLIST); \
			fi; \
		fi
		@./sudiffif $(FONTLIST) $(PSTYDIR)/$(FONTLIST); \
			if [ $$? != 0 ];then \
				echo installing in $(PSTYDIR): $(FONTLIST); \
				sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PSTYDIR) $(FONTLIST); \
			fi; \

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

$(PFNTPHP): fontlink.txt
	@echo installing in $(PSTYDIR): fontlink.php;\
	   echo sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PBINDIR) fontlink.php fontlink.txt $(PRODUCT)-fontlink.php;\
	

rebrand: readme
	@prvprd=$$(awk '/^PRODUCT = *(.+)/{print $$3}' Makefile); \
	[ -z "$(cmdArg1)" ] && echo "fatal: cannot rebrand without a new name use \"make rebrand <new-name>\"" && exit 7; \
	[ ! -e $$prvprd.php ] && echo "fatal: cannot rebrand absent $$prvprd.php - check variable \$$(PRODUCT) in Makefile" && exit 8; \
	echo "rebranding: $$prvprd -> $(cmdArg1)"; \
	cp $(REPODIR)/git4$(PRODUCT) $(REPODIR)/git4$(cmdArg1) && \
	mv $$prvprd.php $(cmdArg1).php && \
	sed -i.bak -e 's/^PRODUCT =.*/PRODUCT = $(cmdArg1)/' Makefile && \
	echo "done. To install the new files in your webserver run \"make\"."; \

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
