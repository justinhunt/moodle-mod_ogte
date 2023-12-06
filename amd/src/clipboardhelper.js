/* jshint ignore:start */
define(['jquery','core/log','mod_ogte/clipboard'], function($, log, clipboard) {

    "use strict";
    log.debug('clipboard helper: initialising');

    return {

        //pass in config, and register any events
        init: function(props){
            this.registerevents();
        },

        registerevents: function() {
            var that = this;
            var cj = new clipboard('.ogte_clipboardbutton',
                {text: function(trigger) {
                    return  $(trigger.getAttribute('data-clipboard-target')).text();
                }
                }
            );

            cj.on('success', function (e) {

                var copied = $(e.trigger).parent().parent().find('.ogte_copied');
                copied.show();
                e.clearSelection();
            });


        }//end of reg events
    };//end of returned object
});//total end

