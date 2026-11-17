/* This file includes original and modified code from various versions of TinyMCE, relicensed under GPL v2+ per LGPL 2.1 §3 where applicable. Source: https://github.com/widgetfactory/ibis; Copyright (c) Tiny Technologies, Inc. All rights reserved. Copyright (c) 1995-2015 Ephox Corp. All rights reserved. Copyright (c) 2009 Moxiecode Systems AB. All rights reserved. Copyright (c) 2009-2025  Ryan Demmer. All rights reserved. For a detailed history of modifications, refer to the Git commit history. Licensed under the GNU General Public License version 2 or later (GPL v2+): https://www.gnu.org/licenses/gpl-2.0.html */
!function() {
    var DOM = ibis.DOM, Event = ibis.dom.Event, extend = ibis.extend, each = ibis.each, Storage = ibis.util.Storage, Delay = ibis.util.Delay, explode = ibis.explode;
    ibis.create("ibis.themes.CoreTheme", {
        controls: {
            bold: [ "bold_desc", "Bold" ],
            italic: [ "italic_desc", "Italic" ],
            underline: [ "underline_desc", "Underline" ],
            strikethrough: [ "striketrough_desc", "Strikethrough" ],
            justifyleft: [ "justifyleft_desc", "JustifyLeft" ],
            justifycenter: [ "justifycenter_desc", "JustifyCenter" ],
            justifyright: [ "justifyright_desc", "JustifyRight" ],
            justifyfull: [ "justifyfull_desc", "JustifyFull" ],
            outdent: [ "outdent_desc", "Outdent" ],
            indent: [ "indent_desc", "Indent" ],
            undo: [ "undo_desc", "Undo" ],
            redo: [ "redo_desc", "Redo" ],
            unlink: [ "unlink_desc", "unlink" ],
            cleanup: [ "cleanup_desc", "mceCleanup" ],
            code: [ "code_desc", "mceCodeEditor" ],
            removeformat: [ "removeformat_desc", "RemoveFormat" ],
            sub: [ "sub_desc", "subscript" ],
            sup: [ "sup_desc", "superscript" ],
            forecolor: [ "forecolor_desc", "ForeColor" ],
            forecolorpicker: [ "forecolor_desc", "mceForeColor" ],
            backcolor: [ "backcolor_desc", "HiliteColor" ],
            backcolorpicker: [ "backcolor_desc", "mceBackColor" ],
            visualaid: [ "visualaid_desc", "mceToggleVisualAid" ],
            newdocument: [ "newdocument_desc", "mceNewDocument" ],
            blockquote: [ "blockquote_desc", "mceBlockQuote" ]
        },
        stateControls: [ "bold", "italic", "underline", "strikethrough", "justifyleft", "justifycenter", "justifyright", "justifyfull", "sub", "sup", "blockquote" ],
        init: function(ed, url) {
            var self = this, settings = (self.editor = ed, self.url = url, self.onResolveName = new ibis.util.Dispatcher(this), 
            self.onResize = new ibis.util.Dispatcher(this), ed.settings), pathLocation = (self.settings = settings = extend({
                theme_path: !0,
                theme_toolbar_location: "top",
                theme_blockformats: "p,address,pre,h1,h2,h3,h4,h5,h6",
                theme_toolbar_align: "left",
                theme_statusbar_location: "bottom",
                theme_fonts: "Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;Wingdings=wingdings,zapf dingbats",
                theme_more_colors: 1,
                theme_row_height: 23,
                theme_resize_horizontal: 1,
                theme_font_sizes: "1,2,3,4,5,6,7",
                theme_font_selector: "span",
                theme_show_current_color: 0,
                readonly: ed.settings.readonly
            }, settings), settings.theme_path_location);
            pathLocation && "none" != pathLocation && (settings.theme_statusbar_location = settings.theme_path_location), 
            "none" == settings.theme_statusbar_location && (settings.theme_statusbar_location = 0), 
            ed.onInit.add(function() {
                ed.settings.readonly || (ed.onNodeChange.add(self.nodeChanged, self), 
                ed.onKeyUp.add(self.updateUndoStatus, self), ed.onMouseUp.add(self.updateUndoStatus, self), 
                ed.dom.bind(ed.dom.getRoot(), "dragend", function() {
                    self.updateUndoStatus(ed);
                }), ed.addShortcut("alt+F10,F10", "", function() {
                    self.toolbarGroup.focus();
                }), ed.addShortcut("alt+F11", "", function() {
                    DOM.get(ed.id + "_path_row").focus();
                }));
            }), ed.onPostRender.add(function() {
                var el = ed.getElement(), containerEl = (DOM.setStyle(ed.id + "_tbl", "width", ""), 
                DOM.get(ed.id + "_parent")), iframeEl = DOM.get(ed.id + "_ifr"), width = settings.width || el.style.width;
                (el = settings.height || el.style.height) && DOM.setStyle(iframeEl, "height", el), 
                width && (DOM.setStyle(containerEl.parentNode, "max-width", width), 
                /%/.test(width) || DOM.setStyle(iframeEl, "max-width", width));
            }), ed.onSetProgressState.add(function(ed, active, delay) {
                var tableEl, id = ed.id;
                active ? self.progressTimer = setTimeout(function() {
                    var container = ed.getContainer(), wrapper = DOM.create("DIV", {
                        id: id + "_progress_wrap",
                        style: "position:relative"
                    });
                    container.insertBefore(wrapper, container.firstChild), tableEl = DOM.get(id + "_tbl"), 
                    DOM.add(wrapper, "div", {
                        id: id + "_blocker",
                        class: "mceBlocker",
                        style: {
                            width: tableEl.clientWidth + 2,
                            height: tableEl.clientHeight + 2
                        }
                    }), DOM.add(wrapper, "div", {
                        id: id + "_progress",
                        class: "mceProgress",
                        style: {
                            left: tableEl.clientWidth / 2,
                            top: tableEl.clientHeight / 2
                        }
                    });
                }, delay || 0) : (DOM.remove(id + "_progress_wrap"), clearTimeout(self.progressTimer));
            }), !1 !== ed.settings.content_css && ed.contentCSS.push(ed.baseURI.toAbsolute(url + "/skins/" + ed.settings.skin + "/content.css"));
        },
        createControl: function(name, controlManager) {
            var control;
            return controlManager.createControl(name) || ((control = this.controls[name]) ? controlManager.createButton(name, {
                title: "advanced." + control[0],
                cmd: control[1],
                ui: control[2],
                value: control[3]
            }) : void 0);
        },
        execCommand: function(cmd, ui, val) {
            return !!(cmd = this[cmd]) && (cmd.call(this, ui, val), !0);
        },
        renderUI: function(renderArgs) {
            var ed = this.editor, settings = this.settings, skinClass = (ed.settings && (ed.settings.aria_label = settings.aria_label + ed.getLang("advanced.help_shortcut")), 
            "mobile" === ed.settings.skin && (ed.settings.skin = "default", settings.skin_variant = "touch"), 
            "mce" + this.ufirst(ed.settings.skin) + "Skin"), skinClass = (settings.skin_variant && (skinClass += " " + skinClass + this.ufirst(settings.skin_variant)), 
            "default" !== ed.settings.skin && (skinClass = "mceDefaultSkin " + skinClass), 
            "rtl" == ed.settings.skin_directionality && (skinClass += " mceRtl"), 
            ed.settings.skin_class = skinClass, DOM.create("div", {
                role: "application",
                "aria-label": settings.aria_label,
                id: ed.id + "_parent",
                class: "mceEditor " + skinClass
            })), sizeContainer = DOM.add(skinClass, "div", {
                role: "presentation",
                id: ed.id + "_tbl",
                class: "mceLayout"
            }), iframeContainer = this.createLayout(settings, sizeContainer, renderArgs, skinClass), targetNode = renderArgs.targetNode;
            return DOM.insertAfter(skinClass, targetNode), Event.add(ed.id + "_path_row", "click", function(evt) {
                if ((evt = DOM.getParent(evt.target, "a")) && "A" == evt.nodeName) return ed.execCommand("mceSelectNodeDepth", !1, evt.getAttribute("data-index") || 0), 
                !1;
            }), "external" == settings.theme_toolbar_location && (renderArgs.deltaHeight = 0), 
            this.deltaHeight = renderArgs.deltaHeight, renderArgs.targetNode = null, 
            {
                iframeContainer: iframeContainer,
                editorContainer: ed.id + "_parent",
                sizeContainer: sizeContainer,
                deltaHeight: renderArgs.deltaHeight
            };
        },
        resizeBy: function(dw, dh) {
            var iframeEl = DOM.get(this.editor.id + "_ifr");
            this.resizeTo(iframeEl.clientWidth + dw, iframeEl.clientHeight + dh);
        },
        resizeTo: function(width, height, store) {
            var ed = this.editor, settings = this.settings, editorEl = DOM.get(ed.id + "_parent"), iframeEl = DOM.get(ed.id + "_ifr");
            width = Math.max(settings.theme_resizing_min_width || 100, width), height = Math.max(settings.theme_resizing_min_height || 100, height), 
            width = Math.min(settings.theme_resizing_max_width || 65535, width), 
            height = Math.min(settings.theme_resizing_max_height || 65535, height), 
            DOM.setStyle(iframeEl, "height", height), settings.theme_resize_horizontal && (DOM.setStyle(editorEl.parentNode, "max-width", width + "px"), 
            DOM.setStyle(iframeEl, "max-width", width + "px")), store && !1 !== settings.use_state_cookies && Storage.setHash("wf_editor_size_" + ed.id, {
                cw: width,
                ch: height
            }), this.onResize.dispatch();
        },
        destroy: function() {
            var id = this.editor.id;
            Event.clear(id + "_resize"), Event.clear(id + "_path_row"), Event.clear(id + "_external_close");
        },
        createLayout: function(settings, container, layout, editorEl) {
            var toolbarLocation = settings.theme_toolbar_location, statusbarLocation = settings.theme_statusbar_location;
            return settings.readonly ? DOM.add(container, "div", {
                class: "mceIframeContainer"
            }) : ("top" == toolbarLocation && this.addToolbars(container, layout), 
            "top" == statusbarLocation && this.addStatusBar(container, layout), 
            settings = DOM.add(container, "div", {
                class: "mceIframeContainer"
            }), "bottom" == toolbarLocation && this.addToolbars(container, layout), 
            "bottom" == statusbarLocation && this.addStatusBar(container, layout), 
            settings);
        },
        addControls: function(buttons, toolbar) {
            var disabled, self = this, settings = self.settings, controlManager = self.editor.controlManager;
            settings.theme_disable && !self._disabled ? (disabled = {}, each(explode(settings.theme_disable), function(name) {
                disabled[name] = 1;
            }), self._disabled = disabled) : disabled = self._disabled, each(explode(buttons), function(name) {
                disabled && disabled[name] || (name = self.createControl(name, controlManager)) && toolbar.add(name);
            });
        },
        addToolbars: function(container, layout) {
            var ed = this.editor, settings = this.settings, controlManager = ed.controlManager, toolbarsExist = !1, toolbarGroup = controlManager.createToolbarGroup("toolbargroup", {
                name: ed.getLang("advanced.toolbar"),
                tab_focus_toolbar: ed.getParam("theme_tab_focus_toolbar"),
                class: "ToolbarGroup"
            });
            this.toolbarGroup = toolbarGroup;
            for (var alignClass = controlManager.classPrefix + this.ufirst(settings.theme_toolbar_align.toLowerCase()), container = DOM.add(container, "div", {
                class: controlManager.classPrefix + "Toolbar " + alignClass,
                role: "toolbar",
                id: ed.id + "_toolbar"
            }), i = 1; ;i++) {
                var buttons = settings["theme_buttons" + i];
                if (!buttons) break;
                var toolbarsExist = !0, toolbar = controlManager.createToolbar("toolbar" + i, {
                    class: "mceToolbarRow" + i,
                    "aria-label": "Toolbar Row " + i
                });
                settings["theme_buttons" + i + "_add"] && (buttons += "," + settings["theme_buttons" + i + "_add"]), 
                settings["theme_buttons" + i + "_add_before"] && (buttons = settings["theme_buttons" + i + "_add_before"] + "," + buttons), 
                this.addControls(buttons, toolbar), toolbarGroup.add(toolbar), layout.deltaHeight -= settings.theme_row_height;
            }
            toolbarsExist || (layout.deltaHeight -= settings.theme_row_height), 
            DOM.setHTML(container, toolbarGroup.renderHTML());
        },
        addStatusBar: function(container, layout) {
            var pathRowEl, self = this, ed = self.editor, settings = self.settings, container = DOM.add(container, "div", {
                class: "mceStatusbar"
            });
            settings.theme_path && (pathRowEl = DOM.add(container, "div", {
                id: ed.id + "_path_row",
                role: "group",
                "aria-labelledby": ed.id + "_path_voice",
                class: "mcePathRow"
            }), DOM.add(pathRowEl, "span", {
                id: ed.id + "_path_voice",
                class: "mcePathLabel"
            }, ed.translate("advanced.path") + ": ")), settings.theme_resizing && (DOM.add(container, "div", {
                id: ed.id + "_resize",
                class: "mceResize",
                tabIndex: "-1"
            }, '<span class="mceIcon mce_resize"></span>'), !1 !== settings.use_state_cookies && ed.onPostRender.add(function() {
                var savedSize = Storage.getHash("wf_editor_size_" + ed.id);
                savedSize && self.resizeTo(savedSize.cw, savedSize.ch, !1);
            }), ed.onPostRender.add(function() {
                var editorContainer, editorParent;
                Event.add(ed.id + "_resize", "click", function(evt) {
                    evt.preventDefault();
                }), Event.add(ed.id + "_resize", "mousedown", function(evt) {
                    var mouseMoveHandler1, mouseMoveHandler2, mouseUpHandler1, mouseUpHandler2, startX, startY, startWidth, startHeight, width, height;
                    function resizeOnMove(evt) {
                        evt.preventDefault(), width = startWidth + (evt.screenX - startX), 
                        height = startHeight + (evt.screenY - startY), self.resizeTo(width, height);
                    }
                    function endResize(evt) {
                        Event.remove(DOM.doc, "mousemove", mouseMoveHandler1), Event.remove(ed.getDoc(), "mousemove", mouseMoveHandler2), 
                        Event.remove(DOM.doc, "mouseup", mouseUpHandler1), Event.remove(ed.getDoc(), "mouseup", mouseUpHandler2), 
                        width = startWidth + (evt.screenX - startX), height = startHeight + (evt.screenY - startY), 
                        self.resizeTo(width, height, !0), ed.nodeChanged();
                    }
                    evt.preventDefault(), startX = evt.screenX, startY = evt.screenY, 
                    evt = DOM.get(self.editor.id + "_ifr"), startWidth = width = evt.clientWidth, 
                    startHeight = height = evt.clientHeight, mouseMoveHandler1 = Event.add(DOM.doc, "mousemove", resizeOnMove), 
                    mouseMoveHandler2 = Event.add(ed.getDoc(), "mousemove", resizeOnMove), 
                    mouseUpHandler1 = Event.add(DOM.doc, "mouseup", endResize), 
                    mouseUpHandler2 = Event.add(ed.getDoc(), "mouseup", endResize);
                }), ed.settings.floating_toolbar && (editorContainer = ed.getContainer(), 
                editorParent = editorContainer.parentNode, Event.add(window, "scroll", Delay.debounce(function() {
                    ed.settings.fullscreen_enabled || (window.pageYOffset > editorParent.offsetTop ? DOM.addClass(editorContainer, "mceToolbarFixed") : DOM.removeClass(editorContainer, "mceToolbarFixed"));
                }, 128)));
            })), layout.deltaHeight -= 21;
        },
        updateUndoStatus: function(ed) {
            var controlManager = ed.controlManager, ed = ed.undoManager;
            controlManager.setDisabled("undo", !ed.hasUndo() && !ed.typing), controlManager.setDisabled("redo", !ed.hasRedo());
        },
        nodeChanged: function(ed, controlManager, node, collapsed, nodeChangeArgs) {
            var self = this, settings = self.settings;
            if (ibis.each(self.stateControls, function(controlName) {
                controlManager.setActive(controlName, ed.queryCommandState(self.controls[controlName][1]));
            }), controlManager.setActive("visualaid", ed.hasVisual), self.updateUndoStatus(ed), 
            controlManager.setDisabled("outdent", !ed.queryCommandState("Outdent")), 
            settings.theme_path && settings.theme_statusbar_location) {
                for (var pathEl = DOM.get(ed.id + "_path") || DOM.add(ed.id + "_path_row", "span", {
                    id: ed.id + "_path",
                    class: "mcePathPath"
                }), parents = (self.statusKeyboardNavigation && (self.statusKeyboardNavigation.destroy(), 
                self.statusKeyboardNavigation = null), DOM.setHTML(pathEl, ""), 
                function() {
                    for (var outParents = [], parents = nodeChangeArgs.parents, i = 0, len = parents.length; i < len; i++) {
                        var parent = parents[i];
                        1 != parent.nodeType || function(node) {
                            return node.hasAttribute("data-mce-bogus") || "bookmark" == node.getAttribute("data-mce-type") || "caret" == node.getAttribute("data-mce-type") || "_mce_caret" == node.id;
                        }(parent) || "BR" == parent.nodeName || parent.hasAttribute("data-mce-root") || parent.hasAttribute("data-mce-internal") || outParents.push(parent);
                    }
                    return outParents;
                }()), html = "", i = 0, len = parents.length; i < len; i++) {
                    var name = (parent = parents[i]).nodeName.toLowerCase(), parent = {
                        name: name,
                        node: parent,
                        title: name
                    };
                    self.onResolveName.dispatch(self, parent), parent.name && (html = parent.disabled ? '<span class="mceText">' + parent.name + "</span>" + html : '<a role="button" title="' + parent.title + '" data-index="' + i + '"><span class="mceText">' + parent.name + "</span></a>" + html);
                }
                DOM.setHTML(pathEl, html), 0 < DOM.select("a", pathEl).length && (self.statusKeyboardNavigation = new ibis.ui.KeyboardNavigation({
                    root: ed.id + "_path_row",
                    items: DOM.select("a", pathEl),
                    excludeFromTabOrder: !0,
                    onCancel: function() {
                        ed.focus();
                    }
                }, DOM));
                var statusBar = DOM.get(ed.id + "_path_row").parentNode, offset = 20;
                ibis.each(statusBar.childNodes, function(child) {
                    if (DOM.hasClass(child, "mcePathRow")) return !0;
                    offset += child.offsetWidth;
                }), ibis.each(DOM.select("a", pathEl), function(anchor) {
                    DOM.removeClass(anchor, "mcePathHidden"), pathEl.offsetWidth + offset + DOM.getPrev(pathEl, ".mcePathLabel").offsetWidth > statusBar.offsetWidth && DOM.addClass(anchor, "mcePathHidden");
                });
            }
        },
        mceNewDocument: function() {
            var ed = this.editor;
            ed.windowManager.confirm({
                title: "advanced.newdocument_desc",
                text: "advanced.newdocument"
            }, function(confirmed) {
                confirmed && ed.execCommand("mceSetContent", !1, "");
            });
        },
        ufirst: function(str) {
            return str.substring(0, 1).toUpperCase() + str.substring(1);
        }
    }), ibis.ThemeManager.add("core", ibis.themes.CoreTheme);
}();