const run = (el) => {
	$(document).on('change', '#frm-selectAccountForm-form-account', function () {
		$(".superUltraSecretSubmit").click();
	});
};

export default { run };
