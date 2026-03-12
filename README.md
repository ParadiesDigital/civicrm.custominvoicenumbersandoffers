# custominvoicenumbersandoffers

This extension adds functionality for custom invoice number cycles in a user chosen format and, a new contribution type 'offers' with it's own number cycle, menu structure and custom displays.

It creates a custom table to the database, where the numbers are stored. It also creates a new financial type, payment method, financial account, message template, custom fields, overview page and navigation menu entry, when offers are activated. Please alter the message template for your own needs. It copies the invoice message template on first install, changes some labels and adds custom fields. When creating new offers, you can add custom text above and below the line items table. Position of the text may be changed in the message templates.

To add different line items to invoices or offers, it is recommended to also install the Line Item Editor Extension.

Uninstalling the extension will reset and delete all changes, except for the numbers for invoices, that have been written using the extension.
Deleting the last invoice or offer in the cycle will set the counter back to the number before. Works only for the last, so no gaps in the number cycle should occure.

This is an [extension for CiviCRM](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/), licensed under [AGPL-3.0](LICENSE.txt).

## Getting Started

Before using the extension, please enable "Tax and Invoicing" in the CiviContribute Component Settings.

Navigate to "Administer > CiviContribute > Custom Invoice numbers and offers" to setup the extension.

New invoice numbers will be available after. Offers will get there own menu when activated.

## Known Issues
