/**
 * Progressive enhancement only — the confirmation works without JavaScript via inline CSS
 * and the :has() selector. This script is just a fallback for browsers that don't support
 * :has(): when the in-form checkbox becomes checked, hide the confirmation panel.
 *
 * Server-side validation remains the source of truth either way.
 */
(function ($) {
    'use strict';

    $(function () {
        var $overlay = $('#wcbd-overlay');
        if (!$overlay.length) {
            return;
        }

        function sync() {
            if ($('#wcbd_confirm').is(':checked')) {
                $overlay.hide();
            } else {
                $overlay.show();
            }
        }

        // The confirm button is a <label for="wcbd_confirm">, so the native click ticks the
        // checkbox; we just react to the resulting change to hide the panel.
        $(document).on('change', '#wcbd_confirm', sync);

        sync();
    });
})(jQuery);
