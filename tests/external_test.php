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

namespace mod_ogte;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ogte/externallib.php');

/**
 * Access checks of the OGTE web services.
 *
 * @package    mod_ogte
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_ogte_external::class)]
final class external_test extends \advanced_testcase {
    /**
     * A guest can use the tools on the site front page, where the public tools live.
     */
    public function test_guest_on_front_page(): void {
        $this->resetAfterTest();
        $ogte = $this->getDataGenerator()->create_module('ogte', ['course' => SITEID]);
        set_config('aiprovider', '', 'mod_ogte');
        $this->setGuestUser();

        // The access checks pass, so the call gets as far as finding no AI provider.
        try {
            \mod_ogte_external::call_ai($ogte->id, 'grammar_correction', 'Cats');
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertEquals('noaiprovider', $e->errorcode);
        }
    }

    /**
     * Without mod/ogte:useai the AI tools are refused.
     */
    public function test_useai_capability_required(): void {
        global $DB;
        $this->resetAfterTest();
        $ogte = $this->getDataGenerator()->create_module('ogte', ['course' => SITEID]);
        $guestrole = $DB->get_field('role', 'id', ['shortname' => 'guest']);
        assign_capability('mod/ogte:useai', CAP_PROHIBIT, $guestrole, \context_system::instance(), true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setGuestUser();

        $this->expectException(\required_capability_exception::class);
        \mod_ogte_external::call_ai($ogte->id, 'grammar_correction', 'Cats');
    }

    /**
     * A user who cannot see the activity cannot use it.
     */
    public function test_not_enrolled(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $ogte = $this->getDataGenerator()->create_module('ogte', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        \mod_ogte_external::call_ai($ogte->id, 'grammar_correction', 'Cats');
    }
}
