console.log("Domain widget view.js loaded")

if (typeof window.DomainWidget === 'undefined') {
	console.error('DomainWidget object not found')
} else {
	console.log('DomainWidget config:', window.DomainWidget)
}

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.domain-search-form').forEach(form => {
		const resultDiv = form.querySelector('.domain-search-result') || (() => {
			const div = document.createElement('div')
			div.className = 'domain-search-result'
			form.appendChild(div)
			return div
		})()

		form.addEventListener('submit', async (e) => {
			e.preventDefault()
			const domainInput = form.querySelector('input[name="domain"]')
			const tldSelect = form.querySelector('select[name="tld"]')
			const domain = domainInput.value.trim()
			const tld = tldSelect.value
			const domain_name_field = document.getElementById('domain_name')

			if (!domain) return resultDiv.textContent = "Please enter a valid domain"

			resultDiv.textContent = 'Checking…'

			try {
				const response = await fetch('/wp-json/pdh-enom/v2/check-domain', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': DomainWidget.token
					},
					body: JSON.stringify({ domain, tld })
				})

				const data = await response.json()
				console.log('API Response:', data)

				const result = data['Domains']
				const available = result.Domain.RRPText !== 'Domain not available'
				const prices = result.Domain.Prices
				const regPrice = parseFloat(prices.Registration)

				console.log('Domain:', result.Domain.Name)
				console.log('Available:', available)
				console.log('Price:', regPrice)

				if (available) {
					resultDiv.textContent = `✅ Domain ${result.Domain.Name} is available! ${DomainWidget.currencySymbol}${regPrice} to register!`

					// If we're on the product page
					if (domain_name_field) {
						domain_name_field.value = result.Domain.Name

						// Update displayed price
						updateDomainPriceOnPage(regPrice)

						// Get the product form
						const productForm = document.querySelector('form.cart')
						if (productForm) {
							// Ensure hidden fields exist with correct values
							ensureHiddenField(productForm, 'domain_name', domain)
							ensureHiddenField(productForm, 'domain_tld', tld)
							ensureHiddenField(productForm, 'domain_registration_price', regPrice.toString())

							console.log('Hidden fields set:', {
								domain_name: domain,
								domain_tld: tld,
								domain_registration_price: regPrice
							})
						}

						// Also add via REST API for immediate cart update
						//await addDomainToCart(domain, tld, regPrice)
					} else {
						// Not on product page - show button to go there
						const button = document.createElement('a')
						button.textContent = 'Configure Domain Registration'
						button.href = '/product/register-domain/?domain_name=' +
							encodeURIComponent(result.Domain.Name) +
							'&domain_registration_price=' + encodeURIComponent(regPrice)
						button.className = 'wp-element-button configure-domain-btn'
						const buttonContainer = document.createElement('div')
						buttonContainer.appendChild(button)
						resultDiv.appendChild(buttonContainer)
					}
				} else {
					if (domain_name_field) {
						domain_name_field.value = ''
					}

					try {
						console.log('Fetching name suggestions for', result.Domain.Name)
						const namesuggestions = await fetch('/wp-json/pdh-enom/v2/get-name-suggestions', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': DomainWidget.token
							},
							body: JSON.stringify({ searchterm: result.Domain.Name })
						})

						const suggData = await namesuggestions.json()
						const suggestions = suggData['DomainSuggestions'] || []

						resultDiv.textContent = `❌ Domain ${result.Domain.Name} is taken.`

						if (suggestions['Domain'] && suggestions['Domain'].length > 0) {
							const suggestionsDiv = document.createElement('div')
							suggestionsDiv.className = 'domain-suggestions'
							suggestionsDiv.innerHTML = '<div class="domain-suggestions"><strong>Suggestions:</strong><ul class="domain-name-suggestions">' +
								suggestions['Domain'].map(s => `<li>${s}</li>`).join('') +
								'</ul></div>'
							resultDiv.appendChild(suggestionsDiv)
						}
					} catch (err) {
						console.error('Error fetching name suggestions', err)
					}
				}
			} catch (err) {
				console.error('Error checking domain:', err)
				resultDiv.textContent = 'Error checking domain.'
			}
		})
	})
})

// Ensure a hidden field exists and has the correct value
function ensureHiddenField(form, name, value) {
	let field = form.querySelector(`input[name="${name}"]`)
	if (!field) {
		field = document.createElement('input')
		field.type = 'hidden'
		field.name = name
		form.appendChild(field)
		console.log(`Created hidden field: ${name}`)
	}
	field.value = value
	console.log(`Set ${name} = ${value}`)
}

// Update the displayed price on the page
function updateDomainPriceOnPage(newPrice) {
	if (typeof newPrice === 'string') newPrice = parseFloat(newPrice.replace(/[^0-9\.\-]+/g, ''))
	if (isNaN(newPrice)) return

	const formatted = newPrice.toFixed(2)
	console.log('Updating price on page to:', formatted)

	document.querySelectorAll('.woocommerce-Price-amount.amount').forEach(el => {
		const currencySpan = el.querySelector('.woocommerce-Price-currencySymbol')
		if (currencySpan) {
			const currencyHtml = currencySpan.outerHTML
			el.innerHTML = `<bdi>${currencyHtml}${formatted}</bdi>`
		} else {
			const symbol = (window.DomainWidget && DomainWidget.currencySymbol) ? DomainWidget.currencySymbol : '£'
			el.innerHTML = `<bdi><span class="woocommerce-Price-currencySymbol">${symbol}</span>${formatted}</bdi>`
		}
	})
}

// Add domain to cart via REST API
async function addDomainToCart(domainName, domainTld, price) {
	console.log('Adding to cart via REST:', { domainName, domainTld, price })

	try {
		const response = await fetch('/wp-json/pdh-enom/v2/add-domain-to-cart', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': DomainWidget.token
			},
			body: JSON.stringify({
				domain_name: domainName,
				domain_tld: domainTld,
				domain_registration_price: price
			})
		})

		const data = await response.json()
		console.log('Add to cart response:', data)

		if (data.success) {
			console.log('✅ Domain added to cart successfully')
			// Optionally refresh cart widget
			if (typeof jQuery !== 'undefined') {
				jQuery(document.body).trigger('wc_fragment_refresh')
			}
		} else {
			console.error('❌ Failed to add domain to cart:', data)
		}
	} catch (err) {
		console.error('❌ Network error adding to cart:', err)
	}
}