<?php
// This file is generated. Do not modify it manually.
return array(
	'enom-check-domain-available' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pdh-hosting-reseller/enom-check-domain-available',
		'version' => '0.1.0',
		'title' => 'Check domain availability',
		'category' => 'widgets',
		'icon' => 'search',
		'description' => 'displays a check domain available on the front end',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'pdh-hosting-reseller',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php',
		'viewScript' => 'file:./view.js',
		'attributes' => array(
			'placeholder' => array(
				'type' => 'string',
				'default' => 'Search a domain name'
			),
			'domain' => array(
				'type' => 'string',
				'default' => 'example.com'
			),
			'tld' => array(
				'type' => 'array',
				'default' => array(
					'.com',
					'.net',
					'.org'
				)
			)
		)
	),
	'pdh-hosting-reseller' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pdh-hosting-reseller/pdh-hosting-reseller',
		'version' => '0.1.0',
		'title' => 'Pdh Hosting Reseller',
		'category' => 'widgets',
		'icon' => 'smiley',
		'description' => 'Example block scaffolded with Create Block tool.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'pdh-hosting-reseller',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php',
		'viewScript' => 'file:./view.js'
	)
);
