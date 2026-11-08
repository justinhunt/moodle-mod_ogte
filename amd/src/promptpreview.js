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
 * The prompt preview on the prompt edit page: fill the prompt being edited for a sample text and
 * a word list level, and show it with its placeholder values. No AI call is made.
 *
 * It refreshes when the list, level or sample changes, and a moment after the prompt is edited.
 *
 * @module     mod_ogte/promptpreview
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification'], function($, Ajax, Templates, Notification) {

    /** @var {Number} Wait this long after typing stops, in ms. */
    var TYPING_DELAY = 800;

    /**
     * Set up the preview.
     *
     * @param {String} rootid
     */
    var PromptPreview = function(rootid) {
        this.root = $('#' + rootid);
        this.name = this.root.data('name');
        this.levels = this.root.data('levels') || {};
        this.listselect = this.root.find('.ogte-pp-list');
        this.levelselect = this.root.find('.ogte-pp-level');
        this.form = $('form').has('[name="prompttext"]').first();
        this.timer = null;
        // Each request gets a number, so a slow early reply never replaces a later one.
        this.requestno = 0;
        this.fillLevels();
        this.registerEvents();
        this.refresh();
    };

    /**
     * Fill the level select for the chosen list. The middle level is a sensible first choice.
     */
    PromptPreview.prototype.fillLevels = function() {
        var levels = this.levels[this.listselect.val()] || [];
        this.levelselect.empty();
        levels.forEach(function(level) {
            this.levelselect.append($('<option>').val(level.key).text(level.label));
        }, this);
        if (levels.length) {
            this.levelselect.val(levels[Math.floor((levels.length - 1) / 2)].key);
        }
    };

    PromptPreview.prototype.registerEvents = function() {
        var self = this;
        this.listselect.on('change', function() {
            self.fillLevels();
            self.refresh();
        });
        this.levelselect.on('change', function() {
            self.refresh();
        });
        this.root.find('.ogte-pp-button').on('click', function() {
            self.refresh();
        });
        var later = function() {
            clearTimeout(self.timer);
            self.timer = setTimeout(function() {
                self.refresh();
            }, TYPING_DELAY);
        };
        this.root.find('.ogte-pp-sample').on('input', later);
        this.form.find('[name="prompttext"], [name="systemprompt"]').on('input', later);
    };

    PromptPreview.prototype.refresh = function() {
        var self = this;
        clearTimeout(this.timer);
        if (!this.listselect.val() || this.levelselect.val() === null) {
            return;
        }
        var requestno = ++this.requestno;
        Promise.resolve(Ajax.call([{
            methodname: 'mod_ogte_preview_prompt',
            args: {
                name: this.name,
                systemprompt: this.form.find('[name="systemprompt"]').val() || '',
                prompttext: this.form.find('[name="prompttext"]').val() || '',
                listid: parseInt(this.listselect.val(), 10),
                level: parseInt(this.levelselect.val(), 10),
                sample: this.root.find('.ogte-pp-sample').val() || ''
            }
        }])[0]).then(function(result) {
            result.values.forEach(function(value) {
                value.token = '{' + value.name + '}';
            });
            result.hasunknown = result.unknown.length > 0;
            result.unknownlist = result.unknown.map(function(name) {
                return '{' + name + '}';
            }).join(', ');
            return Templates.renderForPromise('mod_ogte/promptpreview_result', result);
        }).then(function(rendered) {
            if (requestno === self.requestno) {
                Templates.replaceNodeContents(self.root.find('.ogte-pp-result'), rendered.html, rendered.js);
            }
            return null;
        }).catch(Notification.exception);
    };

    return {
        /**
         * Start the preview.
         *
         * @param {String} rootid The id of the preview's container.
         */
        init: function(rootid) {
            new PromptPreview(rootid);
        }
    };
});
