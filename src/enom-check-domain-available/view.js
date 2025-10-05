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
if (typeof DomainWidget === 'undefined') {
	console.error('DomainWidget object not found')

}
console.log(DomainWidget.token)
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
				const result = data
				console.log('result', result)
				if (result.RRPText === 'Domain not available') {
					available = false
				} else {
					available = true
				}
				console.log(result.RRPText)
				console.log(available)

				if (available) {
					resultDiv.textContent = `✅ Domain ${result.DomainName} is available! `
					//check if the product domain name field exists if not ask if user wants to register domain and forward them to the domain single product page



					if (domain_name) {
						// add the domain name to product field

						domain_name.value = result.DomainName

					} else {
						const button = document.createElement('a')
						button.textContent = 'Configure Domain Registration'
						button.href = '/product/domain-registration/?domain_name=' + encodeURIComponent(result.DomainName)
						button.className = 'wp-element-button configure-domain-btn'
						const buttonContainer = document.createElement('div')
						buttonContainer.appendChild(button)
						resultDiv.appendChild(buttonContainer)
					}



				} else {
					resultDiv.textContent = `❌ Domain ${result.DomainName} is taken.`
					if (domain_name) {
						domain_name.value = ''
					}
				}


			} catch (err) {
				resultDiv.textContent = 'Error checking domain.'
			}
		})
	})
})




/* eslint-enable no-console */
