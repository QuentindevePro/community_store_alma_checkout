<?php

use Concrete\Core\Form\Service\Form;

 defined("C5_EXECUTE") or die("Access Denied.");
extract($vars);

/** @var Form $form */
?>


<div class="form-group">
    <?= $form->label("almaCheckoutMerchantId", t("Alma Merchant ID")) ?>
    <?= $form->text("almaCheckoutMerchantId", $almaCheckoutMerchantId) ?>
</div>

<div class="form-group">
    <?= $form->label("almaCheckoutMinimum", t("Minimum Order Value")) ?>
    <?= $form->number("almaCheckoutMinimum", $almaCheckoutMinimum, ["step" => "0.01"]) ?>
</div>


<div class="form-group">
    <?= $form->label("almaCheckoutMinimumInstallments", t("Minimum Installments count")) ?>
    <?= $form->number("almaCheckoutMinimumInstallments", $almaCheckoutMinimumInstallments) ?>
</div>

<div class="form-group">
    <?= $form->label("almaCheckoutMaximumInstallments", t("Maximum Installments count")) ?>
    <?= $form->number("almaCheckoutMaximumInstallments", $almaCheckoutMaximumInstallments) ?>

<div class="form-group">
    <?= $form->label("invoiceMaximum", t("Maximum Order Value")) ?>
    <?= $form->number("almaCheckoutMaximum", $almaCheckoutMaximum, ["step" => "0.01"]) ?>
</div>

<div class="form-group">
    <?= $form->label("almaCheckoutMode", t("Mode")) ?>
    <?= $form->select("almaCheckoutMode", ["test" => t("Test"), "live" => t("Live")], $almaCheckoutMode) ?>

<div class="form-group">
    <?= $form->label("almaCheckoutTestPrivateApiKey", t("Test Private API Key")) ?>
    <?= $form->text("almaCheckoutTestPrivateApiKey", $almaCheckoutTestPrivateApiKey) ?>
</div>

<div class="form-group">
    <?= $form->label("almaCheckoutLivePrivateApiKey", t("Live Private API Key")) ?>
    <?= $form->text("almaCheckoutLivePrivateApiKey", $almaCheckoutLivePrivateApiKey) ?>
</div>