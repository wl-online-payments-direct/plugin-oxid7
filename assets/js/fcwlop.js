/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

function fcwlopSubmitPaymentForm(paymentForm) {
    paymentForm.submit();
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

function fcwlopOnClickRefund(oElement) {
    var dRefundAmount = parseFloat(document.getElementById('fcwlop_refund_amount').value.replace(',', '.'));

    var sErrorMessageRefund = document.getElementById('fcwlop_error_message_refund_greater_null').value;
    var sConfirmSure = document.getElementById('fcwlop_confirm_message').value;

    if (dRefundAmount == 0) {
        alert(sErrorMessageRefund);
    } else {
        if (confirm(sConfirmSure)) {
            oElement.form.fnc.value = 'refund';
            oElement.form.fcwlop_refund_source.value = 'by-amount';
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