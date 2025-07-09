SHELL = /bin/bash

PTARGETS=webgit.php
REPODIR = $(HOME)/gitrepos
XTRGOWN = pi
XTRGPRM = 4610
XTRGSRC = /usr/bin/git
XTRGGRP = www-data
XTARGET = $(REPODIR)/webgit

LBINDIR = /usr/local/bin
UBINDIR = ~/bin
PBINDIR = /data/www
PSTYDIR = $(PBINDIR)/webgit-style
POWNER  = www-data
PGROUP  = www-data
PSTYLES	= dark-theme light-theme webgit-layout

all: php layout suidbin

php: 
	@for n in $(PTARGETS);\
	do \
	sudo diff -q $$n $(PBINDIR)/$$n > /dev/null;\
	if [ "$$?" != "0"	];then \
     echo sudo installing in $(PBINDIR): $$n;\
	   sudo install -o $(POWNER) -g $(PGROUP) -m 500 -t $(PBINDIR) $$n;\
	fi;\
	done;\
	for n in $(PSTYLES);\
	do \
	sudo diff -q $$n.css $(PSTYDIR)/$$n.css > /dev/null;\
	if [ "$$?" != "0"	];then \
     echo sudo installing in $(PSTYDIR): $$n.css;\
	   sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PSTYDIR) $$n.css;\
	fi;\
	done;\

layout: webgit-layout.css
	@sudo diff -q webgit-layout.css $(PSTYDIR)/webgit-layout.css > /dev/null; \
	if [ "$$?" != "0"	];then \
    echo sudo installing in $(PSTYDIR)/webgit-style: style webgit-layout.css;\
		sudo install -o $(POWNER) -g $(PGROUP) -m 400 -t $(PBINDIR)/webgit-style  webgit-layout.css; \
	fi;\

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
	fi; \

OLDinstall: $(TARGETS) $(UTARGETS)
	@for n in $(UTARGETS);\
	do \
	diff -q $$n $(UBINDIR)/$$n > /dev/null;\
	if [ "$$?" != "0"	];then \
	   echo install -m 755 -t $(UBINDIR) $$n;\
	   install -m 755 -t $(UBINDIR) $$n;\
	fi;\
	done
	@for n in $(TARGETS);\
	do \
	diff -q $$n $(LBINDIR)/$$n > /dev/null;\
	if [ "$$?" != "0"	];then \
	   echo sudo install -m 755 -t $(LBINDIR) $$n;\
	   sudo install -m 755 -t $(LBINDIR) $$n;\
	fi;\
	done;\

