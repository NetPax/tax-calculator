document.addEventListener("DOMContentLoaded", function () {
	const form = document.getElementById("tax-calculator-form");
	const productNameField = form.querySelector("input[name='product_name']");
	const netAmountField = form.querySelector("input[name='net_amount']");
	const vatRateField = form.querySelector("select[name='vat_rate']");

	form.addEventListener("submit", function (event) {
		let isValid = true;
		let messages = [];

		if (productNameField.value.trim() === "") {
			messages.push("Błąd: Wprowadź nazwę produktu.");
			isValid = false;
		}

		const netAmount = parseFloat(netAmountField.value);
		if (isNaN(netAmount) || netAmount <= 0) {
			messages.push("Błąd: Wprowadź poprawną kwotę netto.");
			isValid = false;
		}

		if (vatRateField.value === "") {
			messages.push("Błąd: Wybierz poprawną stawkę VAT.");
			isValid = false;
		}

		if (!isValid) {
			event.preventDefault();
			alert(messages.join("\n"));
		}
	});
});