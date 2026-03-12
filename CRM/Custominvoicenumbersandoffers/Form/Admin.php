<?php

class CRM_Custominvoicenumbersandoffers_Form_Admin extends CRM_Core_Form {

  private $isFirstSetup = FALSE;
  private $isConfigured = FALSE;

  public function preProcess() {
    // Handle full reset request (delete all extension data)
    $resetAll = CRM_Utils_Request::retrieve('reset_all', 'Boolean');
    if ($resetAll) {
      try {
        $this->performFullReset();
        CRM_Core_Session::setStatus(ts('All extension data has been deleted. The extension has been reset to initial state.'), ts('Reset Complete'), 'success');
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV] Error during reset: ' . $e->getMessage());
        CRM_Core_Session::setStatus(ts('Error during reset: %1', [1 => $e->getMessage()]), ts('Error'), 'error');
      }
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/custominvoicenumbersandoffers/admin'));
      return;
    }
    
    // Allow resetting setup via URL parameter for testing
    $resetSetup = CRM_Utils_Request::retrieve('reset_setup', 'Boolean');
    if ($resetSetup) {
      Civi::settings()->set('cinv_setup_completed', 0);
      CRM_Core_Session::setStatus(ts('Setup is unlocked. You can now reconfigure.'), ts('Reset'), 'info');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/custominvoicenumbersandoffers/admin'));
    }
    
    // Check if setup was completed (value must be exactly 1)
    $setupCompleted = Civi::settings()->get('cinv_setup_completed');
    $this->isConfigured = ($setupCompleted == 1 || $setupCompleted === 1);
    $this->isFirstSetup = !$this->isConfigured;
  }

  public function buildQuickForm() {
    // Check permissions
    if (!CRM_Core_Permission::check('administer CiviCRM')) {
      CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
    }

    // Status messages will be shown in template, not here to avoid form initialization issues

    // Determine if fields should be readonly (if already configured and not first setup)
    $fieldsReadOnly = $this->isConfigured ? 'readonly' : NULL;

    // --- Invoice Settings ---
    $invoiceAttrs = ['size' => 10];
    if ($fieldsReadOnly) $invoiceAttrs['readonly'] = $fieldsReadOnly;
    $this->add('text', 'invoice_prefix', ts('Invoice Prefix'), $invoiceAttrs);
    
    $formatAttrs = ['size' => 20, 'placeholder' => 'e.g. YYYYMMXX or YYYYMM-XX'];
    if ($fieldsReadOnly) $formatAttrs['readonly'] = $fieldsReadOnly;
    $this->add('text', 'invoice_format', ts('Invoice Number Format'), $formatAttrs);
    
    // Start Index first
    $indexAttrs = ['size'=>4];
    if ($fieldsReadOnly) $indexAttrs['readonly'] = $fieldsReadOnly;
    $this->add('text', 'invoice_start_index', ts('Start Index'), $indexAttrs);

    // Start Year - always read-only
    $yearAttrs = ['size'=>4, 'maxlength'=>4, 'readonly' => 'readonly'];
    $this->add('text', 'invoice_start_year', ts('Start Year'), $yearAttrs);

    // Start Month - always read-only, display current month (two digits)
    $monthAttrs = ['size' => 2, 'maxlength' => 2, 'readonly' => 'readonly', 'class' => 'invoice-month-select'];
    $this->add('text', 'invoice_start_month', ts('Start Month'), $monthAttrs);

    // --- Invoice Types Multi-Select ---
    // Financial types are always editable, even after setup is completed,
    // so users can add or remove types without resetting the sequences table.
    $typeAttrs = [
      'size' => 5,
      'style' => 'width:400px;',
    ];
    
    // Get invoice type options with error handling
    $invoiceTypeOptions = [];
    try {
      $invoiceTypeOptions = $this->getInvoiceTypeOptions();
      if (!is_array($invoiceTypeOptions)) {
        $invoiceTypeOptions = [];
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV] Error getting invoice type options: ' . $e->getMessage());
      $invoiceTypeOptions = [];
    }
    
    // Use advmultiselect for dual-listbox interface
    $this->add('advmultiselect', 'invoice_types', ts('Financial Types for Custom Numbers'), $invoiceTypeOptions, NULL, $typeAttrs);

    // --- Offers ---
    // Only lock offer fields if setup is configured AND offers are enabled
    $offersEnabled = (bool) Civi::settings()->get('cinv_enable_offers');
    $offerFieldsReadOnly = ($this->isConfigured && $offersEnabled) ? 'readonly' : NULL;
    
    // Checkbox cannot be readonly, so we always allow it but disable when configured
    $offersCheckboxAttrs = [];
    if ($this->isConfigured && $offersEnabled) {
      $offersCheckboxAttrs['disabled'] = 'disabled';
    }
    $this->add('checkbox', 'enable_offers', ts('Activate Offers'), $offersCheckboxAttrs);
    
    $offerPrefixAttrs = ['size' => 10, 'placeholder' => 'OFF_'];
    if ($offerFieldsReadOnly) $offerPrefixAttrs['readonly'] = $offerFieldsReadOnly;
    $this->add('text', 'offer_prefix', ts('Offer Prefix'), $offerPrefixAttrs);
    
    $offerFormatAttrs = ['size' => 20, 'placeholder' => 'e.g. YYYYMMXX or YYYYMM-XX'];
    if ($offerFieldsReadOnly) $offerFormatAttrs['readonly'] = $offerFieldsReadOnly;
    $this->add('text', 'offer_format', ts('Offer Number Format'), $offerFormatAttrs);

    // Offer: Start Index first, then Start Year and Start Month (both readonly)
    $offerIndexAttrs = ['size'=>4];
    if ($offerFieldsReadOnly) $offerIndexAttrs['readonly'] = $offerFieldsReadOnly;
    $this->add('text', 'offer_start_index', ts('Offer Start Index'), $offerIndexAttrs);

    $offerYearAttrs = ['size'=>4, 'maxlength'=>4, 'readonly' => 'readonly'];
    $this->add('text', 'offer_start_year', ts('Offer Start Year'), $offerYearAttrs);

    $offerMonthAttrs = ['size' => 2, 'maxlength' => 2, 'readonly' => 'readonly', 'class' => 'offer-month-select'];
    $this->add('text', 'offer_start_month', ts('Offer Start Month'), $offerMonthAttrs);

    // (Reset buttons removed — configuration changes now truncate sequences)

    // --- Submit ---
    // Always add buttons, but they will be hidden in template when setup is completed
    $buttonLabel = $this->isFirstSetup ? ts('Complete Setup') : ts('Save Changes');
    $buttons = [
      [
        'type' => 'submit',
        'name' => $buttonLabel,
        'isDefault' => TRUE,
      ],
    ];
    $this->addButtons($buttons);

    // --- Assign flags to template ---
    $this->assign('isFirstSetup', $this->isFirstSetup);
    $this->assign('isConfigured', $this->isConfigured);
    $this->assign('showButtons', true);  // Always show buttons (financial types are always editable)
  }

  public function setDefaultValues() {
    try {
      $invoicePrefix = Civi::settings()->get('cinv_invoice_prefix');
      $invoiceFormat = Civi::settings()->get('cinv_invoice_format');
      $invoiceStartYear = Civi::settings()->get('cinv_invoice_start_year');
      
      // Always display the current month for Start Month (two digits)
      $invoiceStartMonth = (int) date('m');
      $invoiceStartIndex = Civi::settings()->get('cinv_invoice_start_index');
      
      if (!$invoiceStartMonth || $invoiceStartMonth < 1 || $invoiceStartMonth > 12) {
        $invoiceStartMonth = (int) date('m');
      }
      
      // Offer Start Month always shows current month
      $offerStartMonth = (int) date('m');

      $invoiceTypes = Civi::settings()->get('cinv_invoice_types');
      
      // Ensure invoice_types is always an array of integers.
      // The setting may be stored as a JSON string, a double-encoded JSON string,
      // or already an array depending on how/when it was saved.
      if (is_string($invoiceTypes) && !empty($invoiceTypes)) {
        $decoded = json_decode($invoiceTypes, true);
        // Handle double-encoded JSON: json_decode returns a string again
        if (is_string($decoded)) {
          $decoded = json_decode($decoded, true);
        }
        $invoiceTypes = is_array($decoded) ? array_map('intval', $decoded) : [];
      } elseif (is_array($invoiceTypes)) {
        $invoiceTypes = array_map('intval', $invoiceTypes);
      } else {
        $invoiceTypes = [];
      }
      
      // If empty, default to all available financial types
      if (empty($invoiceTypes)) {
        $allTypes = $this->getInvoiceTypeOptions();
        $invoiceTypes = is_array($allTypes) ? array_keys($allTypes) : [];
      }
      
      $result = [
        'invoice_prefix'       => (string) ($invoicePrefix ?: 'INV_'),
        'invoice_format'       => (string) ($invoiceFormat ?: 'YYYYMMXX'),
        'invoice_start_year'   => (string) ($invoiceStartYear ?: date('Y')),
        'invoice_start_month'  => $invoiceStartMonth,
        'invoice_start_index'  => (string) ($invoiceStartIndex ?: '1'),
        'invoice_types'        => $invoiceTypes,
        'enable_offers'        => (int) (Civi::settings()->get('cinv_enable_offers') ?: 0),
        'offer_prefix'         => (string) (Civi::settings()->get('cinv_offer_prefix') ?: 'OFF_'),
        'offer_format'         => (string) (Civi::settings()->get('cinv_offer_format') ?: 'YYYYMMXX'),
        'offer_start_year'     => (string) (Civi::settings()->get('cinv_offer_start_year') ?: date('Y')),
        'offer_start_month'    => $offerStartMonth,
        'offer_start_index'    => (string) (Civi::settings()->get('cinv_offer_start_index') ?: '1'),
      ];
      
      return $result;
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV] Error in setDefaultValues: ' . $e->getMessage());
      // Return minimal defaults to prevent crash
      return [
        'invoice_prefix' => 'INV_',
        'invoice_format' => 'YYYYMMXX',
        'invoice_start_year' => date('Y'),
        'invoice_start_month' => (int) date('m'),
        'invoice_start_index' => '1',
        'invoice_types' => [],
        'enable_offers' => 0,
        'offer_prefix' => 'OFF_',
        'offer_format' => 'YYYYMMXX',
        'offer_start_year' => date('Y'),
        'offer_start_month' => (int) date('m'),
        'offer_start_index' => '1',
      ];
    }
  }

  public function postProcess() {
    // Capture current settings to detect whether configuration changed
    // during this request. If it changed, we'll truncate the sequences
    // table so new numbers start from configured Start Index.
    $prevSettings = [
      'invoice_prefix' => Civi::settings()->get('cinv_invoice_prefix'),
      'invoice_format' => Civi::settings()->get('cinv_invoice_format'),
      'invoice_start_year' => Civi::settings()->get('cinv_invoice_start_year'),
      'invoice_start_month' => Civi::settings()->get('cinv_invoice_start_month'),
      'invoice_start_index' => Civi::settings()->get('cinv_invoice_start_index'),
      'enable_offers' => Civi::settings()->get('cinv_enable_offers'),
      'offer_prefix' => Civi::settings()->get('cinv_offer_prefix'),
      'offer_format' => Civi::settings()->get('cinv_offer_format'),
      'offer_start_year' => Civi::settings()->get('cinv_offer_start_year'),
      'offer_start_month' => Civi::settings()->get('cinv_offer_start_month'),
      'offer_start_index' => Civi::settings()->get('cinv_offer_start_index'),
    ];
    

    // Check if this is an unlock request
    $unlockRequest = CRM_Utils_Request::retrieve('unlock_setup', 'Boolean');
    if ($unlockRequest && !$this->isFirstSetup) {
      Civi::settings()->set('cinv_setup_completed', 0);
      Civi::cache()->flush();
      CRM_Core_Session::setStatus(ts('Configuration unlocked. You can now modify the settings.'), ts('Unlocked'), 'success');
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/custominvoicenumbersandoffers/admin'));
    }

    $values = $this->exportValues();
    
    // Validate invoice format
    try {
      if (!empty($values['invoice_format'])) {
        $this->validateFormat($values['invoice_format'], 'Invoice');
      }
    } catch (Exception $e) {
      throw $e;
    }
    
    // Validate offer format if offers are enabled
    try {
      if (!empty($values['enable_offers']) && !empty($values['offer_format'])) {
        $this->validateFormat($values['offer_format'], 'Offer');
      }
    } catch (Exception $e) {
      throw $e;
    }

    // Store new values or keep existing ones if field was disabled and not submitted
    $invoicePrefix = !empty($values['invoice_prefix']) ? (string) $values['invoice_prefix'] : Civi::settings()->get('cinv_invoice_prefix');
    $invoiceFormat = !empty($values['invoice_format']) ? (string) $values['invoice_format'] : Civi::settings()->get('cinv_invoice_format');
    $invoiceStartYear = !empty($values['invoice_start_year']) ? (string) $values['invoice_start_year'] : Civi::settings()->get('cinv_invoice_start_year');
    $invoiceStartMonth = isset($values['invoice_start_month']) && $values['invoice_start_month'] !== '' ? (int) $values['invoice_start_month'] : (int) Civi::settings()->get('cinv_invoice_start_month');
    $invoiceStartIndex = !empty($values['invoice_start_index']) ? (string) $values['invoice_start_index'] : Civi::settings()->get('cinv_invoice_start_index');

    $enableOffers = !empty($values['enable_offers']) ? 1 : (int) Civi::settings()->get('cinv_enable_offers');
    $offerPrefix = !empty($values['offer_prefix']) ? (string) $values['offer_prefix'] : Civi::settings()->get('cinv_offer_prefix');
    $offerFormat = !empty($values['offer_format']) ? (string) $values['offer_format'] : Civi::settings()->get('cinv_offer_format');
    $offerStartYear = !empty($values['offer_start_year']) ? (string) $values['offer_start_year'] : Civi::settings()->get('cinv_offer_start_year');
    $offerStartMonth = isset($values['offer_start_month']) && $values['offer_start_month'] !== '' ? (int) $values['offer_start_month'] : (int) Civi::settings()->get('cinv_offer_start_month');
    $offerStartIndex = !empty($values['offer_start_index']) ? (string) $values['offer_start_index'] : Civi::settings()->get('cinv_offer_start_index');

    // --- Invoice ---
    Civi::settings()->set('cinv_invoice_prefix', $invoicePrefix ?: 'INV_');
    
    Civi::settings()->set('cinv_invoice_format', $invoiceFormat ?: 'YYYYMMXX');
    
    Civi::settings()->set('cinv_invoice_start_year', $invoiceStartYear ?: date('Y'));
    
    Civi::settings()->set('cinv_invoice_start_month', $invoiceStartMonth ?: (int) date('m'));
    
    Civi::settings()->set('cinv_invoice_start_index', $invoiceStartIndex ?: '1');

    // --- Save selected invoice types ---
    $invoiceTypes = [];
    if (isset($values['invoice_types'])) {
      if (is_array($values['invoice_types'])) {
        $invoiceTypes = $values['invoice_types'];
      } elseif (is_string($values['invoice_types'])) {
        // Try to decode if it's JSON
        $decoded = json_decode($values['invoice_types'], true);
        $invoiceTypes = is_array($decoded) ? $decoded : [$values['invoice_types']];
      }
    }
    
    // Ensure we have at least one type
    if (empty($invoiceTypes)) {
      $invoiceTypes = [1]; // Default to first financial type
    }
    
    Civi::settings()->set('cinv_invoice_types', json_encode($invoiceTypes));

    // --- Offers ---
    // Detect whether offers are being enabled for the first time
    $wasOffersEnabled = (bool) Civi::settings()->get('cinv_enable_offers');
    $nowOffersEnabled = (bool) $enableOffers;

    Civi::settings()->set('cinv_enable_offers', (int) $enableOffers);
    
    Civi::settings()->set('cinv_offer_prefix', $offerPrefix ?: 'OFF_');
    
    Civi::settings()->set('cinv_offer_format', $offerFormat ?: 'YYYYMMXX');
    
    Civi::settings()->set('cinv_offer_start_year', $offerStartYear ?: date('Y'));
    
    Civi::settings()->set('cinv_offer_start_month', $offerStartMonth ?: (int) date('m'));
    
    Civi::settings()->set('cinv_offer_start_index', $offerStartIndex ?: '1');

    // If offers are being enabled and have not been set up yet, run full setup
    if ($nowOffersEnabled && !Civi::settings()->get('cinv_offer_financial_type_id')) {
      custominvoicenumbersandoffers_setup_offers();
      CRM_Core_Session::setStatus(
        ts('Offers have been activated. A new financial type, payment method, financial account, message template, custom fields, offer overview and navigation menu entry have been created.'),
        ts('Offers Activated'),
        'success'
      );
    }

    // If configuration changed, truncate the full sequences table so the
    // next generated numbers start from the configured Start Index.
    $newSettings = [
      'invoice_prefix' => Civi::settings()->get('cinv_invoice_prefix'),
      'invoice_format' => Civi::settings()->get('cinv_invoice_format'),
      'invoice_start_year' => Civi::settings()->get('cinv_invoice_start_year'),
      'invoice_start_month' => Civi::settings()->get('cinv_invoice_start_month'),
      'invoice_start_index' => Civi::settings()->get('cinv_invoice_start_index'),
      'enable_offers' => Civi::settings()->get('cinv_enable_offers'),
      'offer_prefix' => Civi::settings()->get('cinv_offer_prefix'),
      'offer_format' => Civi::settings()->get('cinv_offer_format'),
      'offer_start_year' => Civi::settings()->get('cinv_offer_start_year'),
      'offer_start_month' => Civi::settings()->get('cinv_offer_start_month'),
      'offer_start_index' => Civi::settings()->get('cinv_offer_start_index'),
    ];

    if ($prevSettings !== $newSettings) {
      $table = 'civicrm_custom_number_sequences';
      try {
        CRM_Core_DAO::executeQuery("DELETE FROM {$table}");

        // Set AUTO_INCREMENT to invoice start index (safeguard: >=1)
        $ai = (int) Civi::settings()->get('cinv_invoice_start_index');
        if ($ai < 1) $ai = 1;
        $maxId = (int) CRM_Core_DAO::singleValueQuery("SELECT COALESCE(MAX(id), 0) FROM {$table}");
        $newAI = max($ai, $maxId + 1);
        CRM_Core_DAO::executeQuery("ALTER TABLE {$table} AUTO_INCREMENT = {$newAI}");
      } catch (Exception $e) {
      }
    }

    // Mark setup as completed only on first setup
    if ($this->isFirstSetup) {
      Civi::settings()->set('cinv_setup_completed', 1);
      CRM_Core_Session::setStatus(
        ts('Setup saved successfully! Your invoice and offer number configuration is now locked. You should only need to change this if you want to reset your numbering and start over or for testing purposes.'),
        ts('Setup saved'),
        'success'
      );
    } else {
      CRM_Core_Session::setStatus(ts('Settings saved.'), ts('Saved'), 'success');
    }

    // Clear all caches
    Civi::cache()->flush();
    Civi::cache('settings')->flush();
    
    // Also rebuild the menu and other CiviCRM caches
    CRM_Core_Invoke::rebuildMenuAndCaches();

    // Redirect to refresh the form
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/custominvoicenumbersandoffers/admin'));
  }



  private function validateFormat($format, $type = 'Invoice') {
    // Format must contain at least one X for sequential index
    if (!preg_match('/X+/i', $format)) {
    }
    
    // Valid tokens: YYYY, MM, M, X (and combinations/separators)
    if (!preg_match('/^[YYYYMMX\-_\/.]+$/i', $format)) {
    }
  }

  public function getTemplateFileName() {
    return 'CRM/Custominvoicenumbersandoffers/Form/Admin.tpl';
  }

  public function getModuleName() {
    return 'org.paradies.digital.custominvoicenumbersandoffers';
  }

  private function getMonthOptions() {
    $months = [];
    for ($i = 1; $i <= 12; $i++) {
      $months[$i] = str_pad($i, 2, '0', STR_PAD_LEFT);
    }
    return $months;
  }

  /**
   * Return available financial types for the multi-select.
   * Fetches all active financial types from the system - API v4.
   */
  private function getInvoiceTypeOptions() {
    $options = [];
    // Get the Offer financial type ID so we can exclude it from the list
    $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
    // Fallback: known localized names of the Offer financial type
    $offerTypeNames = ['Offer', 'Angebot', "l'offre", 'oferta'];
    try {
      $financialTypes = \Civi\Api4\FinancialType::get(FALSE)
        ->addWhere('is_active', '=', TRUE)
        ->execute();
      
      foreach ($financialTypes as $ft) {
        if (isset($ft['id']) && isset($ft['name'])) {
          // Hide the Offer financial type — offers have their own number cycle
          // Primary check: by stored ID
          if ($offerTypeId && $ft['id'] == $offerTypeId) {
            continue;
          }
          // Fallback check: by known localized names (in case setting is missing)
          if (!$offerTypeId && in_array($ft['name'], $offerTypeNames, true)) {
            continue;
          }
          $options[$ft['id']] = $ft['name'];
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV] Error fetching financial types: ' . $e->getMessage());
    }
    
    // Always return an array, even if empty
    return is_array($options) ? $options : [];
  }

  public function getDefaultEntity() {
    return NULL;
  }

  public function getEntityId() {
    return NULL;
  }

  /**
   * Perform a complete reset of all extension data
   * This includes:
   * - All offers (contributions with Offer financial type)
   * - Custom fields and custom group
   * - Financial type, account, payment instrument
   * - Profile
   * - Number sequences
   * - Message template modifications (custom field references)
   * - Settings
   */
  private function performFullReset() {
    try {
      // Get IDs we need to delete
      $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
      $offerAccountId = Civi::settings()->get('cinv_offer_financial_account_id');
      $offerPaymentInstrumentId = Civi::settings()->get('cinv_offer_payment_instrument_id');
      $offerProfileId = Civi::settings()->get('cinv_offer_profile_id');
      
      // 1. Delete all offers (contributions with Offer financial type)
      if ($offerTypeId) {
        try {
          $offers = \Civi\Api4\Contribution::get(FALSE)
            ->addSelect('id')
            ->addWhere('financial_type_id', '=', $offerTypeId)
            ->execute();
          
          $deleteCount = 0;
          foreach ($offers as $offer) {
            \Civi\Api4\Contribution::delete(FALSE)
              ->addWhere('id', '=', $offer['id'])
              ->execute();
            $deleteCount++;
          }
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV RESET] Error deleting offers: ' . $e->getMessage());
        }
      }
      
      // 2. Delete number sequences
      try {
        CRM_Core_DAO::executeQuery('TRUNCATE TABLE civicrm_custom_number_sequences');
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV RESET] Error truncating sequences: ' . $e->getMessage());
      }
      
      // 3. Delete profile
      if ($offerProfileId) {
        try {
          \Civi\Api4\UFGroup::delete(FALSE)
            ->addWhere('id', '=', $offerProfileId)
            ->execute();
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV RESET] Error deleting profile: ' . $e->getMessage());
        }
      }
      
      // 4. Delete custom fields and custom group
      try {
        $customGroup = \Civi\Api4\CustomGroup::get(FALSE)
          ->addWhere('name', '=', 'offer_details')
          ->execute()
          ->first();
        
        if ($customGroup) {
          // Delete custom fields first
          $customFields = \Civi\Api4\CustomField::get(FALSE)
            ->addWhere('custom_group_id', '=', $customGroup['id'])
            ->execute();
          
          foreach ($customFields as $field) {
            \Civi\Api4\CustomField::delete(FALSE)
              ->addWhere('id', '=', $field['id'])
              ->execute();
          }
          
          // Then delete the group
          \Civi\Api4\CustomGroup::delete(FALSE)
            ->addWhere('id', '=', $customGroup['id'])
            ->execute();
        }
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV RESET] Error deleting custom fields: ' . $e->getMessage());
      }
      
      // 5. Delete payment instrument
      if ($offerPaymentInstrumentId) {
        try {
          \Civi\Api4\OptionValue::delete(FALSE)
            ->addWhere('value', '=', $offerPaymentInstrumentId)
            ->addWhere('option_group_id:name', '=', 'payment_instrument')
            ->execute();
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV RESET] Error deleting payment instrument: ' . $e->getMessage());
        }
      }
      
      // 6. Delete EntityFinancialAccount relationships for Offer financial type
      if ($offerTypeId) {
        try {
          $relations = \Civi\Api4\EntityFinancialAccount::get(FALSE)
            ->addWhere('entity_table', '=', 'civicrm_financial_type')
            ->addWhere('entity_id', '=', $offerTypeId)
            ->execute();
          
          foreach ($relations as $rel) {
            \Civi\Api4\EntityFinancialAccount::delete(FALSE)
              ->addWhere('id', '=', $rel['id'])
              ->execute();
          }
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV RESET] Error deleting EntityFinancialAccount: ' . $e->getMessage());
        }
      }
      
      // 7. Delete financial type
      if ($offerTypeId) {
        try {
          \Civi\Api4\FinancialType::delete(FALSE)
            ->addWhere('id', '=', $offerTypeId)
            ->execute();
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV RESET] Error deleting financial type: ' . $e->getMessage());
        }
      }
      
      // 8. Delete financial account
      if ($offerAccountId) {
        try {
          \Civi\Api4\FinancialAccount::delete(FALSE)
            ->addWhere('id', '=', $offerAccountId)
            ->execute();
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV RESET] Error deleting financial account: ' . $e->getMessage());
        }
      }
      
      // 8b. Clean up message templates (remove custom field references and offer template)
      try {
        $offerTemplateId = Civi::settings()->get('cinv_offer_message_template_id');
        if ($offerTemplateId) {
          \Civi\Api4\MessageTemplate::delete(FALSE)
            ->addWhere('id', '=', $offerTemplateId)
            ->execute();
        } else {
          // Fallback delete by name if setting is missing
          $offerTemplate = \Civi\Api4\MessageTemplate::get(FALSE)
            ->addWhere('name', '=', 'contribution_offer_receipt')
            ->execute()
            ->first();
          if ($offerTemplate) {
            \Civi\Api4\MessageTemplate::delete(FALSE)
              ->addWhere('id', '=', $offerTemplate['id'])
              ->execute();
          }
        }

        // Find the contribution_invoice_receipt template specifically (legacy cleanup)
        $template = \Civi\Api4\MessageTemplate::get(FALSE)
          ->addSelect('id', 'msg_text', 'msg_html', 'msg_subject')
          ->addWhere('workflow_name', '=', 'contribution_invoice_receipt')
          ->execute()
          ->first();
        
        if ($template && $offerTypeId) {
          $msgText = $template['msg_text'] ?? '';
          $msgHtml = $template['msg_html'] ?? '';
          $msgSubject = $template['msg_subject'] ?? '';
          $modified = FALSE;
          
          // Check if template contains our modifications
          if (strpos($msgText, 'offer_intro_text') !== FALSE || 
              strpos($msgText, 'offer_addition') !== FALSE ||
              strpos($msgHtml, 'offer_intro_text') !== FALSE || 
              strpos($msgHtml, 'offer_addition') !== FALSE ||
              strpos($msgHtml, 'financial_type_id == ' . $offerTypeId) !== FALSE) {
            
            // Remove the conditional blocks containing custom fields
            // Pattern: {if $contribution.financial_type_id == XXX}...{/if} blocks containing our custom fields
            $cleanedText = preg_replace(
              '/\{if[^}]*financial_type_id[^}]*' . $offerTypeId . '[^}]*\}.*?offer_(intro_text|addition).*?\{\/if\}/s',
              '',
              $msgText
            );
            
            $cleanedHtml = preg_replace(
              '/\{if[^}]*financial_type_id[^}]*' . $offerTypeId . '[^}]*\}.*?offer_(intro_text|addition).*?\{\/if\}/s',
              '',
              $msgHtml
            );
            
            // Revert conditional Invoice/Offer labels back to just "Invoice"
            $conditionalPatterns = [
              // Pattern: {if $contribution.financial_type_id == X}{ts}OFFER{/ts}{else}{ts}INVOICE{/ts}{/if} → {ts}INVOICE{/ts}
              '/\{if[^}]*financial_type_id[^}]*' . $offerTypeId . '[^}]*\}\{ts\}OFFER\{\/ts\}\{else\}\{ts\}INVOICE\{\/ts\}\{\/if\}/' => '{ts}INVOICE{/ts}',
              
              // Pattern: {if $contribution.financial_type_id == X}{ts}Offer Date:{/ts}{else}{ts}Invoice Date:{/ts}{/if} → {ts}Invoice Date:{/ts}
              '/\{if[^}]*financial_type_id[^}]*' . $offerTypeId . '[^}]*\}\{ts\}Offer Date:\{\/ts\}\{else\}\{ts\}Invoice Date:\{\/ts\}\{\/if\}/' => '{ts}Invoice Date:{/ts}',
              
              // Pattern: {if $contribution.financial_type_id == X}{ts}Offer Number:{/ts}{else}{ts}Invoice Number:{/ts}{/if} → {ts}Invoice Number:{/ts}
              '/\{if[^}]*financial_type_id[^}]*' . $offerTypeId . '[^}]*\}\{ts\}Offer Number:\{\/ts\}\{else\}\{ts}Invoice Number:\{\/ts\}\{\/if\}/' => '{ts}Invoice Number:{/ts}',
              
              // Pattern: {if $contribution.financial_type_id == X}{ts}Offer Receipt{/ts}{else}{ts}Invoice Receipt{/ts}{/if} → {ts}Invoice Receipt{/ts}
              '/\{if[^}]*financial_type_id[^}]*' . $offerTypeId . '[^}]*\}\{ts\}Offer Receipt\{\/ts\}\{else\}\{ts}Invoice Receipt\{\/ts\}\{\/if\}/' => '{ts}Invoice Receipt{/ts}',
            ];
            
            foreach ($conditionalPatterns as $pattern => $replacement) {
              $cleanedText = preg_replace($pattern, $replacement, $cleanedText ?? $msgText);
              $cleanedHtml = preg_replace($pattern, $replacement, $cleanedHtml ?? $msgHtml);
            }
            
            // Revert subject line to default
            $defaultSubject = '{if $component == \'event\'}Event Registration Invoice{else}Contribution Invoice{/if}{if $title}: $title{/if} - {$contact.display_name}';
            if (strpos($msgSubject, 'financial_type_id') !== FALSE || strpos($msgSubject, 'Offer') !== FALSE) {
              $cleanedSubject = $defaultSubject;
            } else {
              $cleanedSubject = $msgSubject;
            }
            
            // Remove empty HTML elements left behind
            $cleanedText = preg_replace('/<(p|div)[^>]*>\s*<\/\1>/i', '', $cleanedText ?? $msgText);
            $cleanedHtml = preg_replace('/<(p|div)[^>]*>\s*<\/\1>/i', '', $cleanedHtml ?? $msgHtml);
            
            // Update the template
            \Civi\Api4\MessageTemplate::update(FALSE)
              ->addWhere('id', '=', $template['id'])
              ->addValue('msg_text', $cleanedText ?? $msgText)
              ->addValue('msg_html', $cleanedHtml ?? $msgHtml)
              ->addValue('msg_subject', $cleanedSubject)
              ->execute();
            
            $modified = TRUE;
          }
        }
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV RESET] Error cleaning message templates: ' . $e->getMessage());
      }
      
      // 9. Clear all settings
      $settingsToDelete = [
        'cinv_invoice_prefix',
        'cinv_invoice_format',
        'cinv_invoice_start_year',
        'cinv_invoice_start_month',
        'cinv_invoice_start_index',
        'cinv_invoice_types',
        'cinv_enable_offers',
        'cinv_offer_prefix',
        'cinv_offer_format',
        'cinv_offer_start_year',
        'cinv_offer_start_month',
        'cinv_offer_start_index',
        'cinv_offer_financial_type_id',
        'cinv_offer_financial_account_id',
        'cinv_offer_payment_instrument_id',
        'cinv_offer_profile_id',
        'cinv_offer_message_template_id',
        'cinv_setup_completed',
      ];
      
      foreach ($settingsToDelete as $setting) {
        Civi::settings()->set($setting, NULL);
      }
      
      // 10. Clear cache
      Civi::cache()->flush();
      
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV RESET] Error during reset: ' . $e->getMessage());
      CRM_Core_Session::setStatus(ts('Error during reset: %1', [1 => $e->getMessage()]), ts('Error'), 'error');
    }
  }

}
