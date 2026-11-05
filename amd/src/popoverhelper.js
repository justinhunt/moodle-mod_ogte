define(['jquery', 'core/log','core/templates', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js'], 
    function ($, log,templates,fancybox,) {
    "use strict"; // jshint ;_;

    /*
    This file is to manage the transcript popover
     */

    log.debug('OGTE Popover helper: initialising');

    var ph = {

        popuptitle: 'Actions: Ignore',//M.util.get_string('popoveractions','mod_ogte','Ignore'), //need to set this up to work in renderer
        currentword: '',

        init: function () {
            this.register_events();
        },

        register_events: function () {

            var that = this;
            //when the ignore switch is toggled, call doIgnore
            $(document).on('change','#mod_ogte_actionignoreswitch',function(e) {
                var word = $(this).attr('data-theword');
                var ignore = $(this).is(':checked');
                that.onIgnore(word, ignore);
            });

        },

        doPopup: function (item,itemword, ignoring) {
            var that= this;
        

            //let's add the popover
            var tdata={};
            tdata.theword=itemword;
            tdata.ignoring=false;
            tdata.ignoring=ignoring;
            log.debug('OGTE Popover helper: doPopup' + itemword + ' ignoring=' + ignoring);
            templates.render('mod_ogte/popoveractionform', tdata).done(function(html, js) {
                new fancybox.Fancybox(
                    [
                      {
                        src: html,
                        type: "html",
                      },
                    ],
                    {
                      // Your custom options
                    }
                  );
            });

        },

        //this function is overridden by the calling class
        //word = word, ignore = true/false
        onIgnore: function(word,ignore){console.log('onIgnore');},

    };//end of thehelper declaration
    return ph;
});