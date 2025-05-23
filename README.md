<h1 style="text-align: center">Worldline for OXID eShop</h1>

## Installation

- Open a shell and change to the root directory of the store (where the composer.json file is located).

  Example:

  <code>cd /var/www/oxideshop/</code>


- Execute the following command:

  <code>composer require wl-online-payments-direct/plugin-oxid7</code>  
  <code>composer require wl-online-payments-direct/sdk-php</code>

## Installation (manual)

In the root shop directory :

- Copy content of module repository into: **vendor/wl-online-payments-direct/plugin-oxid7**

- Run command: <pre>composer require wl-online-payments-direct/sdk-php</pre>

- Run commands:
<pre>
  vendor/bin/oe-console oe:module:install vendor/wl-online-payments-direct/plugin-oxid7
  vendor/bin/oe-console oe:module:install-assets
  vendor/bin/oe-console oe:module:activate fcwlop
</pre>

IF class not found issue:

Edit < shoproot >/composer.json, section "autoload" as follows :
  <pre>"autoload": {
    "psr-4": {
      "FC\\FCWLOP\\": "./vendor/wl-online-payments-direct/plugin-oxid7"
    }
  }</pre>
<pre>composer dump-autoload</pre>

## Configuration
To use the module after activation : \
Navigate to : Admin > Extensions > Modules > Worldline Online Payment direct > Settings

- Basic configuration :
  - You will need Worldline account credentials. If you don't have an account yet, use the button to reach Worldine website and open one. 
  - Mode : select the operation mode, Live or Sandbox (testing).
  - Enter the PspId, which is your Worldline account id.
  - Create API Key/Secret on your Worldline portal ([https://merchant-portal.preprod.worldline-solutions.com/developer/payment-api](https://merchant-portal.preprod.worldline-solutions.com/developer/payment-api) for testing context) and report them into API Key and API Secret fields.
  - Create Webhook Key/Secret on your Worldline portal ([https://merchant-portal.preprod.worldline-solutions.com/developer/webhooks](https://merchant-portal.preprod.worldline-solutions.com/developer/webhooks) for testing context) and report them into Webhook Key and Webhook Secret fields.
  - On Worldline portal Webhook configuration, you need to fill a URL for the system to reach your shop. You can copy it from the module configuration zone, with the "Webhook exposed URL - Copy to clipboard" button.
  - Fill the API endpoints for Live and Test environment.
      - LIVE : https://payment.direct.worldline-solutions.com
      - SANDBOX : https://payment.preprod.direct.worldline-solutions.com

**Save config at that point.**

- Payment methods configuration :
  - First click on the "Payment methods setup / update" button to install the payment methods.
  - Capture mode : select between Direct sales (authorization + auto capture) or Manual (preauthorization then manual capture)
  - Select the delay before the cronjob automatically cancels unfinished/error orders. Pick "Inactive" to disable this feature.
  - Select the checkout version for the credit cards
      - Redirected : Using the Worldine Hosted checkout page, like other methods
      - On payment page : Collects card data and proceed to the transaction directly on the shop side (hosted iframes).
  - Group credit cards : Option to show the credit cards as one entry on shop side and give the choice on Worldline hosted checkout page ("Redirected" checkout type only)


Payment methods can be then activated as any other Oxid payment method.
