/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

function fcwlopGetSelectedPaymentMethod() {
    var paymentForm = document.getElementById('payment');
    if (paymentForm && paymentForm.paymentid) {
        if (paymentForm.paymentid.length) {
            for (var i = 0; i < paymentForm.paymentid.length; i++) {
                if (paymentForm.paymentid[i].checked === true) {
                    return paymentForm.paymentid[i].value;
                }
            }
        } else {
            return paymentForm.paymentid.value;
        }
    }
    return false;
}

function fcwlopSubmitPaymentForm(paymentForm) {
    var checkedValue = fcwlopGetSelectedPaymentMethod();
    var tokenField = document.getElementById('fcwlop_token_id');
    if (checkedValue === 'fcwlopgroupedcard' && tokenField != null) {
        fcwlopSubmitCCForm();
    } else {
        paymentForm.submit();
    }
}

function fcwlopSubmitCCForm() {
    tokenizer.submitTokenization()
        .then((result) => {
            if (result.success) {
                fcwlopCCTokenCreated(result);

                var paymentForm = document.getElementById('payment');
                paymentForm.submit();
            } else {
                fcwlopCCTokenFailed(result.error)
            }
        });
}

function fcwlopCCTokenCreated(result) {
    let displayErrorBox = document.getElementById('fcwlop_creditcard_error_box');
    let displayError = document.getElementById('fcwlop_creditcard_error');
    displayError.textContent = '';
    displayErrorBox.style.display = 'none';
    document.getElementById('fcwlop_token_id').value = result.hostedTokenizationId;
}

function fcwlopCCTokenFailed(error) {
    let displayErrorBox = document.getElementById('fcwlop_creditcard_error_box');
    let displayError = document.getElementById('fcwlop_creditcard_error');
    displayError.textContent = error.message;
    displayErrorBox.style.display = 'block';
}

function fcwlopToggleDetails(sId, blOpen) {
    var target = window.document.getElementById(sId + '-title');
    var lines = document.querySelectorAll('.' + sId);
    if (blOpen) {
        target.getElementsByClassName('unfold-icon')[0].style.display = 'none';
        target.getElementsByClassName('fold-icon')[0].style.display = '';

        lines.forEach(function (elem, index) {
            elem.style.display = '';
        });
    } else {
        target.getElementsByClassName('unfold-icon')[0].style.display = '';
        target.getElementsByClassName('fold-icon')[0].style.display = 'none';

        lines.forEach(function (elem, index) {
            elem.style.display = 'none';
        });
    }
}

function fcwlopOnClickCapture(oElement) {
    var dCaptureAmount = parseFloat(document.getElementById('fcwlop_capture_amount').value.replace(',', '.'));

    var sErrorMessageCapture = document.getElementById('fcwlop_error_message_capture_greater_null').value;
    var sConfirmSure = document.getElementById('fcwlop_confirm_message').value;

    if (dCaptureAmount == 0) {
        alert(sErrorMessageCapture);
    } else {
        if (confirm(sConfirmSure)) {
            oElement.form.fnc.value = 'capture';
            oElement.form.fcwlop_capture_source.value = 'by-amount';
            oElement.form.submit();
        }
    }
}

function fcwlopOnClickRefund(oElement, sType) {
    if (sType == 'by-position') {
        oElement.form.fnc.value = 'refund';
        oElement.form.fcwlop_refund_source.value = sType;
        oElement.form.submit();
        return;
    }

    var dRefundAmount = parseFloat(document.getElementById('fcwlop_refund_amount').value.replace(',', '.'));
    var dMaxRefundable = parseFloat(document.getElementById('fcwlop_refund_limit').value.replace(',', '.'));

    var sErrorMessageZeroRefund = document.getElementById('fcwlop_error_message_refund_greater_null').value;
    var sErrorMessageMaxRefund = document.getElementById('fcwlop_error_message_refund_lower_max').value;
    var sConfirmSure = document.getElementById('fcwlop_confirm_message').value;

    if (dRefundAmount == 0) {
        alert(sErrorMessageZeroRefund);
    } else if (dRefundAmount > dMaxRefundable) {
        alert(sErrorMessageMaxRefund);
    } else {
        if (confirm(sConfirmSure)) {
            oElement.form.fnc.value = 'refund';
            oElement.form.fcwlop_refund_source.value = sType;
            oElement.form.submit();
        }
    }
}

function fcwlopOnClickCancel(oElement) {
    var sConfirmSure = document.getElementById('fcwlop_confirm_message').value;
    if (confirm(sConfirmSure)) {
        oElement.form.fnc.value = 'cancel';
        oElement.form.submit();
    }
}