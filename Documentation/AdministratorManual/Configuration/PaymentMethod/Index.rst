.. include:: ../../../Includes.txt

Payment Method Configuration
============================

The payment method for Payrexx is configured like any other payment method. There are all configuration options
from Cart available.

::

   plugin.tx_cart {
       payments {
           options {
               2 {
                   provider = PAYREXX
                   title = Payrexx
                   extra = 0.00
                   taxClassId = 1
                   status = open
                   available.from = 0.01
               }
           }
       }
   }

|

.. container:: table-row

   Property
      plugin.tx_cart.payments.options.n.provider
   Data type
      string
   Description
      Defines that the payment provider for Payrexx should be used.
      The value must be PAYREXX (all uppercase).
      This information is mandatory and ensures that the extension Cart Payrexx takes control and for the authorization of payment the user forwards to the Payrexx site.

.. NOTE::
   Further configuration options can be found in the extcode/cart documentation
   at `Payment Methods <https://docs.typo3.org/p/extcode/cart/main/en-us/AdministratorManual/Configuration/PaymentMethods/Index.html>`_
   and `Payment Methods Main Configuration <https://docs.typo3.org/p/extcode/cart/main/en-us/AdministratorManual/Configuration/PaymentMethods/MainConfiguration/Index.html>`_.