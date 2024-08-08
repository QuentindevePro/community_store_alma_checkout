<?php

use Alma\API\Entities\Payment;

defined('C5_EXECUTE') or die("Access Denied.");
extract($vars);

/** @var Payment $payment */
?>

<script>
    $.getScript("https://cdn.jsdelivr.net/npm/@alma/in-page@2.x/dist/index.umd.js").done(() => {
        $.post(
            "<?= \URL::to("/checkout/alma/create_session") ?>", {
                installments_count: 4
            },
            (payment) => {
                console.log("Request successful");
                const inPage = Alma.InPage.initialize({
                    merchantId: payment.merchant_id,
                    amountInCents: payment.purchase_amount,
                    installmentsCount: payment.installments_count,
                    selector: "#alma-in-page",
                    environment: "<?= $alma_mode ?>",
                });

                document.querySelector("#alma-pay-btn").addEventListener("click", () => {
                    inPage.startPayment({
                        paymentId: payment.id
                    });
                });
            }
        ).fail((jqXHR, textStatus, errorThrown) => {
            alert("Error while fetching Alma payment session: " + errorThrown);
        });
    });
</script>

<div id="alma-in-page"></div>
<span class="btn btn-primary" id="alma-pay-btn">Payer en 4x avec Alma</span>