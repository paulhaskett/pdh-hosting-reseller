/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n'

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor'
import { TextControl } from '@wordpress/components'
import { useState, useEffect } from '@wordpress/element'
/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss'

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const [domain, setDomain] = useState('')
	const [result, setResult] = useState(null)



	return (
		<div {...useBlockProps()}>
			<TextControl
				label={__('Placeholder text', 'domain-search')}
				value={attributes.placeholder}
				onChange={(value) => setAttributes({ placeholder: value })}
				placeholder={__('Enter placeholder text…', 'domain-search')}
			/>
			<p>{__('This block will display a domain search form on the site front-end.', 'domain-search')}</p>

		</div>
	)
}
