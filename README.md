<strong>The plugin is in beta stage.</strong>   
<strong>Composer Installation steps are not fully working.</strong>   
<strong>Module has to be manually installed for now</strong>


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
  - < WIP >

**Save config at that point.**

- Payment methods configuration :
  - < WIP >


Payment methods can be then activated as any other Oxid payment method.
