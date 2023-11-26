
define(['jquery', 'core/log','core/str','mod_ogte/utils'], function($, log,str, utils) {
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
    var passagebox= $('#the_al_passage');
    var levelstats= $('#the_al_levelstats');
    var articlestats= $('#the_al_articlestats');
    var themessage= $('#the_al_message');
    var thebutton= $('#the_al_button');
    var listselect= $('#the_listselect');
    var levelselect= $('#the_levelselect');
    var addtoIgnoreButton = $('#the_addtoignore');
    var ignorelist = $('#the_ignorelist');
    var statusmessage =$('#the_al_status_message');

    var app= {

        strings:[],
        opts: {},

        //initialize
        init: function (props) {
            log.debug('initializing article leveler');

            //pick up opts from html
            var theid = '#' + props.optsid;
            var configcontrol = $(theid).get(0);
            if (configcontrol) {
                this.opts = JSON.parse(configcontrol.value);
                $(theid).remove();
            } else {
                //if there is no config we might as well give up
                log.debug('No config found on page. Giving up.');
                return;
            }

            this.init_strings();
            this.registerEvents();

            //JSON rating
            var jsonrating_string = hiddenJSONRatingBox.val();
            log.debug('is json?');
            if (this.isJSON(jsonrating_string)) {
                log.debug('yes json');
                var jsonrating = JSON.parse(jsonrating_string);
                log.debug('is level stats');
                if (jsonrating.hasOwnProperty('passage')) {
                    log.debug('yes level stats');
                    this.updateAllFromJSONRating(jsonrating);
                }
            }
            //List and Level ID
            var listid = hiddenListIdBox.val();
            var levelid = hiddenLevelIdBox.val();
            if (listid !== '' && levelid !== '') {
                listselect.val(listid);
                levelselect.val(levelid);
            }
            //Ignores
            var ignores = hiddenIgnoresBox.val();
            if (ignores !== '') {
                ignorelist.val(ignores);
            }
        },


        //init strings
        init_strings: function(){
            var that =this;
            var strs=['already-ignored','select-to-ignore','do-ignore', 'enter-something','text-too-long-5000'];
            for (var key in strs) {
                var thestring = strs[key];
                str.get_string(thestring,'mod_ogte').done(function(s){that.strings[thestring]=s;});
            }
        },

        //Update Stats and Analysis
        updateAllFromJSONRating: function (jsonrating) {
            log.debug('updateAllFromJSONRating');
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
            const selectedList = listselect.val();
            log.debug('selected list: ' + selectedList);
            // Clear existing options
            levelselect.empty();

            // Populate options based on the selected category
            app.opts.listlevels[selectedList].forEach(function (option) {
                levelselect.append($('<option value="' +option.key+'">' + option.label+ '</option>'));
            });
            levelselect.prop('selectedIndex', 0);
            hiddenListIdBox.val(selectedList);
            hiddenLevelIdBox.val(levelselect.val());
        },

        //Register Event Handlers
        registerEvents: function () {
            var that=this;
            addtoIgnoreButton.on('click', function (e) {
                var word = utils.getSelectedWord();
                if (word !== '') {
                    var newignorelist = ignorelist.val() + ' ' + word;
                    ignorelist.val(newignorelist);
                    hiddenIgnoresBox.val(newignorelist);
                    statusmessage.text(app.strings["already-ignored"] + word);
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
                    statusmessage.text(app.strings["select-to-ignore"]);
                    addtoIgnoreButton.hide();
                    return;
                } else {
                    var word = selectedText.trim();
                    //we only single words
                    if (word.includes(" ")) {
                        statusmessage.text(app.strings["select-to-ignore"]);
                        addtoIgnoreButton.hide();
                        return;
                    }
                    //we only want words that we did not ignore yet
                    var ignores = ignorelist.val();
                    if (ignores.toLowerCase().includes(word.toLowerCase())) {
                        statusmessage.text(app.strings["already-ignored"] + word);
                        addtoIgnoreButton.hide();
                        return;
                    }

                    //if we get here its ignorable
                    statusmessage.text("");
                    addtoIgnoreButton.text(app.strings["do-ignore"] + word);
                    addtoIgnoreButton.data("ignore", word);
                    addtoIgnoreButton.show();
                }
            });

            //Add the ignores list to the hidden text box used to submit the form when text is edited
            ignorelist.on('change', function (e) {
                hiddenIgnoresBox.val($(this).val());
            });

            listselect.on('change', function (e) {
                var listid =  $(this).val();
                hiddenListIdBox.val(listid);
                that.updateLevelDropdown();
            });

            //Level select on change
            levelselect.on('change', function (e) {
                var levelid =  $(this).val();
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
                    themessage.text(app.strings['enter-something']);
                    return;
                }
                if (thepassage.length > 5000) {
                    themessage.text(app.strings['text-too-long-5000']);
                    return;
                }
                var language = 'en-US';

                var ignore = ignorelist.val();
                var listid = listselect.val();
                var listlevel = levelselect.val();
                var ogteid=app.opts.ogteid;

                utils.levelPassage(thepassage, ignore, listid, listlevel, ogteid).then(function (ajaxresult) {
                    var theresponse = JSON.parse(ajaxresult);
                    if (theresponse) {
                        hiddenJSONRatingBox.val(ajaxresult);
                        that.updateAllFromJSONRating(theresponse);
                    } else {
                        log.debug('ajax call to level coverage failed');
                    }
                });//end of level passage
            });//end of click

        },

    };
    return app;

});