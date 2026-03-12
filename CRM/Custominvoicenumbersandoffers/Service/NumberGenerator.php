<?php

class CRM_Custominvoicenumbersandoffers_Service_NumberGenerator
{

  public static function generate(int $contributionId, bool $isOffer = FALSE): void
  {
    $table = 'civicrm_custom_number_sequences';

    $type = $isOffer ? 'offer' : 'invoice';
    
    // Verify contribution exists
    $contributionExists = CRM_Core_DAO::singleValueQuery(
      "SELECT id FROM civicrm_contribution WHERE id = %1",
      [1 => [$contributionId, 'Integer']]
    );
    
    if (!$contributionExists) {
      CRM_Core_Error::debug_log_message(
        "[CINV] ERROR: Contribution {$contributionId} not found - cannot generate {$type} number"
      );
      return;
    }

    // For invoices, check if the financial type is selected for custom numbering
    if (!$isOffer) {
      // Get the contribution's financial_type_id
      $financialTypeId = CRM_Core_DAO::singleValueQuery(
        "SELECT financial_type_id FROM civicrm_contribution WHERE id = %1",
        [1 => [$contributionId, 'Integer']]
      );

      // Get selected invoice types
      $selectedTypes = Civi::settings()->get('cinv_invoice_types');
      if (!is_array($selectedTypes)) {
        $selectedTypes = $selectedTypes ? (array) json_decode($selectedTypes, true) : [];
      }

      // Skip if no financial type is selected for invoices or this type is not selected
      if (empty($selectedTypes) || !in_array($financialTypeId, $selectedTypes)) {
        return;
      }
    }

    // Load settings for current type
    $prefix = (string) (Civi::settings()->get("cinv_{$type}_prefix") ?? '');
    $format = (string) (Civi::settings()->get("cinv_{$type}_format") ?? 'YYYYMMXX');
    $startYear = (int) (Civi::settings()->get("cinv_{$type}_start_year") ?? date('Y'));
    $startMonth = (int) (Civi::settings()->get("cinv_{$type}_start_month") ?? date('m'));
    $startIndex = (int) (Civi::settings()->get("cinv_{$type}_start_index") ?? 1);

    $currentYear = (int) date('Y');
    $currentMonth = (int) date('m');
    $prefix = (string) ($prefix ?: '');

    // Determine the active cycle year/month based on format
    // Check if format contains MM or M for monthly reset
    $hasMonthlyReset = (strpos($format, 'MM') !== FALSE || strpos($format, 'M') !== FALSE);

    // Determine which cycle period we're in
    if (!$hasMonthlyReset) {
      // Yearly cycle - use current year, month is 0 (not used in queries)
      $cycleYear = $currentYear;
      $cycleMonth = 0;
    } else {
      // Monthly cycle - use current year and month
      $cycleYear = $currentYear;
      $cycleMonth = $currentMonth;
    }

    // Build query to get last index in current cycle
    $where = "type = %1 AND year = %2";
    $params = [
      1 => [$type, 'String'],
      2 => [$cycleYear, 'Integer'],
    ];

    if ($hasMonthlyReset) {
      $where .= " AND month = %3";
      $params[3] = [$cycleMonth, 'Integer'];
    }

    $sql = "
  SELECT MAX(sequence_index)
  FROM {$table}
  WHERE {$where}
";

    $lastIndex = CRM_Core_DAO::singleValueQuery($sql, $params);

    // Determine next index.
    // If there's a previous sequence entry, normally we'd increment it.
    // However, if the configured startIndex is larger than the next sequential
    // value, we should use the configured startIndex instead. This allows
    // resetting numbers to a higher value via the admin UI.
    if ($lastIndex !== NULL && $lastIndex !== FALSE && $lastIndex !== '') {
      $last = (int) $lastIndex;
      $nextIndex = max($last + 1, max(1, (int) $startIndex));
    } else {
      // No previous entries in this cycle, use startIndex (minimum 1)
      $nextIndex = max(1, (int) $startIndex);
    }

    // Count X's in format to determine padding
    $xCount = strlen($format) - strlen(str_replace(['X', 'x'], '', $format));
    $formattedIndex = str_pad($nextIndex, max(1, $xCount), '0', STR_PAD_LEFT);

    // Replace tokens in format - use simple str_replace instead of regex
    $cycleMonthInt = (int) $cycleMonth;
    $paddedMonth = $cycleMonthInt > 0 ? str_pad((string) $cycleMonthInt, 2, '0', STR_PAD_LEFT) : '00';

    $finalNumber = $prefix . str_replace(
      ['YYYY', 'MM', 'M'],
      [(string) $cycleYear, $paddedMonth, (string) $cycleMonthInt],
      $format
    );

    // X-Blöcke korrekt ersetzen
    $finalNumber = preg_replace_callback('/X+/i', function ($matches) use ($formattedIndex) {
      $length = strlen($matches[0]);
      return str_pad($formattedIndex, $length, '0', STR_PAD_LEFT);
    }, $finalNumber);

    // --- Insert into custom table ---
    CRM_Core_DAO::executeQuery("
  INSERT INTO {$table}
  (contribution_id, type, prefix, year, month, sequence_index, number_value)
  VALUES (%1,%2,%3,%4,%5,%6,%7)
", [
      1 => [$contributionId, 'Integer'],
      2 => [$type, 'String'],
      3 => [$prefix, 'String'],
      4 => [$cycleYear, 'Integer'],
      5 => [$cycleMonth, 'Integer'],
      6 => [$nextIndex, 'Integer'],
      7 => [$finalNumber, 'String'],
    ]);


    // --- Update contribution ---
    // Store both invoice and offer numbers in the standard invoice_number field
    try {
      CRM_Core_DAO::executeQuery("
        UPDATE civicrm_contribution
        SET invoice_number = %1
        WHERE id = %2
      ", [
        1 => [$finalNumber, 'String'],
        2 => [$contributionId, 'Integer'],
      ]);
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message(
        "[CINV] ERROR updating invoice_number for contribution {$contributionId}: " . $e->getMessage()
      );
    }
  }
}