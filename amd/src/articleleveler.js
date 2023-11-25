
require(['jquery', 'core/log','mod_ogte/utils'], function($, log, utils) {
    "use strict"; // jshint ;_;
    /*
    This file combines with the articleleveler.mustache template to create the article leveler
     */

    log.debug('OGTE Article: initialising');

    var hiddenTextBox = $("input[name='text']");
    var hiddenListIdBox = $("input[name='listid']");
    var hiddenLevelIdBox = $("input[name='levelid']");
    var hiddenIgnoresBox = $("input[name='ignores']");
    var hiddenTitleBox = $("input[name='title']");
    var hiddenJSONRatingBox = $("input[name='jsonrating']");
    var passagebox= $('#{{uniqid}}_al_passage');
    var levelstats= $('#{{uniqid}}_al_levelstats');
    var articlestats= $('#{{uniqid}}_al_articlestats');
    var themessage= $('#{{uniqid}}_al_message');
    var thebutton= $('#{{uniqid}}_al_button');
    var listselect= $('#{{uniqid}}_listselect');
    var levelselect= $('#{{uniqid}}_levelselect');
    var addtoIgnoreButton = $('#{{uniqid}}_addtoignore');
    var ignorelist = $('#{{uniqid}}_ignorelist');
    var statusmessage =$('#{{uniqid}}_al_status_message');

    return {


        //Update Stats and Analysis
        updateAllFromJSONRating: function (jsonrating) {
            themessage.text('');
            passagebox.html(jsonrating.passage);

            //add stats to original
            var articleStatsTable = utils.analyzeText(jsonrating.passage);
            articlestats.html(articleStatsTable);
            articlestats.show();

            var levelStatsTable = utils.levelStats(jsonrating);
            levelstats.html(levelStatsTable);
            levelstats.show();
        },

        //is the string JSON?
        isJSON:  function (str) {
            try {
                JSON.parse(str);
                return true;
            } catch (e) {
                return false;
            }
        },

        //Get selected text in the editable div
        getSelectedText:  function () {
            var selectedText = "";
            if (window.getSelection) {
                selectedText = window.getSelection().toString();
            } else if (document.selection && document.selection.type !== "Control") {
                selectedText = document.selection.createRange().text;
            }
            return selectedText;
        },

        // Function to update the options in the second dropdown based on the selection in the first dropdown
        updateLevelDropdown: function () {
            const selectedCategory = $('#firstDropdown').val();
            const secondDropdown = $('#secondDropdown');

            // Clear existing options
            secondDropdown.empty();

            // Populate options based on the selected category
            options[selectedCategory].forEach(function (option) {
                secondDropdown.append($('<option>').text(option));
            });
        },

        //Register Event Handlers
        registerEvents: function () {
            addtoIgnoreButton.on('click', function (e) {
                var word = utils.getSelectedWord();
                if (word !== '') {
                    var newignorelist = ignorelist.val() + ' ' + word;
                    ignorelist.val(newignorelist);
                    hiddenIgnoresBox.val(newignorelist);
                    statusmessage.text("{{#str}}already-ignored, mod_ogte{{/str}}" + word);
                    addtoIgnoreButton.hide();
                }
            });

            //prevent the editable div from creating more divs on copy and paste
            passagebox.on('paste', function (e) {
                e.preventDefault();
                var text = (e.originalEvent || e).clipboardData.getData('text/plain');
                // Insert plain text without additional divs
                $(this).text(text);
                //also update our hidden text box
                hiddenTextBox.val($(this).text());
            });

            //Add the text to the hidden text box used to submit the form when text is edited
            passagebox.on('input', function (e) {
                var thetext = $(this)[0].innerText;
                hiddenTextBox.val(thetext);
            });

            passagebox.on('mouseup', function (e) {
                var selectedText = getSelectedText();
                if (selectedText === '') {
                    statusmessage.text("{{#str}}select-to-ignore, mod_ogte{{/str}}");
                    addtoIgnoreButton.hide();
                    return;
                } else {
                    var word = selectedText.trim();
                    //we only single words
                    if (word.includes(" ")) {
                        statusmessage.text("{{#str}}select-to-ignore, mod_ogte{{/str}}");
                        addtoIgnoreButton.hide();
                        return;
                    }
                    //we only want words that we did not ignore yet
                    var ignores = ignorelist.val();
                    if (ignores.toLowerCase().includes(word.toLowerCase())) {
                        statusmessage.text("{{#str}}already-ignored, mod_ogte{{/str}}" + word);
                        addtoIgnoreButton.hide();
                        return;
                    }

                    //if we get here its ignorable
                    statusmessage.text("");
                    addtoIgnoreButton.text("{{#str}}do-ignore, mod_ogte{{/str}}" + word);
                    addtoIgnoreButton.data("ignore", word);
                    addtoIgnoreButton.show();
                }
            });

            //Add the ignores list to the hidden text box used to submit the form when text is edited
            ignorelist.on('change', function (e) {
                hiddenIgnoresBox.val($(this).val());
            });

            //Add the ignores list to the hidden text box used to submit the form when text is edited
            levelselect.on('change', function (e) {
                var listleveldata = $(this).val();
                var listid = listleveldata.split('_')[0];
                var levelid = listleveldata.split('_')[1];
                hiddenListIdBox.val(listid);
                hiddenLevelIdBox.val(levelid);
            });


            thebutton.on('click', function () {
                //show a spinner
                themessage.html('<i class="fa fa-spinner fa-spin fa-sm"></i>');

                //get text and clean it up
                //TO DO there will be more cleaning to do.
                var thepassage = passagebox[0].innerText;

                //no super long readings or empty ones
                if (!thepassage || thepassage.trim() === '') {
                    themessage.text('{{#str}}enter-something, mod_ogte{{/str}}');
                    return;
                }
                if (thepassage.length > 5000) {
                    themessage.text('{{#str}}text-too-long-5000, mod_ogte{{/str}}');
                    return;
                }
                var language = 'en-US';

                var ignore = ignorelist.val();
                var listleveldata = levelselect.val();
                var listid = listleveldata.split('_')[0];
                var listlevel = listleveldata.split('_')[1];
                var ogteid=1;

                utils.levelPassage(thepassage, ignore, listid, listlevel, ogteid).then(function (ajaxresult) {
                    var theresponse = JSON.parse(ajaxresult);
                    if (theresponse) {
                        hiddenJSONRatingBox.val(ajaxresult);
                        this.updateAllFromJSONRating(theresponse);
                    } else {
                        log.debug('ajax call to level coverage failed');
                    }
                });//end of level passage
            });//end of click

        },

        //initialize
       init: function () {
            this.registerEvents();
            //JSON rating
            var jsonrating_string = hiddenJSONRatingBox.val();
            if (this.isJSON(jsonrating_string)) {
                var jsonrating = JSON.parse(jsonrating_string);
                if (jsonrating.hasOwnProperty('levelstats')) {
                    updateAllFromJSONRating(jsonrating);
                }
            }
            //List and Level ID
            var listid = hiddenListIdBox.val();
            var levelid = hiddenLevelIdBox.val();
            if (listid !== '' && levelid !== '') {
                listselect.val(listid);
                levelselect.val(listid + '_' + levelid);
            }
            //Ignores
            var ignores = hiddenIgnoresBox.val();
            if (ignores !== '') {
                ignorelist.val(ignores);
            }
        },

    }

});