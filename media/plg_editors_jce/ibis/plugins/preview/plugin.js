/* jce - 3.0.5 | 2026-11-11 | https://www.joomlacontenteditor.net | Source: https://github.com/widgetfactory/jce | Copyright (C) 2006 - 2026 Ryan Demmer. All rights reserved | GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html */
!function() {
    var DOM = ibis.DOM, counter = 0;
    ibis.PluginManager.add("preview", function(ed, url) {
        var self = this;
        function isEditorActive() {
            return 0 == DOM.hasClass(ed.getElement(), "wf-no-editor");
        }
        ed.onInit.add(function(ed) {
            0 != isEditorActive() && "wf-editor-preview" === (ed.settings.active_tab || "") && (ed.hide(), 
            DOM.hide(ed.getElement()), self.toggle());
        }), this.hide = function() {
            DOM.hide(ed.id + "_editor_preview");
        }, this.isHidden = function() {
            return DOM.isHidden(ed.id + "_editor_preview");
        }, this.toggle = function() {
            var s = ed.settings, element = ed.getElement(), container = element.parentNode, element = DOM.getPrev(element, ".wf-editor-header"), ifrHeight = parseInt(DOM.get(ed.id + "_ifr").style.height, 10) || s.height, preview = DOM.get(ed.id + "_editor_preview"), iframe = DOM.get(ed.id + "_editor_preview_iframe"), o = ibis.util.Storage.getHash("ibis_" + ed.id + "_size"), o = (o && o.height && (ifrHeight = o.height), 
            preview || (preview = DOM.add(container, "div", {
                role: "textbox",
                id: ed.id + "_editor_preview",
                class: "wf-editor-preview"
            }), iframe = DOM.add(preview, "iframe", {
                frameborder: 0,
                src: 'javascript:""',
                id: ed.id + "_editor_preview_iframe"
            })), ed.settings.container_height || sessionStorage.getItem("wf-editor-container-height") || ifrHeight), ifrHeight = (DOM.hasClass(container, "mce-fullscreen") && (o = DOM.getViewPort().h - element.offsetHeight), 
            DOM.setStyle(preview, "height", o), DOM.setStyle(preview, "max-width", "100%"), 
            ed.settings.container_width || sessionStorage.getItem("wf-editor-container-width")), content = (ifrHeight && !DOM.hasClass(container, "mce-fullscreen") && DOM.setStyle(preview, "max-width", ifrHeight), 
            DOM.show(preview), ed.getContent());
            function update(text) {
                var doc = iframe.contentWindow.document, css = [], html = "<!DOCTYPE html>", html = (html = (html += '<head xmlns="http://www.w3.org/1999/xhtml">') + ('<base href="' + s.document_base_url + '" />')) + '<meta http-equiv="X-UA-Compatible" content="IE=7" />' + '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />', css = s.compress.css ? [ s.site_url + "index.php?option=com_jce&task=editor.pack&type=css&slot=preview&" + s.query ] : ed.contentCSS;
                ibis.each(css, function(url) {
                    html += '<link href="' + url + '" rel="stylesheet" type="text/css" />';
                }), html = (html += '</head><body style="margin:5px;">') + text + "</body></html>", 
                doc.open(), doc.write(html), doc.close(), DOM.removeClass(container, "mce-loading");
            }
            ibis.util.XHR.send({
                url: s.site_url + "index.php?option=com_jce&task=plugin.display&plugin=preview&" + ibis.query,
                data: JSON.stringify({
                    method: "showPreview",
                    data: encodeURIComponent(content),
                    id: function() {
                        for (var guid = new Date().getTime().toString(32), i = 0; i < 5; i++) guid += Math.floor(65535 * Math.random()).toString(32);
                        return "wf_" + guid + (counter++).toString(32);
                    }()
                }),
                content_type: "application/json",
                success: function(x) {
                    var o = {};
                    try {
                        o = JSON.parse(x);
                    } catch (e) {
                        o.error = /[{}]/.test(o) ? "The server returned an invalid JSON response" : x;
                    }
                    var r = o.result;
                    update(r = x && !o.error ? r : content);
                },
                error: function(e, x) {
                    update(content);
                }
            });
        };
    });
}();