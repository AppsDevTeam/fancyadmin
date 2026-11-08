# Formuláře v modalech načítané až při otevření

Stránka s několika modaly (karta záznamu) by jinak při každém zobrazení sestavovala všechny
jejich formuláře - číselníky, ceny, šablony - i když uživatel většinou žádný neotevře.
`LazyComponentsTrait` a `assets/js/lazyModal.js` je sestaví až při prvním otevření modalu.

## Presenter

```php
use ADT\FancyAdmin\UI\Presenters\LazyComponentsTrait;

class PatientPresenter extends AuthPresenter
{
	use LazyComponentsTrait {
		isLazyComponentLoaded as private isLazyComponentRequested;
	}

	protected function getLazyComponents(): array
	{
		return ['medicalRecordForm', 'treatmentPlanForm'];
	}

	// Modal otevřený rovnou z URL (úprava záznamu) potřebuje formulář hned
	public function isLazyComponentLoaded(string $name): bool
	{
		return $this->isLazyComponentRequested($name)
			|| ($name === 'medicalRecordForm' && $this->editedRecord !== null);
	}

	public function actionDetail(int $id): void
	{
		$this->detailEntity = ...;   // komponenta záznam potřebuje
		if ($this->isLazyComponentSignal()) {
			return;                  // zbytek stránky se nepřekresluje
		}
		...
	}
}
```

V `render*()` stejně přeskoč přípravu zbytku stránky přes `isLazyComponentSignal()`.

## Šablona

```latte
<div class="modal fade" id="recordModal" tabindex="-1"
	data-lazy-url="{link lazyComponent!, name: medicalRecordForm}"
	n:attr="data-lazy-state: $presenter->isLazyComponentLoaded('medicalRecordForm') ? loaded">
	...
	<div class="modal-body">
		{snippet lazyMedicalRecordForm}
			{if $presenter->isLazyComponentLoaded('medicalRecordForm')}
				{control medicalRecordForm}
			{else}
				<p class="text-muted mb-0"><i class="far fa-spinner-third fa-spin me-2"></i>Načítám…</p>
			{/if}
		{/snippet}
	</div>
</div>
```

Snippet se jmenuje `lazy` + název komponenty s velkým prvním písmenem.

## JavaScript

`import '@fancyadmin/js/lazyModal';` v hlavním JS. Init formuláře (Select2, datum, vlastní
skripty) musí běžet i po AJAX překreslení (`$.nette.ext('live').after`). Kód, který formulář
předvyplňuje před otevřením modalu, počká na načtení:

```js
import { whenLazyModalLoaded } from '@fancyadmin/js/lazyModal';

Modal.getOrCreateInstance(modal).show();
whenLazyModalLoaded(modal, () => {
	modal.querySelector('input[name="toothNumber"]').value = toothNum;
});
```

Na kartě pacienta v adt-dent to zkrátilo zobrazení karty zhruba o třetinu (77 → 45 ms).
