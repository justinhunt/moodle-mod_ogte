
define(['jquery', 'core/log', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js'], function ($, log, codemirror) {
    "use strict"; // jshint ;_;

    /*
    This file contains CodeMirror editor initialization helper.
     */

    log.debug('OGTE CodeMirror helper: initialising');

    return {
        //pass in the textarea element or id
        init: function (textareaId, opts) {
            var el = document.getElementById(textareaId);
            if (!el) {
                log.error("OGTE CodeMirror helper: Initializing on non-existent element " + textareaId);
                return null;
            }

            var defaultOpts = {
                lineNumbers: false,
                lineWrapping: true,
                mode: "text/plain",
                viewportMargin: Infinity
            };

            var mergedOpts = $.extend(defaultOpts, opts || {});

            var editor = codemirror.fromTextArea(el, mergedOpts);
            return editor;
        },

        //Helper to quickly apply marks based on worddata array
        applyWordData: function (editor, worddata) {
            // First clear all existing marks
            var marks = editor.getAllMarks();
            marks.forEach(function (mark) {
                mark.clear();
            });

            if (!worddata || worddata.length === 0) {
                return;
            }

            var doc = editor.getDoc();
            // CodeMirror deals in {line, ch} coordinates. We scan line by line.
            var lineCount = doc.lineCount();
            
            var dataIndex = 0;
            
            for (var lineNo = 0; lineNo < lineCount; lineNo++) {
                var lineText = doc.getLine(lineNo);
                
                // We use a regex to find all word tokens contiguous on this line
                var regex = /\S+/g;
                var match;
                
                while ((match = regex.exec(lineText)) !== null) {
                    if (dataIndex >= worddata.length) break;
                    
                    var wd = worddata[dataIndex];
                    
                    // Skip any newline markers the backend returned
                    while(wd && wd.word === "\n") {
                        dataIndex++;
                        if (dataIndex >= worddata.length) break;
                        wd = worddata[dataIndex];
                    }
                    if (!wd) break;

                    var from = {line: lineNo, ch: match.index};
                    var to = {line: lineNo, ch: match.index + match[0].length};

                    // Apply if there is a css class
                    if (wd.class) {
                        editor.markText(from, to, {
                            className: wd.class,
                            attributes: wd.data || {}
                        });
                    }

                    dataIndex++;
                }
            }
        }
    };//end of return value
});
