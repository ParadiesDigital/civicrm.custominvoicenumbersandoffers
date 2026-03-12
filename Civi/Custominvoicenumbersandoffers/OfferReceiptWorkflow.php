<?php

namespace Civi\Custominvoicenumbersandoffers;

use Civi\WorkflowMessage\GenericWorkflowMessage;

/**
 * WorkflowMessage class for the Offer Receipt template.
 *
 * Registers 'contribution_offer_receipt' workflow so CiviCRM
 * can resolve contribution tokens during template preview.
 *
 * @support template-only
 */
class OfferReceiptWorkflow extends GenericWorkflowMessage {

  const WORKFLOW = 'contribution_offer_receipt';

  /**
   * The contribution ID.
   *
   * @var int|null
   * @scope tokenContext as contributionId
   */
  public $contributionId;

  /**
   * The contribution record.
   *
   * @var array|null
   * @scope tokenContext
   */
  public $contribution;

  /**
   * Line items for the offer.
   *
   * @var array
   * @scope tplParams as lineItems
   */
  public $lineItems = [];

  /**
   * Tax rate breakdown for display.
   *
   * @var array
   * @scope tplParams as taxRateBreakdown
   */
  public $taxRateBreakdown = [];

  /**
   * Currency code.
   *
   * @var string|null
   * @scope tplParams as currency
   */
  public $currency;

  /**
   * Set the contribution data.
   *
   * @param array $contribution
   * @return $this
   */
  public function setContribution(array $contribution): self {
    $this->contribution = $contribution;
    $this->contributionId = $contribution['id'] ?? NULL;
    return $this;
  }

  /**
   * Get the contribution data.
   *
   * @return array|null
   */
  public function getContribution(): ?array {
    return $this->contribution;
  }

}
