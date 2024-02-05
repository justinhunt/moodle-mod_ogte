
define(['jquery', 'core/log','core/notification','core/str','core/templates','mod_ogte/utils','mod_ogte/clipboardhelper','mod_ogte/popoverhelper'],
    function($, log,notification,str, templates, utils, clipboardhelper,popoverhelper) {
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
    var downloadButton = $('.ogte_downloadbutton');
    var sendToEditorButton = $('.ogte_ar_sendtoeditor_button')
    var ignorelist = $('#the_ignorelist');
    var statusmessage =$('#the_al_status_message');
    var outoflistwords_block =$('#the_outoflistwords');
    var outoflevelwords_block =$('#the_outoflevelwords');
    var ignoredwords_block =$('#the_ignoredwords');
    var outoflevelfreq_block =$('#the_outoflevelfreq');

    var app= {

        strings:{},
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
            clipboardhelper.init({});

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
            }else{
                var listid = listselect.val();
                this.updateLevelDropdown(listid);
            }
            //Ignores
            var ignores = hiddenIgnoresBox.val();
            if (ignores !== '') {
                ignorelist.val(ignores);
            }

            //init the popover now that we have set the correct callback event handling thingies
            popoverhelper.init();
        },


        //init strings
        init_strings: function(){
            var that =this;
            var strs=['alreadyignored','selecttoignore','doignore', 'entersomething','texttoolong5000',
                'ignored','outoflist','outoflevel','outoflevelfreq'];
            for (var key in strs) {
                ///log.debug('getting string: ' + strs[key]);
                var thestring = strs[key];
                str.get_string(thestring,'mod_ogte').done(function(s){that.strings[thestring]=s;});
            }

            //Promises are promises and jsonrating cant wait around for them, so we hack it
            that.strings.alreadyignored = "Already Ignored";
            that.strings.selecttoignore = "Select to Ignore";
            that.strings.doignore = "Ignore";
            that.strings.entersomething = "Enter something";
            that.strings.texttoolong5000 = "Text too long (5000 characters max)";
            that.strings.ignored = "Ignored";
            that.strings.outoflist = "Out of List";
            that.strings.outoflevel = "Out of Level";
            that.strings.outoflevelfreq =  "% Out of Level";

            // Set up strings
            str.get_strings([
                { "key": "alreadyignored", "component": 'mod_ogte'},
                { "key": "selecttoignore", "component": 'mod_ogte'},
                { "key": 'doignore', "component": 'mod_ogte' },
                { "key": 'entersomething', "component": 'mod_ogte'},
                { "key": 'texttoolong5000', "component": 'mod_ogte' },
                { "key": 'ignored', "component": 'mod_ogte' },
                { "key": 'outoflist', "component": 'mod_ogte'},
                { "key": 'outoflevel', "component": 'mod_ogte' },
                { "key": 'outoflevelfreq', "component": 'mod_ogte'},
                
            ]).done(function (s) {
                var i = 0;
                that.strings.alreadyignored = s[i++];
                that.strings.selecttoignore = s[i++];
                that.strings.doignore = s[i++];
                that.strings.entersomething = s[i++];
                that.strings.texttoolong5000 = s[i++];
                that.strings.ignored = s[i++];
                that.strings.outoflist = s[i++];
                that.strings.outoflevel = s[i++];
                that.strings.outoflevelfreq = s[i++];
            });
        },

        //Update Stats and Analysis
        updateAllFromJSONRating: function (jsonrating) {
            log.debug('updateAllFromJSONRating');
            themessage.text('');
            passagebox.html(jsonrating.passage);

            // Add level stats to the page
            jsonrating.listname=app.opts.listlevels[jsonrating.listid][jsonrating.levelid].listname;
            jsonrating.levelname=app.opts.listlevels[jsonrating.listid][jsonrating.levelid].label;
            templates.render('mod_ogte/levelstatstable', jsonrating).done(function(html, js) {

                // Update the page.
                levelstats.fadeOut("fast", function() {
                    templates.replaceNodeContents(levelstats, html, js);
                    levelstats.fadeIn("fast");
                }.bind(this));

            }.bind(this)).fail(notification.exception);

            //add text stats to the page
            var textStatsData = utils.analyzeText(jsonrating.passage);
            templates.render('mod_ogte/textstatstable', textStatsData).done(function(html, js) {

                    // Update the page.
                articlestats.fadeOut("fast", function() {
                        templates.replaceNodeContents(articlestats, html, js);
                    articlestats.fadeIn("fast");
                    }.bind(this));

            }.bind(this)).fail(notification.exception);

            //add more coverage stats to the page as blocks
            var ignoredAndOutOfData = utils.analyzeOutListLevelsIgnored(jsonrating.passage);

            //out of list words block
            outoflistwords_block.show();
            templates.render('mod_ogte/block_uncovered',
                {words: ignoredAndOutOfData.outoflist,haswords: ignoredAndOutOfData.outoflist.length>0, haslevels: false, title: this.strings.outoflist}).done(function(html, js) {

                // Update the page.
                outoflistwords_block.fadeOut("fast", function() {
                    templates.replaceNodeContents(outoflistwords_block, html, js);
                    outoflistwords_block.fadeIn("fast");
                }.bind(this));

            }.bind(this)).fail(notification.exception);

            //out of level words block
            outoflevelwords_block.show();
            templates.render('mod_ogte/block_uncovered',
                {words: ignoredAndOutOfData.outoflevel, haswords: ignoredAndOutOfData.outoflevel.length>0, haslevels: true, title: this.strings.outoflevel}).done(function(html, js) {

                // Update the page.
                outoflevelwords_block.fadeOut("fast", function() {
                    templates.replaceNodeContents(outoflevelwords_block, html, js);
                    outoflevelwords_block.fadeIn("fast");
                }.bind(this));

            }.bind(this)).fail(notification.exception);

            //ignored words block
            ignoredwords_block.show();
            templates.render('mod_ogte/block_uncovered',
                {words: ignoredAndOutOfData.ignored, haswords: ignoredAndOutOfData.ignored.length>0, haslevels: false,title: this.strings.ignored}).done(function(html, js) {

                // Update the page.
                ignoredwords_block.fadeOut("fast", function() {
                    templates.replaceNodeContents(ignoredwords_block, html, js);
                    ignoredwords_block.fadeIn("fast");
                }.bind(this));

            }.bind(this)).fail(notification.exception);

            //out of level frequency block
            var outOfLevelFreqData = utils.calc_outoflevel_frequencies(jsonrating.passage);
            outoflevelfreq_block.show();
            templates.render('mod_ogte/block_outoflevelfreq',
                {levels: outOfLevelFreqData,haslevels: outOfLevelFreqData.length>0, title: this.strings.outoflevelfreq}).done(function(html, js) {

                // Update the page.
                outoflevelfreq_block.fadeOut("fast", function() {
                    templates.replaceNodeContents(outoflevelfreq_block, html, js);
                    outoflevelfreq_block.fadeIn("fast");
                }.bind(this));

            }.bind(this)).fail(notification.exception);

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

        setStatusMessage: function (message) {
            statusmessage.text(message);
            setTimeout(function () {
                statusmessage.fadeOut();
            },2000);
        },

        doPopover: function (that,e) {
            if(e.target.tagName === "SPAN" || e.target.tagName === "DIV") {
                var selectedText = $(that).text();
            }else if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") {
                var selectedText = that.getSelectedText();
            }

            if (selectedText === '') {
                addtoIgnoreButton.hide();
                return;
            } else {
                var word = selectedText.trim();
                //we only single words
                if (word.includes(" ")) {
                    addtoIgnoreButton.hide();
                    return;
                }
                //we only want words that we did not ignore yet
                var ignores = ignorelist.val();
                if (ignores.toLowerCase().includes(word.toLowerCase())) {
                    app.setStatusMessage(app.strings["alreadyignored"] + word);
                    addtoIgnoreButton.hide();
                    return;
                }

                //if we get here its ignorable
                statusmessage.text("");
                //show the ignore button .. old code
                addtoIgnoreButton.text(app.strings["doignore"] + word);
                addtoIgnoreButton.data("ignore", word);
                addtoIgnoreButton.show();
                //show the popover
                if (!popoverhelper.isShowing(that)) {
                    popoverhelper.doPopup(that,selectedText );
                }else{
                    popoverhelper.doPopup(that, selectedText );
                }
            }
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
                    app.setStatusMessage(app.strings["alreadyignored"] + word);
                    addtoIgnoreButton.hide();
                }
            });

            downloadButton.on('click', function (e) {
                var target_selector = $(this).attr('data-download-target');
                var text = $(target_selector).text();

                var filename='article.txt';
                var filename_selector = $(this).attr('data-download-filename');
                if(filename_selector!=='' && filename_selector!==undefined) {
                    filename = $(filename_selector).val() + '.txt';
                    filename=filename.trim().replace(/ /g,"_");
                }
                utils.downloadTextContent(text, filename);
            });

            sendToEditorButton.on('click',function(){

                //get the article text to update
                var target_selector = $(this).attr('data-send-target');
                var text = $(target_selector).val();

                //set the text to the article leveler and level it
                passagebox.text(text);
                hiddenTextBox.val(text);
                thebutton.click();

                //switch to the article leveler tab
                $('.mod_ogte_tab-content .tab-pane').removeClass('active');
                $('.mod_ogte_tab-content #articleleveler').addClass('active');
                $('.mod_ogte_nav-pills .nav-link').removeClass('active');
                $('.mod_ogte_nav-pills a[href="#articleleveler"]').addClass('active');
                $('#articleleveler').tab('show');

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
/*
            passagebox.on('mouseup', function (e) {
                that.doPopover(this,e);
            });
*/
            passagebox.on('click','span', function (e) {
                that.doPopover(this,e);
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
                    themessage.text(app.strings['entersomething']);
                    return;
                }
                if (thepassage.length > 50000) {
                    themessage.text(app.strings['texttoolong5000']);
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