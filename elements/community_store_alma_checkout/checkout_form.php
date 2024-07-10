<?php defined('C5_EXECUTE') or die("Access Denied.");
extract($vars);
?>

<script src="https://cdn.jsdelivr.net/npm/@alma/in-page@2.x/dist/index.umd.js"></script>
<script>
    $(window).on('load', function() {

        $('.store-btn-complete-order').on('click', function (e) {
            // Open Checkout with further options
            var currentpmid = $('input[name="payment-method"]:checked:first').data('payment-method-id');

            if (currentpmid === <?= $pmID; ?>) {
                $(this).prop('disabled', true);
                $(this).val('<?= t('Processing...'); ?>');

                const inPage = Alma.InPage.initialize({
                    merchantId: "TEST MARCHAND",
                    amountInCents: 20000,
                    installmentsCount: 3,
                    selector: "#alma-in-page"
                });

                inPage.startPayment({ paymentId: paymentID});
            }
        });

    });
</script>