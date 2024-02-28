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

    public static function get_list_options(){
        global $DB;
        $listopts=[];
        $alllists = $DB->get_records(constants::M_LISTSTABLE,[]);
        foreach($alllists as $list){
            if(self::is_json($list->props)){
                if(self::is_json($list->props)){
                    $listopts[] = ['key' => $list->id, 'label' => $list->name];
                }
            }
        }
        return $listopts;
    }

    public static function get_level_options($listid=0){
        global $DB;
        $listlevels=[];
        if($listid){
            $alllists = $DB->get_records(constants::M_LISTSTABLE,['id'=>$listid,'ispropernouns'=>0]);
        }else{
            $alllists = $DB->get_records(constants::M_LISTSTABLE,['ispropernouns'=>0]);
        }
        foreach($alllists as $list){
            if(self::is_json($list->props)){
                if(self::is_json($list->props)){
                    $listprops = json_decode($list->props);
                    $onelistlevels=[];
                    foreach ($listprops as $index => $level) {
                        $onelistlevels[] = ['key' => $index, 'label' => $level->name,'listname'=>$list->name ];
                    }
                    $listlevels[$list->id]=$onelistlevels;
                }
            }
        }
        if($listid){
            return $onelistlevels;
        }else{
            return $listlevels;
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

        $sql = 'SELECT listrank
                   FROM {'. constants::M_WORDSTABLE .'} w
                   WHERE
                        w.word = :theword AND
                        w.list = :listid';

        $passage = self::clean_text($passage);
        $words = preg_split('/[\s]+/', $passage, -1, PREG_SPLIT_NO_EMPTY);
        $ignores = preg_split('/[\s]+/', $ignore, -1, PREG_SPLIT_NO_EMPTY);
        if(is_array($ignores)){
            $ignores = array_map(function($word){
                    $word = trim(preg_replace('/[^a-zA-Z0-9]/', '', strip_tags($word)));
                    return strtolower($word);
                }, $ignores);
            $ignores= array_unique($ignores);
        }

        $inlevel=0;
        $outoflist=0;
        $outoflevel=0;
        $wordcount=0;
        $ignored=0;
        $propernouns=0;
        $numbers=0;
        $retwords = [];
        foreach($words as $word){
            $cleanword = trim(preg_replace('/[^a-zA-Z0-9]/', '', strip_tags($word)));
            //$cleanword = self::clean_text($word);
            $cleanword= strtolower($cleanword);
            if(!empty($cleanword)) {
                //check if its being ignored
                if (is_array($ignores) && in_array($cleanword, $ignores)) {
                    $retwords[] = \html_writer::span($word, 'mod_ogte_ignored', ['data-index' => $wordcount]);
                    $ignored++;
                }elseif(self::is_numeric_with_unit($cleanword)){
                    $retwords[]=\html_writer::span($word, 'mod_ogte_number', ['data-index'=>$wordcount]);
                    $numbers++;
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
                            $retwords[] = \html_writer::span($word, 'mod_ogte_propernoun', ['data-index' => $wordcount, 'data-listrank' => 0]);
                            $propernouns++;
                        }else {
                            $retwords[] = \html_writer::span($word, 'mod_ogte_outoflist', ['data-index' => $wordcount, 'data-listrank' => 0]);
                            $outoflist++;
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
                            $retwords[] = \html_writer::span($word, 'mod_ogte_outoflevel',$atts);
                            $outoflevel++;
                        }else{
                            $retwords[] = \html_writer::span($word, 'mod_ogte_inlevel',$atts);
                            $inlevel++;
                        }
                    }
                }
                $wordcount++;
            }else{
                $retwords[] = $word;
            }
        }
        //adjust for numbers
        $wordcount = $wordcount - $numbers;

        if($wordcount == 0){
            return ['passage'=>$passage,'status'=>'error','message'=>'no words found','coverage'=>0];
        }else{
            $coverage = round(($inlevel/($wordcount-$ignored))*100);
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
