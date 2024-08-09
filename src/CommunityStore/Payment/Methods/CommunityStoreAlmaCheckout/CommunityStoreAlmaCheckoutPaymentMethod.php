<?php

namespace Concrete\Package\CommunityStoreAlmaCheckout\Src\CommunityStore\Payment\Methods\CommunityStoreAlmaCheckout;

use Concrete\Core\Support\Facade\Config;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use Alma\API\Client as AlmaClient;
use Alma\API\Entities\Payment;
use Alma\API\RequestError;
use Concrete\Core\Routing\Redirect;
use Concrete\Core\Support\Facade\Log;
use Concrete\Core\Support\Facade\Session;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\OrderItem;
use ErrorException;
use Concrete\Core\User\User;
use Concrete\Package\CommunityStore\Src\CommunityStore\Customer\Customer;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\OrderStatus\OrderStatus;
use Concrete\Package\CommunityStore\Src\CommunityStore\Utilities\Calculator;
use Illuminate\Support\Facades\URL;

class CommunityStoreAlmaCheckoutPaymentMethod extends StorePaymentMethod
{
    public const MERCHANT_ID = "community_store_alma_checkout.merchant_id";

    public const MINIMUM_CHECKOUT = "community_store_alma_checkout.minimum_checkout";
    public const MAXIMUM_CHECKOUT = "community_store_alma_checkout.maximum_checkout";

    public const TEST_PRIVATE_KEY = "community_store_alma_checkout.test_private_key";
    public const LIVE_PRIVATE_KEY = "community_store_alma_checkout.live_private_key";

    public const MIN_INSTALLMENTS = "community_store_alma_checkout.minimum_installments";
    public const MAX_INSTALLMENTS = "community_store_alma_checkout.maximum_installments";

    public const MODE = "community_store_alma_checkout.mode";

    public function getName()
    {
        return "Alma";
    }

    public function dashboardForm()
    {
        $this->set("form", $this->app->make("helper/form"));
        $this->set("almaCheckoutMerchantId", Config::get($this::MERCHANT_ID));

        $this->set("almaCheckoutMinimum", Config::get($this::MINIMUM_CHECKOUT));
        $this->set("almaCheckoutMaximum", Config::get($this::MAXIMUM_CHECKOUT));

        $this->set("almaCheckoutTestPrivateApiKey", Config::get($this::TEST_PRIVATE_KEY));
        $this->set("almaCheckoutLivePrivateApiKey", Config::get($this::LIVE_PRIVATE_KEY));

        $this->set("almaCheckoutMode", Config::get($this::MODE));

        $this->set("almaCheckoutMinimumInstallments", Config::get($this::MIN_INSTALLMENTS));
        $this->set("almaCheckoutMaximumInstallments", Config::get($this::MAX_INSTALLMENTS));
    }

    public function save(array $data = [])
    {
        Config::save($this::MERCHANT_ID, $data["almaCheckoutMerchantId"]);
        Config::save($this::MINIMUM_CHECKOUT, $data["almaCheckoutMinimum"]);
        Config::save($this::MAXIMUM_CHECKOUT, $data["almaCheckoutMaximum"]);
        Config::save($this::TEST_PRIVATE_KEY, $data["almaCheckoutTestPrivateApiKey"]);
        Config::save($this::LIVE_PRIVATE_KEY, $data["almaCheckoutLivePrivateApiKey"]);

        Config::save($this::MIN_INSTALLMENTS, $data["almaCheckoutMinimumInstallments"]);
        Config::save($this::MAX_INSTALLMENTS, $data["almaCheckoutMaximumInstallments"]);
        Config::save($this::MODE, $data["almaCheckoutMode"]);
    }

    public function validate($args, $e)
    {
        return $e;
    }

    public function submitPayment()
    {
        return [
            "error" => 0,
            "transactionReference" => ""
        ];
    }

    public function getPaymentMaximum()
    {
        $defaultMax = 1_000_000; // TODO: Maybe Alma has real limits ?
        $max = Config::get($this::MAXIMUM_CHECKOUT);

        if (!ctype_digit($max)) {
            return $defaultMax;
        } else {
            return $max;
        }
    }

    public function getPaymentMinimum()
    {
        $defaultMin = 10_000; // TODO: Maybe Alma has real limits ? IIRC min is 50
        $min = Config::get($this::MINIMUM_CHECKOUT);

        if (!ctype_digit($min)) {
            return $defaultMin;
        } else {
            return $min;
        }
    }

    public function checkoutForm()
    {
        // For weird reasons the In-Page Integration Javascript library needs the mode in uppercase.
        $this->set("almaMode", strtoupper(Config::get($this::MODE)));
    }

    public function createSession()
    {
        header("Content-Type: application/json");
        // Avoid cache issues
        header("Cache-Control: no-cache, no-store, must-revalidate");

        $user = new User();
        $userLocale = $user->getUserDefaultLanguage();
        $installmentsCount = intval($this->request->get("installments_count"));

        $pm = StorePaymentMethod::getByHandle("community_store_alma_checkout");
        $order = Order::add($pm, null, "incomplete");

        if (!isset($order)) {
            return Redirect::to("/cart");
        }

        $amountInCents = intval(Calculator::getGrandTotal() * 100);
        $customer = new Customer();

        // TODO: Les adresses ne sont pas encore disponibles à ce moment-là
        $billingAddress = [
            "first_name"  => $customer->getValue("billing_first_name"),
            "last_name"   => $customer->getValue("billing_last_name"),
            "email"       => $customer->getEmail(),
            "phone"       => $customer->getValue("billing_phone"),
            "line1" => $customer->getAddressValue("billing_address", "address1"),
            "line2" => $customer->getAddressValue("billing_address", "address2"),
            "postal_code" => $order->getAddressValue("billing_address", "postal_code"),
            "city"        => $order->getAddressValue("billing_address", "city"),
            "country"     => $order->getAddressValue("billing_address", "country")
        ];

        $shippingAddress = [
            "first_name"  => $customer->getValue("shipping_first_name"),
            "last_name"   => $customer->getValue("shipping_last_name"),
            "email"       => $customer->getEmail(),
            "phone"       => $customer->getValue("shipping_phone"),
            "line1" => $customer->getAddressValue("shipping_address", "address1"),
            "line2" => $customer->getAddressValue("shipping_address", "address2"),
            "postal_code" => $order->getAddressValue("shipping_address", "postal_code"),
            "city"        => $order->getAddressValue("shipping_address", "city"),
            "country"     => $order->getAddressValue("shipping_address", "country")
        ];

        $almaMode = match (Config::get($this::MODE)) {
            "live" => AlmaClient::LIVE_MODE,
            "test" => AlmaClient::TEST_MODE,
            default => AlmaClient::TEST_MODE
        };

        $secretKey = match ($almaMode) {
            AlmaClient::LIVE_MODE => Config::get($this::LIVE_PRIVATE_KEY),
            AlmaClient::TEST_MODE => Config::get($this::TEST_PRIVATE_KEY),
            default => Config::get($this::TEST_PRIVATE_KEY)
        };

        try {
            $almaClient = new AlmaClient($secretKey, ["mode" => $almaMode]);
            $payment = $almaClient->payments->create(
                [
                    "payment" => [
                        "custom_data" => ["order_id" => $order->getOrderID()],
                        "origin" => "online_in_page",
                        "return_url" => strval(\URL::to("/checkout/complete")),
                        "ipn_callback_url" => strval(\URL::to("/checkout/alma/ipn_callback")),
                        "purchase_amount" => $amountInCents,
                        "installments_count" => $installmentsCount,
                        "locale" => $userLocale, // TODO: Mettre la locale du client

                        "billing_address"    => $billingAddress,

                        "shipping_address"   => $shippingAddress,
                    ],
                    "customer" =>
                    [
                        "first_name" => "Foo",
                        "last_name"  => "Bar",
                        "email"      => "quentin.dutilleul@exterieurstock.fr",
                        "phone"      => "0627038806",
                        "addresses"  =>
                        [
                            [
                                "first_name" => "Foo",
                                "last_name"  => "Bar",
                                "email"      => "quentin.dutilleul@exterieurstock.fr",
                                "phone"      => "0627038806",
                            ],
                        ],
                    ],
                ]
            );

            if (isset($payment->message)) {
                http_response_code(500);
            }

            echo json_encode($payment);
        } catch (RequestError $e) {
            http_response_code(500);

            echo json_encode($e->response->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    public function ipnCallback()
    {
        header("Content-Type: application/json");
        $pmID = StorePaymentMethod::getByHandle("community_store_alma_checkout")->getID();
        $mode = Config::get($this::MODE);
        $secretKey = match ($mode) {
            "live" => Config::get($this::LIVE_PRIVATE_KEY),
            "test" => Config::get($this::TEST_PRIVATE_KEY),
            default => Config::get($this::TEST_PRIVATE_KEY)
        };

        $almaMode = match ($mode) {
            "live" => AlmaClient::LIVE_MODE,
            "test" => AlmaClient::TEST_MODE,
            default => AlmaClient::TEST_MODE
        };

        try {
            $almaClient = new AlmaClient($secretKey, ["mode" => $almaMode]);
            $payment = $almaClient->payments->fetch($this->request->get("pid"));

            $orderID = $payment->custom_data["order_id"];
            /** @var Order $order */
            $order = Order::getByID($orderID);

            if ($payment->state == Payment::STATE_IN_PROGRESS || $payment->state == Payment::STATE_PAID) {
                $order->setPaymentMethodID($pmID);
                $order->setTransactionReference($payment->id);
                $order->completeOrder($payment->id);
                $order->updateStatus(OrderStatus::getStartingStatus()->getHandle());
                $order->save();
            }

            if (count($payment->refunds) > 0) {
                $order->setRefunded(new \DateTime());
                $order->setRefundReason("Refunded by Alma Dash");
                $order->save();
            }

            http_response_code(200);
        }
        // If the Alma API Call failed
        catch (RequestError $e) {
            http_response_code($e->response->responseCode);
            echo json_encode([
                "origin" => "Alma API Call",
                "error" => $e->getErrorMessage(),
                "response_code" => $e->response->responseCode,
            ]);
        }
        exit;
    }
}
