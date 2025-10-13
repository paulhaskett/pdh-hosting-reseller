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

				if (available) {
					resultDiv.textContent = `✅ Domain ${result.Domain.Name} is available! £${prices.Registration} to register!`
					//TO DO not working check if the product domain name field exists if not ask if user wants to register domain and forward them to the domain single product page
					const priceEl = document.querySelector('.woocommerce-Price-amount')
					if (priceEl) priceEl.textContent = `£${prices.Registration}`


					if (domain_name) {
						// add the domain name to product field

						domain_name.value = result.Domain.Name


					} else {
						const button = document.createElement('a')
						button.textContent = 'Configure Domain Registration'
						button.href = '/product/register-domain/?domain_name=' + encodeURIComponent(result.Domain.Name) + '&price' + encodeURIComponent(prices.Registration)
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




/* eslint-enable no-console */
