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
 * Utils for OGTE
 *
 * @package    mod_ogte
 * @copyright  2023 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_ogte;
defined('MOODLE_INTERNAL') || die();

use \mod_ogte\constants;



/**
 * Functions used generally across this mod
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils{

    //const CLOUDPOODLL = 'http://localhost/moodle';
    //const CLOUDPOODLL = 'https://vbox.poodll.com/cphost';
    const CLOUDPOODLL = 'https://cloud.poodll.com';



    //see if this is truly json or some error
    public static function is_json($string) {
        if(!$string){return false;}
        if(empty($string)){return false;}
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    public static function curl_fetch($url,$postdata=false,$method='get')
    {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();

        if($method=='get') {
            $result = $curl->get($url, $postdata);
        }else{
            $result = $curl->post($url, $postdata);
        }
        return $result;
    }

    //This is called from the settings page and we do not want to make calls out to cloud.poodll.com on settings
    //page load, for performance and stability issues. So if the cache is empty and/or no token, we just show a
    //"refresh token" links
    public static function fetch_token_for_display($apiuser,$apisecret){
       global $CFG;

       //First check that we have an API id and secret
        //refresh token
        $refresh = \html_writer::link($CFG->wwwroot . '/mod/ogte/refreshtoken.php',
                get_string('refreshtoken',constants::M_COMPONENT)) . '<br>';


        $message = '';
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
        if(empty($apiuser)){
           $message .= get_string('noapiuser',constants::M_COMPONENT) . '<br>';
       }
        if(empty($apisecret)){
            $message .= get_string('noapisecret',constants::M_COMPONENT);
        }

        if(!empty($message)){
            return $refresh . $message;
        }

        //Fetch from cache and process the results and display
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //if we have no token object the creds were wrong ... or something
        if(!($tokenobject)){
            $message = get_string('notokenincache',constants::M_COMPONENT);
            //if we have an object but its no good, creds werer wrong ..or something
        }elseif(!property_exists($tokenobject,'token') || empty($tokenobject->token)){
            $message = get_string('credentialsinvalid',constants::M_COMPONENT);
        //if we do not have subs, then we are on a very old token or something is wrong, just get out of here.
        }elseif(!property_exists($tokenobject,'subs')){
            $message = 'No subscriptions found at all';
        }
        if(!empty($message)){
            return $refresh . $message;
        }

        //we have enough info to display a report. Lets go.
        foreach ($tokenobject->subs as $sub){
            $sub->expiredate = date('d/m/Y',$sub->expiredate);
            $message .= get_string('displaysubs',constants::M_COMPONENT, $sub) . '<br>';
        }
        //Is app authorised
        if(in_array(constants::M_COMPONENT,$tokenobject->apps)){
            $message .= get_string('appauthorised',constants::M_COMPONENT) . '<br>';
        }else{
            $message .= get_string('appnotauthorised',constants::M_COMPONENT) . '<br>';
        }

        return $refresh . $message;

    }

    //We need a Poodll token to make all this recording and transcripts happen
    public static function fetch_token($apiuser, $apisecret, $force=false)
    {

        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');
        $tokenuser = $cache->get('recentpoodlluser');
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
        $now = time();

        //if we got a token and its less than expiry time
        // use the cached one
        if($tokenobject && $tokenuser && $tokenuser==$apiuser && !$force){
            if($tokenobject->validuntil == 0 || $tokenobject->validuntil > $now){
               // $hoursleft= ($tokenobject->validuntil-$now) / (60*60);
                return $tokenobject->token;
            }
        }

        // Send the request & save response to $resp
        $token_url = self::CLOUDPOODLL . "/local/cpapi/poodlltoken.php";
        $postdata = array(
            'username' => $apiuser,
            'password' => $apisecret,
            'service'=>'cloud_poodll'
        );
        $token_response = self::curl_fetch($token_url,$postdata);
        if ($token_response) {
            $resp_object = json_decode($token_response);
            if($resp_object && property_exists($resp_object,'token')) {
                $token = $resp_object->token;
                //store the expiry timestamp and adjust it for diffs between our server times
                if($resp_object->validuntil) {
                    $validuntil = $resp_object->validuntil - ($resp_object->poodlltime - $now);
                    //we refresh one hour out, to prevent any overlap
                    $validuntil = $validuntil - (1 * HOURSECS);
                }else{
                    $validuntil = 0;
                }

                $tillrefreshhoursleft= ($validuntil-$now) / (60*60);


                //cache the token
                $tokenobject = new \stdClass();
                $tokenobject->token = $token;
                $tokenobject->validuntil = $validuntil;
                $tokenobject->subs=false;
                $tokenobject->apps=false;
                $tokenobject->sites=false;
                if(property_exists($resp_object,'subs')){
                    $tokenobject->subs = $resp_object->subs;
                }
                if(property_exists($resp_object,'apps')){
                    $tokenobject->apps = $resp_object->apps;
                }
                if(property_exists($resp_object,'sites')){
                    $tokenobject->sites = $resp_object->sites;
                }

                $cache->set('recentpoodlltoken', $tokenobject);
                $cache->set('recentpoodlluser', $apiuser);

            }else{
                $token = '';
                if($resp_object && property_exists($resp_object,'error')) {
                    //ERROR = $resp_object->error
                }
            }
        }else{
            $token='';
        }
        return $token;
    }

    //check token and tokenobject(from cache)
    //return error message or blank if its all ok
    public static function fetch_token_error($token){
        global $CFG;

        //check token authenticated
        if(empty($token)) {
            $message = get_string('novalidcredentials', constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $message;
        }

        // Fetch from cache and process the results and display.
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //we should not get here if there is no token, but lets gracefully die, [v unlikely]
        if (!($tokenobject)) {
            $message = get_string('notokenincache', constants::M_COMPONENT);
            return $message;
        }

        //We have an object but its no good, creds were wrong ..or something. [v unlikely]
        if (!property_exists($tokenobject, 'token') || empty($tokenobject->token)) {
            $message = get_string('credentialsinvalid', constants::M_COMPONENT);
            return $message;
        }
        // if we do not have subs.
        if (!property_exists($tokenobject, 'subs')) {
            $message = get_string('nosubscriptions', constants::M_COMPONENT);
            return $message;
        }
        // Is app authorised?
        if (!property_exists($tokenobject, 'apps') || !in_array(constants::M_COMPONENT, $tokenobject->apps)) {
            $message = get_string('appnotauthorised', constants::M_COMPONENT);
            return $message;
        }

        //just return empty if there is no error.
        return '';
    }

    public static function markup_newlines($passage){
        //in js we gt the text with element.innertext()
        //this turns new lines into \n, we want that to survive clean_text and other text cleanup
        //so we can restore it as <br> later
        //innertest gives us: cr => \n + cr + blank line => \n\n\n 
        //but if we turn that to <br> we get <br><br><br> which is not what we want
        $passage = str_replace("\n\n\n","\n\n", $passage);
        $passage = str_replace("\r\n", ' ' .constants::M_FAKENEWLINE . ' ', $passage);
        $passage = str_replace("\n", ' ' .constants::M_FAKENEWLINE . ' ', $passage);
        return $passage;
    }

    public static function clean_text($passage){
        //remove slashes
        $passage = stripslashes($passage );

        //clean up unicode encoded &nbsp;
        $passage = str_replace("\xc2\xa0", ' ', $passage);

        //clean up other encoded &nbsp;
        $passage = preg_replace('/\s*&nbsp;|&#160;|&\#x00A0;|&\#0160;|\xC2\xA0/u', ' ', $passage);

        // Convert HTML entities to their corresponding characters
        //I think we do not need this
        //$passage = html_entity_decode($passage, ENT_QUOTES, 'UTF-8');

        // Remove tags and convert multiple spaces into a single space
        $cleanedText = preg_replace('/<[^>]*>/', ' ', $passage);
        $cleanedText = preg_replace('/\s+/', ' ', $cleanedText);

        // Trim spaces from the beginning and end of the text
        $cleanedText = trim($cleanedText);

        return $cleanedText;
    }

    /*
     * Turn a passage with text "lines" into html "brs"
     *
     * @param String The passage of text to convert
     * @param String An optional pad on each replacement (needed for processing when marking up words as spans in passage)
     * @return String The converted passage of text
     */
    public static function lines_to_brs($passage,$seperator=''){
        //see https://stackoverflow.com/questions/5946114/how-to-replace-newline-or-r-n-with-br
        return str_replace("\r\n",$seperator . '<br>' . $seperator,$passage);
        //this is better but we can not pad the replacement and we need that
        //return nl2br($passage);
    }


    //take a json string of session errors/self-corrections, and count how many there are.
    public static function count_objects($items){
        $objects = json_decode($items);
        if($objects){
            $thecount = count(get_object_vars($objects));
        }else{
            $thecount=0;
        }
        return $thecount;
    }

    public static function count_list_words($listid){
        global $DB;
        $allwords =$DB->count_records(constants::M_WORDSTABLE,['list'=>$listid]);

        $sql = 'SELECT COUNT(headword)
                   FROM {'. constants::M_WORDSTABLE .'} w
                   WHERE
                        w.word = w.headword AND
                        w.list = :listid';
        $headwords=$DB->count_records_sql($sql,['listid'=>$listid]);
        return [$headwords,$allwords];
    }

    public static function get_list_options($courseid){
        global $DB;
        $listopts=[];
        $alllists = $DB->get_records(constants::M_LISTSTABLE,['ispropernouns'=>0]);
        foreach($alllists as $list){
            if($list->courseid==0 || $list->courseid==$courseid) {
                if (self::is_json($list->props)) {
                    $listopts[] = ['key' => $list->id, 'label' => $list->name];
                }
            }
        }
        return $listopts;
    }

    public static function get_level_options($courseid,$listid=0){
        global $DB;
        $listlevels=[];
        if($listid){
            $alllists = $DB->get_records(constants::M_LISTSTABLE,['id'=>$listid,'ispropernouns'=>0]);
        }else{
            $alllists = $DB->get_records(constants::M_LISTSTABLE,['ispropernouns'=>0]);
        }
        foreach($alllists as $list){
            if($list->courseid==0 || $list->courseid==$courseid) {
                if (self::is_json($list->props)) {
                    $listprops = json_decode($list->props);
                    $onelistlevels = [];
                    foreach ($listprops as $index => $level) {
                        $onelistlevels[] = ['key' => $index, 'label' => $level->name, 'listname' => $list->name];
                    }
                    $listlevels[$list->id] = $onelistlevels;
                }
            }
        }
        if($listid){
            return $onelistlevels;
        }else{
            return $listlevels;
        }
    }

    public static function fetch_word_family($word,$listid){
        global $DB;
        $headword =$DB->get_field(constants::M_WORDSTABLE,'headword',['word'=>$word]);
        if(!$headword){
            return [$word];
        };
        $words = $DB->get_fieldset_select(constants::M_WORDSTABLE,'word','headword=:headword AND list=:listid',['headword'=>$headword,'listid'=>$listid]);
        if($words && is_array($words) && count($words)>0) {
            return $words;
        }else{
            return [$word];
        }
    }

    public static function get_lang_options(){
        return array(
            constants::M_LANG_ARAE => constants::M_LANG_ARAE,
            constants::M_LANG_ARSA => constants::M_LANG_ARSA,
            constants::M_LANG_DADK => constants::M_LANG_DADK,
            constants::M_LANG_DEDE => constants::M_LANG_DEDE,
            constants::M_LANG_DEAT => constants::M_LANG_DEAT,
            constants::M_LANG_DECH => constants::M_LANG_DECH,
            constants::M_LANG_ENUS =>  constants::M_LANG_ENUS,
            constants::M_LANG_ENGB => constants::M_LANG_ENGB,
            constants::M_LANG_ENAU => constants::M_LANG_ENAU,
            constants::M_LANG_ENZA =>  constants::M_LANG_ENZA,
            constants::M_LANG_ENIN => constants::M_LANG_ENIN ,
            constants::M_LANG_ENIE => constants::M_LANG_ENIE,
            constants::M_LANG_ENWL =>constants::M_LANG_ENWL,
            constants::M_LANG_ENAB => constants::M_LANG_ENAB,
            constants::M_LANG_FAIR => constants::M_LANG_FAIR,
            constants::M_LANG_FILPH => constants::M_LANG_FILPH ,
            constants::M_LANG_FRCA => constants::M_LANG_FRCA ,
            constants::M_LANG_FRFR =>constants::M_LANG_FRFR,
            constants::M_LANG_HIIN =>constants::M_LANG_HIIN,
            constants::M_LANG_HEIL =>constants::M_LANG_HEIL,
            constants::M_LANG_IDID =>constants::M_LANG_IDID,
            constants::M_LANG_ITIT =>constants::M_LANG_ITIT,
           // constants::M_LANG_JAJP =>constants::M_LANG_JAJP,
           // constants::M_LANG_KOKR =>constants::M_LANG_KOKR,
            constants::M_LANG_MINZ =>constants::M_LANG_MINZ,
            constants::M_LANG_MSMY =>constants::M_LANG_MSMY,
            constants::M_LANG_NLNL =>constants::M_LANG_NLNL,
            constants::M_LANG_NLBE =>constants::M_LANG_NLBE,
            constants::M_LANG_PTBR =>constants::M_LANG_PTBR,
            constants::M_LANG_PTPT =>constants::M_LANG_PTPT,
            constants::M_LANG_RURU =>constants::M_LANG_RURU,
            constants::M_LANG_TAIN =>constants::M_LANG_TAIN,
            constants::M_LANG_TEIN =>constants::M_LANG_TEIN,
            constants::M_LANG_TRTR =>constants::M_LANG_TRTR,
           // constants::M_LANG_ZHCN =>constants::M_LANG_ZHCN,
            constants::M_LANG_NONO =>constants::M_LANG_NONO,
            constants::M_LANG_PLPL =>constants::M_LANG_PLPL,
            constants::M_LANG_RORO =>constants::M_LANG_RORO,
            constants::M_LANG_SVSE =>constants::M_LANG_SVSE,
            constants::M_LANG_UKUA =>constants::M_LANG_UKUA,
            constants::M_LANG_EUES =>constants::M_LANG_EUES,
            constants::M_LANG_FIFI =>constants::M_LANG_FIFI,
            constants::M_LANG_HUHU =>constants::M_LANG_HUHU,
            constants::M_LANG_BGBG =>constants::M_LANG_BGBG,
            constants::M_LANG_CSCZ =>constants::M_LANG_CSCZ,
            constants::M_LANG_ELGR =>constants::M_LANG_ELGR,
            constants::M_LANG_HRHR =>constants::M_LANG_HRHR,
            constants::M_LANG_LTLT =>constants::M_LANG_LTLT,
            constants::M_LANG_LVLV =>constants::M_LANG_LVLV,
            constants::M_LANG_SKSK =>constants::M_LANG_SKSK,
            constants::M_LANG_SLSI =>constants::M_LANG_SLSI,
            constants::M_LANG_ISIS =>constants::M_LANG_ISIS,
            constants::M_LANG_MKMK =>constants::M_LANG_MKMK,
            constants::M_LANG_SRRS =>constants::M_LANG_SRRS
        );
    }

    public static function get_coverage($passage,$ignore,$listid,$listlevel){
        global $DB;
        $list = $DB->get_record(constants::M_LISTSTABLE,['id'=>$listid]);
        $levels = json_decode($list->props);
        $selectedlevel= $levels[$listlevel];


        //do we have proper nouns
        $propernounlist = $DB->get_record(constants::M_LISTSTABLE,['ispropernouns'=>1,'lang'=>$list->lang]);
        
        //does the list have multi-word-terms
        //TO DO -  assume multiwordterms is true, because we will need it for propernouns, if "fetch_multiwordterms" is empty, it will still work
        //and the code will be simpler (fewer if statements)
        $hasmultiwordterms = $DB->get_record(constants::M_LISTSTABLE,['hasmultiwordterms'=>1,'lang'=>$list->lang]);

        $sql = 'SELECT listrank
                   FROM {'. constants::M_WORDSTABLE .'} w
                   WHERE
                        LOWER(w.word) = :theword AND
                        w.list = :listid';

        //rewrite new line markers to survive subsequent text clean up                
        $passage = self::markup_newlines($passage);
        //text clean up                
        $passage = self::clean_text($passage);
        
        //if the list has multiword terms we need to pre-parse the passage, spot such terms and replace spaces with underscores
        if($hasmultiwordterms) {
            $multiwordterms = self::fetch_multiwordterms($listid);
            if ($propernounlist){
                $multiwordpropernouns = self::fetch_multiwordterms($propernounlist->id);
                $multiwordterms = array_merge($multiwordterms, $multiwordpropernouns);
            }

            foreach($multiwordterms as $term){
                //dealing with capitals here is a bit tricky,
                // though its hacky we just do it four times and hope we get a strike
                //all lower case term
                $passage = str_replace($term,str_replace(' ','_',$term),$passage);
                //capital first letter term
                $passage = str_replace(ucfirst($term),str_replace(' ','_',ucfirst($term)),$passage);
                //capital first letter of each word term
                $passage = str_replace(ucwords($term),str_replace(' ','_',ucwords($term)),$passage);
                //capitalize all letters in Term
                $passage = str_replace(strtoupper($term),str_replace(' ','_',strtoupper($term)),$passage);
            }
        }

        $words = preg_split('/[\s]+/', $passage, -1, PREG_SPLIT_NO_EMPTY);
        $ignores = preg_split('/[\s]+/', $ignore, -1, PREG_SPLIT_NO_EMPTY);
        if(is_array($ignores)){
            $ignores = array_map(function($word){
                    $word = preg_replace(constants::M_APOSTROPHES, "'", $word);
                    $word = trim(preg_replace('/[^\'a-zA-Z0-9]/', '', strip_tags($word)));
                    $word=self::handle_apostrophes($word);
                    return strtolower($word);
                }, $ignores);
            $ignores= array_unique($ignores);

            //get family of each ignored word and add to list
            //eg if "run" is ignored, we also ignore "running" and "runs"
            foreach($ignores as $ignore){
                $ignorefamily = self::fetch_word_family($ignore,$listid);
                $ignores = array_merge($ignores,$ignorefamily);
            }
            //remove duplicates
            $ignores= array_unique($ignores);
        }

        $inlevel=0;
        $outoflist=0;
        $outoflevel=0;
        $wordcount=0;
        $ignored=0;
        $propernouns=0;
        $numbers=0;
        $newlines=0;
        $retwords = [];
        foreach($words as $word){

            //standardize apostrophes in each word
            //this takes all the wonky things that look like apostrophes and turns them into real apostrophes
            $word = preg_replace(constants::M_APOSTROPHES, "'", $word);

            //first we clean the word of any junk (commas, full stops etc) and lower case it
            //but if the list has multiwords we need to replace underscores in the word with spaces
            //we just added them to get through the word splitting process
            if($hasmultiwordterms){
                $preword = $word;
                $word = str_replace('_',' ',$word);
                $ismultiwordterm= $preword != $word;
                //when we do the clean up we leave spaces in, because there are spaces between words in multiword terms
                $cleanword = trim(preg_replace('/[^\'a-zA-Z0-9 ]/', '', strip_tags($word)));
                //if we are currently dealing with a real multiword term, eg "a lot of" we are going to need to return to the profiler tool
                //the individual words in the term, so we can tag them all. This makes it a bit messy so we need a words_in_term array and count
                //e.g we return each of "a", "lot" and "of" as separate words each with attributes for level and word count.
                if($ismultiwordterm){
                    $words_in_term = preg_split('/[\s]+/', $word, -1, PREG_SPLIT_NO_EMPTY);
                    $words_in_term_count = count($words_in_term);
                }else{
                    $words_in_term_count=1;
                }
            }else{
                $ismultiwordterm=false;
                $words_in_term_count=1;
                $cleanword = trim(preg_replace('/[^\'a-zA-Z0-9]/', '', strip_tags($word)));
            }

            //lower case the clean word (all words in list are supposed to be lowercase)
            $cleanword= strtolower($cleanword);

            //handle apostrophes. 
            //This is where we deal with "don't" "won't" etc
            $cleanword = self::handle_apostrophes($cleanword);

            //strip any apostrophes that remain at start and end of word
            $cleanword =trim($cleanword, "'");

            if(!empty($cleanword)) {
                //check if its being ignored
                if (is_array($ignores) && in_array($cleanword, $ignores)) {
                    //for now we don't "ignore" multi-words, but it is easy to add if we decide to
                    $retwords[] = \html_writer::span($word, 'mod_ogte_ignored', ['data-index' => $wordcount]);
                    $ignored+= $words_in_term_count;
                }elseif(self::is_numeric_with_unit($cleanword)){
                    //for now we don't "ignore" numerics, but it is easy to add if we decide to
                    $retwords[]=\html_writer::span($word, 'mod_ogte_number', ['data-index'=>$wordcount]);
                    $numbers+= $words_in_term_count;

               //restore new words and remove from calculation
                }elseif($cleanword===constants::M_FAKENEWLINE){
                    //return an html new line
                    $retwords[]=\html_writer::empty_tag('br');
                    $newlines+= $words_in_term_count;//always 1 right?

                }else{
                    //search for the word listrank and process depending on the level
                    //due to some lists having capitalized versions of the same word, multiple entries are not impossible
                    //we just take the first one
                    $listrank = $DB->get_field_sql($sql, ['theword' => $cleanword, 'listid' => $listid],IGNORE_MULTIPLE);
                    //if its not there, its not in the list
                    if (!$listrank) {
                        //it could be a proper noun
                        $propernounid = null;
                        if ($propernounlist) {
                            $propernounid = $DB->get_field_sql($sql, ['theword' => $cleanword, 'listid' => $propernounlist->id], IGNORE_MULTIPLE);
                        }
                        if (!empty($propernounid)) {
                            //for single word terms, just return. For multi-word terms, loop through each word in term and return them
                            if(!$ismultiwordterm) {
                                $retwords[] = \html_writer::span($word, 'mod_ogte_propernoun', ['data-index' => $wordcount, 'data-listrank' => 0]);
                            }else{
                                for($i=0;$i<$words_in_term_count;$i++){
                                    $retwords[] = \html_writer::span($words_in_term[$i], 'mod_ogte_propernoun', ['data-index' => $wordcount+$i, 'data-listrank' => 0]);
                                }
                            }
                            $propernouns+= $words_in_term_count;
                        }else {
                            //for single word terms, just return. For multi-word terms, loop through each word in term and return them
                            if(!$ismultiwordterm) {
                                $retwords[] = \html_writer::span($word, 'mod_ogte_outoflist', ['data-index' => $wordcount, 'data-listrank' => 0]);
                            }else{
                                for($i=0;$i<$words_in_term_count;$i++){
                                    $retwords[] = \html_writer::span($words_in_term[$i],'mod_ogte_outoflist', ['data-index' => $wordcount+$i, 'data-listrank' => 0]);
                                }

                            }
                            $outoflist+= $words_in_term_count;
                        }

                    //if its in the list, tag the word with level data, and check if its within or outside of the selected level
                    } else {
                        //tag the word with level data
                        $atts = ['data-index' => $wordcount,'data-listrank'=>$listrank,'data-listlevel'=>'','data-listlevelname'=>''];
                        foreach ($levels as $levelid=>$level){
                            if($listrank <= $level->top && $listrank >= $level->bottom){
                                $atts['data-listlevel']=$levelid;
                                $atts['data-listlevelname']=$level->name;
                            }
                        }
                        //check if its within or outside the selected level
                        if ($listrank > $selectedlevel->top) {
                            //for single word terms, just return. For multi-word terms, loop through each word in term and return them
                            if(!$ismultiwordterm) {
                                $retwords[] = \html_writer::span($word, 'mod_ogte_outoflevel', $atts);
                            }else{
                                for($i=0;$i<$words_in_term_count;$i++){
                                    $atts['data-index'] = $wordcount+$i;
                                    $retwords[] = \html_writer::span($words_in_term[$i], 'mod_ogte_outoflevel', $atts);
                                }

                            }
                            $outoflevel+= $words_in_term_count;
                        }else{
                            //for single word terms, just return. For multi-word terms, loop through each word in term and return them
                            if(!$ismultiwordterm) {
                                $retwords[] = \html_writer::span($word, 'mod_ogte_inlevel', $atts);
                            }else{
                                for($i=0;$i<$words_in_term_count;$i++){
                                    $atts['data-index'] = $wordcount+$i;
                                    $retwords[] = \html_writer::span($words_in_term[$i], 'mod_ogte_inlevel', $atts);
                                }
                            }
                            $inlevel+= $words_in_term_count;
                        }
                    }
                }
                $wordcount+= $words_in_term_count;
            }else{
                $retwords[] = $word;
            }
        }
        //adjust for numbers
        $wordcount = $wordcount - $numbers - $newlines;

        if($wordcount <= 0){
            return ['passage'=>$passage,'status'=>'error','message'=>'no words found','coverage'=>0];
        }else{
            $adjustedwordcount = $wordcount - $ignored - $propernouns;
            if($adjustedwordcount < 1){
                $coverage=0;
            }else{
                $coverage = round(($inlevel + $ignored + $propernouns)/$wordcount *100);
            }

            return ['passage'=>implode(' ',$retwords),
                'status'=>'success',
                'message'=>'coverage returned',
                'listid'=>$listid,
                'levelid'=>$listlevel,
                'coverage'=>$coverage,
                'inlevel'=>$inlevel,
                'outoflevel'=>$outoflevel,
                'outoflist'=>$outoflist,
                'ignored'=>$ignored,
                'propernouns'=>$propernouns,
                'rawwordcount'=>$wordcount + $numbers,
                'wordcount'=>$wordcount,
                'propernouns_percent'=>self::makePercent($propernouns,$wordcount),
                'inlevel_percent'=>self::makePercent($inlevel,$wordcount),
                'outoflevel_percent'=>self::makePercent($outoflevel,$wordcount),
                'outoflist_percent'=>self::makePercent($outoflist,$wordcount),
                'ignored_percent'=>self::makePercent($ignored,$wordcount)];
        }
    }
    
    public static function handle_apostrophes($theword){
        // Define common contraction patterns
        $contractions = array(
            "'s" => " is",
            "'d" => " had",
          // "won't" => "will not",
          // "can't" => "can not",
          // "shan't" => "shall not",
            "n't" => "not",
            "'ll" => " will",
            "'re" => " are",
            "'ve" => " have",
            "'m" => " am",
            "'em" => " them",
            "in'" => "ing",
        );
        
        //handle apostrophes
        foreach ($contractions as $pattern => $replacement) {
            $thepos = strpos($theword, $pattern);
            if ($thepos !== false) {
                switch($pattern){
                    case "n't":
                        $nts=["can't"=>"can","won't"=>"will","shan't"=>"shall"];
                        if(array_key_exists($theword,$nts) ){
                            //can't => can
                            $theword = $nts[$theword];
                        }else{
                            //shouldn't => should
                            $theword  = substr($theword, 0, $thepos) ;
                        }
                        break;

                    case "'ll":
                        //we'll => we
                        $theword  = substr($theword, 0, $thepos);
                        break;

                    case "'re":
                        //they're => they
                        $theword  = substr($theword, 0, $thepos);
                        break;

                    case "'ve":
                        //we've => we
                        $theword  = substr($theword, 0, $thepos);
                        break;

                    case "'d":
                        //he'd => he
                        $theword  = substr($theword, 0, $thepos);
                        break;

                    case "'s":
                        //Bob's => Bob
                        $theword  = substr($theword, 0, $thepos);
                        break;

                    case "'m":
                    case "'em":
                        //stuff'm => stuff
                        //beat'em => beat
                        $theword  = substr($theword, 0, $thepos);
                        break;

                    case "in'":
                        //blowin' => blowing
                        $theword = substr($theword, 0, $thepos) . $replacement;
                        break;
                    default:
                        $theword =substr($theword, 0, $thepos);
                }
            }
        }
        return $theword;
    }

    public static function fetch_multiwordterms($list){
        global $DB;
        $sql = 'SELECT word
            FROM {'. constants::M_WORDSTABLE .'} w
            WHERE
                 w.word LIKE "% %" AND
                 w.list = :listid';
        $multiwordterms = $DB->get_fieldset_sql($sql, ['listid' => $list]);
        return $multiwordterms;

    }

    public static function is_numeric_with_unit($str) {

        //if it's a number return true
        if(is_numeric($str)){
            return true;
        }

        //if it's a currency return true
        $currencypattern='/^[$€£¥₹]\s*\d+(?:\.\d+)?$/';
        if (preg_match($currencypattern, $str)) {
            return true;
        }

        //now check for units
        // Regular expression to match numeric part and unit part
        $unitpattern = '/^([\d.]+)\s*(' . constants::M_UNITS . ')$/i';

        // Perform the regular expression match
        if (!preg_match($unitpattern, $str, $matches)) {
            return false;
        }

        // Extract numeric part and unit part
        $numeric_part = $matches[1];
        $unit = $matches[2];
        if(\core_text::strlen($numeric_part) > 0 && \core_text::strlen($unit) > 0){
            return true;
        }else{
            return false;
        }

    }

    public static function makePercent($count,$total){
        if($total == 0){
            return 0;
        }else{
            return round(($count/$total)*100);
        }
    }
}
