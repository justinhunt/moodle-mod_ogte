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

/**
 * mod_ogte data generator.
 *
 * @package    mod_ogte
 * @category   test
 * @copyright  2014 David Monllao <david.monllao@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_ogte data generator class.
 *
 * @package    mod_ogte
 * @category   test
 * @copyright  2014 David Monllao <david.monllao@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ogte_generator extends testing_module_generator {

    /**
     * @var int keep track of how many ogtes have been created.
     */
    protected $ogtecount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->ogtecount = 0;
        parent::reset();
    }


    public function create_instance($record = null,?array $options = null) {
        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test ogte name ' . $this->ogtecount;
        }
        if (!isset($record->intro)) {
            $record->intro = 'Test ogte name ' . $this->ogtecount;
        }
        if (!isset($record->days)) {
            $record->days = 0;
        }
        if (!isset($record->grade)) {
            $record->grade = 100;
        }

        $this->ogtecount++;

        return parent::create_instance($record, (array)$options);
    }

}
