var _elqQ = _elqQ || [];
_elqQ.push(["elqGetCustomerGUID"]);

jQuery(document).ready(function($) {
	$('.gform_wrapper form').submit(function(e) {
		$(this).append('<input type="hidden" name="elqCustomerGUID" />');
		$('input[name=elqCustomerGUID]', this).each(function(index, value){$(this).val(GetElqCustomerGUID());});
	});
});
