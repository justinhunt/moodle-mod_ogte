<?php
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

namespace mod_ogte\local;

use core\hook\output\before_http_headers;
use mod_ogte\constants;

/**
 * Hook callbacks for mod_ogte.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Log anonymous visitors to the site front page in as guest, before any output is sent.
     *
     * The OGTE tools embedded on the front page call web services that need a session, so an
     * anonymous visitor must become a guest first. Doing it here, before the page head goes out,
     * allows a real HTTP redirect. The visitor is sent to the site course, where require_login()
     * logs them in as guest (and applies any guest site policy), and core sends them back to the
     * front page.
     *
     * @param before_http_headers $hook
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $CFG, $PAGE;

        if (during_initial_install() || isloggedin()) {
            return;
        }
        if ($PAGE->pagetype !== 'site-index') {
            return;
        }
        if (empty($CFG->autologinguests) || empty($CFG->guestloginbutton) || empty($CFG->siteguest)) {
            return;
        }
        if (!get_config(constants::M_COMPONENT, 'frontpageguestlogin')) {
            return;
        }

        redirect(new \moodle_url('/course/view.php', ['id' => SITEID]));
    }
}
