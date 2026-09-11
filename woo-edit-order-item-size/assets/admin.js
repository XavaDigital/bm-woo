/* Edit Order Item Size — order screen modal */
(function ($) {
    'use strict';

    var data = window.weoisData || { nonce: '', i18n: {} };

    function initProductSearch(element) {
        var $el = $(element);
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        $el.select2({
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { action: 'search_products', nonce: data.nonce, term: params.term };
                },
                processResults: function (results) {
                    return { results: results };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: data.i18n.search || 'Search for a product…',
            dropdownParent: $el.closest('.edit-variation-container')
        });
    }

    function overlay(id, inner) {
        return '<div id="' + id + '" class="weois-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:159999;display:flex;align-items:center;justify-content:center;overflow-y:auto;">' + inner + '</div>';
    }

    function closeModal(itemId) {
        var $modal = $('#edit-item-modal-' + itemId);
        $modal.find('.product-selector').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });
        $modal.remove();
    }

    // Open
    $(document).on('click', '.edit-item-variation', function (e) {
        e.preventDefault();
        var itemId = $(this).data('item-id');
        if ($('#edit-item-modal-' + itemId).length) {
            $('#edit-item-modal-' + itemId).show();
            return;
        }

        $('body').append(overlay('edit-item-loading',
            '<div style="background:#fff;padding:30px;border-radius:5px;text-align:center;"><span class="spinner is-active" style="float:none;margin:0 auto 10px;"></span><p>' + (data.i18n.loading || 'Loading…') + '</p></div>'));

        $.post(ajaxurl, { action: 'get_edit_form', item_id: itemId, nonce: data.nonce })
            .done(function (response) {
                $('#edit-item-loading').remove();
                if (!response.success) {
                    window.alert(response.data && response.data.message ? response.data.message : (data.i18n.error || 'Error'));
                    return;
                }
                $('body').append(overlay('edit-item-modal-' + itemId,
                    '<div style="background:#fff;padding:30px;border-radius:5px;max-width:800px;width:90%;max-height:90vh;overflow-y:auto;position:relative;margin:20px;">' +
                        '<button type="button" class="close-edit-modal" data-item-id="' + itemId + '" style="position:absolute;top:15px;right:15px;background:none;border:none;font-size:24px;cursor:pointer;color:#666;" title="Close">&times;</button>' +
                        '<h2 style="margin-top:0;padding-right:30px;">' + (data.i18n.title || 'Edit Product') + '</h2>' +
                        '<div class="edit-variation-container" data-item-id="' + itemId + '">' + response.data.html + '</div>' +
                    '</div>'));
                $('#edit-item-modal-' + itemId + ' .product-selector').each(function () {
                    initProductSearch(this);
                });
            })
            .fail(function () {
                $('#edit-item-loading').remove();
                window.alert(data.i18n.error || 'Error');
            });
    });

    // Close / cancel / click outside
    $(document).on('click', '.close-edit-modal, .cancel-variation-edit', function (e) {
        e.preventDefault();
        closeModal($(this).data('item-id'));
    });
    $(document).on('click', '.weois-modal', function (e) {
        if (e.target === this && this.id !== 'edit-item-loading') {
            closeModal(this.id.replace('edit-item-modal-', ''));
        }
    });

    // Product switched: reload attribute dropdowns for the chosen product
    $(document).on('change', '.product-selector', function () {
        var productId = $(this).val();
        var itemId = $(this).data('item-id');
        var $container = $(this).closest('.edit-variation-container').find('.variation-attributes-container');
        if (!productId) {
            return;
        }
        $container.html('<p>' + (data.i18n.loading || 'Loading…') + '</p>');
        $.post(ajaxurl, { action: 'load_product_variations', nonce: data.nonce, product_id: productId, item_id: itemId })
            .done(function (response) {
                if (response.success) {
                    $container.html(response.data.html);
                } else {
                    $container.html('<p style="color:#d63638;">' + (response.data && response.data.message ? response.data.message : (data.i18n.error || 'Error')) + '</p>');
                }
            })
            .fail(function () {
                $container.html('<p style="color:#d63638;">' + (data.i18n.error || 'Error') + '</p>');
            });
    });

    // Save
    $(document).on('click', '.save-variation-changes', function (e) {
        e.preventDefault();
        var $button = $(this);
        var itemId = $button.data('item-id');
        var $container = $button.closest('.edit-variation-container');
        var $spinner = $container.find('.spinner');
        var $message = $container.find('.save-message');

        var attributes = {};
        $container.find('.variation-attribute').each(function () {
            attributes[$(this).data('attribute')] = $(this).val();
        });

        var payload = {
            action: 'save_order_item_variation',
            nonce: data.nonce,
            item_id: itemId,
            product_id: $container.find('.product-selector').val() || 0,
            attributes: attributes
        };

        if ($container.find('.wgnn-field').length) {
            payload.wgnn_fields = {};
            $container.find('.wgnn-field').each(function () {
                payload.wgnn_fields[$(this).data('key')] = $(this).val();
            });
        }

        if ($container.find('.tm-epo-field').length) {
            payload.tm_epo_fields = [];
            $container.find('.tm-epo-field').each(function () {
                payload.tm_epo_fields.push({ index: $(this).data('field-index'), value: $(this).val() });
            });
        }

        $spinner.addClass('is-active');
        $message.hide();
        $button.prop('disabled', true);

        $.post(ajaxurl, payload)
            .done(function (response) {
                $spinner.removeClass('is-active');
                if (response.success) {
                    $message.text('✓ ' + response.data.message + ' ' + (data.i18n.reloading || 'Reloading…')).show();
                    window.setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    $button.prop('disabled', false);
                    window.alert(response.data && response.data.message ? response.data.message : (data.i18n.error || 'Error'));
                }
            })
            .fail(function () {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
                window.alert(data.i18n.error || 'Error');
            });
    });
})(jQuery);
