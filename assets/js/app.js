//
// jQuery as a global — MUST be first so it is set before the legacy plugins below
// are evaluated. Imports are hoisted.
//
import $ from './_jquery-global';

//
// CSRF token pro signaly — doplnuje ho do formularu a uklizi z adresniho radku.
// Driv nez cokoliv, co odesila formulare nebo meni historii prohlizece.
//
import './signalCsrf';

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
// nette-forms a window.Nette nastavuje builtin Form z adt-js-components; vlastní kopie by formuláře
// inicializovala dřív a jeho chyby u polí by se při odeslání nepoužily
import './dependentSelectBox'
// window.Chart = Chart;

// AJAX přes Naja - všechny odkazy a formuláře (naja.js)
import { onContentLoaded } from './naja';

import './toggleMenu';

import './pageReady';

onContentLoaded(function($el) {
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
// Builtiny (Select, AjaxSelect, Form, Replicator, Chart…) adt-js-components 3 spouští samy, nastavení
// jde z jsComponentsConfig přes data-adt-js-components (výchozí select.selector viz JsComponents::DEFAULTS)
// AdtJsComponents.init('components-panels-base-baseChartPanel', 'UI/Portal/Components/Panels/Base/BaseChartPanelControl');
AdtJsComponents.init('select-account-form', '~UI/Components/Forms/SelectAccount');
AdtJsComponents.init('portal-components-grids-traits-signInAsIdentity', '~UI/Components/Grids/Traits/SignInAsIdentity');
AdtJsComponents.init('fancyadmin-passkey-login', '~UI/Components/Forms/SignIn');
AdtJsComponents.init('fancyadmin-passkey-form', '~UI/Components/Forms/Passkey');
// AdtJsComponents.init('portal-components-forms-dashboardFilter', 'UI/Portal/Components/Forms/DashboardFilter');
// AdtJsComponents.init('portal-components-forms-changeLicenceForm', 'UI/Portal/Components/Forms/ChangeLicence');
// AdtJsComponents.init('portal-components-forms-warehouseOperationForm', 'UI/Portal/Components/Forms/WarehouseOperation');
// AdtJsComponents.init('companySitePlanDetail', 'UI/Portal/Presenters/CompanySitePlans');
// AdtJsComponents.init('dashboard', 'UI/Portal/Presenters/Dashboard');
// AdtJsComponents.init('dashboard', 'assets/js/dashboard');
AdtJsComponents.init('translate', 'Translate');

// AdtJsComponents.init('print-dashboard', 'assets/js/printDashboard');
// AdtJsComponents.init('safari-support', 'assets/js/safariSupport');
//
// import './netteForm';
import './flashes';
import './persistentNotice';
// import './userDropdown';
import './tableActionsShadow';
import './quickbar';
// import './_datagrid';
// import './dateRange';
// import '../../../vendor/ublaboo/datagrid/assets/datagrid';
// import './addToHomeScreen';
// import './fullscreen';
// import './structured-filter';
// import './filteredSearch'

import './sideMenu'
import './themeToggle'
import './userMenuSelect'
import './datagrid/datagrid'
import './datagrid'
import './datagridInfiniteScroll'
import './datagridBulk'
import './sidePanel'
import './tomSelectDropdownSize'
import './tooltip'
