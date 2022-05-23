.. include:: ../../../Includes.txt

Main Configuration
==================

The plugin needs to know the instanceName and the secret.

::

   plugin.tx_cartpayrexx {
       instanceName =
       secret =
   }

|

.. container:: table-row

   Property
         plugin.tx_cartpayrexx.instanceName
   Data type
         string
   Description
         Sets the REST API instanceName. Take the value from your Payrexx account.

.. container:: table-row

   Property
         plugin.tx_cartpayrexx.secret
   Data type
         string
   Description
         Sets the REST API secret. Take the value from your Payrexx account.

.. NOTE::
   Furthermore, the email configuration for the store must be done via
   TypoScript, because the webhook does not evaluate the plugin
   configuration of the shopping cart (see: `Email Configuration <https://docs.typo3.org/p/extcode/cart/main/en-us/AdministratorManual/Configuration/Order/Mail/Index.html>`_)