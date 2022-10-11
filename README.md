This module integrates a Magento 2 based webstore with the **[Checkout.com](https://www.checkout.com)** payment service.  
The module is **free** and **open source**.

## Demo videos
1. [A payment is **captured**  in the simpliest case](https://www.youtube.com/watch?v=63dyHw_u4wI)
2. [A payment is **captured with 3D Secure** validation](https://www.youtube.com/watch?v=P0NFkaXuXtU)
3. [A payment is **preauthorized** on an order placement, and then **captured** from Magento 2 backend](https://www.youtube.com/watch?v=iUC7CMhyHiM)
4. [A payment is **preauthorized** on an order placement, and then **captured** from the linked Checkout.com account](https://www.youtube.com/watch?v=13mH3zIx86A)
5. [A payment is **preauthorized** on an order placement, and then **voided** from Magento 2 backend](https://www.youtube.com/watch?v=rADpHE8XyY0)
6. [A payment is **preauthorized** on an order placement, and then **voided** from the linked Checkout.com account](https://www.youtube.com/watch?v=QArgVj4g-Sc)
7. [**Capturing** a **suspicious** (presumably fraudulent) payment from the Magento 2 backend](https://www.youtube.com/watch?v=t1NDr3eoS4g)
8. [**Capturing** a **suspicious** (presumably fraudulent) payment from the linked Checkout.com account](https://www.youtube.com/watch?v=tfAvP19_6WM)
9. [**Denying** a **suspicious** payment from the Magento 2 backend](https://www.youtube.com/watch?v=7odT-fqby8o)
10. [**Denying** a **suspicious** payment from the linked Checkout.com account](https://www.youtube.com/watch?v=nwWiJ_8kjFM)
11. [**Refunding** a payment from the Magento 2 backend](https://www.youtube.com/watch?v=JmDB2_cXx1U)
12. [**Refunding** a payment from the linked Checkout.com account](https://www.youtube.com/watch?v=nqDdcC2D3GU)

## How to install
[Hire me in Upwork](https://www.upwork.com/fl/mage2pro), and I will: 
- install and configure the module properly on your website
- answer your questions
- solve compatiblity problems with third-party checkout, shipping, marketing modules
- implement new features you need 

### 2. Self-installation
```
bin/magento maintenance:enable
rm -f composer.lock
composer clear-cache
composer require mage2pro/checkout.com:*
bin/magento setup:upgrade
bin/magento cache:enable
rm -rf var/di var/generation generated/code
bin/magento setup:di:compile
rm -rf pub/static/*
bin/magento setup:static-content:deploy -f en_US <additional locales>
bin/magento maintenance:disable
```

## How to update
```
bin/magento maintenance:enable
composer remove mage2pro/checkout.com
rm -f composer.lock
composer clear-cache
composer require mage2pro/checkout.com:*
bin/magento setup:upgrade
bin/magento cache:enable
rm -rf var/di var/generation generated/code
bin/magento setup:di:compile
rm -rf pub/static/*
bin/magento setup:static-content:deploy -f en_US <additional locales>
bin/magento maintenance:disable
```

