/* jce - 3.0.5 | 2026-11-11 | https://www.joomlacontenteditor.net | Source: https://github.com/widgetfactory/jce | Copyright (C) 2006 - 2026 Ryan Demmer. All rights reserved | GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html */
ibis.PluginManager.add("print", function(ed) {
    ed.addCommand("mcePrint", function() {
        ed.getWin().print();
    }), ed.addButton("print", {
        title: "print.desc",
        cmd: "mcePrint"
    });
});