/* This file includes original and modified code from various versions of TinyMCE, relicensed under GPL v2+ per LGPL 2.1 §3 where applicable. Source: https://github.com/widgetfactory/ibis; Copyright (c) Tiny Technologies, Inc. All rights reserved. Copyright (c) 1995-2015 Ephox Corp. All rights reserved. Copyright (c) 2009 Moxiecode Systems AB. All rights reserved. Copyright (c) 2009-2025  Ryan Demmer. All rights reserved. For a detailed history of modifications, refer to the Git commit history. Licensed under the GNU General Public License version 2 or later (GPL v2+): https://www.gnu.org/licenses/gpl-2.0.html */
var ibis, ibisPopup = {
    init: function() {
        var self = this, win = this.getWin();
        ibis = win.ibis, this.editor = ibis.EditorManager.activeEditor, this.params = this.editor.windowManager.params, 
        this.features = this.editor.windowManager.features, this.dom = this.editor.windowManager.createInstance("ibis.dom.DOMUtils", document, {
            ownEvents: !0,
            proxy: ibisPopup.eventProxy
        }), this.dom.bind(window, "ready", this.onDOMLoaded, this), this.listeners = [], 
        this.onInit = {
            add: function(fn, scope) {
                self.listeners.push({
                    func: fn,
                    scope: scope
                });
            }
        }, this.isWindow = !1, this.id = this.getWindowArg("mce_window_id"), this.editor.windowManager.onOpen.dispatch(this.editor.windowManager, window);
    },
    getWin: function() {
        return !window.frameElement && window.dialogArguments || opener || parent || top;
    },
    getWindowArg: function(name, defaultValue) {
        return name = this.params[name], ibis.is(name) ? name : defaultValue;
    },
    getParam: function(name, defaultValue) {
        return this.editor.getParam(name, defaultValue);
    },
    getLang: function(name, defaultValue) {
        return this.editor.getLang(name, defaultValue);
    },
    execCommand: function(cmd, ui, val, args) {
        return (args = args || {}).skip_focus = 1, this.editor.execCommand(cmd, ui, val, args);
    },
    pickColor: function(elementId) {
        var elm = document.getElementById(elementId);
        this.execCommand("mceColorPicker", !0, {
            color: elm.value,
            callback: function(color) {
                elm.value = color;
                try {
                    elm.onchange();
                } catch (ex) {}
            }
        });
    },
    openBrowser: function(args) {
        this.editor.execCallback("file_browser_callback", args, window);
    },
    confirm: function(title, callback, scope) {
        this.editor.windowManager.confirm(title, callback, scope, window);
    },
    alert: function(title, callback, scope) {
        this.editor.windowManager.alert(title, callback, scope, window);
    },
    close: function() {
        this.editor && (this.editor.windowManager.close(window), ibis = this.editor = this.params = this.dom = this.dom.doc = null);
    },
    onDOMLoaded: function() {
        var editor = this.editor, title = document.title;
        document.body.style.display = "", this.isWindow ? window.focus() : editor.windowManager.setTitle(window, title), 
        this.isWindow || this.dom.bind(document, "focus", function() {
            editor.windowManager.focus(this.id);
        }), ibis.each(this.listeners, function(listener) {
            listener.func.call(listener.scope, editor);
        }), window.focus();
    },
    eventProxy: function(id) {
        return function(evt) {
            ibisPopup.dom.events.callNativeHandler(id, evt);
        };
    }
};

ibisPopup.init(), window.tinyMCEPopup = ibisPopup;