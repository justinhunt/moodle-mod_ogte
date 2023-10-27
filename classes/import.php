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
 * Handles the import of items from CSV.
 *
 * @package   mod_ogte
 * @copyright 2023 Justin Hunt <justin@poodll.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_ogte;

/**
 * Class for importing a word list into an ogte
 *
 * @copyright 2023 Justin Hunt <justin@poodll.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import  {

    /**
     * process constructor.
     *
     * @param \csv_import_reader $cir
     * @param string|null $progresstrackerclass
     * @throws \coding_exception
     */
    public function __construct(import_csv_reader $cir,$moduleinstance, $modulecontext, $course,$cm, $listid) {
        $this->cir = $cir;
        $this->moduleinstance = $moduleinstance;
        $this->modulecontext = $modulecontext;
        $this->course = $course;
        $this->cm = $cm;
        $this->errors = 0;
        $this->listid=$listid;


        //set the keycols
        //the keycol details are not needs, just legacy  TO DO remove them. We just need a field to report back with
        $keycols = [];
        $keycols['listrank']=['type'=>'int','optional'=>false,'default'=>null,'dbname'=>'listrank'];
        $keycols['headword']=['type'=>'string','optional'=>false,'default'=>null,'dbname'=>'headword'];
        $keycols['words']=['type'=>'string','optional'=>false,'default'=>null,'dbname'=>'word'];
        $this->keycolumns = $keycols;

        // Keep timestamp consistent.
        $today = time();
        $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);

    }

    public function import_process() {
        global $DB;

        $allwords=0;
        $headwords=0;
        $this->errors = 0;
        $this->upt = new import_tracker($this->keycolumns);
        $this->upt->start(); // Start table.

        // Init csv import helper.
        $this->cir->init();

        $linenum = 0; // lines start at 1
        $overcounts=0; // mismatch count between headwords and actual headwords
        //this happens because lower case and capitalized versons of the same word may exist in list
        //eg h:185 ch: 188 - 284 dad dads daddy daddies Dad Dads Daddy Daddies
        while ($line = $this->cir->next()) {
            $linenum++;
            $this->upt->flush();
            $this->upt->track('line', $linenum);
            $wordsadded=$this->import_process_line($line);
            $this->upt->track('words', $wordsadded);
            $allwords+=$wordsadded;
            $headwords++;
            //uncomment this to display a list of overcounted headwords after importing
            /*
            list($checkheadwords,$checkallwords) = utils::count_list_words($this->listid);
            if($checkheadwords> $headwords + $overcounts){
                echo 'h:' . $headwords . ' ch: ' . $checkheadwords . ' - ' . implode(' ',$line) .'<br/>';
                $overcounts++;
            }
            */

        }

        $this->upt->close(); // Close table.
        $this->cir->close();
        $this->cir->cleanup(true);

        //update list table entry
        list($trueheadwords,$trueallwords) = utils::count_list_words($this->listid);
        if($allwords != $trueallwords || $headwords !=$trueheadwords){
            //because there ARE overcounts, we just use headwords and not $trueheadwords
        }
        $DB->update_record(constants::M_LISTSTABLE,['id'=>$this->listid,'headwords'=>$headwords,'allwords'=>$trueallwords]);
    }


    /**
     * Process one line from CSV file
     *
     * @param array $line
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function import_process_line(array $line)
    {
        global $DB, $CFG, $SESSION;


        $listrank = $line[0]; //for now we force this to be at index 0
        if(!$listrank){
            $this->upt->track('status',get_string('error:listrank',constants::M_COMPONENT), 'error',true);
            return false;
        }

        if(count($line)<2){
            $this->upt->track('status',get_string('error:toofewcols',constants::M_COMPONENT), 'error',true);
            return false;
        }

        // Pre-Process Import Data, and turn into DB Ready data.
        $newrecords = $this->preprocess_import_data($line);
        if(!$newrecords || !is_array($newrecords) || count($newrecords)==0){
            $this->upt->track('status','Error - Failed', 'error');
            return false;
        }


        //turn array into object
        $successes=0;
        foreach($newrecords as $newrec) {
            $newrecord = (object)$newrec;
            $result = $DB->insert_record(constants::M_WORDSTABLE, $newrecord);
            if($result){$successes++;}
        }

        if($successes==count($newrecords)){
            $this->upt->track('status','Success', 'normal');
        }else{
            $this->upt->track('status','Incomplete', 'error');
        }

        return $successes;
        //Do what we have to do

    }


    public function preprocess_import_data($line){

        //return value init
        $newrecords = [];
        $listrank=0;
        $headword='';
        foreach ($line as $keynum => $value) {
            switch($keynum){
                case 0:
                    $listrank = intval($value);
                    if($listrank==0) {
                        $this->upt->track('listrank', 'invalid rank:' . s($value), 'error');
                        return false;
                    }
                    $this->upt->track('listrank', s($value), 'normal');
                    break;
                case 1:
                    $headword=trim(strval($value));
                    if(empty( $headword)){
                        $this->upt->track('headword', 'empty headword:' . s($value), 'error');
                        return false;
                    }else{
                        $this->upt->track('headword', s($value), 'normal');
                    }

                    //NB no break here, because we want headword to be treated as a word below
                default:
                    $newrecord=[];
                    $newrecord['list']=$this->listid;
                    $newrecord['listrank']=$listrank;
                    $newrecord['headword']=$headword;
                    $newrecord['word']=trim(strval($value));
                    if(!empty($newrecord['word'])){
                        $newrecords[] = $newrecord;
                    }
            }
        }
        return $newrecords;
    }

}
