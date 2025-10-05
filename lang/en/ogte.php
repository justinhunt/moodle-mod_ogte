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
 * Language strings
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

$string['eventogtecreated'] = 'OGTE created';
$string['eventogteviewed'] = 'OGTE viewed';
$string['evententriesviewed'] = 'OGTE entries viewed';
$string['eventogtedeleted'] = 'OGTE deleted';
$string['evententryupdated'] = 'OGTE entry updated';
$string['evententrycreated'] = 'OGTE entry created';
$string['eventfeedbackupdated'] = 'OGTE feedback updated';

$string['section'] = 'Section';
$string['title'] = 'Title';
$string['question'] = 'Question';
$string['answer'] = 'Answer';
$string['name'] = 'Name: ';

$string['submitted'] = 'Submitted on';
$string['accessdenied'] = 'Access denied';
$string['alwaysopen'] = 'Always open';
$string['blankentry'] = 'Blank entry';
$string['preventry'] = 'Previous entry';
$string['downloadmode'] = 'Download';
$string['viewmode'] = 'OGTE';
$string['mode'] = 'Mode';
$string['downloadmesage'] = 'Thank you for completing the module. You will be able to download a copy of all your reflections in a PDF format from the button below.';
$string['preventry'] = 'Previous entry';
$string['daysavailable'] = 'Days available';
$string['deadline'] = 'Days Open';
$string['editingended'] = 'Editing period has ended';
$string['editingends'] = 'Editing period ends';
$string['entries'] = 'Entries';
$string['entry'] = 'Entry';
$string['feedbackupdated'] = 'Feedback updated for {$a} entries';
$string['gradeingradebook'] = 'Current grade in gradebook';
$string['ogte:addentries'] = 'Add ogte entries';
$string['ogte:addinstance'] = 'Add a new ogte';
$string['ogte:manageentries'] = 'Manage ogte entries';
$string['ogte:use'] = 'Use ogte';
$string['ogte:manage'] = 'Manage ogte';
$string['ogtename'] = 'OGTE name';
$string['ogtequestion'] = 'OGTE question';
$string['mailsubject'] = 'OGTE feedback';
$string['modulename'] = 'OGTE';
$string['modulename_help'] = 'The Online Graded Text Editor allows teachers to author and assess passages of text designed for use as reading activities with students. Features include:
    
* AI Text passage generation
    
* AI Passage Simplification
    
* Passage difficulty evaluation (NGSL etc)    ';
$string['modulename_link'] = 'mod/ogte/view';
$string['modulenameplural'] = 'OGTEs';
$string['needsregrade'] = 'Entry has changed since last feedback was saved.';
$string['newogteentries'] = 'New ogte entries';
$string['nodeadline'] = 'Always open';
$string['noentriesmanagers'] = 'There are no teachers';
$string['noentry'] = 'No entry';
$string['noratinggiven'] = 'No rating given';
$string['notopenuntil'] = 'This ogte won\'t be open until';
$string['notstarted'] = 'You have not started this ogte yet';
$string['overallrating'] = 'Overall rating';
$string['pluginadministration'] = 'OGTE module administration';
$string['pluginname'] = 'OGTE';
$string['rate'] = 'Rate';
$string['removeentries'] = 'Remove all entries';
$string['removemessages'] = 'Remove all OGTE entries';
$string['saveallfeedback'] = 'Save all my feedback';
$string['search:activity'] = 'OGTE - activity information';
$string['search:entry'] = 'OGTE - entries';
$string['showrecentactivity'] = 'Show recent activity';
$string['showoverview'] = 'Show ogtes overview on my moodle';
$string['startoredit'] = 'Edit';
$string['download'] = 'Download';
$string['viewallentries'] = 'View {$a} ogte entries';
$string['viewentries'] = 'View entries';
$string['completionanswergroup'] = 'Require answers';
$string['completionanswer'] = 'Student must answer this activity at least once';
$string['privacy:metadata:ogte_entries'] = 'A record of ogte entry';
$string['privacy:metadata:ogte_entries:userid'] = 'The ID of the user';
$string['privacy:metadata:ogte_entries:modified'] = 'The start time of the ogte entries.';
$string['privacy:metadata:ogte_entries:text'] = 'The text written by user';
$string['privacy:metadata:ogte_entries:rating'] = 'The rating received by user to journl';
$string['privacy:metadata:ogte_entries:entrycomment'] = 'The comment received by user to ogte';

$string['correct-grammar'] = 'Correct Grammar';
$string['corrected-passage'] = 'Corrected Passage';
$string['generate-article'] = 'Generate article';
$string['rewrite-article'] = 'Rewrite article';
$string['rewritten-article'] = 'Rewritten article';
$string['original-article'] = 'Original article';
$string['original-passage'] = 'Original passage';
$string['passage-language'] = 'Passage language';
$string['article-topic-here'] = 'Article topic here';
$string['paste-text-here'] = 'Paste text here';
$string['entersomething'] = 'Please enter something in the text area.';
$string['texttoolong5000'] = 'The text is too long. Passages must be 5000 characters are shorter.';
$string['texttoolong50000'] = 'The text is too long. Passages must be 50000 characters are shorter.';
$string['text-too-long-200'] = 'The prompt is too long. Prompts must be 200 characters are shorter.';
$string['articlegenerator'] = 'Article Generator';
$string['articlerewriter'] = 'Article Rewriter';
$string['articlechecker'] = 'Article Checker';

$string['displaysubs'] = '{$a->subscriptionname} : expires {$a->expiredate}';
$string['noapiuser'] = "No API user entered. OGTE will not work correctly.";
$string['noapisecret'] = "No API secret entered. OGTE will not work correctly.";
$string['credentialsinvalid'] = "The API user and secret entered could not be used to get access. Please check them.";
$string['appauthorised']= "Poodll OGTE is authorised for this site.";
$string['appnotauthorised']= "Poodll OGTE is NOT authorised for this site.";
$string['refreshtoken']= "Refresh license information";
$string['notokenincache']= "Refresh to see license information. Contact Poodll support if there is a problem.";
//these errors are displayed on activity page
$string['nocredentials'] = 'API user and secret not entered. Please enter them on <a href="{$a}">the settings page.</a> You can get them from <a href="https://poodll.com/member">Poodll.com.</a>';
$string['novalidcredentials'] = 'API user and secret were rejected and could not gain access. Please check them on <a href="{$a}">the settings page.</a> You can get them from <a href="https://poodll.com/member">Poodll.com.</a>';
$string['nosubscriptions'] = "There is no current subscription for this site/plugin.";
$string['apiuser']='Poodll API User ';
$string['apiuser_details']='The Poodll account username that authorises Poodll on this site.';
$string['apisecret']='Poodll API Secret ';
$string['apisecret_details']='The Poodll API secret. See <a href= "https://support.poodll.com/support/solutions/articles/19000083076-cloud-poodll-api-secret">here</a> for more details';

$string['freetrial'] = "Get Cloud Poodll API Credentials and a Free Trial";
$string['freetrial_desc'] = "A dialog should appear that allows you to register for a free trial with Poodll. After registering you should login to the members dashboard to get your API user and secret. And to register your site URL.";
$string['fillcredentials']="Set API user and secret with existing credentials";

$string['nolists']="No Lists!!";
$string['managelists']="Manage lists";
$string['listinstructions']="Add or edit lists below";
$string['editlist']="Edit";
$string['deletelist']="Delete";
$string['list']="List";
$string['listname']="Name";
$string['listprops']="Props";
$string['listdescription']="Description";
$string['liststatus']="Status";
$string['timecreated']="Created";
$string['actions']="Actions";
$string['liststatusempty']="Empty";
$string['liststatusready']="Ready";
$string['editinglist']="";
$string['listformtitle']="";
$string['lists']="Lists";
$string['savelist']="Save List";
$string['addlist']="Add WordList";
$string['importlist']="Import Words";
$string['importresults']="Import Results";
$string['importinstructions']="Prepare the data and import";
$string['import']="Import";
$string['importing']="Importing";
$string['importlistfromfile']='Importing {$a} from File';
$string['importitemsresult']='Import Results';
$string['clearwordslist']='Clear Words';
$string['confirmclearwords']="Are you sure you want to delete ALL the words from this list?";
$string['confirmclearwordstitle']="Clearing Words from List";
$string['listprops']="Props";
$string['articleleveler']="Article Leveler";
$string['level-article']="Level Passage";
$string['go']="GO";
$string['article-text-here']="Passage text goes here";

$string['csvdelimiter']="CSV Delimiter";
$string['examplecsv']="Example CSV";
$string['backtolists']='Back to Word Lists';
$string['addeditlists']='Add / Edit Word Lists';

$string['confirmlistdelete']="Are you sure you want to delete this list?";
$string['confirmlistdeletetitle']="Deleting Word List";
$string['lang']="Language";
$string['listlang']="Lang";
$string['listlevel']="List / Level";
$string['listheadwords']="Words(head)";
$string['listallwords']="Words(all)";
$string['error:failed']="Error - failed";
$string['error:toofewcols']="Error - too few cols";
$string['error:list']="Error - list rank";
$string['addtoignore']="Add to Ignore List";
$string['selecttoignore']="Select words to ignore ..";
$string['ignorelist']="Words to Ignore";
$string['spacelistofwords']="Add a space separated list of words";
$string['spacelistofwords']="Add a space separated list of words";
$string['select-list-level-then-go']="Select word list and level, then press GO";
$string['viewentry']="View";
$string['deleteentry']="delete";
$string['downloadentrypdf']="Download as PDF";
$string['downloadentrytxt']="Download as TXT";
$string['noentries']="There are no passages. Please add a new one.";
$string['addnew']="Add New";
$string['author']="Author";

$string['title']="Title";
$string['actions']="Actions";
$string['untitled']="Untitled";
$string['excerpt']="Excerpt";
$string['confirmentrydelete'] = 'Are you sure you want to <i>DELETE</i> entry? : {$a}';
$string['confirmentrydeletetitle'] = 'Really delete entry?';
$string['alreadyignored']='Ignoring: ';
$string['ignoring']='Ignoring';
$string['doignore']='Ignore: ';
$string['totalwords']="Total Words";
$string['total']="Total";
$string['level']="Level";
$string['frequency']="Frequency";
$string['inlevel']="In Level";
$string['outoflevel']="Out of Level";
$string['outoflevelfreq']="% Out of Level";
$string['outoflist']="Out of List";
$string['ignored']="Ignored";
$string['coverage']="Coverage";
$string['characters']="Characters";
$string['words']="Words";
$string['word']="Word";
$string['no_words']="No words";
$string['copied']="Copied";
$string['no_out_of_levels']="No words out of Level";
$string['averagewordlength']="Av. Word Length";
$string['sentences']="Sentences";
$string['averagesentencelength']="Av. Sentence Length";
$string['entryforminstructions']="Enter a title, and click the 'Save' button to save your changes. Click the 'Cancel' button to return to the list of passages.";
$string['downloadallpdf']="Download All (PDF)";
$string['downloadalltxt']="Download All (TXT)";
$string['articleleveler_instructions'] ="Enter a passage of text in the box below, select the correct wordlist and level of the wordlist to check against and press the go button.";
$string['articlegenerator_instructions'] ="Enter a non-fiction topic in the box below. e.g 'ladybirds' or 'The pros and cons of alcohol.' Then press the 'Generate Article' button. The AI helper will generate a short easy English passage of text suitable for language learners. N.B The AI helper was trained only on non-fiction topics. It won't write stories. But it will make up facts and it will often not be truthful. Be sure to check and correct the resulting article if necessary.";
$string['articlerewrite_instructions'] ="Enter a passage of text in the 'Original article' box below. Then choose the level from 1 (very easy) to 5 (not easy) and press the 'Rewrite Article' button. The AI helper will rewrite the article into easy English suitable for language learners. N.B. It is trained to simplify the original passage, not make it more difficult.";
$string['articlechecker_instructions'] ="Enter a passage of text in the 'Original article' box below. Then press the 'Correct grammar' button. The AI helper will check the article and show the re-written version in a new text area.";

$string['ihavetext']="I have Text";
$string['createtextwithai']="Create Text with AI";
$string['fiction']="Fiction";
$string['nonfiction']="Non Fiction";
$string['back']="Back";
$string['send-to-editor']="Send to Article Leveler";
$string['popoveractions']='Action: {$a}';
$string['propernouns']='Is Proper Nouns List';
$string['propernouns_details']='You can only specify one list as a proper noun list per language. This list will be used to identify proper nouns in the text. Proper nouns are words that are names of people, places, or things.';
$string['wordlist']="Word List";
$string['wordlevel']="Word Level";


