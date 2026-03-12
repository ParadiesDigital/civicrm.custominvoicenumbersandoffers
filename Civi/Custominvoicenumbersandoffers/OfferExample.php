<?php

namespace Civi\Custominvoicenumbersandoffers;

use Civi\WorkflowMessage\WorkflowMessageExample;

/**
 * Provides example/preview data for the Offer Receipt message template.
 *
 * Allows the CiviCRM Message Template admin UI to render a preview
 * of the offer template with sample John Doe data.
 */
class OfferExample extends WorkflowMessageExample {

  /**
   * {@inheritDoc}
   */
  public function getExamples(): iterable {
    yield [
      'name' => 'workflow/contribution_offer_receipt/OfferExample',
      'title' => ts('Offer - John Doe (EUR)'),
      'tags' => ['preview'],
      'workflow' => 'contribution_offer_receipt',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function build(array &$example): void {
    try {
      $contact = [
        'contact_id' => 1,
        'contact_type' => 'Individual',
        'display_name' => 'John Doe',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'prefix_id:label' => 'Mr.',
        'email' => 'john.doe@example.com',
        'street_address' => 'Musterstraße 1',
        'supplemental_address_1' => '',
        'city' => 'Berlin',
        'postal_code' => '10115',
        'state_province_name' => 'Berlin',
        'country' => 'Germany',
      ];

      $contribution = [
        'id' => 1,
        'contact_id' => 1,
        'total_amount' => '1500.00',
        'tax_amount' => '0.00',
        'net_amount' => '1500.00',
        'fee_amount' => '0.00',
        'currency' => 'EUR',
        'receive_date' => date('Y-m-d H:i:s'),
        'contribution_status_id' => 1,
        'contribution_status_id:name' => 'Completed',
        'financial_type_id' => 1,
        'payment_instrument_id' => 4,
        'invoice_number' => 'OFF-2026-0001',
        'source' => 'Offer Preview',
        'trxn_id' => 'PREVIEW-001',
      ];

      $lineItems = [
        [
          'title' => 'Website Design',
          'label' => 'Website Design',
          'qty' => 1,
          'unit_price' => '1200.00',
          'line_total' => '1200.00',
          'tax_amount' => '0.00',
          'tax_rate' => '0',
          'field_title' => 'Website Design',
          'financial_type_id' => 1,
        ],
        [
          'title' => 'Hosting (12 months)',
          'label' => 'Hosting (12 months)',
          'qty' => 12,
          'unit_price' => '25.00',
          'line_total' => '300.00',
          'tax_amount' => '0.00',
          'tax_rate' => '0',
          'field_title' => 'Hosting (12 months)',
          'financial_type_id' => 1,
        ],
      ];

      // All fields in modelProps must be declared as properties on OfferReceiptWorkflow.
      // Fields with @scope tplParams are automatically passed to Smarty.
      // Fields with @scope tokenContext are passed to the token processor.
      $example['data'] = [
        'workflow' => 'contribution_offer_receipt',
        'modelProps' => [
          'contributionId' => $contribution['id'],
          'contribution' => $contribution,
          'lineItems' => $lineItems,
          'taxRateBreakdown' => [],
          'currency' => $contribution['currency'],
        ],
        'tokenContext' => [
          'contactId' => $contact['contact_id'],
          'contributionId' => $contribution['id'],
        ],
      ];
    }
    catch (\Throwable $e) {
      \CRM_Core_Error::debug_log_message('[CINV] OfferExample::build() error: ' . $e->getMessage());
      \CRM_Core_Error::debug_log_message('[CINV] OfferExample::build() trace: ' . $e->getTraceAsString());
    }
  }

}
