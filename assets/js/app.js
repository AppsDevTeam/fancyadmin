//
// jQuery as a global — MUST be first so it is set before the legacy plugins below
// (and bundled deps like nette.ajax.js) are evaluated. Imports are hoisted.
//
import $ from './_jquery-global';

//
// Register FancyAdmin's own JS components (before the init() calls below).
//
import './_registerFancyadminComponents';

//
// SCSS styles
//
import '../scss/app.scss';

//
// Legacy non-modular jQuery plugins (rely on the global set above).
//
import 'jquery-ui-bundle';
import '@regru/jquery-menu-aim';

// import {Chart} from "chart.js/auto";
import Nette from 'nette-forms';
import './dependentSelectBox'
Nette.initOnLoad();
window.Nette = Nette;
// window.Chart = Chart;

import 'nette.ajax.js';
$.nette.init({
	load: function (rh) {
		$(this.linkSelector).off('click.nette', rh).on('click.nette', rh);
		$(this.formSelector).off('submit.nette', rh).on('submit.nette', rh)
			.off('click.nette', ':image', rh).on('click.nette', ':image', rh)
			.off('click.nette', ':submit', rh).on('click.nette', ':submit', rh);
		$(this.buttonSelector).closest('form')
			.off('click.nette', this.buttonSelector, rh).on('click.nette', this.buttonSelector, rh);
	}
}, {
	linkSelector: 'a:not([target]):not([href^="http"]):not([href^="//"]):not(.noajax)',
	formSelector: 'form',
	buttonSelector: 'input.ajax[type="submit"], button.ajax[type="submit"], input.ajax[type="image"]'
});
import 'adt-nette-ajax';

import './toggleMenu';

import './history.ajax.js';
if ($.nette.ext('history')) {
	// Podmíněné je to kvůli Safari na iOS, protože tam je z nějakého důvodu .ext('history') undefined
	$.nette.ext('history').cache = false; // TODO: kvůli tomuto se všechny AJAX requesty posílají 2x, ale nevíme proč!
}

$.nette.ext('live').after(function($el) {
	$('[data-dependentselectbox]').dependentSelectBox();
});

// import 'daterangepicker';

//
// Modular vendor JS files
//
import 'bootstrap';
// Alternatively, you may import plugins individually as needed
// If you chose to import plugins individually, you must also install exports-loader
// import 'bootstrap/js/dist/util';
// import 'bootstrap/js/dist/dropdown';
// ...

//
// Components & presenters JS
//

import AdtJsComponents from 'adt-js-components';
// AdtJsComponents.initCurrencyInput();
// // AdtJsComponents.initDateInput();
// // AdtJsComponents.initRecaptcha();
// AdtJsComponents.initSelect2();
// AdtJsComponents.initAjaxSelect();
// AdtJsComponents.initSubmitForm();
// AdtJsComponents.initReplicator();
//
// AdtJsComponents.init('components-panels-base-baseChartPanel', 'UI/Portal/Components/Panels/Base/BaseChartPanelControl');
AdtJsComponents.init('select-account-form', '~UI/Components/Forms/SelectAccount');
AdtJsComponents.init('portal-components-grids-traits-signInAsIdentity', '~UI/Components/Grids/Traits/SignInAsIdentity');
// AdtJsComponents.init('portal-components-forms-dashboardFilter', 'UI/Portal/Components/Forms/DashboardFilter');
// AdtJsComponents.init('portal-components-forms-changeLicenceForm', 'UI/Portal/Components/Forms/ChangeLicence');
// AdtJsComponents.init('portal-components-forms-warehouseOperationForm', 'UI/Portal/Components/Forms/WarehouseOperation');
// AdtJsComponents.init('companySitePlanDetail', 'UI/Portal/Presenters/CompanySitePlans');
// AdtJsComponents.init('dashboard', 'UI/Portal/Presenters/Dashboard');
// AdtJsComponents.init('dashboard', 'assets/js/dashboard');
AdtJsComponents.init('messaging', 'Messaging');
AdtJsComponents.init('notifications', 'Notifications');
AdtJsComponents.init('translate', 'Translate');

// AdtJsComponents.init('print-dashboard', 'assets/js/printDashboard');
// AdtJsComponents.init('safari-support', 'assets/js/safariSupport');
//
// import './netteForm';
import './flashes';
// import './userDropdown';
// import './tableActionsShadow';
// import './_datagrid';
// import './dateRange';
// import '../../../vendor/ublaboo/datagrid/assets/datagrid';
// import './addToHomeScreen';
// import './fullscreen';
// import './structured-filter';
// import './filteredSearch'
// import 'forms-replicator';

import './sideMenu'
import './datagrid/datagrid'
import './datagrid'
import './sidePanel'
