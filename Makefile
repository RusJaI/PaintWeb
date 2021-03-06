# PaintWeb Makefile
#
# Copyright (c) 2009-2014, Mihai Sucan
# All rights reserved.
# 
# Redistribution and use in source and binary forms, with or without modification,
# are permitted provided that the following conditions are met:
# 
# 1. Redistributions of source code must retain the above copyright notice, this
#    list of conditions and the following disclaimer.
# 
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
# 
# 3. Neither the name of the copyright holder nor the names of its contributors
#    may be used to endorse or promote products derived from this software without
#    specific prior written permission.
# 
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
# ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
# ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
# 
# $URL: http://code.google.com/p/paintweb $
# $Date: 2014-01-28 12:31:25 $

include config-default.mk
-include config-local.mk

# This holds the absolute path to the parent of the current working directory.  
# Given /home/robod/src/paintweb this variable will hold /home/robod/src/.
FOLDER_PARENT=$(dir $(CURDIR))

# This holds the name of the current working directory. Given 
# /home/robod/src/paintweb this variable will hold only "paintweb".
FOLDER_SELF=$(subst $(FOLDER_PARENT),,$(CURDIR))

# Package output file
FILE_PAINTWEB=paintweb.js
FILE_PWLIB=$(FOLDER_INCLUDES)/lib.js
FILE_JSONLIB=$(FOLDER_INCLUDES)/json2.js
FILE_CONFIG=config-example.json

# Generate the paths to the tools and extensions
FILES_EXTENSIONS=$(addprefix $(FOLDER_SRC)/$(FOLDER_EXTENSIONS)/, $(addsuffix .js, $(EXTENSIONS)))
FILES_TOOLS=$(addprefix $(FOLDER_SRC)/$(FOLDER_TOOLS)/, $(addsuffix .js, $(TOOLS)))

# Alternatively... include all extensions and tools
#FILES_EXTENSIONS=$(wildcard $(FOLDER_SRC)/$(FOLDER_EXTENSIONS)/*.js)
#FILES_TOOLS=$(wildcard $(FOLDER_SRC)/$(FOLDER_TOOLS)/*.js)

FILES_COLORS=$(wildcard $(FOLDER_SRC)/$(FOLDER_COLORS)/*.json)
FILES_LANG=$(wildcard $(FOLDER_SRC)/$(FOLDER_LANG)/*.json)

FOLDER_INTERFACE=$(FOLDER_INTERFACES)/$(INTERFACE)
INTERFACE_LAYOUT=$(FOLDER_INTERFACE)/layout.xhtml
INTERFACE_SCRIPT=$(FOLDER_INTERFACE)/script.js
INTERFACE_STYLE=$(FOLDER_INTERFACE)/style.css

# Dependencies of the main PaintWeb file
FILE_PAINTWEB_DEPS=$(FOLDER_SRC)/$(FILE_PWLIB) \
							 $(FILES_TOOLS) \
							 $(FILES_EXTENSIONS) \
							 $(FOLDER_SRC)/$(INTERFACE_SCRIPT) \
							 $(FOLDER_SRC)/$(INTERFACE_LAYOUT) \
							 $(FOLDER_SRC)/$(FILE_PAINTWEB)

# Files which only need to be concatenated into the main PaintWeb package
FILE_PAINTWEB_CAT=$(FOLDER_SRC)/$(FILE_PWLIB) \
							 $(FILES_TOOLS) \
							 $(FILES_EXTENSIONS) \
							 $(FOLDER_SRC)/$(INTERFACE_SCRIPT)

JSVAR_fileCache=pwlib.fileCache[

BUILD_VERSION=$(shell sed -rn 's~\s+this.version = ([0-9.]+); //!~\1~p' $(FOLDER_SRC)/$(FILE_PAINTWEB))
BUILD_DATE=$(shell date +'%Y%m%d')

#### Make rules

# The default rule
all: $(FOLDER_BUILD)/$(FILE_PAINTWEB) \
	$(FOLDER_BUILD)/$(FILE_JSONLIB) \
	$(FOLDER_BUILD)/$(INTERFACE_STYLE) \
	$(FOLDER_BUILD)/$(FOLDER_COLORS) \
	$(FOLDER_BUILD)/$(FOLDER_LANG) \
	$(FOLDER_BUILD)/$(FILE_CONFIG) \
	$(FOLDER_TINYMCE_PLUGIN)/editor_plugin.js

# The main PaintWeb script file.
$(FOLDER_BUILD)/$(FILE_PAINTWEB): $(FILE_PAINTWEB_DEPS)
	mkdir -p $(FOLDER_BUILD)
	cat $(FILE_PAINTWEB_CAT) > $(@:.js=.src.js)
	# Add the interface layout.
	echo "$(JSVAR_fileCache)'$(INTERFACE_LAYOUT)'] = " >> $(@:.js=.src.js)
	$(BIN_XHTML) < $(FOLDER_SRC)/$(INTERFACE_LAYOUT) | $(BIN_JSON) >> $(@:.js=.src.js)
	echo ";" >> $(@:.js=.src.js)
	# Add the final script: PaintWeb itself
	sed -e 's~this.build = -1; //!~this.build = $(BUILD_DATE);~1' $(FOLDER_SRC)/$(FILE_PAINTWEB) >> $(@:.js=.src.js)
	$(BIN_JS) $(@:.js=.src.js) > $@

# The color palettes
$(FOLDER_BUILD)/$(FOLDER_COLORS): $(FILES_COLORS)
	mkdir -p $(FOLDER_BUILD)/$(FOLDER_COLORS)
	cp $^ $(FOLDER_BUILD)/$(FOLDER_COLORS)

# The language files
$(FOLDER_BUILD)/$(FOLDER_LANG): $(FILES_LANG)
	mkdir -p $(FOLDER_BUILD)/$(FOLDER_LANG)
	cp $^ $(FOLDER_BUILD)/$(FOLDER_LANG)

# The JSON library file.
$(FOLDER_BUILD)/$(FILE_JSONLIB): $(FOLDER_SRC)/$(FILE_JSONLIB)
	mkdir -p $(FOLDER_BUILD)/$(FOLDER_INCLUDES)
	$(BIN_JS) $^ > $@

# The interface stylesheet.
$(FOLDER_BUILD)/$(INTERFACE_STYLE): $(FOLDER_SRC)/$(INTERFACE_STYLE)
	mkdir -p $(FOLDER_BUILD)/$(FOLDER_INTERFACE)
	$(BIN_CSS) $^ > $^.tmp
	$(BIN_CSS_IMAGES) $^.tmp > $(FOLDER_BUILD)/$(INTERFACE_STYLE)
	rm $^.tmp

# Copy the example configuration file.
$(FOLDER_BUILD)/$(FILE_CONFIG): $(FOLDER_SRC)/$(FILE_CONFIG)
	cp $^ $@

# Compress the TinyMCE plugin.
$(FOLDER_TINYMCE_PLUGIN)/editor_plugin.js: $(FOLDER_TINYMCE_PLUGIN)/editor_plugin_src.js
	$(BIN_JS) $^ > $@

.PHONY : docs release snapshot package tags moodle moodle20 moodle19 config
docs:
	$(BIN_JSDOC) $(FOLDER_SRC) $(FOLDER_DOCS_API)

release: package
	mv /tmp/paintweb.tar.bz2 ./paintweb-$(BUILD_VERSION).tar.bz2

snapshot: package
	mv /tmp/paintweb.tar.bz2 ./paintweb-$(BUILD_VERSION)-$(BUILD_DATE).tar.bz2

# Create the PaintWeb package.
package:
	tar --exclude=".*" --exclude="*~" --exclude="*bak" --exclude="*bz2" --exclude="tags" --exclude="config-local.mk" \
		-C $(FOLDER_PARENT) -cjvf /tmp/paintweb.tar.bz2 $(FOLDER_SELF)

# Generate the tags file for the project.
tags:
	ctags -R $(FOLDER_SRC) --JavaScript-kinds=fcm --fields=afiklmnsSt

# Generate a custom Moodle 1.9 build.
moodle19: EXTENSIONS=colormixer moodle
moodle19: all
	tar --exclude=".*" --exclude="*~" --exclude="*bak" --exclude="*bz2" --exclude="tags" --exclude="config-local.mk" \
		--exclude="paintweb/docs" --exclude="paintweb/demos" --exclude="paintweb/tests" --exclude="paintweb/scripts" \
		--exclude="ext/moodle/*20.php" \
		-C $(FOLDER_PARENT) -cjvf /tmp/paintweb.tar.bz2 $(FOLDER_SELF)
	mv /tmp/paintweb.tar.bz2 ./paintweb-$(BUILD_VERSION)-$(BUILD_DATE)-moodle19.tar.bz2

# Generate a custom Moodle 2.0 build.
moodle20: EXTENSIONS=colormixer moodle
moodle20: all
	tar --exclude=".*" --exclude="*~" --exclude="*bak" --exclude="*bz2" --exclude="tags" --exclude="config-local.mk" \
		--exclude="paintweb/docs" --exclude="paintweb/demos" --exclude="paintweb/tests" --exclude="paintweb/scripts" \
		--exclude="ext/moodle/*19.php" \
		-C $(FOLDER_PARENT) -cjvf /tmp/paintweb.tar.bz2 $(FOLDER_SELF)
	mv /tmp/paintweb.tar.bz2 ./paintweb-$(BUILD_VERSION)-$(BUILD_DATE)-moodle20.tar.bz2

config:
	cp config-default.mk config-local.mk

# vim:set spell spl=en fo=wan1croql tw=80 ts=2 sw=2 sts=0 sta noet ai cin fenc=utf-8 ff=unix:

