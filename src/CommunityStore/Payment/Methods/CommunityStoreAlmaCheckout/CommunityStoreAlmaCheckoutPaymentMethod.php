<?php

namespace Concrete\Package\CommunityStoreAlmaCheckout\Src\CommunityStore\Payment\Methods\CommunityStoreAlmaCheckout;

use Concrete\Core\Support\Facade\Config;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use Alma\API\Client as AlmaClient;
use Concrete\Core\Routing\Redirect;
use Concrete\Core\Support\Facade\Session;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\OrderItem;
use ErrorException;
use Concrete\Core\User\User;

class CommunityStoreAlmaCheckoutPaymentMethod extends StorePaymentMethod
{
    const MERCHANT_ID = "community_store_alma_checkout.merchant_id";

    const MINIMUM_CHECKOUT = "community_store_alma_checkout.minimum_checkout";
    const MAXIMUM_CHECKOUT = "community_store_alma_checkout.maximum_checkout";
    
    const TEST_PRIVATE_KEY = "community_store_alma_checkout.test_private_key";
    const LIVE_PRIVATE_KEY = "community_store_alma_checkout.live_private_key";

    const MODE = "community_store_alma_checkout.mode";

    public function getName()
    {
        return t("Alma Checkout");
    }

    public function dashboardForm() {
        $this->set("form", $this->app->make("helper/form"));
        $this->set("almaCheckoutMerchantId", Config::get($this::MERCHANT_ID));
        
        $this->set("almaCheckoutMinimum", Config::get($this::MINIMUM_CHECKOUT));
        $this->set("almaCheckoutMaximum", Config::get($this::MAXIMUM_CHECKOUT));
        
        $this->set("almaCheckoutTestPrivateApiKey", Config::get($this::TEST_PRIVATE_KEY));
        
        $this->set("almaCheckoutLivePrivateApiKey", Config::get($this::LIVE_PRIVATE_KEY));
    }

    public function save(array $data = [])
    {
        Config::save($this::MERCHANT_ID, $data["almaCheckoutMerchantId"]);
        Config::save($this::MINIMUM_CHECKOUT, $data["almaCheckoutMinimum"]);
        Config::save($this::MAXIMUM_CHECKOUT, $data["almaCheckoutMaximum"]);
        Config::save($this::TEST_PRIVATE_KEY, $data["almaCheckoutTestPrivateApiKey"]);
        Config::save($this::LIVE_PRIVATE_KEY, $data["almaCheckoutLivePrivateApiKey"]);
    }

    public function validate($args, $e)
    {
        return $e;
    }

    public function getPaymentMaximum()
    {
        $defaultMax = 1_000_000; // 1 Million
        $max = Config::get($this::MAXIMUM_CHECKOUT);

        if (!ctype_digit($max)) return $defaultMax;

        else return $max;
    }

    public function getPaymentMinimum()
    {
        $defaultMin = 10_000; // 10 Mille
        $min = Config::get($this::MINIMUM_CHECKOUT);

        if (!ctype_digit($min)) return $defaultMin;

        else return $min;
    }

    public function checkoutForm()
    {
        $this->set("alma_merchant_id", Config::get($this::MERCHANT_ID));
        $this->set("alma_mode", Config::get($this::MODE));
    }

    public function createSession()
    {
        $user = new User();
        $userLocale = $user->getUserDefaultLanguage();
        
        // Récupère l"order. Si l"order n"existe pas, redirige vers la page d"accueil
        /** @var Order $order */
        $orderID = Session::get("community_store.tempOrderID");
        $order = Order::getByID($orderID); // TODO: Comment récupérer la commande ? Réflexion: récupérer la commande avec l"oID le plus gros bindée au client ?
        if (!$order) return Redirect::to("/");
        
        $orderItems = $order->getOrderItems()->toArray();
        $amountInCents = round(array_reduce($orderItems, function ($acc, OrderItem $item) {
            return $acc + $item->getPricePaid() * 100;
        }, 0));

        header("TotalInCents: $amountInCents");
        // TODO: Les adresses ne sont pas encore disponibles à ce moment-là
        $billingAddress = [
            "first_name"  => $order->getAttribute("billing_first_name"),
            "last_name"   => $order->getAttribute("billing_last_name"),
            "email"       => $order->getAttribute("email"),
            "line1"       => $order->getAttribute("billing_address")->address1,
            "postal_code" => $order->getAttribute("billing_address")->postal_code,
            "city"        => $order->getAttribute("billing_address")->city,
            "country"     => $order->getAttribute("billing_address")->country,
        ];

        $shippingAddress = [
            "first_name"  => $order->getAttribute("shipping_first_name"),
            "last_name"   => $order->getAttribute("shipping_last_name"),
            "email"       => $order->getAttribute("email"),
            "line1"       => $order->getAttribute("billing_address")->address1,
            "postal_code" => $order->getAttribute("billing_address")->postal_code,
            "city"        => $order->getAttribute("billing_address")->city,
            "country"     => $order->getAttribute("billing_address")->country,
        ];

        $mode = Config::get($this::MODE);
        $secretKey = match ($mode) {
            "live" => Config::get($this::LIVE_PRIVATE_KEY),
            "test" => Config::get($this::TEST_PRIVATE_KEY),
            default => throw new ErrorException("Environment mode is not a valid value.")
        };

        $almaMode = match ($mode) {
            "live" => AlmaClient::LIVE_MODE,
            "test" => AlmaClient::TEST_MODE
        };

        $almaClient = new AlmaClient($secretKey, $almaMode);
        $payment = $almaClient->payments->create(
            [
                "origin" => "online_in_page",
                "payment" => [
                    "return_url" => "/checkout/complete",
                    "ipn_callback_url" => "idk", // TODO: Implement IPN callback
                    "purchase_amount" => $amountInCents,
                    "installments_count" => 4, // TODO: ???
                    "locale" => $userLocale, // TODO: Mettre la locale du client

                    "billing_address"    => $billingAddress,

                    "shipping_address"   => $shippingAddress,
                ],
                "customer" =>
                [
                    "first_name" => $order->getAttribute("billing_first_name"),
                    "last_name"  => $order->getAttribute("billing_last_name"),
                    "email"      => $order->getAttribute("email"),
                    "phone"      => $order->getAttribute("billing_phone"),
                    "addresses"  =>
                    [
                        [
                            "first_name" => $order->getAttribute("billing_first_name"),
                            "last_name"  => $order->getAttribute("billing_last_name"),
                            "email"      => $order->getAttribute("email"),
                            "phone"      => $order->getAttribute("billing_phone"),
                        ],
                    ],
                ],
            ]
        );
        header("Content-Type: application/json");
        // Problème de cache où le navigateur décide de garder l"ancienne valeur
        // le paymentID n"est donc plus valide
        header("Cache-Control: no-cache, no-store, must-revalidate"); 
        echo json_encode($payment);
    }
}
