<?php
if (! defined('ABSPATH')) {
	exit;
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
				<option value="com">.com</option>
				<option value="net">.net</option>
				<option value="org">.org</option>
				<option value="co.uk">.co.uk</option>
			</select>
			<button type="submit" class="wp-element-button">Check Availability</button>
			<div class="domain-search-result"></div>
		</form>


	</div>
</div>


<?php
