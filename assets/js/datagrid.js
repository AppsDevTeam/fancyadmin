import { showSortableError } from './flashes';

// Explicit global: ublaboo renders inline scripts that call datagridSortable().
// ESM modules are strict, so the previous implicit global assignment threw.
window.datagridSortable = function($el) {
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
                    if (errorThrown !== 'abort') {
                        showSortableError();
                    }
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
});

/**
 * Funkce zajistuje proklik na detail kery je uvedeny ve sloupci col-name (musime mit name pokazde i kdyby slo
 * o id nebo jiny identifikator) a ignoruje kliknuti na bunky obsahujici odkaz, select, tlacitka ci inputy, steje tak
 * ignoruje sloupec col-action. Sloupcum s odkazem (i col-name) je treba pridat tridu click-line-detail-no-link-im-the-link.
 */
$(document).on('click', '.click-line-detail-no-link table tbody td:not(.col-action):not(:has(a, button, input, select))', (e) => {
	$(e.currentTarget).closest('tr').find('.col-name > a').click();
});

/**
 * Funkce slouzi k prokliku pres bunku, ktera obsahuje link. Napriklad pokud budeme mit moznost se prokliknout jak na detail
 * zarizeni, tak platby. Musime ale na bunku dat classu click-line-detail-no-link-im-the-link
 */
$(document).on('click', '.click-line-detail-no-link-im-the-link', (e) => {
	$(e.currentTarget).find('a').click();
});

/**
 * Proklik na detail ve stromovem gridu (DataGridTree.latte). Strom nema tabulku, radek je .datagrid-tree-item-content,
 * prvni sloupec (obdoba col-name) je .datagrid-tree-item-left a vedle odkazu na detail je v nem odkaz sipky
 * [data-toggle-tree]. Detail je prvni odkaz v .datagrid-tree-item-left, ktery neni sipka.
 *
 * Klik na odkaz (i sipku), tlacitko, input, select nebo akce (.datagrid-tree-item-right-actions) dela dal svoje.
 * Stejne jako u tabulky click-line-detail ignoruje sloupce s tlacitky, inputy a selecty, click-line-detail-no-link
 * navic i sloupce s odkazem.
 *
 * Odkaz se klika nativne (link.click()), jQuery .click() na <a> nenaviguje.
 */
const treeRowSelector = '.datagrid-tree-item:not(.datagrid-tree-header) > .datagrid-tree-item-content';

const openTreeRowDetail = (e, ignoredColumnSelector) => {
	const $target = $(e.target);
	if ($target.closest('a, button, input, select, textarea, label, .datagrid-tree-item-right-actions', e.currentTarget).length) {
		return;
	}
	if ($target.closest('.datagrid-tree-item-right-columns-column', e.currentTarget).is(ignoredColumnSelector)) {
		return;
	}
	const link = $(e.currentTarget).find('.datagrid-tree-item-left a:not([data-toggle-tree])').get(0);
	if (link) {
		link.click();
	}
};

$(document).on('click', '.click-line-detail ' + treeRowSelector, (e) => {
	openTreeRowDetail(e, ':has(button, input, select)');
});

$(document).on('click', '.click-line-detail-no-link ' + treeRowSelector, (e) => {
	openTreeRowDetail(e, ':has(a, button, input, select)');
});

$(document).on('click', 'a.link-confirmation', function (e) {
	if (!confirm($(this).data('confirm-text'))){
		event.preventDefault();
	}
});

$(document).on('click', 'tbody tr:not(.row-item-detail) td:not(.col-action)', function (event) {
	if (event.target.tagName.toLowerCase() === 'a' || $(event.target).hasClass('menu-open-button')) {
		return;  // Klik byl na odkaz, takže nevyvoláváme žádnou akci.
	}

	let $tr = $(this).parent();

	if ($tr.next().hasClass('toggled')) {
		$tr.next().removeClass('toggled');
		$tr.next().find('.item-detail-content').css('display', 'none');
	} else {
		$tr.next().addClass('toggled');
		$tr.next().find('.item-detail-content').css('display', 'block');
	}
});