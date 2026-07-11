// Bridge legacy jquery.nette.ajax with netteForms 3.x.
//
// nette.ajax's `validation` extension calls `Nette.validateForm(form)` and passes the
// whole <form>, relying on the old (Nette 2.4) behaviour where the scope came from
// `form["nette-submittedBy"]`. netteForms 3.x instead derives the validation scope from
// the *submit button* passed as the sender (its `formnovalidate` +
// `data-nette-validation-scope`). Given a form it therefore validates every control,
// so buttons with setValidationScope() fail on unrelated required fields and their
// AJAX request is aborted (the button appears dead).
//
// nette.ajax still sets `form["nette-submittedBy"]` to the clicked button before
// validating, so re-dispatch validation against that button to honour
// setValidationScope(). The `submitter.form === sender` guard skips stale expandos
// pointing to detached buttons (e.g. after a snippet redraw); the expando is cleared
// after use so a later programmatic submit cannot reuse it.
import Nette from 'nette-forms';

const origValidateForm = Nette.validateForm.bind(Nette);
Nette.validateForm = function (sender, onlyCheck) {
	if (sender instanceof HTMLFormElement) {
		const submitter = sender['nette-submittedBy'];
		if (submitter && submitter.form === sender) {
			const result = origValidateForm(submitter, onlyCheck);
			delete sender['nette-submittedBy'];
			return result;
		}
	}
	return origValidateForm(sender, onlyCheck);
};
