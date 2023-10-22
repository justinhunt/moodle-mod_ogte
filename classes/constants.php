<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/16
 * Time: 19:31
 */

namespace mod_ogte;

defined('MOODLE_INTERNAL') || die();

class constants
{
//component name, db tables, things that define app
const M_COMPONENT='mod_ogte';
const M_TABLE='ogte';
const M_MODNAME='ogte';
const M_URL='/mod/ogte';
const M_PATH='/mod/ogte';
const M_CLASS='mod_ogte';
const M_ENTRIESTABLE='ogte_entries';
const M_LISTSTABLE="ogte_list";
const M_WORDSTABLE="ogte_listwords";
const M_PLUGINSETTINGS ='/admin/settings.php?section=modsettingogte';
const M_CLASS_LISTSCONTAINER='mod_ogte_listscontainer';
const M_ID_LISTSTABLE='mod_ogte_liststable_opts_9999';
const M_LISTSTATUS_EMPTY=0;
const M_LISTSTATUS_READY=1;

}