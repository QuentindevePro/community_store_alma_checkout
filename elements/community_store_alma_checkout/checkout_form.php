<?php

use Alma\API\Entities\Payment;

defined('C5_EXECUTE') or die("Access Denied.");
extract($vars);

/** @var Payment $payment */
?>

<script src="https://cdn.jsdelivr.net/npm/@alma/in-page@2.x/dist/index.umd.js"></script>
<script>
    $.getScript("https://cdn.jsdelivr.net/npm/@alma/in-page@2.x/dist/index.umd.js").done(() => {
        $.ajax({
            url: "<?= \URL::to("/checkout/almacheckoutcreatesession") ?>",
            type: "GET",
            cache: false,
            dataType: 'json',
            success: (payment) => {
                console.log("Request successful");
                const inPage = Alma.InPage.initialize({
                    merchantId: payment.merchant_id,
                    amountInCents: payment.purchase_amount,
                    installmentsCount: payment.installments_count,
                    selector: "#alma-in-page",
                    environment: "<?= $alma_mode ?>",
                });

                document.querySelector("#pay-button").addEventListener("click", () => {
                    inPage.startPayment({
                        paymentId: payment.id
                    });
                });
            }
        }).fail((jqXHR, textStatus, errorThrown) => {
            alert("Error while fetching Alma payment session: " + errorThrown);
        });

    });
</script>

<div id="alma-in-page"></div>
<button id="pay-button"><?= t("Checkout") ?></button>