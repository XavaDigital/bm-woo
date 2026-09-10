/**
 * Garment Name & Number — front end.
 *
 * 1. Moves the Name / Number rows (rendered by PHP as a separate <table class="variations">)
 *    into the real WooCommerce variation table so they sit with the size / colour rows.
 * 2. Copies the computed styling of the variation <select> onto the text inputs so they
 *    match the theme exactly, whatever the theme is.
 */
(function () {
    'use strict';

    var COPY_PROPS = [
        'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing',
        'text-transform', 'text-align', 'color', 'background-color',
        'border-top-width', 'border-top-style', 'border-top-color',
        'border-right-width', 'border-right-style', 'border-right-color',
        'border-bottom-width', 'border-bottom-style', 'border-bottom-color',
        'border-left-width', 'border-left-style', 'border-left-color',
        'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
        'padding-top', 'padding-bottom', 'padding-left',
        'height', 'min-height', 'box-shadow', 'box-sizing', 'margin-top', 'margin-bottom'
    ];

    function isVisible(el) {
        return !!(el && el.offsetParent !== null && el.getBoundingClientRect().width > 0);
    }

    function contentWidth(el) {
        var cs = window.getComputedStyle(el);
        return el.clientWidth - parseFloat(cs.paddingLeft || 0) - parseFloat(cs.paddingRight || 0);
    }

    function mirror(select, input) {
        if (!isVisible(select)) {
            input.classList.add('wgnn-input--unmirrored');
            return;
        }
        var cs = window.getComputedStyle(select);
        COPY_PROPS.forEach(function (prop) {
            var v = cs.getPropertyValue(prop);
            if (v !== '' && v !== null) {
                input.style.setProperty(prop, v);
            }
        });
        // Selects usually reserve right padding for the arrow; a text input needs the same
        // on both sides so typed text lines up with the select's text.
        input.style.setProperty('padding-right', cs.getPropertyValue('padding-left'));
        input.style.setProperty('-webkit-appearance', 'none');
        input.style.setProperty('appearance', 'none');
        input.style.setProperty('background-image', 'none');
        input.style.setProperty('outline-offset', cs.getPropertyValue('outline-offset'));
        sizeWidth(select, input);
        input.classList.remove('wgnn-input--unmirrored');
    }

    function sizeWidth(select, input) {
        if (!isVisible(select)) {
            return;
        }
        var selW = select.getBoundingClientRect().width;
        var cell = select.closest('td');
        var cellW = cell ? contentWidth(cell) : 0;
        if (cellW && Math.abs(selW - cellW) <= 2) {
            input.style.setProperty('width', '100%');
        } else if (selW) {
            input.style.setProperty('width', selW + 'px');
        }
    }

    function merge(ours) {
        var form = ours.closest('form.variations_form') || ours.closest('form.cart');
        var target = form ? form.querySelector('table.variations:not(.wgnn-variations)') : null;
        var rows = Array.prototype.slice.call(ours.querySelectorAll('tr.wgnn-row'));
        ours.removeAttribute('data-wgnn-merge');

        if (!target || !rows.length) {
            return; // simple product (or no table): our own table stays where PHP put it
        }

        var tbody = target.tBodies[0] || target;
        var firstOriginal = tbody.firstElementChild;
        rows.forEach(function (row) {
            if (ours.getAttribute('data-wgnn-position') === 'before' && firstOriginal) {
                tbody.insertBefore(row, firstOriginal);
            } else {
                tbody.appendChild(row);
            }
        });
        ours.parentNode.removeChild(ours);

        var select = target.querySelector('td.value select');
        if (!select) {
            return;
        }
        var inputs = rows.map(function (row) { return row.querySelector('input.wgnn-input'); }).filter(Boolean);
        inputs.forEach(function (input) { mirror(select, input); });

        var timer = null;
        window.addEventListener('resize', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                inputs.forEach(function (input) { sizeWidth(select, input); });
            }, 100);
        });
        // Theme fonts may finish loading after us; re-measure once they do.
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                inputs.forEach(function (input) { mirror(select, input); });
            });
        }
    }

    function init() {
        Array.prototype.slice.call(document.querySelectorAll('table.wgnn-variations[data-wgnn-merge="1"]')).forEach(merge);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
