<?php

/**
 * Page to display Offers for a contact in the contact summary tab.
 * Shows only contributions with the Offer financial type.
 */
class CRM_Custominvoicenumbersandoffers_Page_OfferTab extends CRM_Core_Page {

  public function run() {
    $contactId = CRM_Utils_Request::retrieve('cid', 'Integer', $this, TRUE);
    $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');

    if (!$offerTypeId) {
      $this->assign('rows', []);
      $this->assign('noOfferType', TRUE);
      parent::run();
      return;
    }

    // Get localized labels
    $locale = CRM_Core_I18n::getLocale();
    $isGerman = (stripos($locale, 'de') === 0);

    $offerLabel = $isGerman ? 'Angebot' : 'Offer';
    $profileId = Civi::settings()->get('cinv_offer_profile_id');

    // Fetch offers for this contact
    $sql = "
      SELECT c.id, c.total_amount, c.currency, c.receive_date,
             c.contribution_status_id, c.invoice_number,
             ov.label AS status_label
      FROM civicrm_contribution c
      LEFT JOIN civicrm_option_value ov
        ON ov.value = c.contribution_status_id
        AND ov.option_group_id = (
          SELECT id FROM civicrm_option_group WHERE name = 'contribution_status'
        )
      WHERE c.contact_id = %1
        AND c.financial_type_id = %2
      ORDER BY c.receive_date DESC
    ";

    $dao = CRM_Core_DAO::executeQuery($sql, [
      1 => [$contactId, 'Integer'],
      2 => [$offerTypeId, 'Integer'],
    ]);

    $rows = [];
    while ($dao->fetch()) {
      $rows[] = [
        'id' => $dao->id,
        'invoice_number' => $dao->invoice_number,
        'receive_date' => CRM_Utils_Date::customFormat($dao->receive_date),
        'total_amount' => CRM_Utils_Money::format($dao->total_amount, $dao->currency),
        'status' => $dao->status_label,
        'view_url' => CRM_Utils_System::url('civicrm/contact/view/contribution', "reset=1&id={$dao->id}&cid={$contactId}&action=view"),
        'edit_url' => CRM_Utils_System::url('civicrm/contact/view/contribution', "reset=1&action=update&id={$dao->id}&cid={$contactId}"),
        'download_url' => CRM_Utils_System::url('civicrm/contribute/invoice', "reset=1&id={$dao->id}&cid={$contactId}"),
      ];
    }

    // Build the "New Offer" URL
    $newOfferUrl = CRM_Utils_System::url(
      'civicrm/contribute/add',
      "reset=1&action=add&context=standalone&financial_type_id={$offerTypeId}"
      . ($profileId ? "&gid={$profileId}" : '')
      . "&cid={$contactId}"
    );

    // Render inline — no .tpl file needed
    $offerNumberLabel = $isGerman ? 'Angebotsnummer' : 'Offer Number';
    $dateLabel = $isGerman ? 'Datum' : 'Date';
    $amountLabel = $isGerman ? 'Betrag' : 'Amount';
    $statusLabel = 'Status';
    $actionsLabel = $isGerman ? 'Aktionen' : 'Actions';
    $viewLabel = $isGerman ? 'Ansehen' : 'View';
    $editLabel = $isGerman ? 'Bearbeiten' : 'Edit';
    $downloadLabel = $isGerman ? 'Herunterladen' : 'Download';
    $newOfferLabel = $isGerman ? 'Neues Angebot' : 'New ' . $offerLabel;
    $noOffersLabel = $isGerman ? 'Keine Angebote vorhanden.' : 'No offers found.';

    $html = '<div class="crm-offer-tab">';
    $html .= '<div class="action-link">';
    $html .= '<a class="button" href="' . htmlspecialchars($newOfferUrl) . '">';
    $html .= '<span><i class="crm-i fa-plus" aria-hidden="true"></i> ' . htmlspecialchars($newOfferLabel) . '</span>';
    $html .= '</a></div>';

    if (empty($rows)) {
      $html .= '<div class="messages status no-popup"><p>' . htmlspecialchars($noOffersLabel) . '</p></div>';
    } else {
      $html .= '<table class="selector row-highlight">';
      $html .= '<thead><tr>';
      $html .= '<th>' . htmlspecialchars($offerNumberLabel) . '</th>';
      $html .= '<th>' . htmlspecialchars($dateLabel) . '</th>';
      $html .= '<th>' . htmlspecialchars($amountLabel) . '</th>';
      $html .= '<th>' . htmlspecialchars($statusLabel) . '</th>';
      $html .= '<th>' . htmlspecialchars($actionsLabel) . '</th>';
      $html .= '</tr></thead><tbody>';

      foreach ($rows as $row) {
        $html .= '<tr>';
        $html .= '<td><a class="crm-popup" href="' . htmlspecialchars($row['view_url']) . '">'
               . htmlspecialchars($row['invoice_number'] ?? '-') . '</a></td>';
        $html .= '<td>' . htmlspecialchars($row['receive_date']) . '</td>';
        $html .= '<td>' . $row['total_amount'] . '</td>';
        $html .= '<td>' . htmlspecialchars($row['status']) . '</td>';
        $html .= '<td>';
        $html .= '<span>';
        $html .= '<a class="action-item crm-popup" href="' . htmlspecialchars($row['view_url']) . '" title="' . htmlspecialchars($viewLabel) . '">'
               . htmlspecialchars($viewLabel) . '</a>';
        $html .= ' | <a class="action-item crm-popup" href="' . htmlspecialchars($row['edit_url']) . '" title="' . htmlspecialchars($editLabel) . '">'
               . htmlspecialchars($editLabel) . '</a>';
        $html .= '</span>';
        $html .= '</td>';
        $html .= '</tr>';
      }

      $html .= '</tbody></table>';
    }
    $html .= '</div>';

    echo $html;
    CRM_Utils_System::civiExit();
  }

}	
