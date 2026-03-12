<?php

require_once 'custominvoicenumbersandoffers.civix.php';

// TODO: Offers tab - commented out for now, to be revisited later
// Inline Page class for separate Offers tab on contact summary
// if (!class_exists('CRM_Custominvoicenumbersandoffers_Page_OfferTab')) { ... }

function custominvoicenumbersandoffers_civicrm_config(&$config)
{
  _custominvoicenumbersandoffers_civix_civicrm_config($config);

  // Default Settings, falls nicht gesetzt
  $defaults = [
    'invoice_prefix' => (string) 'INV_',
    'invoice_format' => (string) 'YYYYMMXX',
    'invoice_start_year' => (string) date('Y'),
    'invoice_start_month' => (int) date('m'),
    'invoice_start_index' => (string) '1',
    'offer_format' => (string) 'YYYYMMXX',
    'offer_prefix' => (string) 'OFF_',
    'offer_start_year' => (string) date('Y'),
    'offer_start_month' => (int) date('m'),
    'offer_start_index' => (string) '1',
    'enable_offers' => (int) 0,
  ];

  foreach ($defaults as $key => $value) {
    $settingKey = 'cinv_' . $key;
    $currentValue = Civi::settings()->get($settingKey);

    if ($currentValue === NULL || $currentValue === '') {
      Civi::settings()->set($settingKey, $value);
    }
  }
  
  // Self-heal: If offers are enabled, ensure financial accounts are properly set up
  $offersEnabled = (bool) Civi::settings()->get('cinv_enable_offers');
  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  
  if ($offersEnabled && $offerTypeId) {
    // Quick check: Verify AR relationships exist for this financial type
    try {
      $relations = \Civi\Api4\EntityFinancialAccount::get(FALSE)
        ->addWhere('entity_table', '=', 'civicrm_financial_type')
        ->addWhere('entity_id', '=', $offerTypeId)
        ->execute();
      
      // If fewer than 3 relationships (Income + AR + Expense needed), trigger the self-heal
      if ($relations->count() < 3) {
        custominvoicenumbersandoffers_ensure_offer_financial_accounts($offerTypeId);
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Self-heal check failed: " . $e->getMessage());
    }
    
    // Self-heal: Ensure message template exists
    try {
      $offerTemplateId = Civi::settings()->get('cinv_offer_message_template_id');
      $templateExists = FALSE;
      
      if ($offerTemplateId) {
        $checkTemplate = \Civi\Api4\MessageTemplate::get(FALSE)
          ->addWhere('id', '=', $offerTemplateId)
          ->execute();
        $templateExists = ($checkTemplate->count() > 0);
      }
      
      if (!$templateExists) {
        custominvoicenumbersandoffers_ensure_offer_message_template();
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Self-heal message template check failed: " . $e->getMessage());
    }
  }
}


/**
 * Ensure Offer financial type has proper account relationships
 * This is a self-healing function that can be called from config hook
 */
function custominvoicenumbersandoffers_ensure_offer_financial_accounts($offerTypeId) {
  try {
    // Verify Income Account relationship exists
    $incomeRels = \Civi\Api4\EntityFinancialAccount::get(FALSE)
      ->addWhere('entity_table', '=', 'civicrm_financial_type')
      ->addWhere('entity_id', '=', $offerTypeId)
      ->addWhere('account_relationship', '=', 3) // Income
      ->execute();
    
    if ($incomeRels->count() === 0) {
      // Get Offer financial account
      $offerAcct = \Civi\Api4\FinancialAccount::get(FALSE)
        ->addWhere('name', 'IN', ['Angebot', 'Offers', 'Offer'])
        ->execute()
        ->first();
      
      if ($offerAcct) {
        \Civi\Api4\EntityFinancialAccount::create(FALSE)
          ->addValue('entity_table', 'civicrm_option_value')
          ->addValue('entity_id', $offerTypeId)
          ->addValue('account_relationship_id:name', 'Asset Account is')
          ->addValue('financial_account_id:name', 'Deposit Bank Account')
          ->execute();
      }
    }
    
    // Verify AR Account relationship exists
    $arRels = \Civi\Api4\EntityFinancialAccount::get(FALSE)
      ->addWhere('entity_table', '=', 'civicrm_financial_type')
      ->addWhere('entity_id', '=', $offerTypeId)
      ->addWhere('account_relationship', '=', 1) // AR
      ->execute();
    
    if ($arRels->count() === 0) {
      // Get or create AR account
      $arAcct = \Civi\Api4\FinancialAccount::get(FALSE)
        ->addWhere('financial_account_type_id', '=', 1) // Asset
        ->addWhere('is_active', '=', TRUE)
        ->execute()
        ->first();
      
      if ($arAcct) {
        \Civi\Api4\EntityFinancialAccount::create(FALSE)
          ->addValue('entity_table', 'civicrm_financial_type')
          ->addValue('entity_id', $offerTypeId)
          ->addValue('account_relationship', 1)
          ->addValue('financial_account_id', $arAcct['id'])
          ->execute();
      }
    }
    
    // Verify Expense Account relationship exists - CRITICAL for Contribution View/Edit
    // CiviCRM core's getContributionTransactionInformation() requires this
    $expenseRels = \Civi\Api4\EntityFinancialAccount::get(FALSE)
      ->addWhere('entity_table', '=', 'civicrm_financial_type')
      ->addWhere('entity_id', '=', $offerTypeId)
      ->addWhere('account_relationship', '=', 5) // Expense Account is
      ->execute();
    
    if ($expenseRels->count() === 0) {
      // Find an expense-type financial account (financial_account_type_id = 5)
      $expenseAcct = \Civi\Api4\FinancialAccount::get(FALSE)
        ->addWhere('financial_account_type_id', '=', 5) // Expenses
        ->addWhere('is_active', '=', TRUE)
        ->execute()
        ->first();
      
      if (!$expenseAcct) {
        // Create a default expense account
        $newExpenseAcct = \Civi\Api4\FinancialAccount::create(FALSE)
          ->addValue('name', 'Banking Fees')
          ->addValue('description', 'Default expense account')
          ->addValue('financial_account_type_id', 5)
          ->addValue('is_active', TRUE)
          ->execute();
        $expenseAcct = $newExpenseAcct->first();
      }
      
      if ($expenseAcct) {
        \Civi\Api4\EntityFinancialAccount::create(FALSE)
          ->addValue('entity_table', 'civicrm_financial_type')
          ->addValue('entity_id', $offerTypeId)
          ->addValue('account_relationship', 5)
          ->addValue('financial_account_id', $expenseAcct['id'])
          ->execute();
      }
    }
    
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV] Self-heal error: " . $e->getMessage());
  }
}


/**
 * Set up all offer-related entities: financial type, custom fields, profile,
 * financial accounts, payment instrument, message template, navigation item.
 *
 * This is called when the user enables "Activate Offers" and saves the setup,
 * NOT during extension install. It is safe to call multiple times (idempotent).
 */
function custominvoicenumbersandoffers_setup_offers() {
  $locale = CRM_Core_I18n::getLocale();
  $offerLabel = 'Offer';
  $offerLabelPlural = 'Offers';
  $newOfferLabel = 'New Offer';
  $offerOverviewLabel = 'Offer Overview';
  $offerAccountName = 'Offers';
  $offerNrLabel = 'Offer Nr.';
  if (stripos($locale, 'de') === 0) {
    $offerLabel = 'Angebot';
    $offerLabelPlural = 'Angebote';
    $newOfferLabel = 'Neues Angebot';
    $offerOverviewLabel = 'Übersicht Angebote';
    $offerAccountName = 'Angebot';
    $offerNrLabel = 'Angebot Nr.';
  } elseif (stripos($locale, 'fr') === 0) {
    $offerLabel = "l'offre";
    $offerLabelPlural = 'offres';
    $newOfferLabel = 'Nouvelle offre';
    $offerOverviewLabel = 'Aperçu des offres';
    $offerAccountName = "l'offre";
    $offerNrLabel = 'Offre N°';
  } elseif (stripos($locale, 'es') === 0) {
    $offerLabel = 'oferta';
    $offerLabelPlural = 'ofertas';
    $newOfferLabel = 'Nueva oferta';
    $offerOverviewLabel = 'Resumen de ofertas';
    $offerAccountName = 'oferta';
    $offerNrLabel = 'Oferta N°';
  }

  // 1. Financial Type
  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  if (!$offerTypeId) {
    try {
      $existingOfferType = \Civi\Api4\FinancialType::get(FALSE)
        ->addWhere('name', '=', $offerLabel)
        ->execute()
        ->first();

      if ($existingOfferType) {
        $offerTypeId = $existingOfferType['id'];
      } else {
        $financialTypeResult = \Civi\Api4\FinancialType::create(FALSE)
          ->addValue('name', $offerLabel)
          ->addValue('description', $offerLabel)
          ->addValue('is_active', TRUE)
          ->execute();

        if ($financialTypeResult->count() > 0) {
          $offerTypeId = $financialTypeResult[0]['id'];
        }
      }
      if ($offerTypeId) {
        Civi::settings()->set('cinv_offer_financial_type_id', $offerTypeId);
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Error creating financial type: " . $e->getMessage());
    }
  }

  // 2. Custom fields + profile
  $profileId = Civi::settings()->get('cinv_offer_profile_id');
  if (!$profileId) {
    try {
      // Get or create the "Offer" custom group
      $customGroupResult = \Civi\Api4\CustomGroup::get(FALSE)
        ->addWhere('name', '=', 'offer_details')
        ->addWhere('extends', '=', 'Contribution')
        ->execute();

      if ($customGroupResult->count() == 0) {
        $customGroupCreate = \Civi\Api4\CustomGroup::create(FALSE)
          ->addValue('name', 'offer_details')
          ->addValue('title', ts('Additional Offer Text'))
          ->addValue('extends', 'Contribution')
          ->addValue('style', 'Inline')
          ->addValue('collapse_display', TRUE)
          ->addValue('is_active', TRUE)
          ->addValue('table_name', 'civicrm_value_offer_details')
          ->execute();
        $customGroupId = $customGroupCreate[0]['id'];
      } else {
        $customGroupId = $customGroupResult[0]['id'];
        try {
          \Civi\Api4\CustomGroup::update(FALSE)
            ->addWhere('id', '=', $customGroupId)
            ->addValue('title', ts('Additional Offer Text'))
            ->addValue('collapse_display', TRUE)
            ->execute();
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV] Error updating custom group: ' . $e->getMessage());
        }
      }

      // Create "Introduction Text" field
      $existingIntroField = \Civi\Api4\CustomField::get(FALSE)
        ->addWhere('name', '=', 'offer_intro_text')
        ->addWhere('custom_group_id', '=', $customGroupId)
        ->execute()
        ->first();

      if (!$existingIntroField) {
        \Civi\Api4\CustomField::create(FALSE)
          ->addValue('custom_group_id', $customGroupId)
          ->addValue('name', 'offer_intro_text')
          ->addValue('label', $offerLabel . ' - 1. Block')
          ->addValue('data_type', 'Memo')
          ->addValue('html_type', 'RichTextEditor')
          ->addValue('help_post', ts('This text will be shown on the message template above the offer item(s).'))
          ->addValue('is_required', FALSE)
          ->addValue('is_active', TRUE)
          ->addValue('column_name', 'offer_intro_text')
          ->execute();
      }

      // Create "Additional Text" field
      $existingAddField = \Civi\Api4\CustomField::get(FALSE)
        ->addWhere('name', '=', 'offer_addition')
        ->addWhere('custom_group_id', '=', $customGroupId)
        ->execute()
        ->first();

      if (!$existingAddField) {
        \Civi\Api4\CustomField::create(FALSE)
          ->addValue('custom_group_id', $customGroupId)
          ->addValue('name', 'offer_addition')
          ->addValue('label', $offerLabel . ' - 2. Block')
          ->addValue('data_type', 'Memo')
          ->addValue('html_type', 'RichTextEditor')
          ->addValue('help_post', ts('This text will be shown on the message template below the offer item(s).'))
          ->addValue('is_required', FALSE)
          ->addValue('is_active', TRUE)
          ->addValue('column_name', 'offer_addition')
          ->execute();
      }

      // Create Profile for Offers
      $existingProfile = \Civi\Api4\UFGroup::get(FALSE)
        ->addWhere('name', '=', 'offer_entry_form')
        ->execute()
        ->first();

      if ($existingProfile) {
        $profileId = $existingProfile['id'];
      } else {
        $profileResult = \Civi\Api4\UFGroup::create(FALSE)
          ->addValue('name', 'offer_entry_form')
          ->addValue('title', $offerLabel . ' Entry Form')
          ->addValue('extends', ['Contribution', 0])
          ->addValue('is_active', TRUE)
          ->addValue('description', 'Form for creating and editing ' . $offerLabelPlural)
          ->execute();
        $profileId = $profileResult[0]['id'];
      }

      // Add fields to the profile
      $profileFields = [
        ['field_name' => 'contact_id', 'label' => 'Contact', 'field_type' => 'Contact', 'is_required' => 1, 'visibility' => 'Public Pages and Listings', 'weight' => 1],
        ['field_name' => 'financial_type_id', 'label' => 'Financial Type', 'field_type' => 'Contribution', 'is_required' => 1, 'visibility' => 'User and User Admin Only', 'weight' => 2],
        ['field_name' => 'receive_date', 'label' => 'Received', 'field_type' => 'Contribution', 'is_required' => 1, 'visibility' => 'Public Pages and Listings', 'weight' => 3],
        ['field_name' => 'source', 'label' => 'Source', 'field_type' => 'Contribution', 'is_required' => 0, 'visibility' => 'Public Pages and Listings', 'weight' => 4],
        ['field_name' => 'contribution_status_id', 'label' => 'Status', 'field_type' => 'Contribution', 'is_required' => 1, 'visibility' => 'Public Pages and Listings', 'weight' => 5],
      ];

      $weight = 6;
      foreach ($profileFields as $fieldConfig) {
        $fieldConfig['uf_group_id'] = $profileId;
        $fieldConfig['weight'] = $fieldConfig['weight'] ?? $weight++;
        try {
          $existingField = \Civi\Api4\UFField::get(FALSE)
            ->addWhere('uf_group_id', '=', $profileId)
            ->addWhere('field_name', '=', $fieldConfig['field_name'])
            ->execute()
            ->first();

          if (!$existingField) {
            $ufField = \Civi\Api4\UFField::create(FALSE);
            foreach ($fieldConfig as $key => $value) {
              $ufField->addValue($key, $value);
            }
            $ufField->execute();
          }
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message("[CINV] Error adding field to profile: " . $e->getMessage());
        }
      }

      // Add custom fields to profile
      try {
        $existingIntroField = \Civi\Api4\UFField::get(FALSE)
          ->addWhere('uf_group_id', '=', $profileId)
          ->addWhere('field_name', '=', 'offer_details.offer_intro_text')
          ->execute()
          ->first();

        if (!$existingIntroField) {
          \Civi\Api4\UFField::create(FALSE)
            ->addValue('uf_group_id', $profileId)
            ->addValue('field_name', 'offer_details.offer_intro_text')
            ->addValue('label', 'Offer Intro Text')
            ->addValue('field_type', 'Contribution')
            ->addValue('is_required', FALSE)
            ->addValue('visibility', 'Public Pages and Listings')
            ->addValue('weight', 7)
            ->execute();
        }

        $existingAddField = \Civi\Api4\UFField::get(FALSE)
          ->addWhere('uf_group_id', '=', $profileId)
          ->addWhere('field_name', '=', 'offer_details.offer_addition')
          ->execute()
          ->first();

        if (!$existingAddField) {
          \Civi\Api4\UFField::create(FALSE)
            ->addValue('label', 'Offer Additional Text')
            ->addValue('field_type', 'Contribution')
            ->addValue('uf_group_id', $profileId)
            ->addValue('field_name', 'offer_details.offer_addition')
            ->addValue('is_required', FALSE)
            ->addValue('visibility', 'Public Pages and Listings')
            ->addValue('weight', 8)
            ->execute();
        }
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message("[CINV] Error adding custom fields to profile: " . $e->getMessage());
      }

      Civi::settings()->set('cinv_offer_profile_id', $profileId);
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Error creating custom fields or profile: " . $e->getMessage());
    }
  }

  // 3. Financial Account + relationships
  $arAccountId = NULL;
  if (!Civi::settings()->get('cinv_offer_financial_account_id') && $offerTypeId) {
    try {
      $incomeRelId = 3;

      $existingAccount = \Civi\Api4\FinancialAccount::get(FALSE)
        ->addWhere('name', 'IN', [$offerAccountName, 'Offers', 'Offer'])
        ->execute()
        ->first();

      $financialAccountId = NULL;
      if (!$existingAccount) {
        $accountResult = \Civi\Api4\FinancialAccount::create(FALSE)
          ->addValue('name', $offerAccountName)
          ->addValue('description', 'Default account for offers')
          ->addValue('accounting_code', '5000')
          ->addValue('financial_account_type_id', 3)
          ->addValue('is_active', TRUE)
          ->execute();
        if ($accountResult->count() > 0) {
          $financialAccountId = $accountResult[0]['id'];
        }
      } else {
        $financialAccountId = $existingAccount['id'];
      }
      if ($financialAccountId) {
        Civi::settings()->set('cinv_offer_financial_account_id', $financialAccountId);
      }

      // AR account
      $arRelId = 1;
      $allAssetAccounts = \Civi\Api4\FinancialAccount::get(FALSE)
        ->addWhere('financial_account_type_id', '=', 1)
        ->addWhere('is_active', '=', TRUE)
        ->execute();

      foreach ($allAssetAccounts as $acct) {
        if (stripos($acct['name'], 'accounts receivable') !== FALSE || stripos($acct['name'], 'debitoren') !== FALSE) {
          $arAccountId = $acct['id'];
          break;
        }
      }
      if (!$arAccountId && $allAssetAccounts->count() > 0) {
        $arAccountId = $allAssetAccounts->first()['id'];
      }
      if (!$arAccountId) {
        $newArAccount = \Civi\Api4\FinancialAccount::create(FALSE)
          ->addValue('name', 'Accounts Receivable')
          ->addValue('description', 'Accounts Receivable for pending contributions')
          ->addValue('financial_account_type_id', 1)
          ->addValue('is_active', TRUE)
          ->execute();
        if ($newArAccount->count() > 0) {
          $arAccountId = $newArAccount[0]['id'];
        }
      }
      Civi::settings()->set('cinv_offer_ar_account_id', $arAccountId);

      // Link financial account to financial type
      if ($financialAccountId && $offerTypeId) {
        $existingRelations = \Civi\Api4\EntityFinancialAccount::get(FALSE)
          ->addWhere('entity_table', '=', 'civicrm_financial_type')
          ->addWhere('entity_id', '=', $offerTypeId)
          ->execute();
        foreach ($existingRelations as $rel) {
          \Civi\Api4\EntityFinancialAccount::delete(FALSE)
            ->addWhere('id', '=', $rel['id'])
            ->execute();
        }

        // Income Account relationship
        \Civi\Api4\EntityFinancialAccount::create(FALSE)
          ->addValue('entity_table', 'civicrm_financial_type')
          ->addValue('entity_id', $offerTypeId)
          ->addValue('account_relationship', $incomeRelId)
          ->addValue('financial_account_id', $financialAccountId)
          ->execute();

        // AR relationship
        if ($arAccountId) {
          \Civi\Api4\EntityFinancialAccount::create(FALSE)
            ->addValue('entity_table', 'civicrm_financial_type')
            ->addValue('entity_id', $offerTypeId)
            ->addValue('account_relationship', $arRelId)
            ->addValue('financial_account_id', $arAccountId)
            ->execute();
        }

        // Expense Account relationship
        $expenseRelId = 5;
        $expenseAcct = \Civi\Api4\FinancialAccount::get(FALSE)
          ->addWhere('financial_account_type_id', '=', 5)
          ->addWhere('is_active', '=', TRUE)
          ->execute()
          ->first();
        if (!$expenseAcct) {
          $newExpenseAcct = \Civi\Api4\FinancialAccount::create(FALSE)
            ->addValue('name', 'Banking Fees')
            ->addValue('description', 'Default expense account')
            ->addValue('financial_account_type_id', 5)
            ->addValue('is_active', TRUE)
            ->execute();
          $expenseAcct = $newExpenseAcct->first();
        }
        if ($expenseAcct) {
          \Civi\Api4\EntityFinancialAccount::create(FALSE)
            ->addValue('entity_table', 'civicrm_financial_type')
            ->addValue('entity_id', $offerTypeId)
            ->addValue('account_relationship', $expenseRelId)
            ->addValue('financial_account_id', $expenseAcct['id'])
            ->execute();
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Error creating Financial Account: " . $e->getMessage());
    }
  } else {
    $arAccountId = Civi::settings()->get('cinv_offer_ar_account_id');
  }

  // 4. Payment Instrument
  if (!Civi::settings()->get('cinv_offer_payment_instrument_id')) {
    try {
      $existingInstrument = \Civi\Api4\OptionValue::get(FALSE)
        ->addWhere('option_group_id:name', '=', 'payment_instrument')
        ->addWhere('name', '=', 'Offer')
        ->execute()
        ->first();

      if (!$existingInstrument) {
        $instrumentResult = \Civi\Api4\OptionValue::create(FALSE)
          ->addValue('option_group_id:name', 'payment_instrument')
          ->addValue('name', 'Offer')
          ->addValue('label', $offerLabel)
          ->addValue('description', 'Offer')
          ->addValue('is_active', TRUE)
          ->execute()
          ->first();

        $offerPaymentInstrumentId = $instrumentResult['value'];
        $offerPaymentInstrumentOptionValueId = $instrumentResult['id'];
        Civi::settings()->set('cinv_offer_payment_instrument_id', $offerPaymentInstrumentId);

        if ($arAccountId) {
          \Civi\Api4\EntityFinancialAccount::create(FALSE)
            ->addValue('entity_table', 'civicrm_option_value')
            ->addValue('entity_id', $offerPaymentInstrumentOptionValueId)
            ->addValue('account_relationship', 1)
            ->addValue('financial_account_id', $arAccountId)
            ->execute();
        }
      } else {
        Civi::settings()->set('cinv_offer_payment_instrument_id', $existingInstrument['value']);
        $offerPaymentInstrumentOptionValueId = $existingInstrument['id'];

        if ($arAccountId) {
          $existingLink = \Civi\Api4\EntityFinancialAccount::get(FALSE)
            ->addWhere('entity_table', '=', 'civicrm_option_value')
            ->addWhere('entity_id', '=', $offerPaymentInstrumentOptionValueId)
            ->addWhere('account_relationship', '=', 1)
            ->execute()
            ->first();

          if (!$existingLink) {
            \Civi\Api4\EntityFinancialAccount::create(FALSE)
              ->addValue('entity_table', 'civicrm_option_value')
              ->addValue('entity_id', $offerPaymentInstrumentOptionValueId)
              ->addValue('account_relationship', 1)
              ->addValue('financial_account_id', $arAccountId)
              ->execute();
          }
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Error creating Offer payment instrument: " . $e->getMessage());
    }

    if ($arAccountId) {
      Civi::settings()->set('cinv_offer_payment_method_ar_account_id', $arAccountId);
    }
  }

  // 5. Message template
  custominvoicenumbersandoffers_ensure_offer_message_template();

  // 6. SearchKit saved search + display for Offer Overview
  if (!Civi::settings()->get('cinv_offer_saved_search_id')) {
    try {
      $existingSearch = \Civi\Api4\SavedSearch::get(FALSE)
        ->addWhere('name', '=', 'offer_overview')
        ->execute()
        ->first();

      $savedSearchId = NULL;
      if ($existingSearch) {
        $savedSearchId = $existingSearch['id'];
      } else {
        $savedSearchResult = \Civi\Api4\SavedSearch::create(FALSE)
          ->addValue('name', 'offer_overview')
          ->addValue('label', $offerOverviewLabel)
          ->addValue('api_entity', 'Contribution')
          ->addValue('api_params', [
            'version' => 4,
            'select' => [
              'id',
              'invoice_number',
              'contact_id.display_name',
              'contact_id',
              'receive_date',
              'total_amount',
              'currency',
              'contribution_status_id:label',
              'source',
            ],
            'where' => [
              ['financial_type_id', '=', $offerTypeId],
            ],
            'orderBy' => [],
            'limit' => 0,
            'groupBy' => [],
            'join' => [],
            'having' => [],
          ])
          ->execute()
          ->first();
        $savedSearchId = $savedSearchResult['id'];
      }

      if ($savedSearchId) {
        Civi::settings()->set('cinv_offer_saved_search_id', $savedSearchId);

        // Create SearchDisplay (table)
        $existingDisplay = \Civi\Api4\SearchDisplay::get(FALSE)
          ->addWhere('name', '=', 'offer_overview_table')
          ->addWhere('saved_search_id', '=', $savedSearchId)
          ->execute()
          ->first();

        if (!$existingDisplay) {
          \Civi\Api4\SearchDisplay::create(FALSE)
            ->addValue('name', 'offer_overview_table')
            ->addValue('label', $offerOverviewLabel)
            ->addValue('saved_search_id', $savedSearchId)
            ->addValue('type', 'table')
            ->addValue('settings', [
              'description' => NULL,
              'sort' => [
                ['receive_date', 'DESC'],
              ],
              'limit' => 50,
              'pager' => [
                'show_count' => TRUE,
                'expose_limit' => TRUE,
              ],
              'placeholder' => 5,
              'columns' => [
                [
                  'type' => 'field',
                  'key' => 'invoice_number',
                  'dataType' => 'String',
                  'label' => $offerNrLabel,
                  'sortable' => TRUE,
                  'link' => [
                    'path' => '',
                    'entity' => 'Contribution',
                    'action' => 'view',
                    'join' => '',
                    'target' => 'crm-popup',
                  ],
                ],
                [
                  'type' => 'field',
                  'key' => 'contact_id.display_name',
                  'dataType' => 'String',
                  'label' => ts('Contact'),
                  'sortable' => TRUE,
                  'link' => [
                    'path' => '',
                    'entity' => 'Contact',
                    'action' => 'view',
                    'join' => '',
                    'target' => '_blank',
                  ],
                ],
                [
                  'type' => 'field',
                  'key' => 'receive_date',
                  'dataType' => 'Date',
                  'label' => ts('Date'),
                  'sortable' => TRUE,
                ],
                [
                  'type' => 'field',
                  'key' => 'total_amount',
                  'dataType' => 'Money',
                  'label' => ts('Amount'),
                  'sortable' => TRUE,
                ],
                [
                  'type' => 'field',
                  'key' => 'contribution_status_id:label',
                  'dataType' => 'String',
                  'label' => ts('Status'),
                  'sortable' => TRUE,
                ],
                [
                  'type' => 'field',
                  'key' => 'source',
                  'dataType' => 'String',
                  'label' => ts('Source'),
                  'sortable' => TRUE,
                ],
                [
                  'type' => 'buttons',
                  'alignment' => 'text-right',
                  'size' => 'btn-xs',
                  'links' => [
                    [
                      'entity' => 'Contribution',
                      'action' => 'view',
                      'join' => '',
                      'target' => 'crm-popup',
                      'icon' => 'fa-external-link',
                      'text' => ts('View'),
                      'style' => 'default',
                      'path' => '',
                      'condition' => [],
                    ],
                    [
                      'entity' => 'Contribution',
                      'action' => 'update',
                      'join' => '',
                      'target' => 'crm-popup',
                      'icon' => 'fa-pencil',
                      'text' => ts('Edit'),
                      'style' => 'default',
                      'path' => '',
                      'condition' => [],
                    ],
                  ],
                ],
              ],
              'actions' => TRUE,
              'classes' => ['table', 'table-striped'],
            ])
            ->execute();
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV] Error creating SearchKit saved search: ' . $e->getMessage());
    }
  }

  // 6b. Update Afform title with localized overview label
  try {
    $extDir = CRM_Core_Resources::singleton()->getPath('custominvoicenumbersandoffers');
    $afformJsonPath = $extDir . DIRECTORY_SEPARATOR . 'ang' . DIRECTORY_SEPARATOR . 'afsearchOfferOverview.aff.json';
    if (file_exists($afformJsonPath)) {
      $afformData = json_decode(file_get_contents($afformJsonPath), TRUE);
      if (is_array($afformData)) {
        $afformData['title'] = $offerOverviewLabel;
        file_put_contents($afformJsonPath, json_encode($afformData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
      }
    }
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message('[CINV] Error updating Afform title: ' . $e->getMessage());
  }

  // 7. Navigation item
  $offerTypeIdNav = Civi::settings()->get('cinv_offer_financial_type_id');
  $profileIdNav = Civi::settings()->get('cinv_offer_profile_id');
  if ($offerTypeIdNav && $profileIdNav) {
    try {
      $contribNav = CRM_Core_DAO::singleValueQuery(
        "SELECT weight FROM civicrm_navigation WHERE name = 'Contributions' AND parent_id IS NULL LIMIT 1"
      );
      $offerWeight = ($contribNav ? (int)$contribNav + 1 : 50);

      $existingNavId = CRM_Core_DAO::singleValueQuery(
        "SELECT id FROM civicrm_navigation WHERE name = 'Offers' AND parent_id IS NULL LIMIT 1"
      );

      $offersNavId = NULL;
      if ($existingNavId) {
        $offersNavId = (int) $existingNavId;
        // Update URL to overview page
        CRM_Core_DAO::executeQuery(
          "UPDATE civicrm_navigation SET url = 'civicrm/offer-overview' WHERE id = %1",
          [1 => [$offersNavId, 'Integer']]
        );
        Civi::settings()->set('cinv_offer_nav_id', $offersNavId);
        _custominvoicenumbersandoffers_fixOfferNavWeight($offersNavId, (int) $contribNav);
      } else {
        $navParams = [
          'label' => $offerLabelPlural,
          'name' => 'Offers',
          'url' => 'civicrm/offer-overview',
          'permission' => 'access CiviContribute',
          'operator' => 'OR',
          'separator' => 0,
          'parent_id' => NULL,
          'weight' => $offerWeight,
          'is_active' => 1,
          'icon' => 'crm-i fa-file-lines',
          'domain_id' => CRM_Core_Config::domainID(),
        ];
        $navResult = CRM_Core_BAO_Navigation::add($navParams);
        if (!empty($navResult->id)) {
          $offersNavId = (int) $navResult->id;
          Civi::settings()->set('cinv_offer_nav_id', $offersNavId);
          _custominvoicenumbersandoffers_fixOfferNavWeight($offersNavId, (int) $contribNav);
        }
      }

      // Add "New Offer" child nav item
      if ($offersNavId) {
        $existingNewOffer = CRM_Core_DAO::singleValueQuery(
          "SELECT id FROM civicrm_navigation WHERE name = 'new_offer' AND parent_id = %1 LIMIT 1",
          [1 => [$offersNavId, 'Integer']]
        );
        if (!$existingNewOffer) {
          $newOfferParams = [
            'label' => $newOfferLabel,
            'name' => 'new_offer',
            'url' => "civicrm/contribute/add?reset=1&action=add&context=standalone&financial_type_id={$offerTypeIdNav}&gid={$profileIdNav}",
            'permission' => 'access CiviContribute',
            'operator' => 'OR',
            'separator' => 0,
            'parent_id' => $offersNavId,
            'weight' => 1,
            'is_active' => 1,
            'domain_id' => CRM_Core_Config::domainID(),
          ];
          CRM_Core_BAO_Navigation::add($newOfferParams);
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV] Error creating Offers navigation item: ' . $e->getMessage());
    }
  }

  // 8. Mark offers as enabled
  Civi::settings()->set('cinv_enable_offers', 1);

  // Flush caches
  Civi::cache()->flush();
  CRM_Core_BAO_Navigation::resetNavigation();
  CRM_Core_Invoke::rebuildMenuAndCaches();
}


/**
 * Ensure Offer message template and workflow exist.
 * Creates the template from the invoice template as a base, including
 * custom field tokens for offer_intro_text and offer_addition.
 * The admin can then further customize the template via the CiviCRM UI.
 */
function custominvoicenumbersandoffers_ensure_offer_message_template() {
  try {
    $locale = CRM_Core_I18n::getLocale();
    $offerLabel = 'Offer';
    if (stripos($locale, 'de') === 0) {
      $offerLabel = 'Angebot';
    } elseif (stripos($locale, 'fr') === 0) {
      $offerLabel = "l'offre";
    } elseif (stripos($locale, 'es') === 0) {
      $offerLabel = 'oferta';
    }

    // Ensure workflow is registered
    $offerWorkflowName = 'contribution_offer_receipt';
    $workflowOptionGroup = \Civi\Api4\OptionGroup::get(FALSE)
      ->addWhere('name', '=', 'msg_template_workflow')
      ->execute()
      ->first();

    if ($workflowOptionGroup) {
      $existingOfferWorkflow = \Civi\Api4\OptionValue::get(FALSE)
        ->addWhere('option_group_id', '=', $workflowOptionGroup['id'])
        ->addWhere('name', '=', $offerWorkflowName)
        ->execute()
        ->first();

      if (!$existingOfferWorkflow) {
        \Civi\Api4\OptionValue::create(FALSE)
          ->addValue('option_group_id', $workflowOptionGroup['id'])
          ->addValue('name', $offerWorkflowName)
          ->addValue('label', 'Contributions - Offers')
          ->addValue('value', $offerWorkflowName)
          ->addValue('is_active', TRUE)
          ->addValue('weight', 15)
          ->execute();
      }
    }

    // Check if templates already exist for this workflow
    $existingTemplates = \Civi\Api4\MessageTemplate::get(FALSE)
      ->addWhere('workflow_name', '=', $offerWorkflowName)
      ->execute();

    if ($existingTemplates->count() > 0) {
      // Store the default template ID if available
      foreach ($existingTemplates as $tpl) {
        if (!$tpl['is_reserved'] && $tpl['is_default']) {
          Civi::settings()->set('cinv_offer_message_template_id', $tpl['id']);
          break;
        }
      }
      return;
    }

    // Build the Offer template content from the invoice template
    $templateContent = _custominvoicenumbersandoffers_build_offer_template_content($offerLabel);
    if (!$templateContent) {
      CRM_Core_Error::debug_log_message("[CINV] Could not build Offer template content (invoice template not found?)");
      return;
    }

    // Create BOTH reserved (pristine) and default (editable) templates per CiviCRM pattern
    try {
      \Civi\Api4\MessageTemplate::create(FALSE)
        ->addValue('workflow_name', $offerWorkflowName)
        ->addValue('msg_title', $templateContent['title'])
        ->addValue('msg_subject', $templateContent['subject'])
        ->addValue('msg_text', $templateContent['text'])
        ->addValue('msg_html', $templateContent['html'])
        ->addValue('is_reserved', TRUE)
        ->addValue('is_default', FALSE)
        ->addValue('is_active', TRUE)
        ->execute();
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Reserved template error: " . $e->getMessage());
    }

    try {
      $defaultTpl = \Civi\Api4\MessageTemplate::create(FALSE)
        ->addValue('workflow_name', $offerWorkflowName)
        ->addValue('msg_title', $templateContent['title'])
        ->addValue('msg_subject', $templateContent['subject'])
        ->addValue('msg_text', $templateContent['text'])
        ->addValue('msg_html', $templateContent['html'])
        ->addValue('is_reserved', FALSE)
        ->addValue('is_default', TRUE)
        ->addValue('is_active', TRUE)
        ->execute();

      if ($defaultTpl->count() > 0) {
        Civi::settings()->set('cinv_offer_message_template_id', $defaultTpl[0]['id']);
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Default template error: " . $e->getMessage());
    }
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV] Error ensuring Offer message template: " . $e->getMessage());
  }
}

/**
 * Build the Offer template content from the invoice template.
 *
 * Copies the invoice template and inserts CiviCRM tokens for the custom fields
 * offer_intro_text (above line items) and offer_addition (below line items).
 * Uses token syntax {contribution.custom_N} which is processed by CiviCRM's
 * TokenProcessor during rendering.
 *
 * @param string $offerLabel Localized label for "Offer"
 * @return array|null Array with keys: title, subject, text, html. NULL on failure.
 */
function _custominvoicenumbersandoffers_build_offer_template_content($offerLabel) {
  // Get invoice template as base - prefer the editable (default) version
  // so that any admin customizations to the invoice design are inherited by offers
  $invoiceTemplate = \Civi\Api4\MessageTemplate::get(FALSE)
    ->addWhere('workflow_name', '=', 'contribution_invoice_receipt')
    ->addWhere('is_default', '=', TRUE)
    ->execute()
    ->first();

  // Fallback to reserved template if no default exists
  if (!$invoiceTemplate) {
    $invoiceTemplate = \Civi\Api4\MessageTemplate::get(FALSE)
      ->addWhere('workflow_name', '=', 'contribution_invoice_receipt')
      ->addWhere('is_reserved', '=', TRUE)
      ->execute()
      ->first();
  }

  if (!$invoiceTemplate) {
    CRM_Core_Error::debug_log_message("[CINV] Invoice template not found - cannot build Offer template");
    return NULL;
  }

  $offerHtml = $invoiceTemplate['msg_html'] ?? '';
  $offerText = $invoiceTemplate['msg_text'] ?? '';
  $offerSubject = $invoiceTemplate['msg_subject'] ?? 'Offer Receipt';

  // Replace Invoice/invoice with Offer/offer in the copied content
  // This only modifies the Offer template copy, NOT the original invoice template
  //
  // IMPORTANT: Protect ALL Smarty variables, tokens and code constructs containing
  // "invoice" from the generic Invoice→Offer replacement. These are real DB/code
  // identifiers that must not change. We use placeholder substitution.
  $protectedPatterns = [];
  $placeholderIndex = 0;

  // Protect all {contribution.invoice_*} tokens (scan both HTML and text)
  $combined = $offerHtml . "\n" . $offerText;
  if (preg_match_all('/\{contribution\.invoice_[a-z_]+\}/', $combined, $matches)) {
    foreach (array_unique($matches[0]) as $match) {
      $ph = '##CINV_PROTECT_' . $placeholderIndex . '##';
      $protectedPatterns[$ph] = $match;
      $offerHtml = str_replace($match, $ph, $offerHtml);
      $offerText = str_replace($match, $ph, $offerText);
      $placeholderIndex++;
    }
  }

  // Protect Smarty variables like $invoiceElements, $invoice_id, etc. (scan both)
  if (preg_match_all('/\$[a-zA-Z_]*[Ii]nvoice[a-zA-Z_]*/', $combined, $matches)) {
    foreach (array_unique($matches[0]) as $match) {
      $ph = '##CINV_PROTECT_' . $placeholderIndex . '##';
      $protectedPatterns[$ph] = $match;
      $offerHtml = str_replace($match, $ph, $offerHtml);
      $offerText = str_replace($match, $ph, $offerText);
      $placeholderIndex++;
    }
  }

  // Protect workflow names like contribution_invoice_receipt
  $workflowPh = '##CINV_PROTECT_' . $placeholderIndex . '##';
  $protectedPatterns[$workflowPh] = 'contribution_invoice_receipt';
  $offerHtml = str_replace('contribution_invoice_receipt', $workflowPh, $offerHtml);
  $offerText = str_replace('contribution_invoice_receipt', $workflowPh, $offerText);
  $placeholderIndex++;

  // Now do the safe text-level replacements (human-readable labels only)
  $textReplacements = [
    '{ts}INVOICE{/ts}' => '{ts}OFFER{/ts}',
    '{ts}Invoice Date:{/ts}' => '{ts}' . $offerLabel . ' Date:{/ts}',
    '{ts}Invoice Number:{/ts}' => '{ts}' . $offerLabel . ' Number:{/ts}',
    '{ts}Invoice Receipt{/ts}' => '{ts}' . $offerLabel . ' Receipt{/ts}',
    'Invoice' => $offerLabel,
    'invoice' => strtolower($offerLabel),
  ];
  foreach ($textReplacements as $old => $new) {
    $offerHtml = str_replace($old, $new, $offerHtml);
    $offerText = str_replace($old, $new, $offerText);
  }
  $offerSubject = str_replace(['Invoice', 'invoice'], [$offerLabel, strtolower($offerLabel)], $offerSubject);

  // Remove sections that don't apply to offers:
  // 1. "Amount Paid" / "Amount Credited" row
  $offerHtml = preg_replace(
    '/<tr>\s*<td\s+colspan="3"><\/td>\s*<td[^>]*>.*?(?:Amount Paid|Amount Credited).*?<\/td>\s*<td[^>]*>.*?\{contribution\.paid_amount\}.*?<\/td>\s*<\/tr>/is',
    '',
    $offerHtml
  );

  // 2. "AMOUNT DUE" row
  $offerHtml = preg_replace(
    '/<tr>\s*<td\s+colspan="3"><\/td>\s*<td[^>]*>.*?AMOUNT DUE.*?<\/td>\s*<td[^>]*>.*?\{contribution\.balance_amount\}.*?<\/td>\s*<\/tr>/is',
    '',
    $offerHtml
  );

  // 3. The horizontal rule row before AMOUNT DUE
  // (keep only one <hr> between sub total and total)

  // 4. DUE DATE row for pending/pay_later
  $offerHtml = preg_replace(
    '/\s*\{if\s[^}]*contribution_status_id:name[^}]*Pending[^}]*\}\s*<tr>.*?DUE DATE.*?<\/tr>\s*\{\/if\}/is',
    '',
    $offerHtml
  );

  // 5. The entire payment advice / cut line section for pending/pay_later
  $offerHtml = preg_replace(
    '/\s*\{if\s[^}]*contribution_status_id:name[^}]*Pending[^}]*is_pay_later[^}]*\}.*?\{\/if\}\s*(?=\{if|<\/div>)/is',
    '',
    $offerHtml
  );

  // 6. The entire CREDIT NOTE / CREDIT ADVICE section
  $offerHtml = preg_replace(
    '/\s*\{if\s[^}]*contribution_status_id:name[^}]*(?:Refunded|Cancelled)[^}]*\}.*?\{\/if\}\s*(?=<\/div>)/is',
    '',
    $offerHtml
  );

  // Clean up the text version similarly
  $offerText = preg_replace('/Amount Paid.*?\n/i', '', $offerText);
  $offerText = preg_replace('/Amount Due.*?\n/i', '', $offerText);
  $offerText = preg_replace('/AMOUNT DUE.*?\n/i', '', $offerText);

  // Restore all protected patterns
  foreach ($protectedPatterns as $ph => $original) {
    $offerHtml = str_replace($ph, $original, $offerHtml);
    $offerText = str_replace($ph, $original, $offerText);
  }

  // Look up custom field IDs for offer_intro_text and offer_addition
  $introFieldId = NULL;
  $additionalFieldId = NULL;
  try {
    $customGroup = \Civi\Api4\CustomGroup::get(FALSE)
      ->addWhere('name', '=', 'offer_details')
      ->addWhere('extends', '=', 'Contribution')
      ->execute()
      ->first();

    if ($customGroup) {
      $introField = \Civi\Api4\CustomField::get(FALSE)
        ->addWhere('name', '=', 'offer_intro_text')
        ->addWhere('custom_group_id', '=', $customGroup['id'])
        ->execute()
        ->first();
      $additionalField = \Civi\Api4\CustomField::get(FALSE)
        ->addWhere('name', '=', 'offer_addition')
        ->addWhere('custom_group_id', '=', $customGroup['id'])
        ->execute()
        ->first();

      if ($introField) {
        $introFieldId = $introField['id'];
      }
      if ($additionalField) {
        $additionalFieldId = $additionalField['id'];
      }
    }
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV] Error looking up custom field IDs: " . $e->getMessage());
  }

  // Insert custom field tokens into HTML using CiviCRM token syntax
  // {contribution.custom_N} is processed by TokenProcessor during rendering
  if ($introFieldId || $additionalFieldId) {
    $introToken = $introFieldId ? '{contribution.custom_' . $introFieldId . '}' : '';
    $additionalToken = $additionalFieldId ? '{contribution.custom_' . $additionalFieldId . '}' : '';

    // Build HTML blocks for the custom fields
    $introHtmlBlock = '';
    if ($introToken) {
      $introHtmlBlock = '<table width="100%" style="font-family: Arial, Verdana, sans-serif; padding: 10px 0 0 0; margin: 0; border-spacing: 0;">' . "\n"
        . '  <tr><td style="font-size: 9pt; padding: 0;">' . $introToken . '</td></tr>' . "\n"
        . '</table>' . "\n";
    }

    $additionalHtmlBlock = '';
    if ($additionalToken) {
      $additionalHtmlBlock = '<table width="100%" style="font-family: Arial, Verdana, sans-serif; padding: 10px 0 0 0; margin: 0; border-spacing: 0;">' . "\n"
        . '  <tr><td style="font-size: 9pt; padding: 0;">' . $additionalToken . '</td></tr>' . "\n"
        . '</table>' . "\n";
    }

    // Insert intro block ABOVE the line items table.
    // The line items table is identified by the header row containing
    // "Description" and "Quantity" (or their {ts} equivalents).
    if ($introHtmlBlock) {
      $inserted = FALSE;
      // Primary: find the table that contains the line items header (Description/Qty)
      // The header row is inside a <table> that starts before the {ts}Description{/ts} text.
      // We look for the opening <table tag that precedes the Description header.
      if (preg_match('/<table[^>]*>[\s\S]*?\{ts\}Description\{\/ts\}/i', $offerHtml, $matches, PREG_OFFSET_CAPTURE)) {
        // Find the <table that starts this block
        $descPos = $matches[0][1];
        $offerHtml = substr($offerHtml, 0, $descPos) . $introHtmlBlock . "\n    " . substr($offerHtml, $descPos);
        $inserted = TRUE;
      }
      if (!$inserted) {
        // Fallback: look for {foreach from=$lineItem} which starts the line items loop
        if (preg_match('/\{foreach\s+from=\$lineItem/i', $offerHtml, $matches, PREG_OFFSET_CAPTURE)) {
          $pos = $matches[0][1];
          // Go back to find the preceding <table
          $preceding = substr($offerHtml, 0, $pos);
          $tablePos = strrpos($preceding, '<table');
          if ($tablePos !== FALSE) {
            $offerHtml = substr($offerHtml, 0, $tablePos) . $introHtmlBlock . "\n    " . substr($offerHtml, $tablePos);
            $inserted = TRUE;
          }
        }
      }
      if (!$inserted) {
        // Last fallback: insert before the first Description/Quantity text
        if (preg_match('/Description/i', $offerHtml, $matches, PREG_OFFSET_CAPTURE)) {
          $pos = $matches[0][1];
          $preceding = substr($offerHtml, 0, $pos);
          $tablePos = strrpos($preceding, '<table');
          if ($tablePos !== FALSE) {
            $offerHtml = substr($offerHtml, 0, $tablePos) . $introHtmlBlock . "\n    " . substr($offerHtml, $tablePos);
            $inserted = TRUE;
          }
        }
      }
      if (!$inserted) {
        // Absolute last fallback: prepend to HTML
        $offerHtml = $introHtmlBlock . $offerHtml;
      }
    }

    // Insert additional block BELOW the line items section.
    // The line items section ends with the totals (Sub Total, Total Amount etc.)
    // followed typically by a closing </table>. We look for the total amount row
    // and insert after the </table> that closes that section.
    if ($additionalHtmlBlock) {
      $inserted = FALSE;

      // Primary: find the TOTAL AMOUNT row (or {ts}Total Amount{/ts}) and its
      // enclosing </table>, then insert after that </table>.
      if (preg_match('/(?:TOTAL AMOUNT|\{ts\}Total Amount\{\/ts\})/i', $offerHtml, $matches, PREG_OFFSET_CAPTURE)) {
        $totalPos = $matches[0][1];
        // Find the next </table> after the total amount row
        $closeTablePos = strpos($offerHtml, '</table>', $totalPos);
        if ($closeTablePos !== FALSE) {
          $insertPos = $closeTablePos + strlen('</table>');
          $offerHtml = substr($offerHtml, 0, $insertPos) . "\n\n    " . $additionalHtmlBlock . substr($offerHtml, $insertPos);
          $inserted = TRUE;
        }
      }
      if (!$inserted) {
        // Fallback: find {/foreach} that closes the lineItem loop, then the next </table>
        if (preg_match('/\{\/foreach\}.*?<\/table>/is', $offerHtml, $matches, PREG_OFFSET_CAPTURE)) {
          $endPos = $matches[0][1] + strlen($matches[0][0]);
          $offerHtml = substr($offerHtml, 0, $endPos) . "\n\n    " . $additionalHtmlBlock . substr($offerHtml, $endPos);
          $inserted = TRUE;
        }
      }
      if (!$inserted) {
        // Last fallback: append to HTML
        $offerHtml .= "\n" . $additionalHtmlBlock;
      }
    }

    // For text version, add tokens at appropriate positions
    if ($introToken) {
      // Insert intro text before the line items in the text version
      $offerText = $introToken . "\n\n" . $offerText;
    }
    if ($additionalToken) {
      // Append additional text at the end of the text version
      $offerText .= "\n\n" . $additionalToken;
    }
  }

  return [
    'title' => 'Contributions - Offers',
    'subject' => $offerSubject,
    'text' => $offerText,
    'html' => $offerHtml,
  ];
}


function custominvoicenumbersandoffers_civicrm_install()
{
  // Create the number sequences table (used for BOTH invoices and offers)
  $sql = "
  CREATE TABLE IF NOT EXISTS civicrm_custom_number_sequences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribution_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    prefix VARCHAR(50) DEFAULT NULL,
    year INT NOT NULL,
    month INT DEFAULT NULL,
    sequence_index INT NOT NULL,
    number_value VARCHAR(255) NOT NULL,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_contribution_type (contribution_id, type),
    KEY idx_reset (type, year, month)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";

  CRM_Core_DAO::executeQuery($sql);

  // Offer-related entities (financial type, custom fields, profile, financial accounts,
  // payment instrument, message template, navigation) are NOT created here.
  // They are set up on-demand when the user enables "Activate Offers" in the admin form.

  // Clear all caches and rebuild menu
  Civi::cache()->flush();
  CRM_Core_Menu::store();
  CRM_Core_Invoke::rebuildMenuAndCaches();
  
  return _custominvoicenumbersandoffers_civix_civicrm_install();
}

function custominvoicenumbersandoffers_civicrm_postInstall()
{
  // Redirect to admin page after installation
  $url = CRM_Utils_System::url('civicrm/custominvoicenumbersandoffers/admin', 'reset=1', TRUE);
  CRM_Core_Session::singleton()->pushUserContext($url);
}

function custominvoicenumbersandoffers_civicrm_xmlMenu(&$files)
{
  $files[] = __DIR__ . '/xml/Menu.xml';
}

function custominvoicenumbersandoffers_civicrm_enable()
{
  _custominvoicenumbersandoffers_civix_civicrm_enable();
  
  // Clear caches and rebuild menu when extension is enabled
  Civi::cache()->flush();
  
  // Rebuild menu and caches (do NOT call CRM_Core_Menu::store() as it tries to re-insert existing menus)
  try {
    CRM_Core_Invoke::rebuildMenuAndCaches();
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV] Warning during menu rebuild: " . $e->getMessage());
  }
}

function custominvoicenumbersandoffers_civicrm_disable()
{
  _custominvoicenumbersandoffers_civix_civicrm_disable();
}

function custominvoicenumbersandoffers_civicrm_uninstall()
{
  try {
    // Get IDs we need to delete
    $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
    $offerAccountId = Civi::settings()->get('cinv_offer_financial_account_id');
    $offerPaymentInstrumentId = Civi::settings()->get('cinv_offer_payment_instrument_id');
    $offerProfileId = Civi::settings()->get('cinv_offer_profile_id');
    $offerMessageTemplateId = Civi::settings()->get('cinv_offer_message_template_id');
    
    // 1. Delete all offer contributions (and their financial transactions)
    // This must happen BEFORE deleting the financial type, since CiviCRM
    // prevents deletion of a financial type that is still referenced.
    if ($offerTypeId) {
      try {
        $offerContributions = \Civi\Api4\Contribution::get(FALSE)
          ->addSelect('id')
          ->addWhere('financial_type_id', '=', $offerTypeId)
          ->execute();

        foreach ($offerContributions as $contrib) {
          try {
            \Civi\Api4\Contribution::delete(FALSE)
              ->addWhere('id', '=', $contrib['id'])
              ->execute();
          } catch (Exception $e) {
            // If API delete fails, try direct SQL as last resort
            try {
              CRM_Core_DAO::executeQuery(
                "DELETE FROM civicrm_line_item WHERE contribution_id = %1",
                [1 => [$contrib['id'], 'Integer']]
              );
              CRM_Core_DAO::executeQuery(
                "DELETE FROM civicrm_financial_item WHERE id IN (SELECT fi.id FROM civicrm_financial_item fi INNER JOIN civicrm_line_item li ON fi.entity_id = li.id AND fi.entity_table = 'civicrm_line_item' WHERE li.contribution_id = %1)",
                [1 => [$contrib['id'], 'Integer']]
              );
              CRM_Core_DAO::executeQuery(
                "DELETE FROM civicrm_contribution WHERE id = %1",
                [1 => [$contrib['id'], 'Integer']]
              );
            } catch (Exception $e2) {
              CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error force-deleting contribution ' . $contrib['id'] . ': ' . $e2->getMessage());
            }
          }
        }
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting offer contributions: ' . $e->getMessage());
      }
    }
    
    // Also delete by known financial type names (fallback if setting was lost)
    try {
      $ftNames = ['Offer', 'Angebot', "l'offre", 'oferta'];
      $orphanFTs = \Civi\Api4\FinancialType::get(FALSE)
        ->addSelect('id')
        ->addWhere('name', 'IN', $ftNames)
        ->execute();
      foreach ($orphanFTs as $ft) {
        if ($offerTypeId && $ft['id'] == $offerTypeId) {
          continue; // Already handled above
        }
        $orphanContribs = \Civi\Api4\Contribution::get(FALSE)
          ->addSelect('id')
          ->addWhere('financial_type_id', '=', $ft['id'])
          ->execute();
        foreach ($orphanContribs as $contrib) {
          try {
            \Civi\Api4\Contribution::delete(FALSE)
              ->addWhere('id', '=', $contrib['id'])
              ->execute();
          } catch (Exception $e) {
            // Ignore individual failures
          }
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error in fallback contribution cleanup: ' . $e->getMessage());
    }
    
    // 2. Delete number sequences table
    try {
      CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS civicrm_custom_number_sequences");
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error dropping table: ' . $e->getMessage());
    }
    
    // 3. Delete profile
    if ($offerProfileId) {
      try {
        \Civi\Api4\UFGroup::delete(FALSE)
          ->addWhere('id', '=', $offerProfileId)
          ->execute();
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting profile: ' . $e->getMessage());
      }
    }
    
    // 4. Delete custom fields and custom group
    try {
      $customGroup = \Civi\Api4\CustomGroup::get(FALSE)
        ->addWhere('name', '=', 'offer_details')
        ->execute()
        ->first();
      
      if ($customGroup) {
        \Civi\Api4\CustomGroup::delete(FALSE)
          ->addWhere('id', '=', $customGroup['id'])
          ->execute();
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting custom group: ' . $e->getMessage());
    }
    
    // 5. Delete payment instrument
    // Note: cinv_offer_payment_instrument_id stores the option 'value', not the row 'id'.
    // We must look up by name to reliably delete it.
    try {
      $piToDelete = \Civi\Api4\OptionValue::get(FALSE)
        ->addWhere('option_group_id:name', '=', 'payment_instrument')
        ->addWhere('name', '=', 'Offer')
        ->execute();

      foreach ($piToDelete as $pi) {
        // Also remove any EntityFinancialAccount linked to this option value
        try {
          \Civi\Api4\EntityFinancialAccount::delete(FALSE)
            ->addWhere('entity_table', '=', 'civicrm_option_value')
            ->addWhere('entity_id', '=', $pi['id'])
            ->execute();
        } catch (Exception $e) {
          // Ignore
        }
        \Civi\Api4\OptionValue::delete(FALSE)
          ->addWhere('id', '=', $pi['id'])
          ->execute();
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting payment instrument: ' . $e->getMessage());
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
        CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting relationships: ' . $e->getMessage());
      }
    }
    
    // 7. Delete financial type (by stored ID + fallback by name)
    try {
      $ftNames = ['Offer', 'Angebot', "l'offre", 'oferta'];
      if ($offerTypeId) {
        // Delete entity-financial-account relationships first (may have been re-created)
        try {
          \Civi\Api4\EntityFinancialAccount::delete(FALSE)
            ->addWhere('entity_table', '=', 'civicrm_financial_type')
            ->addWhere('entity_id', '=', $offerTypeId)
            ->execute();
        } catch (Exception $e) {
          // Ignore
        }
        \Civi\Api4\FinancialType::delete(FALSE)
          ->addWhere('id', '=', $offerTypeId)
          ->execute();
      }
      // Fallback: clean up by known names in case the setting was lost
      $orphanFTs = \Civi\Api4\FinancialType::get(FALSE)
        ->addWhere('name', 'IN', $ftNames)
        ->execute();
      foreach ($orphanFTs as $ft) {
        try {
          \Civi\Api4\EntityFinancialAccount::delete(FALSE)
            ->addWhere('entity_table', '=', 'civicrm_financial_type')
            ->addWhere('entity_id', '=', $ft['id'])
            ->execute();
        } catch (Exception $e) {
          // Ignore
        }
        try {
          \Civi\Api4\FinancialType::delete(FALSE)
            ->addWhere('id', '=', $ft['id'])
            ->execute();
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Could not delete financial type ' . $ft['name'] . ' (ID ' . $ft['id'] . '): ' . $e->getMessage());
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting financial type: ' . $e->getMessage());
    }
    
    // 8. Delete financial account
    if ($offerAccountId) {
      try {
        \Civi\Api4\FinancialAccount::delete(FALSE)
          ->addWhere('id', '=', $offerAccountId)
          ->execute();
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting financial account: ' . $e->getMessage());
      }
    }
    
    // 9. Delete message templates (both reserved and default)
    try {
      // Get all templates with this workflow
      $templates = \Civi\Api4\MessageTemplate::get(FALSE)
        ->addWhere('workflow_name', '=', 'contribution_offer_receipt')
        ->execute();
      
      if ($templates->count() > 0) {
        $templateIds = [];
        foreach ($templates as $tpl) {
          $templateIds[] = $tpl['id'];
        }
        
        // Delete all templates for this workflow
        \Civi\Api4\MessageTemplate::delete(FALSE)
          ->addWhere('id', 'IN', $templateIds)
          ->execute();
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting message templates: ' . $e->getMessage());
    }
    
    // 10. Delete workflow registration
    try {
      $workflowOptionGroup = \Civi\Api4\OptionGroup::get(FALSE)
        ->addWhere('name', '=', 'msg_template_workflow')
        ->execute()
        ->first();
      
      if ($workflowOptionGroup) {
        $workflow = \Civi\Api4\OptionValue::get(FALSE)
          ->addWhere('option_group_id', '=', $workflowOptionGroup['id'])
          ->addWhere('name', '=', 'contribution_offer_receipt')
          ->execute()
          ->first();
        
        if ($workflow) {
          \Civi\Api4\OptionValue::delete(FALSE)
            ->addWhere('id', '=', $workflow['id'])
            ->execute();
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting workflow: ' . $e->getMessage());
    }
    
    // 11. Delete Offers navigation item and children from the database
    try {
      $offerNavId = Civi::settings()->get('cinv_offer_nav_id');
      if ($offerNavId) {
        // Delete child items first
        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_navigation WHERE parent_id = %1",
          [1 => [$offerNavId, 'Integer']]
        );
        \Civi\Api4\Navigation::delete(FALSE)
          ->addWhere('id', '=', $offerNavId)
          ->execute();
      }
      // Also clean up by name in case the setting was lost
      $orphanOffersId = CRM_Core_DAO::singleValueQuery(
        "SELECT id FROM civicrm_navigation WHERE name = 'Offers' AND parent_id IS NULL LIMIT 1"
      );
      if ($orphanOffersId) {
        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_navigation WHERE parent_id = %1",
          [1 => [$orphanOffersId, 'Integer']]
        );
        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_navigation WHERE id = %1",
          [1 => [$orphanOffersId, 'Integer']]
        );
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting Offers navigation item: ' . $e->getMessage());
    }

    // 12. Delete SearchKit saved search and display
    try {
      $savedSearch = \Civi\Api4\SavedSearch::get(FALSE)
        ->addWhere('name', '=', 'offer_overview')
        ->execute()
        ->first();

      if ($savedSearch) {
        \Civi\Api4\SearchDisplay::delete(FALSE)
          ->addWhere('saved_search_id', '=', $savedSearch['id'])
          ->execute();

        \Civi\Api4\SavedSearch::delete(FALSE)
          ->addWhere('id', '=', $savedSearch['id'])
          ->execute();
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Error deleting SearchKit saved search: ' . $e->getMessage());
    }

    // 13. Clear all extension settings
    $settingKeys = [
      'cinv_invoice_prefix',
      'cinv_invoice_format',
      'cinv_invoice_start_year',
      'cinv_invoice_start_month',
      'cinv_invoice_start_index',
      'cinv_invoice_types',
      'cinv_offer_prefix',
      'cinv_offer_format',
      'cinv_offer_start_year',
      'cinv_offer_start_month',
      'cinv_offer_start_index',
      'cinv_enable_offers',
      'cinv_offer_financial_type_id',
      'cinv_offer_financial_account_id',
      'cinv_offer_ar_account_id',
      'cinv_offer_payment_instrument_id',
      'cinv_offer_payment_method_ar_account_id',
      'cinv_offer_profile_id',
      'cinv_offer_message_template_id',
      'cinv_offer_nav_id',
      'cinv_offer_saved_search_id',
      'cinv_setup_completed',
    ];
    
    foreach ($settingKeys as $key) {
      Civi::settings()->revert($key);
    }
    
    // Clear caches and reset navigation
    Civi::cache()->flush();
    CRM_Core_BAO_Navigation::resetNavigation();
    CRM_Core_Menu::store();
    CRM_Core_Invoke::rebuildMenuAndCaches();
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message('[CINV UNINSTALL] Critical error during uninstall: ' . $e->getMessage());
    throw $e;
  }
  
  _custominvoicenumbersandoffers_civix_civicrm_uninstall();
}

/**
 * Hook to enforce payment instrument and other fields BEFORE saving contribution
 */
function custominvoicenumbersandoffers_civicrm_pre($op, $objectName, $id, &$params) {
  if ($objectName !== 'Contribution') {
    return;
  }

  // Handle deletion: remove sequence entry if it was the newest in its cycle
  if ($op === 'delete' && $id) {
    _custominvoicenumbersandoffers_handleContributionDelete((int) $id);
    return;
  }
  
  if ($op !== 'create' && $op !== 'edit') {
    return;
  }
  
  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  $financialTypeId = $params['financial_type_id'] ?? NULL;
  
  // Check if this is an Offer
  $isOffer = ($offerTypeId && $financialTypeId == $offerTypeId);
  
  if (!$isOffer) {
    return;
  }

  // Do not set a payment processor for offers
  unset($params['payment_processor_id']);

  // Force payment instrument to "Offer"
  $offerPaymentInstrumentId = Civi::settings()->get('cinv_offer_payment_instrument_id');
  if ($offerPaymentInstrumentId) {
    $params['payment_instrument_id'] = $offerPaymentInstrumentId;
  }
  
  // Ensure receive_date is set
  if (empty($params['receive_date'])) {
    $params['receive_date'] = date('Y-m-d H:i:s');
  }
  
  // Set contribution status to "Pending" if not set
  if (empty($params['contribution_status_id'])) {
    $params['contribution_status_id'] = 2; // Pending
  }
  
  // Remove empty optional integer fields from params to prevent validation errors.
  // CiviCRM forms may submit '' for these, which fails integer validation.
  $optionalIntegerFields = [
    'campaign_id',
    'contribution_page_id',
    'creditnote_id',
  ];
  
  foreach ($optionalIntegerFields as $field) {
    if (array_key_exists($field, $params) && ($params[$field] === NULL || $params[$field] === '' || $params[$field] === 'null')) {
      unset($params[$field]);
    }
  }
}

/**
 * Hook to handle LineItem and post-contribution operations
 */
function custominvoicenumbersandoffers_civicrm_post(
  $op,
  $objectName,
  $objectId,
  &$objectRef
) {
  // Handle contribution view/retrieve - ensure critical fields are set
  if ($objectName === 'Contribution' && ($op === 'view' || $op === 'retrieve')) {
    $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
    $isOffer = ($offerTypeId && ($objectRef->financial_type_id ?? NULL) == $offerTypeId);
    
    if ($isOffer) {
      // Ensure critical fields are present during view
      if (empty($objectRef->receive_date)) {
        CRM_Core_Error::debug_log_message("[CINV POST-HOOK] WARNING: Offer contribution {$objectId} has NULL receive_date!");
        $objectRef->receive_date = date('Y-m-d H:i:s');
      }
      
      if (empty($objectRef->contribution_status_id)) {
        CRM_Core_Error::debug_log_message("[CINV POST-HOOK] WARNING: Offer contribution {$objectId} has NULL contribution_status_id!");
        $objectRef->contribution_status_id = 2; // Pending
      }
    }
    return;
  }

  // If a LineItem is being created, check if it's part of an Offer and force the financial_type_id
  if ($objectName === 'LineItem' && $op === 'create') {
    $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');

    if ($offerTypeId && !empty($objectRef->contribution_id)) {
      try {
        $contrib = \Civi\Api4\Contribution::get(FALSE)
          ->addWhere('id', '=', $objectRef->contribution_id)
          ->execute()
          ->first();
        
        if ($contrib && $contrib['financial_type_id'] == $offerTypeId) {
          // Force the financial_type_id on the line item
          CRM_Core_DAO::executeQuery(
            "UPDATE civicrm_line_item SET financial_type_id = %1 WHERE id = %2",
            [1 => [$offerTypeId, 'Integer'], 2 => [$objectId, 'Integer']]
          );
        }
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message("[CINV] LineItem post-hook error: " . $e->getMessage());
      }
    }
    return;
  }

  // Everything below is for Contribution create only
  if ($objectName !== 'Contribution' || $op !== 'create') {
    return;
  }

  $financialTypeId = $objectRef->financial_type_id ?? NULL;
  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  $isOffer = ($offerTypeId && $financialTypeId == $offerTypeId);

  // For offers, ensure payment instrument is set and clean NULL integer fields
  if ($isOffer && $objectId) {
    try {
      // Set payment instrument to "Offer" if configured
      $offerPaymentInstrumentId = Civi::settings()->get('cinv_offer_payment_instrument_id');
      if ($offerPaymentInstrumentId) {
        CRM_Core_DAO::executeQuery(
          "UPDATE civicrm_contribution SET payment_instrument_id = %1 WHERE id = %2",
          [
            1 => [$offerPaymentInstrumentId, 'Integer'],
            2 => [$objectId, 'Integer'],
          ]
        );
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV] Error setting payment instrument: " . $e->getMessage());
    }

    // Clean NULL integer fields that cause validation errors when viewing/editing
    try {
      $sql = "UPDATE civicrm_contribution 
              SET campaign_id = NULL, contribution_page_id = NULL, creditnote_id = NULL
              WHERE id = %1 
              AND (campaign_id = 0 OR contribution_page_id = 0 OR creditnote_id = 0)";
      CRM_Core_DAO::executeQuery($sql, [1 => [$objectId, 'Integer']]);
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV POST-HOOK] Error during post-create cleanup: " . $e->getMessage());
    }
  }

  // Generate number sequence (for BOTH offers and invoices)
  // Check if already processed
  $exists = CRM_Core_DAO::singleValueQuery(
    "SELECT id FROM civicrm_custom_number_sequences WHERE contribution_id = %1",
    [1 => [$objectId, 'Integer']]
  );

  if ($exists) {
    return;
  }

  CRM_Custominvoicenumbersandoffers_Service_NumberGenerator::generate(
    $objectId,
    $isOffer
  );
}

/**
 * Use Offer-specific message template for Offer receipts.
 *
 * In CiviCRM 6.x, the template content is already loaded by the time this hook fires
 * (inside renderTemplateRaw, after resolveContent()). Setting messageTemplateID here
 * does NOT cause a re-load. Instead, we detect the offer here and set a static flag,
 * then swap the actual content in hook_civicrm_alterMailContent.
 */
function custominvoicenumbersandoffers_civicrm_alterMailParams(&$params, $context) {
  // Only process on the 'messageTemplate' context (first invocation, before rendering)
  if ($context !== 'messageTemplate') {
    return;
  }

  $workflow = $params['workflow'] ?? '';

  // Only intercept the standard invoice/receipt workflow
  if ($workflow !== 'contribution_invoice_receipt') {
    return;
  }

  // Contribution ID is in tokenContext (set by CRM_Contribute_Form_Task_Invoice::printPDF)
  $contributionId = $params['tokenContext']['contributionId'] ?? NULL;
  if (empty($contributionId)) {
    return;
  }

  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  if (!$offerTypeId) {
    return;
  }

  try {
    $contribution = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('financial_type_id')
      ->addWhere('id', '=', $contributionId)
      ->execute()
      ->first();

    if ($contribution && (int) $contribution['financial_type_id'] === (int) $offerTypeId) {
      // Set a static flag for alterMailContent to pick up
      Civi::$statics['custominvoicenumbersandoffers']['swap_offer_template'] = TRUE;
    }
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV alterMailParams] Error: " . $e->getMessage());
  }
}

/**
 * Swap the already-loaded invoice template content with the Offer template content.
 *
 * This hook fires after alterMailParams, and receives the actual template content
 * ($mailContent) by reference. This is the correct place to replace HTML/text/subject
 * because by the time hooks fire, resolveContent() has already loaded the workflow template.
 */
function custominvoicenumbersandoffers_civicrm_alterMailContent(&$content) {
  // Check if alterMailParams flagged this as an Offer
  if (empty(Civi::$statics['custominvoicenumbersandoffers']['swap_offer_template'])) {
    return;
  }

  // Clear the flag immediately to avoid affecting subsequent emails
  Civi::$statics['custominvoicenumbersandoffers']['swap_offer_template'] = FALSE;

  $offerTemplateId = Civi::settings()->get('cinv_offer_message_template_id');
  if (empty($offerTemplateId)) {
    return;
  }

  try {
    $offerTemplate = \Civi\Api4\MessageTemplate::get(FALSE)
      ->addSelect('msg_html', 'msg_text', 'msg_subject')
      ->addWhere('id', '=', $offerTemplateId)
      ->execute()
      ->first();

    if ($offerTemplate) {
      $html = $offerTemplate['msg_html'] ?? '';
      $text = $offerTemplate['msg_text'] ?? '';
      $subject = $offerTemplate['msg_subject'] ?? '';

      // Self-heal: Fix old Smarty syntax for custom fields.
      // Old code used {$contribution.custom_N|escape:'html'} wrapped in {if} blocks.
      // Correct CiviCRM 6.x token syntax is {contribution.custom_N} (no $, no modifiers).
      $needsDbUpdate = FALSE;
      if (preg_match('/\{\$contribution\.custom_\d+/', $html) || preg_match('/\{\$contribution\.custom_\d+/', $text)) {
        // Remove {if ($contribution.custom_N|default:'')} ... {/if} wrappers
        // and convert {$contribution.custom_N|escape:'html'} to {contribution.custom_N}
        $fixPattern = '/\{if\s*\(\$contribution\.(custom_\d+)\|default:[\'"][\'"]\)\}\s*/';
        $html = preg_replace($fixPattern, '', $html);
        $text = preg_replace($fixPattern, '', $text);

        // Remove the matching {/if} (only those immediately following custom field blocks)
        $html = preg_replace('/\{\/if\}\s*(?=\n*\s*(?:\{if\s*\(\$contribution\.custom_|\<\/div\>\s*\{\/if\}|$))/s', '', $html);

        // Convert the Smarty variable to a CiviCRM token
        $html = preg_replace('/\{\$contribution\.(custom_\d+)\|escape:[\'"]html[\'"]\}/', '{contribution.$1}', $html);
        $text = preg_replace('/\{\$contribution\.(custom_\d+)\|escape:[\'"]html[\'"]\}/', '{contribution.$1}', $text);

        // Also handle any remaining {$contribution.custom_N} without modifiers
        $html = preg_replace('/\{\$contribution\.(custom_\d+)\}/', '{contribution.$1}', $html);
        $text = preg_replace('/\{\$contribution\.(custom_\d+)\}/', '{contribution.$1}', $text);

        // Remove {* offer_intro_text *} and {* offer_addition *} comments
        $html = preg_replace('/\s*\{\*\s*offer_\w+\s*\*\}/', '', $html);
        $text = preg_replace('/\s*\{\*\s*offer_\w+\s*\*\}/', '', $text);

        // Clean up empty {if}...{/if} blocks that remain
        $html = preg_replace('/\{if[^}]*\}\s*\{\/if\}/', '', $html);
        $text = preg_replace('/\{if[^}]*\}\s*\{\/if\}/', '', $text);

        $needsDbUpdate = TRUE;
      }

      // Replace the already-loaded invoice content with our Offer template content
      if (!empty($html)) {
        $content['html'] = $html;
      }
      if (!empty($text)) {
        $content['text'] = $text;
      }
      if (!empty($subject)) {
        $content['subject'] = $subject;
      }

      // Persist the fix to the database so it only needs to happen once
      if ($needsDbUpdate) {
        try {
          \Civi\Api4\MessageTemplate::update(FALSE)
            ->addWhere('id', '=', $offerTemplateId)
            ->addValue('msg_html', $html)
            ->addValue('msg_text', $text)
            ->execute();
        } catch (Exception $e) {
          CRM_Core_Error::debug_log_message("[CINV alterMailContent] Could not update stored template: " . $e->getMessage());
        }
      }
    } else {
      CRM_Core_Error::debug_log_message("[CINV alterMailContent] Offer template ID {$offerTemplateId} not found in database");
    }
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV alterMailContent] Error loading offer template: " . $e->getMessage());
  }
}

function custominvoicenumbersandoffers_civicrm_navigationMenu(&$menu)
{
  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  $profileId = Civi::settings()->get('cinv_offer_profile_id');

  // Get localized Offer labels
  $locale = CRM_Core_I18n::getLocale();
  $offerLabel = 'Offer';
  $offerLabelPlural = 'Offers';
  $newOfferLabel = 'New Offer';
  if (stripos($locale, 'de') === 0) {
    $offerLabel = 'Angebot';
    $offerLabelPlural = 'Angebote';
    $newOfferLabel = 'Neues Angebot';
  } elseif (stripos($locale, 'fr') === 0) {
    $offerLabel = "l'offre";
    $offerLabelPlural = 'offres';
    $newOfferLabel = 'Nouvelle offre';
  } elseif (stripos($locale, 'es') === 0) {
    $offerLabel = 'oferta';
    $offerLabelPlural = 'ofertas';
    $newOfferLabel = 'Nueva oferta';
  }

  // The Offers navigation item is persisted in the civicrm_navigation table
  // (created during install). We only need to ensure it's in the right position
  // in case it already appears from the DB. If not found (e.g. DB was reset),
  // re-create it as a fallback.
  if ($offerTypeId && $profileId) {
    $newOfferUrl = "civicrm/contribute/add?reset=1&action=add&context=standalone&financial_type_id={$offerTypeId}&gid={$profileId}";
    $overviewUrl = 'civicrm/offer-overview';

    // Check if Offers item already exists in the menu tree (loaded from DB)
    $offersNavKey = NULL;
    foreach ($menu as $key => $item) {
      if (!empty($item['attributes']['name']) && $item['attributes']['name'] === 'Offers') {
        $offersNavKey = $key;
        break;
      }
    }

    if ($offersNavKey !== NULL) {
      // Offers exists in menu - ensure URL points to overview (migration)
      $menu[$offersNavKey]['attributes']['url'] = $overviewUrl;

      // Ensure "New Offer" child exists in the in-memory tree
      $hasNewOfferChild = FALSE;
      if (!empty($menu[$offersNavKey]['child'])) {
        foreach ($menu[$offersNavKey]['child'] as $child) {
          if (!empty($child['attributes']['name']) && $child['attributes']['name'] === 'new_offer') {
            $hasNewOfferChild = TRUE;
            break;
          }
        }
      }
      if (!$hasNewOfferChild) {
        $maxNavID = 0;
        _custominvoicenumbersandoffers_findMaxNavID($menu, $maxNavID);
        $childNavID = $maxNavID + 1;
        $menu[$offersNavKey]['child'][$childNavID] = [
          'attributes' => [
            'label' => $newOfferLabel,
            'name' => 'new_offer',
            'url' => $newOfferUrl,
            'permission' => 'access CiviContribute',
            'operator' => 'OR',
            'separator' => 0,
            'parentID' => $menu[$offersNavKey]['attributes']['navID'],
            'navID' => $childNavID,
            'weight' => 1,
            'active' => 1,
          ],
        ];
      }
    } else {
      // Fallback: create Offers in the in-memory tree
      $maxNavID = 0;
      _custominvoicenumbersandoffers_findMaxNavID($menu, $maxNavID);
      $newNavID = $maxNavID + 1;
      $childNavID = $newNavID + 1;

      $contributionsWeight = 0;
      foreach ($menu as $key => $item) {
        if (!empty($item['attributes']['name']) && $item['attributes']['name'] === 'Contributions') {
          $contributionsWeight = $item['attributes']['weight'] ?? 0;
          break;
        }
      }

      $offersItem = [
        'attributes' => [
          'label' => $offerLabelPlural,
          'name' => 'Offers',
          'url' => $overviewUrl,
          'permission' => 'access CiviContribute',
          'operator' => 'OR',
          'separator' => 0,
          'parentID' => NULL,
          'navID' => $newNavID,
          'weight' => $contributionsWeight + 1,
          'icon' => 'crm-i fa-file-lines',
          'active' => 1,
        ],
        'child' => [
          $childNavID => [
            'attributes' => [
              'label' => $newOfferLabel,
              'name' => 'new_offer',
              'url' => $newOfferUrl,
              'permission' => 'access CiviContribute',
              'operator' => 'OR',
              'separator' => 0,
              'parentID' => $newNavID,
              'navID' => $childNavID,
              'weight' => 1,
              'active' => 1,
            ],
          ],
        ],
      ];

      $newMenu = [];
      $inserted = FALSE;
      foreach ($menu as $key => $item) {
        $newMenu[$key] = $item;
        if (!empty($item['attributes']['name']) && $item['attributes']['name'] === 'Contributions') {
          $newMenu[$newNavID] = $offersItem;
          $inserted = TRUE;
        }
      }
      if (!$inserted) {
        $newMenu[$newNavID] = $offersItem;
      }
      $menu = $newMenu;

      // Also persist to DB so it appears in Navigation Menu admin
      try {
        $contribWeight = CRM_Core_DAO::singleValueQuery(
          "SELECT weight FROM civicrm_navigation WHERE name = 'Contributions' AND parent_id IS NULL LIMIT 1"
        );
        $navParams = [
          'label' => $offerLabelPlural,
          'name' => 'Offers',
          'url' => $overviewUrl,
          'permission' => 'access CiviContribute',
          'operator' => 'OR',
          'separator' => 0,
          'parent_id' => NULL,
          'weight' => ($contribWeight ? (int)$contribWeight + 1 : 50),
          'is_active' => 1,
          'icon' => 'crm-i fa-file-lines',
          'domain_id' => CRM_Core_Config::domainID(),
        ];
        $navResult = CRM_Core_BAO_Navigation::add($navParams);
        if (!empty($navResult->id)) {
          $offersNavId = (int) $navResult->id;
          Civi::settings()->set('cinv_offer_nav_id', $offersNavId);
          _custominvoicenumbersandoffers_fixOfferNavWeight($offersNavId, (int) $contribWeight);

          // Add "New Offer" child
          $newOfferParams = [
            'label' => $newOfferLabel,
            'name' => 'new_offer',
            'url' => $newOfferUrl,
            'permission' => 'access CiviContribute',
            'operator' => 'OR',
            'separator' => 0,
            'parent_id' => $offersNavId,
            'weight' => 1,
            'is_active' => 1,
            'domain_id' => CRM_Core_Config::domainID(),
          ];
          CRM_Core_BAO_Navigation::add($newOfferParams);
        }
      } catch (Exception $e) {
        CRM_Core_Error::debug_log_message('[CINV] Error persisting Offers nav fallback: ' . $e->getMessage());
      }
    }
  }

  // Administer > CiviContribute: add admin link
  foreach ($menu as &$item) {

    if ($item['attributes']['name'] === 'Administer') {

      // Unterpunkt "CiviContribute" finden
      if (!empty($item['child'])) {
        foreach ($item['child'] as &$child) {

          if ($child['attributes']['name'] === 'CiviContribute') {

            // Unser Menüpunkt unter CiviContribute einfügen
            $child['child'][] = [
              'attributes' => [
                'label' => ts('Custom Invoice Numbers and Offers'),
                'name' => 'cinv_admin',
                'url' => 'civicrm/custominvoicenumbersandoffers/admin',
                'permission' => 'administer CiviCRM',
                'operator' => 'OR',
                'separator' => 0,
              ],
            ];
          }
        }
      }
    }
  }
}

/**
 * Customize Contribution form when opened for an Offer financial type.
 * Ensures profile fields are properly displayed and applies Offer-specific styling.
 */
function custominvoicenumbersandoffers_civicrm_buildForm($formName, &$form)
{

  // Check if this is a view contribution form - handles multiple possible form names
  $isViewForm = in_array($formName, [
    'CRM_Contribute_Form_ContributionView',
    'CRM_Contribute_Form_Contribution_View', // Alternative name
  ]);

  if ($isViewForm) {
    // CRITICAL: Before the form loads the contribution, clean NULL integer fields from database
    // This prevents validation errors when CiviCRM tries to load the contribution
    try {
      $contributionId = $form->get('id') ?? ($form->_id ?? NULL);
      
      if ($contributionId) {
        // Get the contribution to check if it's an offer
        $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
        
        if ($offerTypeId) {
          $contrib = \Civi\Api4\Contribution::get(FALSE)
            ->addSelect('financial_type_id')
            ->addWhere('id', '=', $contributionId)
            ->execute()
            ->first();
          
          if ($contrib && $contrib['financial_type_id'] == $offerTypeId) {
            // This is an offer - clean NULL integer fields in database before loading
            // Only use fields that exist in the civicrm_contribution schema
            $sql = "UPDATE civicrm_contribution SET creditnote_id = NULL, campaign_id = NULL, contribution_page_id = NULL WHERE id = %1 AND (creditnote_id = 0 OR campaign_id = 0 OR contribution_page_id = 0)";
            CRM_Core_DAO::executeQuery($sql, [1 => [$contributionId, 'Integer']]);
          }
        }
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("[CINV buildForm] Error during cleanup: " . $e->getMessage());
    }
    
    // View label customization handled via alterContent hook
    return;
  }

  // Check both possible form names for Contribution forms
  $isContributionForm = in_array($formName, [
    'CRM_Contribute_Form_Contribution',
    'CRM_Contribute_Form_Contribution_Main',
  ]);

  if (!$isContributionForm) {
    return;
  }

  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  
  // CRITICAL: If editing an existing offer, clean NULL integer fields from database before loading form
  try {
    $contributionId = $form->get('id') ?? ($form->_id ?? NULL);
    
    if ($contributionId && $offerTypeId) {
      $contrib = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('financial_type_id')
        ->addWhere('id', '=', $contributionId)
        ->execute()
        ->first();
      
      if ($contrib && $contrib['financial_type_id'] == $offerTypeId) {
        // This is an offer - clean NULL integer fields in database before loading form
        // Only use fields that exist in the civicrm_contribution schema
        $sql = "UPDATE civicrm_contribution SET creditnote_id = NULL, campaign_id = NULL, contribution_page_id = NULL WHERE id = %1 AND (creditnote_id = 0 OR campaign_id = 0 OR contribution_page_id = 0)";
        CRM_Core_DAO::executeQuery($sql, [1 => [$contributionId, 'Integer']]);
      }
    }
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV buildForm] Error during edit cleanup: " . $e->getMessage());
  }

  // Detect if this is an Offer form:
  // 1) New offer: financial_type_id in URL
  // 2) Editing existing offer: contribution in DB has Offer financial type
  $ftid = CRM_Utils_Request::retrieve('financial_type_id', 'Integer', NULL, FALSE, NULL);
  $isOfferForm = ($ftid && $offerTypeId && $ftid == $offerTypeId);

  if (!$isOfferForm && $offerTypeId) {
    // Check if editing an existing Offer contribution
    $editContribId = $form->get('id') ?? ($form->_id ?? NULL);
    if ($editContribId) {
      $editFtId = CRM_Core_DAO::singleValueQuery(
        "SELECT financial_type_id FROM civicrm_contribution WHERE id = %1",
        [1 => [$editContribId, 'Integer']]
      );
      if ((int) $editFtId === (int) $offerTypeId) {
        $isOfferForm = TRUE;
      }
    }
  }

  if (!$isOfferForm) {
    return;
  }

  // Temporarily set Offer as the default financial type so AJAX calls pick it up
  try {
    \Civi\Api4\FinancialType::update(FALSE)
      ->addWhere('id', '=', $offerTypeId)
      ->addValue('is_default', TRUE)
      ->execute();

    // Clear any other defaults
    \Civi\Api4\FinancialType::update(FALSE)
      ->addWhere('id', '!=', $offerTypeId)
      ->addValue('is_default', FALSE)
      ->execute();
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message("[CINV] Error setting default financial type: " . $e->getMessage());
  }

  // Pre-set financial_type_id and payment_instrument_id in the form data
  $offerPaymentInstrumentId = Civi::settings()->get('cinv_offer_payment_instrument_id');
  $defaults = ['financial_type_id' => $offerTypeId];
  
  if ($offerPaymentInstrumentId) {
    $defaults['payment_instrument_id'] = $offerPaymentInstrumentId;
  }
  
  $form->setDefaults($defaults);

  // Inject script directly via CRM_Core_Resources
  $locale = CRM_Core_I18n::getLocale();
  $offerLabel = 'Offer';
  $offerSubmit = 'Save Offer';
  if (stripos($locale, 'de') === 0) {
    $offerLabel = 'Angebot';
    $offerSubmit = 'Angebot speichern';
  } elseif (stripos($locale, 'fr') === 0) {
    $offerLabel = "l'offre";
    $offerSubmit = "Sauvegarder l'offre";
  } elseif (stripos($locale, 'es') === 0) {
    $offerLabel = 'oferta';
    $offerSubmit = 'Guardar oferta';
  }

  // Use offerLabel for page title too
  $offerTitle = $offerLabel;

  // Translate field labels based on locale
  $contactLabel = 'Client';
  $sourceLabel = 'Source';
  $statusLabel = 'Status';
  $dateLabel = 'Date';
  
  if (stripos($locale, 'de') === 0) {
    $contactLabel = 'Kunde';
    $sourceLabel = 'Quelle';
    $statusLabel = 'Status';
    $dateLabel = 'Datum';
  } elseif (stripos($locale, 'fr') === 0) {
    $contactLabel = 'Client';
    $sourceLabel = 'Source';
    $statusLabel = 'Statut';
    $dateLabel = 'Date';
  } elseif (stripos($locale, 'es') === 0) {
    $contactLabel = 'Cliente';
    $sourceLabel = 'Fuente';
    $statusLabel = 'Estado';
    $dateLabel = 'Fecha';
  }

  $script = "
  (function() {
    var offerTypeId = '" . $offerTypeId . "';
    var offerLabel = '" . addslashes($offerLabel) . "';
    var contactLabelText = '" . addslashes($contactLabel) . "';
    var sourceLabelText = '" . addslashes($sourceLabel) . "';
    var statusLabelText = '" . addslashes($statusLabel) . "';
    var dateLabelText = '" . addslashes($dateLabel) . "';
    var offerTitleText = '" . addslashes($offerTitle) . "';
    
    function setFinancialType(select) {
      if (!select) return;
      
      // Set the underlying select value
      select.value = offerTypeId;
      
      // Find the Select2 wrapper using the select's ID
      var selectId = select.id;
      var select2ContainerId = '#s2id_' + selectId;
      var select2Container = document.querySelector(select2ContainerId);
      
      if (select2Container) {
        // Find the select2-chosen span and update it
        var chosenSpan = select2Container.querySelector('.select2-chosen');
        if (chosenSpan) {
          // Get the text from the selected option
          var option = select.querySelector('option[value=\"' + offerTypeId + '\"]');
          if (option) {
            chosenSpan.textContent = option.textContent;
          } else {
            chosenSpan.textContent = offerLabel;
          }
        }
        
        // Try to update via Select2 API if available
        if (window.CRM && window.CRM.\$ && window.CRM.\$(select).data('select2')) {
          try {
            window.CRM.\$(select).select2('val', offerTypeId);
          } catch(e) {}
        }
      }
      
      // Prevent user interaction but allow form submission
      // (disabled fields don't submit, so we use pointer-events: none in CSS instead)
      select.classList.add('offer-financial-type-readonly');
      
      // Also prevent change events from modifying the value
      select.addEventListener('change', function(e) {
        e.preventDefault();
        this.value = offerTypeId;
        if (window.CRM && window.CRM.\$ && window.CRM.\$(this).data('select2')) {
          try {
            window.CRM.\$(this).select2('val', offerTypeId);
          } catch(e) {}
        }
      }, true);
    }
    
    function updateAllFinancialTypes() {
      // Find all financial_type selects
      var financialTypeSelects = document.querySelectorAll('select[name*=\"financial_type\"]');
      financialTypeSelects.forEach(function(select) {
        setFinancialType(select);
      });
      
      // Also look for Select2 containers that might not have a loaded select yet
      var select2Containers = document.querySelectorAll('[id^=\"s2id_item_financial_type_id_\"]');
      select2Containers.forEach(function(container) {
        container.classList.add('offer-financial-type-readonly');
        var selectId = container.id.substring(5); // Remove 's2id_' prefix
        var select = document.getElementById(selectId);
        if (select) {
          setFinancialType(select);
        }
      });
    }
    
    function updateLabelsByClass() {
      var contactLabelEl = document.querySelector('tr.crm-contribution-form-block-contact_id td.label label');
      if (contactLabelEl) {
        contactLabelEl.textContent = contactLabelText;
      }
      var sourceLabelEl = document.querySelector('tr.crm-contribution-form-block-source td.label label');
      if (sourceLabelEl) {
        sourceLabelEl.textContent = sourceLabelText;
      }
      var statusLabelEl = document.querySelector('tr.crm-contribution-form-block-contribution_status_id td.label label');
      if (statusLabelEl) {
        statusLabelEl.textContent = statusLabelText;
      }
      var dateLabelEl = document.querySelector('tr.crm-contribution-form-block-receive_date td.label label');
      if (dateLabelEl) {
        dateLabelEl.textContent = dateLabelText;
      }
    }

    function updateOfferForm() {
      // Update all financial types first
      updateAllFinancialTypes();
      
      // Update browser tab title
      document.title = offerTitleText;
      
      // Update page title using CSS class selector (language-independent)
      var pageTitle = document.querySelector('.crm-page-title');
      if (pageTitle) {
        pageTitle.textContent = offerTitleText;
      }
      
      // Hide unnecessary sections
      var hideIds = ['softCredit', 'pcp', 'billing-payment-block'];
      hideIds.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
      });
      
      // Hide by class selector
      document.querySelectorAll('div.action-link.css_right.crm-link-credit-card-mode').forEach(function(el) {
        el.style.display = 'none';
      });
      
      // Hide by class patterns
      document.querySelectorAll('fieldset.payment-details_group').forEach(function(el) {
        el.style.display = 'none';
      });
      document.querySelectorAll('details.crm-AdditionalDetail-accordion').forEach(function(el) {
        el.style.display = 'none';
      });
      document.querySelectorAll('details.crm-Premium-accordion').forEach(function(el) {
        el.style.display = 'none';
      });
      
      // Update labels using class selectors
      updateLabelsByClass();
      
      // Update submit button
      var submitBtn = document.querySelector('[type=\"submit\"]') || 
                      document.querySelector('button[name*=\"_qf_\"]') ||
                      document.querySelector('input[value*=\"Save\"]');
      
      if (submitBtn) {
        if (submitBtn.hasAttribute('value')) {
          submitBtn.setAttribute('value', '" . addslashes($offerSubmit) . "');
        } else {
          submitBtn.textContent = '" . addslashes($offerSubmit) . "';
        }
      }
      
      // Add offer-form class
      var form = document.querySelector('form');
      if (form) {
        form.classList.add('offer-form');
      }
    }
    
    // Watch for dynamically added line items (new Select2 containers)
    var observer = new MutationObserver(function(mutations) {
      var hasNewSelects = false;
      
      mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length > 0) {
          mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) {
              // Check if this is a Select2 container for financial_type
              if (node.id && node.id.indexOf('s2id_item_financial_type_id_') !== -1) {
                hasNewSelects = true;
              }
              
              // Check children
              var newSelect2s = node.querySelectorAll ? node.querySelectorAll('[id^=\"s2id_item_financial_type_id_\"]') : [];
              if (newSelect2s.length > 0) {
                hasNewSelects = true;
              }
            }
          });
        }
        
        // Watch for text changes in select2-chosen spans
        if (mutation.type === 'childList' || mutation.type === 'characterData') {
          if (mutation.target && mutation.target.classList && 
              (mutation.target.classList.contains('select2-chosen') || 
               mutation.target.closest('.select2-chosen'))) {
            var span = mutation.target.classList && mutation.target.classList.contains('select2-chosen') ? 
                      mutation.target : mutation.target.closest('.select2-chosen');
            if (span && span.textContent.indexOf('Donation') !== -1) {
              span.textContent = offerLabel;
              hasNewSelects = true;
            }
          }
        }
      });
      
      // If new selects were added, update them
      if (hasNewSelects) {
        setTimeout(updateAllFinancialTypes, 300);
        setTimeout(updateAllFinancialTypes, 800);
        setTimeout(updateAllFinancialTypes, 1500);
      }
    });
    
    // Start observing the form for changes
    var form = document.querySelector('form');
    if (form) {
      observer.observe(form, {
        childList: true,
        subtree: true,
        attributes: false,
        characterData: true
      });
    }
    
    // Execute in phases
    updateOfferForm();
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', updateOfferForm);
    }
    
    // Aggressive retry for Select2 initialization
    setTimeout(updateAllFinancialTypes, 500);
    setTimeout(updateAllFinancialTypes, 1000);
    setTimeout(updateAllFinancialTypes, 1500);
    setTimeout(updateAllFinancialTypes, 2000);
  })();
  ";

  CRM_Core_Resources::singleton()->addScript($script);
  CRM_Core_Resources::singleton()->addStyle('
    .custom-group-Donor_Information {
      display: none !important;
    }
    
    form.offer-form .crm-section {
      background-color: rgba(10, 123, 176, 0.02);
      border: 1px solid rgba(10, 123, 176, 0.1);
      padding: 12px;
      border-radius: 3px;
      margin-bottom: 15px;
    }

    form.offer-form tr.crm-contribution-form-block-financial_type_id,
    form.offer-form tr.crm-contribution-form-block-contribution_type_id,
    form.offer-form tr.crm-contribution-form-block-is_email_receipt {
      display: none !important;
    }
    
  ');
}

/**
 * Alter rendered page content for Offer contributions.
 * Minimal approach: only acts on Contribution view/edit pages,
 * uses a lightweight SQL query instead of API4 to check financial type.
 */
function custominvoicenumbersandoffers_civicrm_alterContent(&$content, $context, $tplName, &$object)
{
  // Only act on Contribution-related templates
  if ($context !== 'page' && $context !== 'form') {
    return;
  }
  if (strpos($tplName, 'CRM/Contribute') === FALSE) {
    return;
  }

  $contributionId = CRM_Utils_Request::retrieve('id', 'Integer');
  if (!$contributionId) {
    return;
  }

  $offerTypeId = Civi::settings()->get('cinv_offer_financial_type_id');
  if (!$offerTypeId) {
    return;
  }

  // Lightweight check: single SQL query, no API overhead
  $financialTypeId = CRM_Core_DAO::singleValueQuery(
    "SELECT financial_type_id FROM civicrm_contribution WHERE id = %1",
    [1 => [$contributionId, 'Integer']]
  );

  if ((int) $financialTypeId !== (int) $offerTypeId) {
    return;
  }

  // This is an Offer contribution — apply label replacements
  $locale = CRM_Core_I18n::getLocale();
  $isGerman = (stripos($locale, 'de') === 0);

  // Server-side text replacements (labels in HTML)
  $content = str_ireplace('Invoice Number', 'Offer Number', $content);
  $content = str_ireplace('Invoice Reference', 'Offer Reference', $content);
  $content = str_ireplace('Payment Details', 'Offer Details', $content);
  $content = str_ireplace('Download Invoice', 'Download', $content);
  $content = str_ireplace('Email Invoice', 'E-Mail', $content);
  $content = str_ireplace('E-Mail Invoice', 'E-Mail', $content);

  // Page/dialog title replacements for both view and edit
  $content = str_ireplace('New Contribution', 'New Offer', $content);
  $content = str_ireplace('Edit Contribution', 'Edit Offer', $content);

  if ($isGerman) {
    $content = str_ireplace('Rechnungsnummer', 'Angebotsnummer', $content);
    $content = str_ireplace('Rechnungsreferenz', 'Angebotsreferenz', $content);
    $content = str_ireplace('Zahlungsdetails', 'Angebotsdetails', $content);
    $content = str_ireplace('Rechnung herunterladen', 'Herunterladen', $content);
    $content = str_ireplace('Rechnung per E-Mail', 'E-Mail', $content);
    $content = str_ireplace('Neuer Beitrag', 'Neues Angebot', $content);
    $content = str_ireplace('Beitrag bearbeiten', 'Angebot bearbeiten', $content);
  }

  // Inject minimal CSS + JS at the end of content
  $offerLabel = $isGerman ? 'Angebot' : 'Offer';
  $viewOfferText = $isGerman ? 'Angebot ansehen für' : 'View Offer for';
  $editOfferText = $isGerman ? 'Angebot bearbeiten' : 'Edit Offer';

  $snippet = <<<HTML
<style>
  .crm-hover-button { display: none !important; }
  td[id^="Donor_Information__"] { display: none !important; }
  tr:has(td[id^="Donor_Information__"]) { display: none !important; }
</style>
<script>
(function(){
  var vt = "{$viewOfferText}";
  var et = "{$editOfferText}";
  var ol = "{$offerLabel}";
  function fix(){
    document.querySelectorAll("span.ui-dialog-title").forEach(function(s){
      if(s.textContent.indexOf("View Contribution from")!==-1)
        s.textContent=s.textContent.replace("View Contribution from",vt);
      if(s.textContent.indexOf("Beitrag ansehen von")!==-1)
        s.textContent=s.textContent.replace("Beitrag ansehen von",vt);
      if(s.textContent.indexOf("Edit Contribution")!==-1)
        s.textContent=s.textContent.replace("Edit Contribution",et);
      if(s.textContent.indexOf("Beitrag bearbeiten")!==-1)
        s.textContent=s.textContent.replace("Beitrag bearbeiten",et);
      if(s.textContent.indexOf("New Contribution")!==-1)
        s.textContent=s.textContent.replace("New Contribution",ol);
      if(s.textContent.indexOf("Neuer Beitrag")!==-1)
        s.textContent=s.textContent.replace("Neuer Beitrag",ol);
    });
    var pt=document.querySelector(".crm-page-title");
    if(pt){
      if(pt.textContent.indexOf("Edit Contribution")!==-1)
        pt.textContent=pt.textContent.replace("Edit Contribution",et);
      if(pt.textContent.indexOf("Beitrag bearbeiten")!==-1)
        pt.textContent=pt.textContent.replace("Beitrag bearbeiten",et);
      if(pt.textContent.indexOf("New Contribution")!==-1)
        pt.textContent=pt.textContent.replace("New Contribution",ol);
      if(pt.textContent.indexOf("Neuer Beitrag")!==-1)
        pt.textContent=pt.textContent.replace("Neuer Beitrag",ol);
    }
  }
  fix();
  new MutationObserver(function(m){m.forEach(function(){fix();})})
    .observe(document.body,{childList:true,subtree:true});
})();
</script>
HTML;

  $content .= $snippet;
}

// TODO: Offers tab on contact summary - commented out for now, to be revisited later
// function custominvoicenumbersandoffers_civicrm_tabset($tabsetName, &$tabs, $context) { ... }

function _custominvoicenumbersandoffers_findMaxNavID($menu, &$max) {
  foreach ($menu as $key => $item) {
    if (is_numeric($key) && $key > $max) {
      $max = $key;
    }
    $navID = $item['attributes']['navID'] ?? 0;
    if (is_numeric($navID) && $navID > $max) {
      $max = (int) $navID;
    }
    if (!empty($item['child'])) {
      _custominvoicenumbersandoffers_findMaxNavID($item['child'], $max);
    }
  }
}

/**
 * Fix the weight of the Offers navigation item so it appears right after
 * Contributions in the Navigation Menu admin. CRM_Core_BAO_Navigation::add()
 * may auto-calculate the weight and place it at the end; this corrects it.
 *
 * @param int $offerNavId  The civicrm_navigation.id of the Offers item
 * @param int $contribWeight  The weight of the Contributions top-level item
 */
function _custominvoicenumbersandoffers_fixOfferNavWeight($offerNavId, $contribWeight) {
  try {
    $targetWeight = $contribWeight + 1;
    $domainId = CRM_Core_Config::domainID();

    // Shift all top-level items that currently occupy weight >= targetWeight
    // (except the Offers item itself) up by 1 to make room
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_navigation
         SET weight = weight + 1
       WHERE parent_id IS NULL
         AND weight >= %1
         AND id != %2
         AND domain_id = %3",
      [
        1 => [$targetWeight, 'Integer'],
        2 => [$offerNavId, 'Integer'],
        3 => [$domainId, 'Integer'],
      ]
    );

    // Set the Offers item to the correct weight
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_navigation SET weight = %1 WHERE id = %2",
      [
        1 => [$targetWeight, 'Integer'],
        2 => [$offerNavId, 'Integer'],
      ]
    );

    // Reset nav cache so the new order takes effect
    CRM_Core_BAO_Navigation::resetNavigation();
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message('[CINV] Error fixing Offers nav weight: ' . $e->getMessage());
  }
}


/**
 * Handle contribution deletion: remove the sequence entry from
 * civicrm_custom_number_sequences IF the deleted contribution held the
 * newest (highest sequence_index) number in its cycle (type + year + month).
 *
 * This allows re-use of the number so there are no gaps in the cycle,
 * but only when deleting the most recent entry. Deleting older entries
 * leaves the sequence intact to avoid breaking the continuity.
 *
 * @param int $contributionId The contribution being deleted.
 */
function _custominvoicenumbersandoffers_handleContributionDelete(int $contributionId): void {
  $table = 'civicrm_custom_number_sequences';

  try {
    // Find the sequence row(s) for this contribution
    $rows = CRM_Core_DAO::executeQuery(
      "SELECT id, type, year, month, sequence_index FROM {$table} WHERE contribution_id = %1",
      [1 => [$contributionId, 'Integer']]
    );

    while ($rows->fetch()) {
      $seqId = (int) $rows->id;
      $type = $rows->type;
      $year = (int) $rows->year;
      $month = (int) $rows->month;
      $seqIndex = (int) $rows->sequence_index;

      // Check if this is the highest sequence_index in its cycle
      $maxIndex = (int) CRM_Core_DAO::singleValueQuery(
        "SELECT MAX(sequence_index) FROM {$table} WHERE type = %1 AND year = %2 AND month = %3",
        [
          1 => [$type, 'String'],
          2 => [$year, 'Integer'],
          3 => [$month, 'Integer'],
        ]
      );

      if ($seqIndex === $maxIndex) {
        // This is the newest entry — safe to remove without creating a gap
        CRM_Core_DAO::executeQuery(
          "DELETE FROM {$table} WHERE id = %1",
          [1 => [$seqId, 'Integer']]
        );
      }
    }
  } catch (Exception $e) {
    CRM_Core_Error::debug_log_message('[CINV] Error handling contribution delete for sequence cleanup: ' . $e->getMessage());
  }
}