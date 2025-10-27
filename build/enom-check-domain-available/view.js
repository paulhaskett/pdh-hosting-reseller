/******/ (() => { // webpackBootstrap
/*!*************************************************!*\
  !*** ./src/enom-check-domain-available/view.js ***!
  \*************************************************/
console.log("Domain widget view.js loaded");
if (typeof window.DomainWidget === 'undefined') {
  console.error('DomainWidget object not found');
} else {
  console.log('DomainWidget config:', window.DomainWidget);
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.domain-search-form').forEach(form => {
    const resultDiv = form.querySelector('.domain-search-result') || (() => {
      const div = document.createElement('div');
      div.className = 'domain-search-result';
      form.appendChild(div);
      return div;
    })();
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const domainInput = form.querySelector('input[name="domain"]');
      const tldSelect = form.querySelector('select[name="tld"]');
      const domain = domainInput.value.trim();
      const tld = tldSelect.value;
      if (!domain) return resultDiv.textContent = "Please enter a valid domain";
      resultDiv.innerHTML = `
    <div style="text-align: center; padding: 20px;">
        <div style="display: inline-flex; gap: 6px; margin-bottom: 10px;">
            <span style="width: 8px; height: 8px; background: #007bff; border-radius: 50%; animation: typingBounce 1.4s infinite;"></span>
            <span style="width: 8px; height: 8px; background: #007bff; border-radius: 50%; animation: typingBounce 1.4s infinite; animation-delay: 0.2s;"></span>
            <span style="width: 8px; height: 8px; background: #007bff; border-radius: 50%; animation: typingBounce 1.4s infinite; animation-delay: 0.4s;"></span>
        </div>
        <p style="margin: 0; font-size: 14px; color: #666;">Checking availability...</p>
    </div>
    <style>
        @keyframes typingBounce {
            0%, 60%, 100% { 
                transform: translateY(0);
                opacity: 0.7;
            }
            30% { 
                transform: translateY(-10px);
                opacity: 1;
            }
        }
    </style>
`;
      try {
        const response = await fetch('/wp-json/pdh-enom/v2/check-domain', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': DomainWidget.token
          },
          body: JSON.stringify({
            domain,
            tld
          })
        });
        const data = await response.json();
        console.log('API Response:', data);
        const result = data['Domains'];
        const available = result.Domain.RRPText !== 'Domain not available';
        console.log('Domain:', result.Domain.Name);
        console.log('Available:', available);
        if (available) {
          const prices = result.Domain.Prices;
          const regPrice = parseFloat(prices.Registration);
          console.log('Price per year:', regPrice);
          resultDiv.textContent = `✅ Domain ${result.Domain.Name} is available! ${DomainWidget.currencySymbol}${regPrice}/year to register!`;

          // Check if we're on the product page
          const isOnProductPage = document.querySelector('form.cart') !== null;
          if (isOnProductPage) {
            // We're on the product page - fill in the form and update price
            console.log('On product page - filling form');
            populateProductFormOnPage(domain, tld, regPrice);

            // Show success message
            resultDiv.innerHTML = `<p style="color: green; font-weight: bold;">✅ ${result.Domain.Name} is available! Price: ${DomainWidget.currencySymbol}${regPrice}/year</p>`;
          } else {
            // Not on product page - show button to go there
            const button = document.createElement('a');
            button.textContent = 'Configure Domain Registration';

            // Build URL with domain info
            const productUrl = new URL('/product/register-domain/', window.location.origin);
            productUrl.searchParams.set('domain_name', domain);
            productUrl.searchParams.set('domain_tld', tld);
            productUrl.searchParams.set('price', regPrice.toString());
            button.href = productUrl.toString();
            button.className = 'wp-element-button configure-domain-btn';
            const buttonContainer = document.createElement('div');
            buttonContainer.appendChild(button);
            resultDiv.appendChild(buttonContainer);
            console.log('Redirect URL:', productUrl.toString());
          }
        } else {
          // Domain not available - show suggestions
          try {
            console.log('Fetching name suggestions for', result.Domain.Name);
            const namesuggestions = await fetch('/wp-json/pdh-enom/v2/get-name-suggestions', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': DomainWidget.token
              },
              body: JSON.stringify({
                searchterm: result.Domain.Name
              })
            });
            const suggData = await namesuggestions.json();
            const suggestions = suggData['DomainSuggestions'] || [];
            resultDiv.textContent = `❌ Domain ${result.Domain.Name} is taken.`;
            if (suggestions['Domain'] && suggestions['Domain'].length > 0) {
              const suggestionsDiv = document.createElement('div');
              suggestionsDiv.className = 'domain-suggestions';
              suggestionsDiv.innerHTML = '<div class="domain-suggestions"><strong>Suggestions:</strong><ul class="domain-name-suggestions">' + suggestions['Domain'].map(s => `<li>${s}</li>`).join('') + '</ul></div>';
              resultDiv.appendChild(suggestionsDiv);
            }
          } catch (err) {
            console.error('Error fetching name suggestions', err);
          }
        }
      } catch (err) {
        console.error('Error checking domain:', err);
        resultDiv.textContent = 'Error checking domain.';
      }
    });
  });
});

/**
 * When on product page, fill form and set up price updates
 */
function populateProductFormOnPage(domain, tld, pricePerYear) {
  console.log('populateProductFormOnPage called:', {
    domain,
    tld,
    pricePerYear
  });

  // Fill in the domain_name field
  const domainNameInput = document.getElementById('domain_name');
  if (domainNameInput) {
    domainNameInput.value = domain + '.' + tld;
    domainNameInput.setAttribute('readonly', 'readonly');
    domainNameInput.style.backgroundColor = '#f5f5f5';
    console.log('Set domain_name to:', domain + '.' + tld);
  }

  // Get the product form
  const productForm = document.querySelector('form.cart');
  if (!productForm) {
    console.warn('Product form not found');
    return;
  }

  // Store price per year globally for calculations
  window.domainPricePerYear = pricePerYear;
  console.log('Stored domainPricePerYear:', window.domainPricePerYear);

  // Create/update hidden fields
  ensureHiddenField(productForm, 'domain_name', domain);
  ensureHiddenField(productForm, 'domain_tld', tld);

  // Set initial price for 1 year
  updatePriceOnProductPage(1, pricePerYear);

  // Listen for years dropdown changes
  const yearsSelect = document.getElementById('domain-years-selector');
  console.log('Years select element:', yearsSelect);
  if (yearsSelect) {
    // Remove any existing listeners by cloning
    const newYearsSelect = yearsSelect.cloneNode(true);
    yearsSelect.parentNode.replaceChild(newYearsSelect, yearsSelect);

    // Add new listener
    newYearsSelect.addEventListener('change', function () {
      const years = parseInt(this.value) || 1;
      console.log('Years changed to:', years);
      updatePriceOnProductPage(years, pricePerYear);
      ensureHiddenField(productForm, 'domain_years', years);
    });
    console.log('Change listener attached to years dropdown');
  } else {
    console.warn('domain_years dropdown not found');
  }
}

/**
 * Update price and hidden field when years change
 */
function updatePriceOnProductPage(years, pricePerYear) {
  if (!pricePerYear || years < 1) return;
  const totalPrice = pricePerYear * years;
  console.log('updatePriceOnProductPage:', {
    years,
    pricePerYear,
    totalPrice
  });

  // Update hidden field in form
  const productForm = document.querySelector('form.cart');
  if (productForm) {
    ensureHiddenField(productForm, 'domain_registration_price', totalPrice.toString());
  }

  // Update displayed price on page
  updateDisplayedPrice(totalPrice, years, pricePerYear);
}

/**
 * Update the displayed price on the page
 */
function updateDisplayedPrice(totalPrice, years, pricePerYear) {
  if (isNaN(totalPrice) || totalPrice <= 0) return;
  const formatted = totalPrice.toFixed(2);
  const symbol = window.DomainWidget && window.DomainWidget.currencySymbol ? window.DomainWidget.currencySymbol : '£';
  console.log('updateDisplayedPrice:', {
    totalPrice: formatted,
    years,
    symbol
  });

  // Update all price display elements
  document.querySelectorAll('.woocommerce-Price-amount.amount').forEach(function (el) {
    let priceHtml = '<bdi><span class="woocommerce-Price-currencySymbol">' + symbol + '</span>' + formatted + '</bdi>';
    if (years > 1) {
      priceHtml += ' <small style="font-size: 0.8em; color: #666;">(' + years + ' years @ ' + symbol + pricePerYear.toFixed(2) + '/year)</small>';
    }
    el.innerHTML = priceHtml;
  });

  // Also try to update .price elements
  document.querySelectorAll('.price').forEach(function (priceEl) {
    const priceAmount = priceEl.querySelector('.woocommerce-Price-amount');
    if (priceAmount) {
      let priceHtml = '<bdi><span class="woocommerce-Price-currencySymbol">' + symbol + '</span>' + formatted + '</bdi>';
      if (years > 1) {
        priceHtml += ' <small style="font-size: 0.8em; color: #666;">(' + years + ' years @ ' + symbol + pricePerYear.toFixed(2) + '/year)</small>';
      }
      priceAmount.innerHTML = priceHtml;
    }
  });
}

/**
 * Ensure a hidden input field exists with the correct value
 */
function ensureHiddenField(form, name, value) {
  let field = form.querySelector('input[name="' + name + '"]');
  if (!field || field.type !== 'hidden') {
    // Create or find hidden field
    let hiddenField = form.querySelector('input[type="hidden"][name="' + name + '"]');
    if (!hiddenField) {
      hiddenField = document.createElement('input');
      hiddenField.type = 'hidden';
      hiddenField.name = name;
      form.appendChild(hiddenField);
      console.log('Created hidden field:', name);
    }
    hiddenField.value = value;
  } else {
    // Update existing field
    field.value = value;
  }
  console.log('Set ' + name + ' = ' + value);
}
/******/ })()
;
//# sourceMappingURL=view.js.map