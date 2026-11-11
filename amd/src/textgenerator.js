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
 * The Text Generator: write a text at a word list level, then check and repair it as the Text
 * Rewriter does: up to MAX_REPAIRS repairs while the targets are not met, keeping the best pass.
 *
 * @module     mod_ogte/textgenerator
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification', 'core/str', 'mod_ogte/utils'],
function($, Ajax, Templates, Notification, Str, Utils) {

    /** @var {Number} The most repair passes after the first attempt. Matches rewriter::MAX_REPAIRS. */
    var MAX_REPAIRS = 3;

    /**
     * Set up one generator.
     *
     * @param {String} rootid The id of the generator's container.
     * @param {Number} ogteid The activity id.
     * @param {Boolean} aiavailable Is an AI provider set up.
     */
    var TextGenerator = function(rootid, ogteid, aiavailable) {
        this.root = $('#' + rootid);
        this.ogteid = ogteid;
        this.aiavailable = aiavailable;
        this.lists = this.root.data('lists') || [];
        this.lengths = this.root.data('lengths') || [];
        this.level = null;
        // The settings the current result was made with.
        this.settings = null;
        this.result = null;
        this.busy = false;
        this.fillLevels();
        this.registerEvents();
    };

    TextGenerator.prototype.find = function(selector) {
        return this.root.find(selector);
    };

    /**
     * The chosen list.
     *
     * @returns {Object|null}
     */
    TextGenerator.prototype.list = function() {
        var listid = parseInt(this.find('.ogte-gen-list').val(), 10);
        return this.lists.filter(function(list) {
            return list.key === listid;
        })[0] || null;
    };

    /**
     * Make a button for each of the chosen list's levels. The level chosen before is kept if
     * the list has it.
     */
    TextGenerator.prototype.fillLevels = function() {
        var self = this;
        var list = this.list();
        var container = this.find('.ogte-gen-levels').empty();
        if (!list) {
            this.level = null;
            return;
        }
        list.levels.forEach(function(level) {
            var button = $('<button type="button" class="ogte-gen-levelbutton" role="radio">')
                .attr('data-level', level.index)
                .append($('<span class="ogte-gen-levellabel">').text(level.label))
                .append($('<span class="ogte-gen-levelrange">').text(level.range));
            container.append(button);
        });
        if (this.level === null || this.level >= list.levels.length) {
            // The middle level is a sensible first choice.
            this.level = Math.floor((list.levels.length - 1) / 2);
        }
        self.selectLevel(this.level);
    };

    /**
     * @param {Number} index
     */
    TextGenerator.prototype.selectLevel = function(index) {
        var list = this.list();
        this.level = index;
        this.find('.ogte-gen-levelbutton').each(function() {
            var selected = parseInt($(this).attr('data-level'), 10) === index;
            $(this).toggleClass('ogte-gen-levelselected', selected).attr('aria-checked', selected ? 'true' : 'false');
        });
        this.find('.ogte-gen-levelsummary').text(list && list.levels[index] ? list.levels[index].summary : '');
    };

    /**
     * Mark the length stop that matches the number of words, if any.
     */
    TextGenerator.prototype.showStop = function() {
        var words = parseInt(this.find('.ogte-gen-words').val(), 10);
        var stop = this.lengths.indexOf(words);
        if (stop >= 0) {
            this.find('.ogte-gen-slider').val(stop);
        }
        this.find('.ogte-gen-stop').each(function() {
            var current = parseInt($(this).attr('data-stop'), 10) === stop;
            $(this).toggleClass('text-primary font-weight-bold fw-bold', current).toggleClass('text-muted', !current);
        });
    };

    TextGenerator.prototype.registerEvents = function() {
        var self = this;
        this.find('.ogte-gen-list').on('change', function() {
            self.fillLevels();
        });
        this.root.on('click', '.ogte-gen-levelbutton', function() {
            self.selectLevel(parseInt($(this).attr('data-level'), 10));
        });
        // The slider picks a length for the number box, which is what is sent. Any number in the
        // range can be typed there instead.
        this.find('.ogte-gen-slider').on('input change', function() {
            self.find('.ogte-gen-words').val(self.lengths[parseInt($(this).val(), 10)]);
            self.showStop();
        });
        this.find('.ogte-gen-stop').on('click', function() {
            self.find('.ogte-gen-words').val(self.lengths[parseInt($(this).attr('data-stop'), 10)]);
            self.showStop();
        });
        this.find('.ogte-gen-words').on('input change', function() {
            self.showStop();
        });
        this.find('.ogte-gen-generate').on('click', function() {
            var settings = self.readSettings();
            if (!settings) {
                return;
            }
            self.confirmReplace(this).then(function() {
                self.generate(settings);
                return null;
            }).catch(function() {
                // Cancelled.
            });
        });
        this.root.on('click', '.ogte-gen-change', function() {
            self.showResult(false);
        });
        this.root.on('click', '.ogte-gen-backtoresult', function() {
            self.showResult(true);
        });
        this.root.on('click', '.ogte-gen-again', function() {
            self.confirmReplace(this).then(function() {
                self.generate(self.settings);
                return null;
            }).catch(function() {
                // Cancelled.
            });
        });
        this.find('.ogte-gen-copy').on('click', function() {
            Utils.copyText(self.textWithTitle()).then(function() {
                self.find('.ogte-gen-copied').show().delay(2000).fadeOut();
                return null;
            }).catch(Notification.exception);
        });
        this.find('.ogte-gen-download').on('click', function() {
            self.download();
        });
        this.showStop();
    };

    /**
     * Read and check the form.
     *
     * @returns {Object|null} The settings, or null after telling the user what is wrong.
     */
    TextGenerator.prototype.readSettings = function() {
        var list = this.list();
        var wordsbox = this.find('.ogte-gen-words');
        var words = parseInt(wordsbox.val(), 10);
        var min = parseInt(wordsbox.attr('min'), 10);
        var max = parseInt(wordsbox.attr('max'), 10);
        var topic = this.find('.ogte-gen-topic').val().trim();
        var alert = function(key, a) {
            Str.get_string(key, 'mod_ogte', a).then(function(s) {
                return Notification.alert('', s);
            }).catch(Notification.exception);
            return null;
        };
        if (!list || this.level === null) {
            return null;
        }
        if (isNaN(words) || words < min || words > max) {
            return alert('gen_lengthrange', {min: min, max: max});
        }
        if (topic === '') {
            return alert('entersomething');
        }
        return {
            listid: list.key,
            listname: list.label,
            level: this.level,
            levellabel: list.levels[this.level].label + (list.levels[this.level].cefr ?
                ' (' + list.levels[this.level].cefr + ')' : ''),
            texttype: this.find('.ogte-gen-texttype:checked').val(),
            words: words,
            topic: topic,
            keywords: this.find('.ogte-gen-keywords').val().trim()
        };
    };

    /**
     * Ask before a new text replaces the one we have.
     *
     * @param {HTMLElement} trigger The button clicked, to return focus to.
     * @returns {Promise} Resolved to go on, rejected if cancelled.
     */
    TextGenerator.prototype.confirmReplace = function(trigger) {
        if (!this.result) {
            return Promise.resolve();
        }
        return Notification.saveCancelPromise(
            Str.get_string('gen_replacetitle', 'mod_ogte'),
            Str.get_string('gen_replacequestion', 'mod_ogte'),
            Str.get_string('rw_replaceok', 'mod_ogte'),
            {triggerElement: trigger}
        );
    };

    /**
     * Show the result, or the settings.
     *
     * @param {Boolean} show
     */
    TextGenerator.prototype.showResult = function(show) {
        this.find('.ogte-gen-settings').toggle(!show);
        this.find('.ogte-gen-back').toggle(!show && this.result !== null);
        this.find('.ogte-gen-result, .ogte-gen-actions').toggle(show);
    };

    TextGenerator.prototype.setProgress = function(message) {
        if (message) {
            this.find('.ogte-gen-progressmessage').text(message);
            this.find('.ogte-gen-progress').show();
        } else {
            this.find('.ogte-gen-progress').hide();
        }
    };

    TextGenerator.prototype.setBusy = function(busy) {
        this.busy = busy;
        this.find('.ogte-gen-generate, .ogte-gen-again, .ogte-gen-change').prop('disabled', busy);
    };

    /**
     * One pass on the server: the first attempt if current is empty, else a repair.
     *
     * @param {Object} settings
     * @param {String} current
     * @param {Array} glossary
     * @returns {Promise}
     */
    TextGenerator.prototype.pass = function(settings, current, glossary) {
        return Promise.resolve(Ajax.call([{
            methodname: 'mod_ogte_generate_text',
            args: {
                ogteid: this.ogteid,
                listid: settings.listid,
                level: settings.level,
                texttype: settings.texttype,
                words: settings.words,
                topic: settings.topic,
                keywords: settings.keywords,
                current: current,
                glossary: glossary
            }
        }])[0]);
    };

    /**
     * Write a text: a first attempt, then repairs.
     *
     * @param {Object} settings From readSettings().
     */
    TextGenerator.prototype.generate = function(settings) {
        var self = this;
        if (this.busy || !settings) {
            return;
        }
        var best = null;
        var title = '';

        // Keep the best pass, and repair until the targets are met or we run out of passes.
        var next = function(result, repairs) {
            // Repairs do not give a title: keep the first one.
            title = title || result.title;
            if (!best || result.score >= best.score) {
                best = result;
            }
            if (result.met || repairs >= MAX_REPAIRS) {
                best.title = title;
                return best;
            }
            var message = Str.get_string('rw_checking', 'mod_ogte', {pass: repairs + 1, max: MAX_REPAIRS});
            return Promise.resolve(message).then(function(s) {
                self.setProgress(s);
                return self.pass(settings, result.text, result.glossary);
            }).then(function(repaired) {
                return next(repaired, repairs + 1);
            });
        };

        // While it works, the progress message takes the place of the form and the result.
        this.setBusy(true);
        this.find('.ogte-gen-settings, .ogte-gen-result, .ogte-gen-actions').hide();
        // Str and Ajax give jQuery promises, which have no finally(): wrap them in native ones.
        Promise.resolve(Str.get_string('gen_generating', 'mod_ogte', settings.levellabel)).then(function(s) {
            self.setProgress(s);
            return self.pass(settings, '', []);
        }).then(function(result) {
            return next(result, 0);
        }).then(function(result) {
            return self.render(settings, result);
        }).catch(function(error) {
            // Go back to where we were.
            self.showResult(self.result !== null);
            Notification.exception(error);
        }).finally(function() {
            self.setProgress('');
            self.setBusy(false);
        });
    };

    /**
     * Show a new result.
     *
     * @param {Object} settings
     * @param {Object} result
     * @returns {Promise}
     */
    TextGenerator.prototype.render = function(settings, result) {
        var self = this;
        return Promise.resolve(Str.get_strings([
            {key: 'gen_untitled', component: 'mod_ogte'},
            {key: 'gen_summary', component: 'mod_ogte',
                param: {list: settings.listname, level: settings.levellabel, words: settings.words}}
        ])).then(function(strings) {
            result.title = result.title || strings[0];
            self.result = result;
            self.settings = settings;
            self.find('#the_ag_article').val(result.text);
            // The Text Profiler reads these when the text is sent to it, so it counts the text as we do.
            self.find('.ogte_ar_sendtoeditor_button').attr({
                'data-send-listid': settings.listid,
                'data-send-levelid': settings.level,
                'data-send-ignores': result.ignores.join(' ')
            });
            var context = $.extend({}, result, {
                summary: strings[1],
                glossarycount: result.glossary.length,
                hasnotes: result.notes.length > 0
            });
            return Templates.renderForPromise('mod_ogte/generator_result', context);
        }).then(function(rendered) {
            Templates.replaceNodeContents(self.find('.ogte-gen-result'), rendered.html, rendered.js);
            self.showResult(true);
            self.root[0].scrollIntoView({behavior: 'smooth', block: 'start'});
            return null;
        });
    };

    /**
     * The title and the text, as plain text.
     *
     * @returns {String}
     */
    TextGenerator.prototype.textWithTitle = function() {
        return this.result.title + '\n\n' + this.result.text;
    };

    TextGenerator.prototype.download = function() {
        var self = this;
        Str.get_string('gen_keywords', 'mod_ogte').then(function(keywordstitle) {
            var lines = [self.textWithTitle()];
            if (self.result.glossary.length) {
                lines.push('', keywordstitle);
                self.result.glossary.forEach(function(item) {
                    lines.push(item.word + ': ' + item.explanation);
                });
            }
            var name = self.result.title.replace(/[^A-Za-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'text';
            Utils.downloadTextContent(lines.join('\n') + '\n', name + '.txt');
            return null;
        }).catch(Notification.exception);
    };

    return {
        /**
         * Start a generator.
         *
         * @param {String} rootid The id of the generator's container.
         * @param {Number} ogteid The activity id.
         * @param {Boolean} aiavailable Is an AI provider set up.
         */
        init: function(rootid, ogteid, aiavailable) {
            new TextGenerator(rootid, ogteid, aiavailable);
        }
    };
});
