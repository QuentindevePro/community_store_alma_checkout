<?php

namespace Concrete\Package\CommunityStoreAlmaCheckout;

use \Concrete\Core\Package\Package;
use \Concrete\Core\Support\Facade\Route;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as PaymentMethod;
use Whoops\Exception\ErrorException;


class Controller extends Package
{
    protected $pkgHandle = 'community_store_alma_checkout';
    protected $appVersionRequired = '8.0';
    protected $pkgVersion = '1.2';
    protected $packageDependencies = ['community_store'=>'2.5'];

    public function on_start()
    {
        require __DIR__ . '/vendor/autoload.php';
        Route::register('/checkout/almacheckoutcreatesession','\Concrete\Package\CommunityStoreAlmaCheckout\Src\CommunityStore\Payment\Methods\CommunityStoreAlmaCheckout\CommunityStoreAlmaCheckoutPaymentMethod::createSession');
        Route::register('/checkout/almacheckoutresponse','\Concrete\Package\CommunityStoreAlmaCheckout\Src\CommunityStore\Payment\Methods\CommunityStoreAlmaCheckout\CommunityStoreAlmaCheckoutPaymentMethod::chargeResponse');
    }

    protected $pkgAutoloaderRegistries = [
        'src/CommunityStore' => '\Concrete\Package\CommunityStoreAlmaCheckout\Src\CommunityStore',
    ];

    public function getPackageDescription()
    {
        return t("Alma Checkout Payment Method for Community Store");
    }

    public function getPackageName()
    {
        return t("Alma Checkout Payment Method");
    }

    public function install()
    {
        if (!@include(__DIR__ . '/vendor/autoload.php')) {
            throw new ErrorException(t('Third party libraries not installed. Use a release version of this add-on with libraries pre-installed, or run composer install against the package folder.'));
        }

        $pkg = parent::install();
        $pm = new PaymentMethod();
        $pm->add('community_store_alma_checkout','Alma Checkout',$pkg);
    }
    
    public function uninstall()
    {
        $pm = PaymentMethod::getByHandle('community_store_alma_checkout');
        if ($pm) {
            $pm->delete();
        }
        $pkg = parent::uninstall();
    }

}
