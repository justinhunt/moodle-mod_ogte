// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The Text Rewriter: analyse a passage, then simplify it to a level.
 *
 * The server does the analysis, the prompts and the checking. This module runs the passes: one
 * rewrite, then up to MAX_REPAIRS repairs while the targets are not met, keeping the best pass.
 *
 * @module     mod_ogte/rewriter
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification', 'core/str', 'mod_ogte/utils'],
function($, Ajax, Templates, Notification, Str, Utils) {

    /** @var {Number} The most repair passes after the first rewrite. Matches rewriter::MAX_REPAIRS. */
    var MAX_REPAIRS = 3;

    /** @var {Number} The longest passage, in characters. Matches rewriter::MAX_INPUT. */
    var MAX_INPUT = 15000;

    /**
     * Split plain text into paragraphs.
     *
     * @param {String} text
     * @returns {String[]}
     */
    var paragraphs = function(text) {
        return text.trim().split(/\n\s*\n/).map(function(p) {
            return p.trim();
        }).filter(function(p) {
            return p !== '';
        });
    };

    /**
     * Copy text to the clipboard.
     *
     * @param {String} text
     * @returns {Promise}
     */
    var copyText = function(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        // Plain http: fall back to a selected textarea.
        var area = $('<textarea>').val(text).css({position: 'fixed', top: 0, left: 0, opacity: 0});
        $('body').append(area);
        area[0].select();
        try {
            document.execCommand('copy');
        } finally {
            area.remove();
        }
        return Promise.resolve();
    };

    /**
     * Set up one rewriter.
     *
     * @param {String} rootid The id of the rewriter's container.
     * @param {Number} ogteid The activity id.
     * @param {Boolean} aiavailable Is an AI provider set up.
     */
    var Rewriter = function(rootid, ogteid, aiavailable) {
        this.root = $('#' + rootid);
        this.ogteid = ogteid;
        this.aiavailable = aiavailable;
        // The passage, list and analysis that the level table was made for.
        this.original = '';
        this.listid = 0;
        this.listname = '';
        this.analysis = null;
        this.level = null;
        this.result = null;
        this.busy = false;
        this.registerEvents();
    };

    Rewriter.prototype.find = function(selector) {
        return this.root.find(selector);
    };

    Rewriter.prototype.registerEvents = function() {
        var self = this;
        this.find('.ogte-rw-analyse').on('click', function() {
            self.analyse();
        });
        this.root.on('click', '.ogte-rw-simplify', function() {
            var level = parseInt($(this).data('level'), 10);
            self.confirmReplace(this).then(function() {
                self.simplify(level, self.analysis.levels[level].label);
                return null;
            }).catch(function() {
                // Cancelled.
            });
        });
        this.root.on('click', '.ogte-rw-tryanother', function() {
            self.showStep(1);
        });
        this.root.on('click', '.ogte-rw-backtoresult', function() {
            self.showStep(2);
        });
        this.root.on('click', '.ogte-rw-rewriteagain', function() {
            self.confirmReplace(this).then(function() {
                self.simplify(self.level, self.result.levellabel);
                return null;
            }).catch(function() {
                // Cancelled.
            });
        });
        this.find('.ogte-rw-copy').on('click', function() {
            copyText(self.result.text).then(function() {
                self.find('.ogte-rw-copied').show().delay(2000).fadeOut();
                return null;
            }).catch(Notification.exception);
        });
        this.find('.ogte-rw-download').on('click', function() {
            self.download();
        });
        // The analysis is for the text as it was: drop it if the text or list changes.
        this.find('.ogte-rw-original, .ogte-rw-list').on('input change', function() {
            if (self.analysis) {
                self.analysis = null;
                self.find('.ogte-rw-analysis').hide().empty();
            }
        });
    };

    /**
     * Show step 1 (the text and the level table) or step 2 (the result).
     *
     * @param {Number} step
     */
    Rewriter.prototype.showStep = function(step) {
        this.find('.ogte-rw-step').removeClass('ogte-rw-step-active');
        this.find('.ogte-rw-step[data-step="' + step + '"]').addClass('ogte-rw-step-active');
        this.find('.ogte-rw-input').toggle(step === 1);
        this.find('.ogte-rw-back').toggle(step === 1 && this.result !== null);
        this.find('.ogte-rw-result, .ogte-rw-actions').toggle(step === 2);
    };

    /**
     * Ask before a new rewrite replaces the one we have.
     *
     * @param {HTMLElement} trigger The button clicked, to return focus to.
     * @returns {Promise} Resolved to go on, rejected if cancelled.
     */
    Rewriter.prototype.confirmReplace = function(trigger) {
        if (!this.result) {
            return Promise.resolve();
        }
        return Notification.saveCancelPromise(
            Str.get_string('rw_replacetitle', 'mod_ogte'),
            Str.get_string('rw_replacequestion', 'mod_ogte'),
            Str.get_string('rw_replaceok', 'mod_ogte'),
            {triggerElement: trigger}
        );
    };

    Rewriter.prototype.setProgress = function(message) {
        if (message) {
            this.find('.ogte-rw-progressmessage').text(message);
            this.find('.ogte-rw-progress').show();
        } else {
            this.find('.ogte-rw-progress').hide();
        }
    };

    Rewriter.prototype.setBusy = function(busy) {
        this.busy = busy;
        this.find('.ogte-rw-analyse, .ogte-rw-simplify, .ogte-rw-rewriteagain, .ogte-rw-tryanother')
            .prop('disabled', busy);
    };

    Rewriter.prototype.analyse = function() {
        var self = this;
        var text = this.find('.ogte-rw-original').val();
        if (!text || text.trim() === '') {
            Str.get_strings([{key: 'textproblem', component: 'mod_ogte'}, {key: 'entersomething', component: 'mod_ogte'}])
                .then(function(strings) {
                    return Notification.alert(strings[0], strings[1]);
                }).catch(Notification.exception);
            return;
        }
        if (text.length > MAX_INPUT) {
            Str.get_string('aitexttoolong', 'mod_ogte', MAX_INPUT).then(function(s) {
                return Notification.alert('', s);
            }).catch(Notification.exception);
            return;
        }
        var listid = parseInt(this.find('.ogte-rw-list').val(), 10);
        this.setBusy(true);
        Promise.resolve(Ajax.call([{
            methodname: 'mod_ogte_analyse_text',
            args: {ogteid: this.ogteid, listid: listid, passage: text}
        }])[0]).then(function(analysis) {
            self.original = text;
            self.listid = listid;
            self.listname = self.find('.ogte-rw-list option:selected').text();
            self.analysis = analysis;
            analysis.aiavailable = self.aiavailable;
            return Templates.renderForPromise('mod_ogte/rewriter_analysis', analysis);
        }).then(function(rendered) {
            var container = self.find('.ogte-rw-analysis');
            Templates.replaceNodeContents(container, rendered.html, rendered.js);
            container.show();
            return null;
        }).catch(Notification.exception).finally(function() {
            self.setBusy(false);
        });
    };

    /**
     * One pass on the server: the first rewrite if current is empty, else a repair.
     *
     * @param {String} current
     * @param {Array} glossary
     * @returns {Promise}
     */
    Rewriter.prototype.pass = function(current, glossary) {
        return Promise.resolve(Ajax.call([{
            methodname: 'mod_ogte_simplify_text',
            args: {
                ogteid: this.ogteid,
                listid: this.listid,
                level: this.level,
                original: this.original,
                current: current,
                glossary: glossary
            }
        }])[0]);
    };

    /**
     * Simplify the analysed passage to a level: a rewrite, then repairs.
     *
     * @param {Number} levelindex
     * @param {String} levellabel
     */
    Rewriter.prototype.simplify = function(levelindex, levellabel) {
        var self = this;
        if (this.busy || !this.original) {
            return;
        }
        this.level = levelindex;
        var best = null;

        // Keep the best pass, and repair until the targets are met or we run out of passes.
        var next = function(result, repairs) {
            if (!best || result.score >= best.score) {
                best = result;
            }
            if (result.met || repairs >= MAX_REPAIRS) {
                return best;
            }
            var message = Str.get_string('rw_checking', 'mod_ogte', {pass: repairs + 1, max: MAX_REPAIRS});
            return Promise.resolve(message).then(function(s) {
                self.setProgress(s);
                return self.pass(result.text, result.glossary);
            }).then(function(repaired) {
                return next(repaired, repairs + 1);
            });
        };

        // While it works, the progress message takes the place of the text and the level table.
        this.setBusy(true);
        this.find('.ogte-rw-input, .ogte-rw-result, .ogte-rw-actions').hide();
        // Str and Ajax give jQuery promises, which have no finally(): wrap them in native ones.
        Promise.resolve(Str.get_string('rw_rewriting', 'mod_ogte', levellabel)).then(function(s) {
            self.setProgress(s);
            return self.pass('', []);
        }).then(function(result) {
            return next(result, 0);
        }).then(function(result) {
            return self.showResult(result);
        }).catch(function(error) {
            // Go back to where we were, so another level or another try can be chosen.
            self.showStep(self.result ? 2 : 1);
            Notification.exception(error);
        }).finally(function() {
            self.setProgress('');
            self.setBusy(false);
        });
    };

    Rewriter.prototype.showResult = function(result) {
        var self = this;
        this.result = result;
        this.find('#the_ar_result').val(result.text);
        // The Text Profiler reads these when the text is sent to it, so it counts the text as we do.
        this.find('.ogte_ar_sendtoeditor_button').attr({
            'data-send-listid': this.listid,
            'data-send-levelid': this.level,
            'data-send-ignores': result.ignores.join(' ')
        });
        var context = $.extend({}, result, {
            listname: this.listname,
            originalparagraphs: paragraphs(this.original),
            glossarycount: result.glossary.length,
            hasnotes: result.notes.length > 0
        });
        return Promise.resolve(Templates.renderForPromise('mod_ogte/rewriter_result', context)).then(function(rendered) {
            Templates.replaceNodeContents(self.find('.ogte-rw-result'), rendered.html, rendered.js);
            self.showStep(2);
            self.root[0].scrollIntoView({behavior: 'smooth', block: 'start'});
            return null;
        });
    };

    Rewriter.prototype.download = function() {
        var self = this;
        Str.get_string('rw_glossary', 'mod_ogte').then(function(glossarytitle) {
            var lines = [self.result.levellabel, '', self.result.text];
            if (self.result.glossary.length) {
                lines.push('', glossarytitle);
                self.result.glossary.forEach(function(item) {
                    lines.push(item.word + ': ' + item.explanation);
                });
            }
            var name = self.result.levellabel.replace(/[^A-Za-z0-9]+/g, '_').replace(/_+$/, '');
            Utils.downloadTextContent(lines.join('\n') + '\n', 'rewritten_' + name + '.txt');
            return null;
        }).catch(Notification.exception);
    };

    return {
        /**
         * Start a rewriter.
         *
         * @param {String} rootid The id of the rewriter's container.
         * @param {Number} ogteid The activity id.
         * @param {Boolean} aiavailable Is an AI provider set up.
         */
        init: function(rootid, ogteid, aiavailable) {
            new Rewriter(rootid, ogteid, aiavailable);
        }
    };
});
