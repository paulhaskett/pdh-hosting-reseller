/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */
console.log("this ran ")
if (typeof window.DomainWidget === 'undefined') {
	console.error('DomainWidget object not found')

}
console.log(window.DomainWidget.token)
console.log(DomainWidget.restUrl)
console.log(DomainWidget.name)

/* eslint-disable no-console */
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
			const domain_name = document.getElementById('domain_name')

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
				const result = data['Domains']
				console.log('result', result)
				if (result.Domain.RRPText === 'Domain not available') {
					available = false
				} else {
					available = true
				}
				console.log('RRPText', result.Domain.RRPText)
				console.log(available)
				const prices = result.Domain.Prices
				const regPrice = prices.Registration
				const productForm = form.closest('form.cart') || form



				if (available) {
					resultDiv.textContent = `✅ Domain ${result.Domain.Name} is available! £${prices.Registration} to register!`



					// add the domain name to product field 
					// if on the single product page and the domain_name field is accessible 
					// else display button to forward to the single product page and fill domain_name field

					if (domain_name) {

						domain_name.value = result.Domain.Name

						// update product price on single product page
						console.log('hello')
						updateDomainPriceOnPage(regPrice)
						// update product price in cart
						ensureHiddenPriceInput(productForm, regPrice)
						addDomainToCart(domain, tld, regPrice)






					} else {
						const button = document.createElement('a')
						button.textContent = 'Configure Domain Registration'
						button.href = '/product/register-domain/?domain_name=' + encodeURIComponent(result.Domain.Name) + '&domain_tld=' + encodeURIComponent(prices.Registration) + '&domain_registration_price=' + encodeURIComponent(prices.Registration)
						button.className = 'wp-element-button configure-domain-btn'
						const buttonContainer = document.createElement('div')
						buttonContainer.appendChild(button)
						resultDiv.appendChild(buttonContainer)
					}



				} else {

					if (domain_name) {
						domain_name.value = ''
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

						const data = await namesuggestions.json()
						console.log('data response', data)
						const suggestions = data['DomainSuggestions'] || []
						console.log('suggestions ', suggestions)
						console.log('suggestions length', suggestions['Domain'].length)
						// create resultDiv and display suggested names
						resultDiv.textContent = `❌ Domain ${result.Domain.Name} is taken.`
						const suggestionsDiv = document.createElement('div')
						suggestionsDiv.className = 'domain-suggestions'

						if (suggestions['Domain'].length > 0) {
							suggestionsDiv.innerHTML = '<div class ="domain-suggestions"><strong>Suggestions:</strong><ul class="domain-name-suggestions">' +
								suggestions['Domain'].map(s => `<li>${s}</li>`).join('') +
								'</ul></div>'
						} else {
							suggestionsDiv.textContent = 'No alternative suggestions found.'
						}

						resultDiv.appendChild(suggestionsDiv)
					} catch (err) {
						console.error('Error fetching name suggestions', err)
					}


				}


			} catch (err) {
				resultDiv.textContent = 'Error checking domain.'
			}
		})
	})
})

// call this when you have newPrice (a number or numeric string)
function updateDomainPriceOnPage(newPrice) {
	if (typeof newPrice === 'string') newPrice = parseFloat(newPrice.replace(/[^0-9\.\-]+/g, ''))
	if (isNaN(newPrice)) return

	// Format to 2 decimal places (change as needed)
	const formatted = newPrice.toFixed(2)
	console.log('newPrice', formatted)
	// Update all WooCommerce price elements on the page
	document.querySelectorAll('.woocommerce-Price-amount.amount').forEach(el => {
		// Keep currency span if present, otherwise insert currency symbol manually
		const currencySpan = el.querySelector('.woocommerce-Price-currencySymbol')
		if (currencySpan) {
			// Replace rest of content inside <bdi> or el
			const currencyHtml = currencySpan.outerHTML
			el.innerHTML = `<bdi>${currencyHtml}${formatted}</bdi>`
		} else {
			// fallback: inject bdi with £ by default (or use DomainWidget.currency if available)
			const symbol = (window.DomainWidget && DomainWidget.currency) ? DomainWidget.currency : '£'
			el.innerHTML = `<bdi><span class="woocommerce-Price-currencySymbol">${symbol}</span>${formatted}</bdi>`
		}
	})

	// TODO Make sure a hidden input named domain_registration_price exists inside the product form

	let cartForm = document.querySelector('form.cart')

	// If not on a product page, try to find domain-fields form 
	if (!cartForm) cartForm = document.querySelector('.domain-search-form') || document.querySelector('form')

	if (cartForm) {
		let hidden = cartForm.querySelector('input[name="domain_registration_price"]')
		if (!hidden) {
			hidden = document.createElement('input')
			hidden.type = 'hidden'
			hidden.name = 'domain_registration_price'
			cartForm.appendChild(hidden)
		}
		hidden.value = formatted

		// Also set domain_name hidden input
		let nameHidden = cartForm.querySelector('input[name="domain_name"]')
		if (!nameHidden) {
			nameHidden = document.createElement('input')
			nameHidden.type = 'hidden'
			nameHidden.name = 'domain_name'
			cartForm.appendChild(nameHidden)
		}
		// and set the tld hidden input
		let tldHidden = cartForm.querySelector('input[name="domain_tld"]')
		if (!tldHidden) {
			tldHidden = document.createElement('input')
			tldHidden.type = 'hidden'
			tldHidden.name = 'domain_tld'
			cartForm.appendChild(tldHidden)
		}
		// If you have the domain name on the page, set it; otherwise set blank
		const visibleDomainInput = document.querySelector('input[name="domain"]') || document.getElementById('domain_name')
		nameHidden.value = visibleDomainInput ? visibleDomainInput.value.trim() : ''
	}
}
// add price to cart form
function ensureHiddenPriceInput(productForm, priceValue) {
	let input = productForm.querySelector('input[name="domain_registration_price"]')
	if (!input) {
		input = document.createElement('input')
		input.type = 'hidden'
		input.name = 'domain_registration_price'
		productForm.appendChild(input)
	}
	input.value = String(priceValue)
	// add flag so server knows this cart item is a domain registration
	let flag = productForm.querySelector('input[name="is_domain_registration"]')
	if (!flag) {
		flag = document.createElement('input')
		flag.type = 'hidden'
		flag.name = 'is_domain_registration'
		productForm.appendChild(flag)
	}
	flag.value = '1'
}

async function addDomainToCart(domainName, domainTld, price) {
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
		if (data.success) {
			console.log('Domain added to cart')
			// Optionally update mini-cart or redirect
		} else {
			console.error('Error adding domain to cart', data)
		}
	} catch (err) {
		console.error('Network error', err)
	}
}

/* eslint-enable no-console */
