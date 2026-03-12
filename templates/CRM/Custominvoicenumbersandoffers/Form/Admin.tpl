
<style>
.crm-form-row {
  padding: 10px 0px 0px 0px;
}
.hint {
  font-size: 9.5pt;
  color: #CF5646;
  font-weight: 500;
}

.crm-custominvoicenumbersandoffers fieldset {
  position: relative;
  padding-bottom: 10px;
  margin-bottom: 5px;
}

.crm-custominvoicenumbersandoffers fieldset:nth-of-type(2) {
  margin-top: 0;
}

.fieldset-preview {
  position: absolute;
  top: 10px;
  right: 15px;
  background-color: #f5f5f5;
  border: 1px solid #ddd;
  padding: 10px 15px;
  border-radius: 4px;
  width: 250px;
}

.fieldset-preview h5 {
  margin: 0 0 8px 0;
  font-size: 12px;
  color: #333;
}

.preview-number {
  font-size: 16px;
  font-weight: bold;
  color: #0a7bb0;
  font-family: monospace;
  padding: 8px;
  background-color: white;
  border: 1px solid #0a7bb0;
  border-radius: 3px;
  display: block;
  text-align: center;
  word-break: break-all;
}

.setup-warning {
  background-color: #d9534f;
  color: white;
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 3px;
  border: none;
}

.setup-warning strong {
  color: white;
}

.setup-warning p {
  margin: 5px 0;
  color: white;
  justify-self: left;
}

.fieldset-preview[style*="display: none"] {
  display: none !important;
}

.fieldset-preview:empty {
  display: none;
}

.enable-offers-row {
display: flex;
flex-direction: row;
}

select[disabled],
input[readonly],
input[disabled] {
  background-color: #666;
  color: white;
}

/* Financial types multi-select styling */
.crm-custominvoicenumbersandoffers tr {
    align-items: center;
    display: flex;
}

.crm-custominvoicenumbersandoffers select[style*="width:300px"] {
  width: 300px !important;
}
/* Make Start Year and Start Month inputs visually match .crm-container .four (4em) */
.crm-custominvoicenumbersandoffers input[name="invoice_start_year"],
.crm-custominvoicenumbersandoffers input[name="invoice_start_month"],
.crm-custominvoicenumbersandoffers input[name="offer_start_year"],
.crm-custominvoicenumbersandoffers input[name="offer_start_month"] {
  width: 4em;
  box-sizing: border-box;
}

/* Reset button styling */
#reset-all-btn {
  background-color: #d9534f !important;
  color: white !important;
  border: 2px solid #c9302c !important;
  font-weight: bold;
  padding: 8px 16px;
  cursor: pointer;
  transition: background-color 0.2s;
}

#reset-all-btn:hover {
  background-color: #c9302c !important;
  border-color: #ac2925 !important;
}

#reset-all-btn:active {
  background-color: #ac2925 !important;
}
</style>

{if $isFirstSetup}
<div class="crm-block">
  <div class="setup-warning">
    <strong>Setup</strong>
    <p>{ts}Before using the extension, please enable "Tax and Invoicing" in the <a href="/civicrm/admin/setting/preferences/contribute" target="_blank">CiviContribute Component Settings</a>.
    <p>Activating offers adds a new financial type, payment method, financial account, custom fields and a new <a href="/civicrm/admin/messageTemplates?reset=1#/workflow" target="_blank">message template</a>, which you might have to edit for your needs.<br>To add different items to invoices or offers, it is recommended to also install the <a href="https://civicrm.org/extensions/line-item-editor" target="_blank">Line Item Editor</a> Extension.</p>
    <p>The values below will be locked after you complete the setup to prevent accidental changes in your number cycles. You can edit your setup at a later point though - please proceed with caution. Changing the setup will reset the current format to start a new number cycle for invoices and offers with the newly saved values. All already created invoices or offers remain unchanged and will not lose or change their numbers.{/ts}</p>
  </div>
</div>
{/if}

<div class="crm-block crm-form-block crm-custominvoicenumbersandoffers">

  <h3>{ts}Settings{/ts}</h3>

  <fieldset>
    <legend>{ts}Invoices{/ts}</legend>

    <div class="fieldset-preview">
      <h5>{ts}Next Invoice Number{/ts}:</h5>
      <div class="preview-number" id="next-invoice-preview">
        <span id="invoice-info">{ts}Loading preview...{/ts}</span>
      </div>
    </div>

    <div class="crm-form-row">
      <label>{$form.invoice_prefix.label}</label>
      <div>{$form.invoice_prefix.html}</div>
      <span class="hint">{ts}Example: "INV_". This prefix will appear before your invoice number. It overwrites the value in CiviContribute Component Settings.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>{$form.invoice_format.label}</label>
      <div>{$form.invoice_format.html}</div>
      <span class="hint">{ts}Custom format using: YYYY (year), MM (month, 2 digits: 01-12), M (month, 1 digit: 1-12), X (sequential digit). Examples: YYYYMMXX, YYYYMM-XX, YYYYXX. Try separators like '-' or '_'. Slashes ('/' or '\') do not work.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>Invoice {$form.invoice_start_index.label}</label>
      <div>{$form.invoice_start_index.html}</div>
      <span class="hint">{ts}Starting number for sequential invoices (example: 1 or 1000). Changeable, if you should have an existing number cycle, that you want to continue. Resets each month or year to 1, depending on chosen format.<br> If you are using months in your number format, the index will restart for the first contribution in a new month, otherwise with the first contribution of a new year.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>Invoice {$form.invoice_start_year.label}</label>
      <div>{$form.invoice_start_year.html}</div>
      <span class="hint">{ts}Used for reference; current year is used when generating numbers.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>Invoice {$form.invoice_start_month.label}</label>
      <div>{$form.invoice_start_month.html}</div>
      <span class="hint">{ts}Used for reference; current month is used when generating numbers.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>{$form.invoice_types.label}</label>
      <div>{$form.invoice_types.html}</div>
      <span class="hint">{ts}Select which financial types are using custom invoice numbers. Only contributions having the selected financial types will receive custom numbering, others use the systems standard. All available financial types are selected in default setup. Hold Ctrl/Cmd to select/deselect multiple. Financial types on the right side are included.{/ts}</span>
    </div>

  </fieldset>

  <fieldset>
    <legend>{ts}Offers{/ts}</legend>

    <div class="fieldset-preview">
      <h5 id="next-offer-label" style="display:none;">{ts}Next Offer Number{/ts}:</h5>
      <div class="preview-number" id="next-offer-preview" style="display:none;">
        <span id="offer-info">{ts}Loading preview...{/ts}</span>
      </div>
    </div>

    <div class="crm-form-row enable-offers-row">
      <label>{$form.enable_offers.label}</label>
      <div>{$form.enable_offers.html}</div>
    </div>

    <div class="crm-form-row">
      <label>{$form.offer_prefix.label}</label>
      <div>{$form.offer_prefix.html}</div>
      <span class="hint">{ts}Example: "OFF_" or "OFFER_". This prefix will appear before your offer number.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>{$form.offer_format.label}</label>
      <div>{$form.offer_format.html}</div>
      <span class="hint">{ts}Custom format using: YYYY (year), MM (month, 2 digits: 01-12), M (month, 1 digit: 1-12), X (sequential digit). Examples: YYYYMMXX, YYYYMM-XX, YYYYXX. Try separators like '-' or '_'. Slashes ('/' or '\') do not work.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>{$form.offer_start_index.label}</label>
      <div>{$form.offer_start_index.html}</div>
<span class="hint">{ts}Starting number for sequential offers (example: 1 or 1000). Changeable, if you should have an existing number cycle, that you want to continue. Resets each month or year to 1, depending on chosen format.<br> If you are using months in your number format, the index will restart for the first offer in a new month, otherwise with the first offer of a new year.{/ts}</span>    </div>

    <div class="crm-form-row">
      <label>{$form.offer_start_year.label}</label>
      <div>{$form.offer_start_year.html}</div>
      <span class="hint">{ts}Used for reference; current year is used when generating numbers.{/ts}</span>
    </div>

    <div class="crm-form-row">
      <label>{$form.offer_start_month.label}</label>
      <div>{$form.offer_start_month.html}</div>
      <span class="hint">{ts}Used for reference; current month is used when generating numbers.{/ts}</span>
    </div>

  </fieldset>

  <fieldset>
    <legend>{ts}Edit Setup{/ts}</legend>
    <p>{ts}Changing the setup will start a new numbering cycle after you save the new values. All already created invoices or offers remain unchanged and keep their numbers.{/ts}</p>
  </fieldset>

  <fieldset style="display: none;">
    <legend>{ts}Debugging{/ts}</legend>
    <p>{ts}Reset all extension data including offers, custom fields, financial types, and settings. This is useful for debugging and testing.{/ts}</p>
    <div style="margin-top: 10px;">
      <button type="button" class="crm-button" id="reset-all-btn" style="background-color: #d9534f; color: white;">
        <span>{ts}Reset Extension (Delete All Data){/ts}</span>
      </button>
    </div>
  </fieldset>

  {if $showButtons}
  <div class="crm-submit-buttons">{$form.buttons.html}</div>
  {else}
  <div class="crm-submit-buttons">
    <button type="button" class="crm-button" id="edit-setup-btn">
      <span>{ts}Edit Setup{/ts}</span>
    </button>
  </div>
  {/if}

  



<script>
// Create namespace for custom invoice numbers and offers
window.CRM = window.CRM || {};
window.CRM.custominvoicenumbersandoffers = {};

(function() {
  const isFirstSetup = {if $isFirstSetup}true{else}false{/if};

  const countSequentialDigits = function(format) {
    const match = format.match(/X+/i);
    return match ? match[0].length : 0;
  };

  const formatNumber = function(format, year, month, index) {
    const padding = countSequentialDigits(format);
    const paddedIndex = String(index).padStart(padding, '0');
    
    let result = format
      .replace(/YYYY/g, year)
      .replace(/MM/g, String(month).padStart(2, '0'))
      .replace(/M/g, String(month))
      .replace(/X+/i, paddedIndex);
    
    return result;
  };

  const updatePreviews = function() {
    const invoicePrefix = document.querySelector('input[name="invoice_prefix"]')?.value || '';
    const invoiceFormat = document.querySelector('input[name="invoice_format"]')?.value || 'YYYYMMXX';
    const invoiceStartIndex = document.querySelector('input[name="invoice_start_index"]')?.value || '1';
    const invoiceStartYear = document.querySelector('input[name="invoice_start_year"]')?.value || '';
    const invoiceStartMonth = document.querySelector('input[name="invoice_start_month"]')?.value || '';
    
    const enableOffers = document.querySelector('input[name="enable_offers"]')?.checked || false;
    const offerPrefix = document.querySelector('input[name="offer_prefix"]')?.value || '';
    const offerFormat = document.querySelector('input[name="offer_format"]')?.value || 'YYYYMMXX';
    const offerStartIndex = document.querySelector('input[name="offer_start_index"]')?.value || '1';
    const offerStartYear = document.querySelector('input[name="offer_start_year"]')?.value || '';
    const offerStartMonth = document.querySelector('input[name="offer_start_month"]')?.value || '';

    let year = invoiceStartYear ? parseInt(invoiceStartYear) : new Date().getFullYear();
    let month = invoiceStartMonth ? parseInt(invoiceStartMonth) : new Date().getMonth() + 1;
    
    let offerYear = offerStartYear ? parseInt(offerStartYear) : new Date().getFullYear();
    let offerMonth = offerStartMonth ? parseInt(offerStartMonth) : new Date().getMonth() + 1;

    try {
      const invoiceCore = formatNumber(invoiceFormat, year, month, invoiceStartIndex);
      const invoiceNumber = invoicePrefix + invoiceCore;
      document.getElementById('invoice-info').textContent = invoiceNumber;
    } catch (e) {
      document.getElementById('invoice-info').textContent = '{ts}Invalid format{/ts}';
    }
    
    const offerPreview = document.getElementById('next-offer-preview');
    const offerLabel = document.getElementById('next-offer-label');
    
    if (enableOffers) {
      offerPreview.style.display = 'block';
      offerLabel.style.display = 'block';
      try {
        const offerCore = formatNumber(offerFormat, offerYear, offerMonth, offerStartIndex);
        const offerNumber = offerPrefix + offerCore;
        document.getElementById('offer-info').textContent = offerNumber;
      } catch (e) {
        document.getElementById('offer-info').textContent = '{ts}Invalid format{/ts}';
      }
    } else {
      offerPreview.style.display = 'none';
      offerLabel.style.display = 'none';
    }
  };

  // Handle form submission - copy disabled select values to hidden inputs
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function(e) {
      const disabledSelects = form.querySelectorAll('select[disabled]');
      disabledSelects.forEach(select => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = select.name;
        hiddenInput.value = select.value;
        form.appendChild(hiddenInput);
      });
    });
  }

  // Update previews on input change
  const allInputs = document.querySelectorAll('input, select');
  allInputs.forEach(input => {
    if (input.name && (input.name.includes('invoice_') || input.name.includes('offer_'))) {
      input.addEventListener('change', updatePreviews);
      input.addEventListener('keyup', updatePreviews);
    }
  });

  // Add validation for year fields
  const yearInputs = document.querySelectorAll('input[type="text"]');
  yearInputs.forEach(input => {
    if (input.name && input.name.includes('_start_year')) {
      input.addEventListener('blur', function() {
        if (this.value && this.value.length < 4) {
          this.value = String(this.value).padStart(4, '0');
        }
      });
      input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 4) {
          this.value = this.value.substring(0, 4);
        }
      });
    }
  });

  // Add confirmation to Complete Setup button
  if (form && isFirstSetup) {
    form.addEventListener('submit', function(e) {
      if (!confirm('{ts}You are about to save your setup. Do you want to proceed?{/ts}')) {
        e.preventDefault();
        return false;
      }
    });
  }

  // Attach Edit Setup button click handler
  const editSetupBtn = document.getElementById('edit-setup-btn');
  if (editSetupBtn) {
    editSetupBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const confirmMsg = '{ts}Changing the setup will reset the current format to start a new number cycle for invoices and offers with the newly saved values. All already created invoices or offers remain unchanged and will not lose or change their current numbers. Do you want to proceed?{/ts}';
      if (confirm(confirmMsg)) {
        const form = document.querySelector('form');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'unlock_setup';
        input.value = '1';
        form.appendChild(input);
        
        const submitBtn = document.querySelector('input[type="submit"]');
        if (submitBtn) {
          submitBtn.click();
        } else {
          form.submit();
        }
      }
    });
  }

  // Attach Reset All button click handler
  const resetAllBtn = document.getElementById('reset-all-btn');
  if (resetAllBtn) {
    resetAllBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const confirmMsg = 'WARNING: This will permanently delete ALL extension data including:\n\n' +
                        '- All offers (contributions)\n' +
                        '- Custom fields and groups\n' +
                        '- Financial types and accounts\n' +
                        '- Payment instruments\n' +
                        '- Profiles\n' +
                        '- Number sequences\n' +
                        '- All settings\n\n' +
                        'This action CANNOT be undone!\n\n' +
                        'Are you absolutely sure you want to continue?';
      if (confirm(confirmMsg)) {
        // Second confirmation
        const userInput = prompt('FINAL WARNING: All extension data will be permanently deleted.\n\nType YES (in capital letters) to confirm:');
        if (userInput === 'YES') {
          const url = CRM.url('civicrm/custominvoicenumbersandoffers/admin', {ldelim}reset: 1, reset_all: 1{rdelim});
          window.location.href = url;
        } else if (userInput !== null) {
          alert('Reset cancelled. You must type YES in capital letters to confirm.');
        }
      }
    });
  }

  // Initial update
  updatePreviews();
})();
</script>
