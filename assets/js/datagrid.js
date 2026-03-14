datagridSortable = function($el) {
    if (typeof $.fn.sortable === 'undefined') {
        return;
    }
    return $el.find('.datagrid [data-sortable]').sortable({
        handle: '.handle-sort',
        items: 'tr',
        axis: 'y',
        update: function(event, ui) {
            var component_prefix, data, item_id, next_id, prev_id, row, url;
            row = ui.item.closest('tr[data-id]');
            item_id = row.data('id');
            prev_id = null;
            next_id = null;
            if (row.prev().length) {
                prev_id = row.prev().data('id');
            }
            if (row.next().length) {
                next_id = row.next().data('id');
            }
            url = $(this).data('sortable-url');
            data = {};
            component_prefix = row.closest('.datagrid').find('tbody').attr('data-sortable-parent-path');
            data[(component_prefix + '-item_id').replace(/^-/, '')] = item_id;
            if (prev_id !== null) {
                data[(component_prefix + '-prev_id').replace(/^-/, '')] = prev_id;
            }
            if (next_id !== null) {
                data[(component_prefix + '-next_id').replace(/^-/, '')] = next_id;
            }
            return $.nette.ajax({
                type: 'GET',
                url: url,
                data: data,
                error: function(jqXHR, textStatus, errorThrown) {
                    return alert(jqXHR.statusText);
                }
            });
        },
        helper: function(e, ui) {
            ui.children().each(function() {
                return $(this).width($(this).width());
            });
            return ui;
        }
    });
};

$(function() {

});

$.nette.ext('live').after(function (el) {
    return datagridSortable(el);
});

$(document).on('hidden.bs.collapse', '.datagrid form > .collapse', (e) => {
	$(e.currentTarget).closest('form').find('.reset-filter').click();
});

/**
 * Funkce zajistuje proklik na detail, pokud je na radku jenom jeden sloupec s odkazem ve sloupci col-name (musime mit
 * name pokazde i kdyby slo o id nebo jiny identifikator) Je klikatelny cely radek az na bunky obsahujici tlacitka, selecty
 * nebo inputy, stejne tak ignoruje sloupec col-action
 */
$(document).on('click', '.click-line-detail table tbody td:not(.col-action):not(:has(button, input, select))', (e) => {
	$(e.currentTarget).closest('tr').find('.col-name > a').click();
})

/**
 * Funkce zajistuje proklik na detail kery je uvedeny ve sloupci col-name (musime mit name pokazde i kdyby slo
 * o id nebo jiny identifikator) a ignoruje kliknuti na bunky obsahujici odkaz, select, tlacitka ci inputy, steje tak
 * ignoruje sloupec col-action. Sloupcum s odkazem (i col-name) je treba pridat tridu click-line-detail-no-link-im-the-link.
 */
$(document).on('click', '.click-line-detail-no-link table tbody td:not(.col-action):not(:has(a, button, input, select))', (e) => {
	$(e.currentTarget).closest('tr').find('.col-name > a').click();
})

/**
 * Funkce slouzi k prokliku pres bunku, ktera obsahuje link. Napriklad pokud budeme mit moznost se prokliknout jak na detail
 * zarizeni, tak platby. Musime ale na bunku dat classu click-line-detail-no-link-im-the-link
 */
$(document).on('click', '.click-line-detail-no-link-im-the-link', (e) => {
	$(e.currentTarget).find('a').click();
})


$(document).on('click', '.side-panel-template-backdrop', () => {
	$('#snippet--sidePanel').html('');
});

$(document).on('click', '.side-panel-template-container .btn-close', () => {
	$('#snippet--sidePanel').html('');
});

$(document).on('click', 'a.link-confirmation', function (e) {
	if (!confirm($(this).data('confirm-text'))){
		event.preventDefault();
	}
});