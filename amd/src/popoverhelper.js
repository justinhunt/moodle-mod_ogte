define(['jquery', 'core/log','core/templates', 'mod_ogte/dependencyloader', 'theme_boost/popover'], function ($, log,templates,dloader,popover,) {
    "use strict"; // jshint ;_;

    /*
    This file is to manage the transcript popover
     */

    log.debug('OGTE Popover helper: initialising');

    var ph = {

        lastitem: false,
        dispose: false, //Bv4 = dispose  Bv3 = destroy
        popuptitle: 'Actions: Ignore',//M.util.get_string('popoveractions','mod_ogte','Ignore'), //need to set this up to work in renderer
        currentword: '',
        showing: false,

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
            $(document).on('click', function(e) {
                    if (that.lastitem && !$(e.target).closest('.popover').length && !$(e.target).closest(that.lastitem).length && that.showing) {
                        $(that.lastitem).popover(that.disposeWord());
                        that.showing = false;
                    }
            });
        },

        //different bootstrap/popover versions have a different word for "dispose" so this method bridges that.
        //we can not be sure what version is installed
        disposeWord: function () {
            if (this.dispose) {
                return this.dispose;
            }
            var version = '3';
            if ($.fn.popover.Constructor.hasOwnProperty('VERSION')) {
                version = $.fn.popover.Constructor.VERSION.charAt(0);
            }
            switch (version) {
                case '4':
                    this.dispose = 'dispose';
                    break;
                case '3':
                default:
                    this.dispose = 'destroy';
                    break;
            }
            return this.dispose;
        },

        remove: function (item) {
            if (item) {
                $(item).popover(this.disposeWord());
            } else if (this.lastitem) {
                $(this.lastitem).popover(this.disposeWord());
                this.lastitem = false;
            }
        },

        isShowing: function(item){
            if(this.lastitem === item) {
                return true;
            }else{
                return false;
            }
        },

        doPopup: function (item,itemword, ignoring) {
            var that= this;
            
            //if we are already showing this item then dispose of it, set last item to null and go home
            if (this.lastitem === item) {
                $(this.lastitem).popover(this.disposeWord());
                this.showing = false;
                this.lastitem = false;
                return;
            }

            //dispose of previous popover, and remember this one
            if (this.lastitem) {
                $(this.lastitem).popover(this.disposeWord());
                this.showing = false;
                this.lastitem = false;
            }
            this.lastitem = item;

            //let's add the popover
            var tdata={};
            tdata.theword=itemword;
            tdata.ignoring=false;
            tdata.ignoring=ignoring;
            log.debug('OGTE Popover helper: doPopup' + itemword + ' ignoring=' + ignoring);
            templates.render('mod_ogte/popoveractionform', tdata).done(function(html, js) {
                $(item).popover({
                    title: that.popuptitle,
                    content: function(){return html;},
                    trigger: 'manual',
                    placement: 'top',
                    html: true,
                    sanitize: false
                });
                $(item).popover('show');
                that.showing = true;
            });

        },

        //this function is overridden by the calling class
        //word = word, ignore = true/false
        onIgnore: function(word,ignore){console.log('onIgnore');},

    };//end of thehelper declaration
    return ph;
});