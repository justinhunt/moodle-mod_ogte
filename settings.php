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
 * Settings page
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

use \mod_ogte\constants;
use \mod_ogte\utils;

if ($ADMIN->fulltree) {


    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apiuser',
        get_string('apiuser', constants::M_COMPONENT),
        get_string('apiuser_details', constants::M_COMPONENT), '', PARAM_TEXT));

        $cloudpoodll_apiuser=get_config(constants::M_COMPONENT,'apiuser');
        $cloudpoodll_apisecret=get_config(constants::M_COMPONENT,'apisecret');
        $show_below_apisecret='';
    //if we have an API user and secret we fetch token
        if(!empty($cloudpoodll_apiuser) && !empty($cloudpoodll_apisecret)) {
            $tokeninfo = utils::fetch_token_for_display($cloudpoodll_apiuser,$cloudpoodll_apisecret);
            $show_below_apisecret=$tokeninfo;
    //if we have no API user and secret we show a "fetch from elsewhere on site" or "take a free trial" link
        }else{
            $amddata=['apppath'=>$CFG->wwwroot . '/' .constants::M_URL];
            $cp_components=['filter_poodll','qtype_cloudpoodll','mod_readaloud','mod_wordcards','mod_solo','mod_englishcentral','mod_pchat',
                'atto_cloudpoodll','tinymce_cloudpoodll', 'assignsubmission_cloudpoodll','assignfeedback_cloudpoodll'];
            foreach($cp_components as $cp_component){
                switch($cp_component){
                    case 'filter_poodll':
                        $apiusersetting='cpapiuser';
                        $apisecretsetting='cpapisecret';
                        break;
                    case 'mod_englishcentral':
                        $apiusersetting='poodllapiuser';
                        $apisecretsetting='poodllapisecret';
                        break;
                    default:
                        $apiusersetting='apiuser';
                        $apisecretsetting='apisecret';
                }
                $cloudpoodll_apiuser=get_config($cp_component,$apiusersetting);
                if(!empty($cloudpoodll_apiuser)){
                    $cloudpoodll_apisecret=get_config($cp_component,$apisecretsetting);
                    if(!empty($cloudpoodll_apisecret)){
                        $amddata['apiuser']=$cloudpoodll_apiuser;
                        $amddata['apisecret']=$cloudpoodll_apisecret;
                        break;
                    }
                }
            }
            $show_below_apisecret=$OUTPUT->render_from_template( constants::M_COMPONENT . '/managecreds',$amddata);
        }


    //get_string('apisecret_details', constants::M_COMPONENT)
    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apisecret',
        get_string('apisecret', constants::M_COMPONENT),$show_below_apisecret, '', PARAM_TEXT));

    $settings->add(new admin_setting_configselect('ogte/showrecentactivity', get_string('showrecentactivity', 'ogte'),
                                                  get_string('showrecentactivity', 'ogte'), 0,
                                                  array('0' => get_string('no'), '1' => get_string('yes'))));

    $settings->add(new admin_setting_configselect('ogte/overview', get_string('showoverview', 'ogte'),
                                                  get_string('showoverview', 'ogte'), 1,
                                                  array('0' => get_string('no'), '1' => get_string('yes'))));
}
