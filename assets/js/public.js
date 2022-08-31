window.Ultimate_Member_Custom_Messages = window.Ultimate_Member_Custom_Messages || {};
(function (window, document, $, umcm, undefined) {
    'use strict';
    var $document;

    var form = jQuery('.um-form form');
    var submit = jQuery('.um-form form #um-submit-btn');

    umcm.init = function () {
        $document = $(document);

        jQuery('.um-form form *').filter(':input').each(function () {
            //your code here
            var $this = jQuery(this);
            var key = $this.data('key');
            switch (key) {
                case 'user_email':
                    $this.attr("type", "email");
                    break;
            }

            if ($this.hasClass("um-required")) {
                $this.attr("required", "required");
            }

        });

        form.on('submit', (e) => {
            e.preventDefault();
            form.validate({
                submitHandler: function (form) {
                    submit.prop('disabled', true);
                    form.submit();
                }
            });
            submit.prop('disabled', false);
        });
        umcm.trigger('umcm_init');
    }

    umcm.trigger = function (evtName) {
        var args = Array.prototype.slice.call(arguments, 1);
        args.push(umcm);
        $document.trigger(evtName, args);
    };
    $(umcm.init);
})(window, document, jQuery, window.Ultimate_Member_Custom_Messages);