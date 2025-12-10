<?php
if (! defined('ABSPATH')) {
	exit;
}

$tldList = [];
try {
	$enom = new PDH_Enom_API();

	// Register the domain
	$result = $enom->get_tld_list();
	error_log(print_r($result, true));
} catch (\Throwable $th) {
	//throw $th;
}

// Attributes passed from block editor
$placeholder = !empty($attributes['placeholder'])
	? esc_attr($attributes['placeholder'])
	: __('Enter domain…', 'domain-search');
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<div class="domain-search ">
		<form class="domain-search-form">
			<input type="text" name="domain" placeholder="<?php echo $placeholder; ?>">
			<select name="tld">
				<?php
				if (!empty($result['tldlist']['tld']) && is_array($result['tldlist']['tld'])) {
					foreach ($result['tldlist']['tld'] as $tldEntry) {
						if (!empty($tldEntry['tld'])) {
							$tld = esc_attr($tldEntry['tld']);
							echo "<option value=\"{$tld}\">.{$tld}</option>";
						}
					}
				}
				?>
			</select>
			<button type="submit" class="wp-element-button">Check Availability</button>
			<div class="domain-search-result"></div>
		</form>


	</div>
	<script>
		const domainInput = document.querySelector('input[name="domain"]');
		const tldSelect = document.querySelector('select[name="tld"]');
		if (!domainInput || !tldSelect) return;

		const tlds = JSON.parse(domainInput.dataset.tlds);

		// Sort TLDs by length descending to match multi-part first
		const sortedTlds = tlds.slice().sort((a, b) => b.length - a.length);

		domainInput.addEventListener('blur', () => {
			// Only run on blur to avoid messing with typing
			let value = domainInput.value.trim().toLowerCase();

			for (const tld of sortedTlds) {
				if (value.endsWith(`.${tld}`)) {
					tldSelect.value = tld;
					domainInput.value = value.slice(0, -tld.length - 1); // remove dot + tld
					break;
				}
			}
		});
	</script>
</div>


<?php
