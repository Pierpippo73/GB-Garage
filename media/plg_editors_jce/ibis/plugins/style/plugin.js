/* jce - 3.0.6 | 2026-11-18 | https://www.joomlacontenteditor.net | Source: https://github.com/widgetfactory/jce | Copyright (C) 2006 - 2026 Ryan Demmer. All rights reserved | GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html */
!function() {
    "use strict";
    const BORDER_STYLE_RE = /^(none|hidden|dotted|dashed|solid|double|groove|ridge|inset|outset)$/i, BORDER_WIDTH_RE = /^(thin|medium|thick|\d+(\.\d+)?(px|em|rem|%|pt|cm|mm|ex|ch|vw|vh|vmin|vmax))$/i, URL_RE = /url\(["']?([^"')]+)["']?\)/i, SIDES = [ "top", "right", "bottom", "left" ];
    function allEqual(a, b, c, d) {
        return "" !== a && a === b && b === c && c === d;
    }
    function stylesToData(styles, ed) {
        const get = prop => styles[prop] || "", toHex = val => val ? ed.dom.toHex(val) : "";
        var [ bgPosH = "", bgPosV = "" ] = get("background-position").split(/\s+/), fourSide = prop => {
            const sh = get(prop);
            var [ t, r, b, l ] = SIDES.map(s => get(prop + "-" + s) || sh);
            return {
                same: allEqual(t, r, b, l),
                top: t,
                right: r,
                bottom: b,
                left: l
            };
        }, pad = fourSide("padding"), fourSide = fourSide("margin");
        const borderParsed = function(val) {
            var result = {
                width: "",
                style: "",
                color: ""
            };
            if (val) for (const part of val.trim().split(/\s+/)) BORDER_STYLE_RE.test(part) ? result.style = part : BORDER_WIDTH_RE.test(part) ? result.width = part : result.color = part;
            return result;
        }(get("border"));
        var borderSide = prop => {
            const sh = get("border-" + prop) || borderParsed[prop];
            let [ t, r, b, l ] = SIDES.map(s => get(`border-${s}-` + prop) || sh);
            return "color" === prop && ([ t, r, b, l ] = [ t, r, b, l ].map(toHex)), 
            {
                same: allEqual(t, r, b, l),
                top: t,
                right: r,
                bottom: b,
                left: l
            };
        }, bStyle = borderSide("style"), bWidth = borderSide("width"), borderSide = borderSide("color"), [ pt, pr, pb, pl ] = SIDES.map(s => get(s)), clipMatch = get("clip").match(/rect\(([^)]+)\)/);
        let cv = [ "", "", "", "" ];
        if (clipMatch) for (cv = clipMatch[1].replace(/,/g, " ").trim().split(/\s+/).map(v => "auto" === v ? "" : v); cv.length < 4; ) cv.push("");
        clipMatch = get("text-decoration");
        return {
            font_family: get("font-family"),
            font_size: get("font-size"),
            font_style: get("font-style"),
            font_weight: get("font-weight"),
            font_variant: get("font-variant"),
            text_transform: get("text-transform"),
            line_height: get("line-height"),
            color: toHex(get("color")),
            text_dec_underline: /underline/i.test(clipMatch),
            text_dec_overline: /overline/i.test(clipMatch),
            text_dec_linethrough: /line-through/i.test(clipMatch),
            text_dec_blink: /blink/i.test(clipMatch),
            text_dec_none: /\bnone\b/i.test(clipMatch),
            background_color: toHex(get("background-color")),
            background_image: get("background-image").replace(URL_RE, "$1"),
            background_repeat: get("background-repeat"),
            background_attachment: get("background-attachment"),
            background_position_h: bgPosH,
            background_position_v: bgPosV,
            word_spacing: get("word-spacing"),
            letter_spacing: get("letter-spacing"),
            vertical_align: get("vertical-align"),
            text_align: get("text-align"),
            text_indent: get("text-indent"),
            white_space: get("white-space"),
            display: get("display"),
            width: get("width"),
            height: get("height"),
            float: styles.float || get("css-float"),
            clear: get("clear"),
            padding_same: pad.same,
            padding_top: pad.top,
            padding_right: pad.right,
            padding_bottom: pad.bottom,
            padding_left: pad.left,
            margin_same: fourSide.same,
            margin_top: fourSide.top,
            margin_right: fourSide.right,
            margin_bottom: fourSide.bottom,
            margin_left: fourSide.left,
            border_style_same: bStyle.same,
            border_style_top: bStyle.top,
            border_style_right: bStyle.right,
            border_style_bottom: bStyle.bottom,
            border_style_left: bStyle.left,
            border_width_same: bWidth.same,
            border_width_top: bWidth.top,
            border_width_right: bWidth.right,
            border_width_bottom: bWidth.bottom,
            border_width_left: bWidth.left,
            border_color_same: borderSide.same,
            border_color_top: borderSide.top,
            border_color_right: borderSide.right,
            border_color_bottom: borderSide.bottom,
            border_color_left: borderSide.left,
            list_style_type: get("list-style-type"),
            list_style_position: get("list-style-position"),
            list_style_image: get("list-style-image").replace(URL_RE, "$1"),
            position: get("position"),
            visibility: get("visibility"),
            z_index: get("z-index"),
            overflow: get("overflow"),
            placement_same: allEqual(pt, pr, pb, pl),
            placement_top: pt,
            placement_right: pr,
            placement_bottom: pb,
            placement_left: pl,
            clip_same: allEqual(cv[0], cv[1], cv[2], cv[3]),
            clip_top: cv[0],
            clip_right: cv[1],
            clip_bottom: cv[2],
            clip_left: cv[3]
        };
    }
    var DOM$1 = ibis.DOM, Event$1 = ibis.dom.Event, Dispatcher = ibis.util.Dispatcher, each$1 = ibis.each, extend = ibis.extend, DOM = (ibis.create("ibis.ui.MeasurementBox:ibis.ui.Control", {
        MeasurementBox: function(id, s, ed) {
            s = extend({
                class: "",
                units: [ "px", "em", "rem", "%", "pt", "cm", "mm", "in", "ex", "vh", "vw" ],
                default_unit: "px"
            }, s), this._super(id, s, ed), this.onChange = new Dispatcher(this), 
            this.onPostRender = new Dispatcher(this), this.classPrefix = "mceMeasurementBox";
        },
        _parse: function(val) {
            var m;
            return val ? (m = /^(-?[0-9.]+)(.*)$/.exec(val)) ? {
                num: m[1],
                unit: m[2] || this.settings.default_unit
            } : {
                num: val,
                unit: ""
            } : {
                num: "",
                unit: this.settings.default_unit
            };
        },
        value: function(val) {
            var num;
            if (!arguments.length) return num = DOM$1.getValue(this.id) || "", unit = DOM$1.getValue(this.id + "_unit") || "", 
            num ? /^-?[0-9.]/.test(num) ? num + unit : num : "";
            var unit = this._parse(val);
            DOM$1.setValue(this.id, unit.num), DOM$1.setValue(this.id + "_unit", unit.unit);
        },
        renderHTML: function() {
            var s = this.settings, prefix = this.classPrefix, inputAttribs = {
                type: "text",
                id: this.id,
                class: "mceTextBox " + prefix + "Input " + (s.class || ""),
                tabindex: 0
            }, inputAttribs = (s.attributes && (inputAttribs = extend(inputAttribs, s.attributes)), 
            DOM$1.createHTML("input", inputAttribs)), sel = '<select id="' + this.id + '_unit" class="' + prefix + 'Unit" tabindex="0">';
            return sel += '<option value=""></option>', each$1(s.units, function(unit) {
                sel += '<option value="' + unit + '">' + unit + "</option>";
            }), sel += "</select>", DOM$1.createHTML("span", {
                class: prefix
            }, inputAttribs + sel);
        },
        postRender: function() {
            var self = this, s = this.settings;
            void 0 !== s.value && this.value(s.value), s.onchange && "function" == typeof s.onchange && this.onChange.add(s.onchange), 
            Event$1.add(this.id, "change", function() {
                self.onChange.dispatch(self, DOM$1.get(self.id));
            }), Event$1.add(this.id + "_unit", "change", function() {
                self.onChange.dispatch(self, DOM$1.get(self.id));
            }), this.onPostRender.dispatch(this, DOM$1.get(this.id));
        },
        setDisabled: function(state) {
            this._super(state);
            var el = DOM$1.get(this.id), sel = DOM$1.get(this.id + "_unit");
            el && (el.disabled = state), sel && (sel.disabled = state);
        },
        destroy: function() {
            this._super(), Event$1.clear(this.id), Event$1.clear(this.id + "_unit");
        }
    }), ibis.ControlManager.prototype.createMeasurementBox = function(id, s) {
        var c, ed = this.editor;
        return id = this.prefix + id, (c = this.get(id)) || (s.label = ed.translate(s.label), 
        s.scope = s.scope || ed, s = extend({
            class: "mce_" + id,
            scope: s.scope,
            control_manager: this
        }, s), c = new ibis.ui.MeasurementBox(id, s, ed), this.add(c));
    }, ibis.DOM), Event = ibis.dom.Event, each = ibis.each, UNITS = [ "px", "em", "rem", "%", "pt", "cm", "mm", "in", "ex", "vh", "vw" ], BORDER_STYLES = [ "none", "solid", "dashed", "dotted", "double", "groove", "ridge", "inset", "outset" ], BORDER_WIDTHS = [ "thin", "medium", "thick", "1px", "2px", "3px", "4px", "5px" ], FONT_SIZES = [ "9px", "10px", "12px", "14px", "16px", "18px", "24px", "xx-small", "x-small", "small", "medium", "large", "x-large", "xx-large", "smaller", "larger" ];
    function showStyleDialog(ed) {
        var cm = ed.controlManager;
        function listBox(id, name, label, values, combobox) {
            var ctrl = cm.createListBox(id, {
                label: ed.getLang("style." + label, label),
                name: name,
                combobox: !!combobox,
                onselect: function() {}
            });
            return ctrl.add("", ""), each(values, function(v) {
                var title = "object" == typeof v ? v.title : v, v = "object" == typeof v ? v.value : v;
                ctrl.add(title, v);
            }), ctrl;
        }
        function measureBox(id, name, label, defaultUnit) {
            return cm.createMeasurementBox(id, {
                label: ed.getLang("style." + label, label),
                name: name,
                units: UNITS,
                default_unit: defaultUnit || "px"
            });
        }
        function fourSides(prefix, makeCtrl) {
            var topCtrl = makeCtrl("style_" + prefix + "_top", prefix + "_top", "Top"), rightCtrl = makeCtrl("style_" + prefix + "_right", prefix + "_right", "Right"), bottomCtrl = makeCtrl("style_" + prefix + "_bottom", prefix + "_bottom", "Bottom"), leftCtrl = makeCtrl("style_" + prefix + "_left", prefix + "_left", "Left");
            return {
                same: cm.createCheckBox("style_" + prefix + "_same", {
                    label: ed.getLang("style.same", "Same for all"),
                    name: prefix + "_same",
                    onchange: function() {
                        var same = this.value();
                        rightCtrl.setDisabled(same), bottomCtrl.setDisabled(same), 
                        leftCtrl.setDisabled(same);
                    }
                }),
                top: topCtrl,
                right: rightCtrl,
                bottom: bottomCtrl,
                left: leftCtrl
            };
        }
        function addFourToForm(form, group) {
            form.add(group.same), form.add(group.top), form.add(group.right), form.add(group.bottom), 
            form.add(group.left);
        }
        var values, textForm = cm.createForm("style_text_form"), textDecUnderline = (textForm.add(listBox("style_font_family", "font_family", "Font Family", (fonts = ed.getParam("fontselect_fonts") || "Arial=Arial, Helvetica, sans-serif;Times New Roman=Times New Roman, Times, serif;Courier New=Courier New, Courier, mono;Georgia=Georgia, Times New Roman, Times, serif;Verdana=Verdana, Arial, Helvetica, sans-serif;Geneva=Geneva, Arial, Helvetica, sans-serif", 
        values = [], "string" == typeof fonts && (fonts = fonts.split(";")), each(fonts, function(font, title) {
            title = "string" == typeof title ? [ title, font ] : font.split("=");
            title[0] && values.push({
                title: title[0].trim(),
                value: (title[1] || title[0]).trim()
            });
        }), values), !0)), textForm.add(listBox("style_font_size", "font_size", "Font Size", FONT_SIZES, !0)), 
        textForm.add(listBox("style_font_weight", "font_weight", "Font Weight", [ "normal", "bold", "bolder", "lighter", "100", "200", "300", "400", "500", "600", "700", "800", "900" ])), 
        textForm.add(listBox("style_font_style", "font_style", "Font Style", [ "normal", "italic", "oblique" ])), 
        textForm.add(listBox("style_font_variant", "font_variant", "Font Variant", [ "normal", "small-caps" ])), 
        textForm.add(listBox("style_text_transform", "text_transform", "Text Case", [ "none", "capitalize", "uppercase", "lowercase" ])), 
        textForm.add(measureBox("style_line_height", "line_height", "Line Height", "em")), 
        cm.createCheckBox("style_text_dec_underline", {
            label: ed.getLang("style.underline", "Underline"),
            name: "text_dec_underline"
        })), textDecOverline = cm.createCheckBox("style_text_dec_overline", {
            label: ed.getLang("style.overline", "Overline"),
            name: "text_dec_overline"
        }), textDecLinethrough = cm.createCheckBox("style_text_dec_linethrough", {
            label: ed.getLang("style.linethrough", "Line Through"),
            name: "text_dec_linethrough"
        }), textDecBlink = cm.createCheckBox("style_text_dec_blink", {
            label: ed.getLang("style.blink", "Blink"),
            name: "text_dec_blink"
        }), fonts = cm.createCheckBox("style_text_dec_none", {
            label: ed.getLang("style.none", "None"),
            name: "text_dec_none"
        }), textDecorationForm = (textForm.add(cm.createTextBox("style_color", {
            label: ed.getLang("style.color", "Color"),
            name: "color",
            subtype: "color",
            colorpicker: function() {
                var self = this, value = self.value(), btn = DOM.get(this.id + "_color");
                ed.settings.color_picker_callback(function(color) {
                    self.value(color), btn.style.backgroundColor = color;
                }, value);
            },
            onchange: function() {
                var isNone = this.value();
                textDecUnderline.setDisabled(isNone), textDecOverline.setDisabled(isNone), 
                textDecLinethrough.setDisabled(isNone), textDecBlink.setDisabled(isNone);
            }
        })), cm.createForm("style_text_decoration_form", {
            class: "mceFlexWidth25"
        })), fonts = (each([ textDecUnderline, textDecOverline, textDecLinethrough, textDecBlink, fonts ], function(c) {
            textDecorationForm.add(c);
        }), cm.createLayout("style_text_decoration_layout", {
            label: ed.getLang("style.decoration", "Decoration")
        })), fonts = (fonts.add(textDecorationForm), textForm.add(fonts), cm.createForm("style_bg_form")), bgImageCtrl = (fonts.add(cm.createTextBox("style_bg_color", {
            label: ed.getLang("style.background_color", "Background Color"),
            name: "background_color",
            subtype: "color",
            colorpicker: function() {
                var self = this, value = self.value(), btn = DOM.get(this.id + "_color");
                ed.settings.color_picker_callback(function(color) {
                    self.value(color), btn.style.backgroundColor = color;
                }, value);
            }
        })), cm.createUrlBox("style_bg_image", {
            label: ed.getLang("style.background_image", "Background Image"),
            name: "background_image",
            clear: !0,
            picker: !!ed.getParam("style_file_browser", !0),
            picker_label: "browse",
            picker_icon: "image",
            onpick: function() {
                ed.execCommand("mceFileBrowser", !0, {
                    caller: "style",
                    callback: function(selected, data) {
                        data && data.length && bgImageCtrl.value(data[0].url);
                    },
                    filter: "images",
                    value: bgImageCtrl.value()
                });
            }
        })), blockForm = (fonts.add(bgImageCtrl), fonts.add(listBox("style_bg_repeat", "background_repeat", "Repeat", [ "no-repeat", "repeat", "repeat-x", "repeat-y" ])), 
        fonts.add(listBox("style_bg_attachment", "background_attachment", "Attachment", [ "fixed", "scroll" ])), 
        fonts.add(listBox("style_bg_pos_h", "background_position_h", "Horizontal Position", [ "left", "center", "right" ], !0)), 
        fonts.add(listBox("style_bg_pos_v", "background_position_v", "Vertical Position", [ "top", "center", "bottom" ], !0)), 
        cm.createForm("style_block_form")), boxStyleForm = (blockForm.add(measureBox("style_word_spacing", "word_spacing", "Word Spacing", "em")), 
        blockForm.add(measureBox("style_letter_spacing", "letter_spacing", "Letter Spacing", "em")), 
        blockForm.add(listBox("style_vertical_align", "vertical_align", "Vertical Alignment", [ "baseline", "sub", "super", "top", "text-top", "middle", "bottom", "text-bottom" ])), 
        blockForm.add(listBox("style_text_align", "text_align", "Text Align", [ "left", "right", "center", "justify" ])), 
        blockForm.add(measureBox("style_text_indent", "text_indent", "Text Indent")), 
        blockForm.add(listBox("style_white_space", "white_space", "Whitespace", [ "normal", "pre", "pre-wrap", "pre-line", "nowrap" ])), 
        blockForm.add(listBox("style_display", "display", "Display", [ "inline", "block", "inline-block", "list-item", "none", "table", "inline-table", "table-cell", "flex", "grid" ])), 
        cm.createForm("style_box_form")), boxPaddingForm = (boxStyleForm.add(measureBox("style_width", "width", "Width")), 
        boxStyleForm.add(measureBox("style_height", "height", "Height")), boxStyleForm.add(listBox("style_float", "float", "Float", [ "none", "left", "right" ])), 
        boxStyleForm.add(listBox("style_clear", "clear", "Clear", [ "none", "left", "right", "both" ])), 
        cm.createForm("style_box_padding_form", {
            legend: ed.getLang("style.box_padding", "Box Padding")
        })), padding = fourSides("padding", function(id, name, label) {
            return measureBox(id, name, label);
        }), boxMarginForm = (addFourToForm(boxPaddingForm, padding), cm.createForm("style_box_margin_form", {
            legend: ed.getLang("style.box_margin", "Box Margin")
        })), margin = fourSides("margin", function(id, name, label) {
            return measureBox(id, name, label);
        }), boxSpacingLayout = (addFourToForm(boxMarginForm, margin), cm.createLayout("style_box_spacing_layout", {
            class: "mceGridLayout"
        })), boxPaddingForm = (boxSpacingLayout.add(boxPaddingForm), boxSpacingLayout.add(boxMarginForm), 
        cm.createForm("border_style_form", {
            legend: ed.getLang("style.border_style", "Border Style")
        })), borderStyle = fourSides("border_style", function(id, name, label) {
            return listBox(id, name, label, BORDER_STYLES);
        }), boxMarginForm = (addFourToForm(boxPaddingForm, borderStyle), cm.createForm("border_width_form", {
            legend: ed.getLang("style.border_width", "Border Width")
        })), borderWidth = fourSides("border_width", function(id, name, label) {
            return listBox(id, name, label, BORDER_WIDTHS, !0);
        }), borderColorForm = (addFourToForm(boxMarginForm, borderWidth), cm.createForm("border_color_form", {
            legend: ed.getLang("style.border_color", "Border Color")
        })), borderColor = fourSides("border_color", function(id, name, label) {
            return cm.createTextBox(id, {
                label: ed.getLang("style." + label, label),
                name: name,
                subtype: "color",
                colorpicker: function() {
                    var self = this, value = self.value(), btn = DOM.get(this.id + "_color");
                    ed.settings.color_picker_callback(function(color) {
                        self.value(color), btn.style.backgroundColor = color;
                    }, value);
                }
            });
        }), listForm = (addFourToForm(borderColorForm, borderColor), cm.createForm("style_list_form")), listImageCtrl = (listForm.add(listBox("style_list_type", "list_style_type", "List Type", [ "disc", "circle", "square", "decimal", "lower-roman", "upper-roman", "lower-alpha", "upper-alpha", "none" ])), 
        listForm.add(listBox("style_list_position", "list_style_position", "Position", [ "inside", "outside" ])), 
        cm.createUrlBox("style_list_image", {
            label: ed.getLang("style.list_style_image", "Bullet Image"),
            name: "list_style_image",
            clear: !0,
            picker: !!ed.getParam("style_file_browser", !0),
            picker_label: "browse",
            picker_icon: "image",
            onpick: function() {
                ed.execCommand("mceFileBrowser", !0, {
                    caller: "style",
                    callback: function(selected, data) {
                        data && data.length && listImageCtrl.value(data[0].url);
                    },
                    filter: "images",
                    value: listImageCtrl.value()
                });
            }
        })), posForm = (listForm.add(listImageCtrl), cm.createForm("style_pos_form")), placementForm = (posForm.add(listBox("style_position", "position", "Position", [ "static", "relative", "absolute", "fixed" ])), 
        posForm.add(listBox("style_visibility", "visibility", "Visibility", [ "visible", "hidden", "inherit" ])), 
        posForm.add(measureBox("style_pos_width", "width", "Width")), posForm.add(measureBox("style_pos_height", "height", "Height")), 
        posForm.add(cm.createTextBox("style_z_index", {
            label: ed.getLang("style.z_index", "Z-Index"),
            name: "z_index",
            subtype: "number"
        })), posForm.add(listBox("style_overflow", "overflow", "Overflow", [ "visible", "hidden", "scroll", "auto" ])), 
        cm.createForm("style_placement_form", {
            legend: ed.getLang("style.placement", "Placement")
        })), placement = fourSides("placement", function(id, name, label) {
            return measureBox(id, name, label);
        }), clipForm = (addFourToForm(placementForm, placement), cm.createForm("style_clip_form", {
            legend: ed.getLang("style.clip", "Clip")
        })), clip = fourSides("clip", function(id, name, label) {
            return measureBox(id, name, label);
        }), posLayout = (addFourToForm(clipForm, clip), cm.createLayout("style_position_layout", {
            class: "mceGridLayout"
        })), tabs = (posLayout.add(placementForm), posLayout.add(clipForm), cm.createTabs("style_tabs")), applyActionIsInsert = (tabs.add({
            id: "style_tab_text",
            title: ed.getLang("style.tab_text", "Text"),
            items: [ textForm ]
        }), tabs.add({
            id: "style_tab_bg",
            title: ed.getLang("style.tab_background", "Background"),
            items: [ fonts ]
        }), tabs.add({
            id: "style_tab_block",
            title: ed.getLang("style.tab_block", "Block"),
            items: [ blockForm ]
        }), tabs.add({
            id: "style_tab_box",
            title: ed.getLang("style.tab_box", "Box"),
            items: [ boxStyleForm, boxSpacingLayout ]
        }), tabs.add({
            id: "style_tab_border",
            title: ed.getLang("style.tab_border", "Border"),
            class: "mceGridLayout",
            items: [ boxPaddingForm, boxMarginForm, borderColorForm ]
        }), tabs.add({
            id: "style_tab_list",
            title: ed.getLang("style.tab_list", "List"),
            items: [ listForm ]
        }), tabs.add({
            id: "style_tab_pos",
            title: ed.getLang("style.tab_positioning", "Positioning"),
            items: [ posForm, posLayout ]
        }), ed.getParam("style_insert_span", !1)), existingStyles = {};
        function collectStyles() {
            {
                var data = tabs.submit();
                const styles = {}, set = (prop, val) => {
                    val && (styles[prop] = val);
                }, setColor = (prop, val) => {
                    val && "#" !== val && (styles[prop] = val);
                };
                set("font-family", data.font_family), set("font-size", data.font_size), 
                set("font-style", data.font_style), set("font-weight", data.font_weight), 
                set("font-variant", data.font_variant), set("text-transform", data.text_transform), 
                set("line-height", data.line_height), setColor("color", data.color), 
                data.text_dec_none ? styles["text-decoration"] = "none" : (dec = [ data.text_dec_underline && "underline", data.text_dec_overline && "overline", data.text_dec_linethrough && "line-through", data.text_dec_blink && "blink" ].filter(Boolean)).length && (styles["text-decoration"] = dec.join(" ")), 
                setColor("background-color", data.background_color), data.background_image && (styles["background-image"] = `url(${data.background_image})`), 
                set("background-repeat", data.background_repeat), set("background-attachment", data.background_attachment), 
                (data.background_position_h || data.background_position_v) && (styles["background-position"] = `${data.background_position_h || "0%"} ` + (data.background_position_v || "0%")), 
                set("word-spacing", data.word_spacing), set("letter-spacing", data.letter_spacing), 
                set("vertical-align", data.vertical_align), set("text-align", data.text_align), 
                set("text-indent", data.text_indent), set("white-space", data.white_space), 
                set("display", data.display), set("width", data.width), set("height", data.height), 
                set("float", data.float), set("clear", data.clear);
                var dec = (prop, prefix) => {
                    const [ t, r, b, l ] = SIDES.map(s => data[prefix + "_" + s]);
                    (t || r || b || l) && (data[prefix + "_same"] ? set(prop, t) : SIDES.forEach((s, i) => set(prop + "-" + s, [ t, r, b, l ][i])));
                };
                dec("padding", "padding"), dec("margin", "margin"), (dec = (prop, isColor) => {
                    const fn = isColor ? setColor : set, [ t, r, b, l ] = SIDES.map(s => data[`border_${prop}_` + s]);
                    (t || r || b || l) && (data[`border_${prop}_same`] ? fn("border-" + prop, t) : SIDES.forEach((s, i) => fn(`border-${s}-` + prop, [ t, r, b, l ][i])));
                })("style"), dec("width"), dec("color", !0), set("list-style-type", data.list_style_type), 
                set("list-style-position", data.list_style_position), data.list_style_image && (styles["list-style-image"] = `url(${data.list_style_image})`), 
                set("position", data.position), set("visibility", data.visibility), 
                set("z-index", data.z_index), set("overflow", data.overflow);
                const {
                    placement_same,
                    placement_top: pTop,
                    placement_right: pRight,
                    placement_bottom: pBot,
                    placement_left: pLeft
                } = data;
                (pTop || pRight || pBot || pLeft) && (placement_same ? SIDES.forEach(s => set(s, pTop)) : (set("top", pTop), 
                set("right", pRight), set("bottom", pBot), set("left", pLeft)));
                let {
                    clip_top: ct,
                    clip_right: cr,
                    clip_bottom: cb,
                    clip_left: cl
                } = data;
                return (ct || cr || cb || cl) && (data.clip_same && (cr = ct, cb = ct, 
                cl = ct), ct = ct || "auto", cr = cr || "auto", cb = cb || "auto", 
                cl = cl || "auto", "auto" === ct && "auto" === cr && "auto" === cb && "auto" === cl || (styles.clip = `rect(${ct} ${cr} ${cb} ${cl})`)), 
                styles;
            }
        }
        function applyStyles(newStyles) {
            var nodes;
            applyActionIsInsert ? (ed.formatter.register("plugin_style", {
                inline: "span",
                styles: existingStyles
            }), ed.formatter.remove("plugin_style"), ed.formatter.register("plugin_style", {
                inline: "span",
                styles: newStyles
            }), ed.formatter.apply("plugin_style")) : (nodes = 1 < ed.selection.getSelectedBlocks().length ? ed.selection.getSelectedBlocks() : ed.selection.getNode(), 
            ed.dom.setAttrib(nodes, "style", ed.dom.serializeStyle(newStyles))), 
            ed.undoManager.add(), ed.nodeChanged();
        }
        ed.windowManager.open({
            title: ed.getLang("style.desc", "CSS Style Properties"),
            items: [ tabs ],
            size: "mce-modal-landscape-xlarge",
            close: function() {
                tabs.destroy();
            },
            open: function() {
                var blocks = ed.selection.getSelectedBlocks(), blocks = (1 === blocks.length ? existingStyles = ed.dom.parseStyle(ed.selection.getNode().style.cssText) : 1 < blocks.length && (existingStyles = {}, 
                each(blocks, function(block) {
                    block = ed.dom.parseStyle(ed.dom.getAttrib(block, "style"));
                    each(block, function(val, key) {
                        existingStyles[key] || (existingStyles[key] = val);
                    });
                })), stylesToData(existingStyles, ed)), blocks = (tabs.update(blocks), 
                each([ [ padding, blocks.padding_same ], [ margin, blocks.margin_same ], [ borderStyle, blocks.border_style_same ], [ borderWidth, blocks.border_width_same ], [ borderColor, blocks.border_color_same ], [ placement, blocks.placement_same ], [ clip, blocks.clip_same ] ], function(pair) {
                    pair[1] && (pair[0].right.setDisabled(!0), pair[0].bottom.setDisabled(!0), 
                    pair[0].left.setDisabled(!0));
                }), blocks.text_dec_none && (textDecUnderline.setDisabled(!0), textDecOverline.setDisabled(!0), 
                textDecLinethrough.setDisabled(!0), textDecBlink.setDisabled(!0)), 
                this.id + "_insert_span"), modalFooter = DOM.get(this.id + "_footer"), insertSpan = DOM.create("label", {
                    class: "mceStyleInsertSpan",
                    for: blocks
                }, '<input type="checkbox" id="' + blocks + '"' + (applyActionIsInsert ? " checked" : "") + "> " + ed.getLang("style.toggle_insert_span", "Apply as &lt;span&gt;"));
                DOM.insertBefore(insertSpan, modalFooter.firstChild), Event.add(blocks, "change", function() {
                    applyActionIsInsert = !!this.checked;
                });
            },
            buttons: [ {
                title: ed.getLang("common.cancel", "Cancel"),
                id: "cancel"
            }, {
                title: ed.getLang("style.apply", "Apply"),
                id: "apply",
                onclick: function() {
                    applyStyles(collectStyles());
                }
            }, {
                title: ed.getLang("update", "Update"),
                id: "insert",
                classes: "primary",
                onsubmit: function() {
                    applyStyles(collectStyles());
                }
            } ]
        });
    }
    ibis.PluginManager.add("style", function(ed, url) {
        function isRootNode(node) {
            return node === ed.dom.getRoot();
        }
        window.matchMedia("(max-width: 600px)").matches || (ed.addCommand("mceStyleProps", function() {
            showStyleDialog(ed);
        }), ed.addCommand("mceSetElementStyle", function(ui, v) {
            var node = ed.selection.getNode();
            node && (ed.dom.setAttrib(node, "style", v), ed.execCommand("mceRepaint"));
        }), ed.onNodeChange.add(function(ed, cm, n) {
            cm.setDisabled("style", isRootNode(n) || n.hasAttribute("data-mce-bogus"));
        }), ed.addButton("style", {
            title: "style.desc",
            cmd: "mceStyleProps"
        }));
    });
}();