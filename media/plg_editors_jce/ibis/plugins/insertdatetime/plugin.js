/* jce - 3.0.6 | 2026-11-18 | https://www.joomlacontenteditor.net | Source: https://github.com/widgetfactory/jce | Copyright (C) 2006 - 2026 Ryan Demmer. All rights reserved | GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html */
ibis.PluginManager.add("insertdatetime", function(ed, url) {
    var daysShort = ed.getLang("insertdatetime.day_short", "Sun,Mon,Tue,Wed,Thu,Fri,Sat").split(","), daysLong = ed.getLang("insertdatetime.day_long", "Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday").split(","), monthsShort = ed.getLang("insertdatetime.months_short", "Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec").split(","), monthsLong = ed.getLang("insertdatetime.months_long", "January,February,March,April,May,June,July,August,September,October,November,December").split(","), defaultFormat = null;
    ed.getParam("insertdatetime", {});
    function addZeros(value, len) {
        for (value = "" + value; value.length < len; ) value = "0" + value;
        return value;
    }
    function getDateTime(fmt, date) {
        return date = date || new Date(), fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = (fmt = fmt.replace(/%D/g, "%m/%d/%Y")).replace(/%r/g, "%I:%M:%S %p")).replace(/%Y/g, "" + date.getFullYear())).replace(/%y/g, "" + date.getYear())).replace(/%m/g, addZeros(date.getMonth() + 1, 2))).replace(/%d/g, addZeros(date.getDate(), 2))).replace(/%H/g, "" + addZeros(date.getHours(), 2))).replace(/%M/g, "" + addZeros(date.getMinutes(), 2))).replace(/%S/g, "" + addZeros(date.getSeconds(), 2))).replace(/%I/g, "" + ((date.getHours() + 11) % 12 + 1))).replace(/%p/g, date.getHours() < 12 ? "AM" : "PM")).replace(/%B/g, "" + monthsLong[date.getMonth()])).replace(/%b/g, "" + monthsShort[date.getMonth()])).replace(/%A/g, "" + daysLong[date.getDay()])).replace(/%a/g, "" + daysShort[date.getDay()])).replace(/%%/g, "%");
    }
    function getTimeFormat() {
        return ed.getParam("insertdatetime.timeformat", ed.getParam("plugin_insertdate_timeFormat", "%H:%M:%S"));
    }
    function getFormats() {
        return ed.getParam("insertdatetime.formats", [ "%H:%M:%S", "%Y-%m-%d", "%I:%M:%S %p", "%D" ]);
    }
    function insertDateTime(format) {
        var userTime, computerTime, timeElm;
        ed.getParam("insertdatetime.element", !1) ? (userTime = getDateTime(format), 
        computerTime = /%[HMSIp]/.test(format) ? getDateTime("%Y-%m-%dT%H:%M") : getDateTime("%Y-%m-%d"), 
        (timeElm = ed.dom.getParent(ed.selection.getStart(), "time")) ? function(timeElm, computerTime, userTime) {
            computerTime = ed.dom.create("time", {
                datetime: computerTime
            }, userTime), ed.dom.replace(computerTime, timeElm), ed.selection.select(computerTime, !0), 
            ed.selection.collapse(!1);
        }(timeElm, computerTime, userTime) : ed.execCommand("mceInsertContent", !1, '<time datetime="' + computerTime + '">' + userTime + "</time>")) : ed.execCommand("mceInsertContent", !1, getDateTime(format));
    }
    ed.addCommand("mceInsertDate", function(ui, value) {
        insertDateTime(value || ed.getParam("insertdatetime.dateformat", ed.getParam("plugin_insertdate_dateFormat", "%Y-%m-%d")));
    }), ed.addCommand("mceInsertTime", function(ui, value) {
        insertDateTime(value || getTimeFormat());
    }), ed.addButton("insertdate", {
        title: "insertdatetime.insertdate_desc",
        cmd: "mceInsertDate"
    }), ed.addButton("inserttime", {
        title: "insertdatetime.inserttime_desc",
        cmd: "mceInsertTime"
    }), this.createControl = function(n, cm) {
        return "insertdatetime" !== n ? null : ((n = cm.createSplitButton("insertdatetime", {
            title: "insertdatetime.insertdatetime_desc",
            icon: "insertdatetime",
            onclick: function() {
                var formats;
                insertDateTime((defaultFormat || (formats = getFormats(), defaultFormat = 0 < formats.length ? formats[0] : getTimeFormat()), 
                defaultFormat));
            }
        })).onRenderMenu.add(function(c, m) {
            m.settings.fetchItems = function() {
                var items = [];
                return ibis.each(getFormats(), function(format) {
                    items.push({
                        title: getDateTime(format),
                        onclick: function() {
                            insertDateTime(defaultFormat = format);
                        }
                    });
                }), items;
            };
        }), n);
    };
});