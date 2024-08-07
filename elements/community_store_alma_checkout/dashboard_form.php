<?php defined("C5_EXECUTE") or die("Access Denied.");
extract($vars);

use Concrete\Core\Support\Facade\Url;
?>


<div class="form-group">
    <?= $form->label("almaCheckoutMerchantId", t("Alma Merchant ID")) ?>
    <?= $form->text("almaCheckoutMerchantId", $almaCheckoutMerchantId) ?>
</div>

<div class="form-group">
    <?= $form->label("invoiceMinimum", t("Minimum Order Value")) ?>
    <?= $form->number("almaCheckoutMinimum", $almaCheckoutMinimum, ["step" => "0.01"]) ?>
</div>

<div class="form-group">
    <?= $form->label("invoiceMaximum", t("Maximum Order Value")) ?>
    <?= $form->number("almaCheckoutMaximum", $almaCheckoutMaximum, ["step" => "0.01"]) ?>
</div>

<div class="form-group">
    <?= $form->label("almaCheckoutTestPrivateApiKey", t("Test Private API Key")) ?>
    <?= $form->text("almaCheckoutTestPrivateApiKey", $almaCheckoutTestPrivateApiKey) ?>
</div>

<div class="form-group">
    <?= $form->label("almaCheckoutLivePrivateApiKey", t("Live Private API Key")) ?>
    <?= $form->text("almaCheckoutLivePrivateApiKey", $almaCheckoutLivePrivateApiKey) ?>
</div>

<?=$form->label('webhook', t('Required Webhook'))?>
<p>
    <?= t('Within the Alma Dashboard configure a Webhook endpoint for the following URL') ?>:
    <br />
    <a href="<?= Url::to('/checkout/almacheckoutresponse'); ?>">
        <?= Url::to('/checkout/almacheckoutresponse'); ?>
    </a>
</p>

<p>
    <?= t('With the Events to send'); ?>:
    <span class="label label-primary badge bg-primary">checkout.session.completed</span>
    <span class="label label-primary  badge bg-primary">charge.refunded</span>
</p>