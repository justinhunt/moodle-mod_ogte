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
const M_ID_RESULTSTABLE='mod_ogte_resultstable_opts_9999';
const M_LISTSTATUS_EMPTY=0;
const M_LISTSTATUS_READY=1;

const M_LANG_ENUS = 'en-US';
const M_LANG_ENGB = 'en-GB';
const M_LANG_ENAU = 'en-AU';
const M_LANG_ENNZ = 'en-NZ';
const M_LANG_ENZA = 'en-ZA';
const M_LANG_ENIN = 'en-IN';
const M_LANG_ESUS = 'es-US';
const M_LANG_ESES = 'es-ES';
const M_LANG_FRCA = 'fr-CA';
const M_LANG_FRFR = 'fr-FR';
const M_LANG_DEDE = 'de-DE';
const M_LANG_DEAT ='de-AT';
const M_LANG_ITIT = 'it-IT';
const M_LANG_PTBR = 'pt-BR';

const M_LANG_DADK = 'da-DK';
const M_LANG_FILPH = 'fil-PH';

const M_LANG_KOKR = 'ko-KR';
const M_LANG_HIIN = 'hi-IN';
const M_LANG_ARAE ='ar-AE';
const M_LANG_ARSA ='ar-SA';
const M_LANG_ZHCN ='zh-CN';
const M_LANG_NLNL ='nl-NL';
const M_LANG_NLBE ='nl-BE';
const M_LANG_ENIE ='en-IE';
const M_LANG_ENWL ='en-WL';
const M_LANG_ENAB ='en-AB';
const M_LANG_FAIR ='fa-IR';
const M_LANG_DECH ='de-CH';
const M_LANG_HEIL ='he-IL';
const M_LANG_IDID ='id-ID';
const M_LANG_JAJP ='ja-JP';
const M_LANG_MSMY ='ms-MY';
const M_LANG_PTPT ='pt-PT';
const M_LANG_RURU ='ru-RU';
const M_LANG_TAIN ='ta-IN';
const M_LANG_TEIN ='te-IN';
const M_LANG_TRTR ='tr-TR';
const M_LANG_NONO ='no-NO';
const M_LANG_NBNO ='nb-NO';
const M_LANG_PLPL ='pl-PL';
const M_LANG_RORO ='ro-RO';
const M_LANG_SVSE ='sv-SE';

const M_LANG_UKUA ='uk-UA';
const M_LANG_EUES ='eu-ES';
const M_LANG_FIFI ='fi-FI';
const M_LANG_HUHU='hu-HU';

const M_LANG_MINZ ='mi-NZ';
const M_LANG_BGBG = 'bg-BG';
const M_LANG_CSCZ = 'cs-CZ';
const M_LANG_ELGR = 'el-GR';
const M_LANG_HRHR = 'hr-HR';
const M_LANG_LTLT = 'lt-LT';
const M_LANG_LVLV = 'lv-LV';
const M_LANG_SKSK = 'sk-SK';
const M_LANG_SLSI = 'sl-SI';
const M_LANG_ISIS = 'is-IS';
const M_LANG_MKMK = 'mk-MK';
const M_LANG_SRRS = 'sr-RS';

const M_UNITS_ARRAY = array('m', 'cm', 'mm', 'km', 'km/h', 'mph', '°C', '°F', 'K', 'N', 'g', 'kg', 'g/m²', 'L', 'ml', 'mL/min', 'gal', 'ft', 'in', 'lb', 'oz', 'Hz', 'kHz', 'MHz', 'GHz');

const M_UNITS = 'm|cm|mm|km|kmh|mph|C|F|K|N|g|kg|gm|L|ml|mLmin|gal|ft|in|lb|oz|Hz|kHz|MHz|GHz';
//because of cleaning, the units get a bit mangled, so we use the mangled ones here
////km/h => kmh and g/m² => gm2 and mL/min => mlmin and °C =>C and °F =>F

const M_APOSTROPHES = "[‘’‛´ʹʹʹʹˋˋʹ＇＇ʹ‘‘ʹ‛‛ʹ]";  # Unicode characters representing apostrophe-like marks
}