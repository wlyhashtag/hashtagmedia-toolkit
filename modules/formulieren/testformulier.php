<?php
/**
 * Testformulier met alle veldtypes die HashtagMedia gebruikt, voor GFAPI::add_form().
 *
 * @package HashtagMedia\Toolkit
 */

defined( 'ABSPATH' ) || exit;

$hm_toolkit_choices = array(
	array(
		'text'  => 'Optie een',
		'value' => 'een',
	),
	array(
		'text'  => 'Optie twee',
		'value' => 'twee',
	),
	array(
		'text'  => 'Optie drie',
		'value' => 'drie',
	),
);

return array(
	'title'          => 'HashtagMedia testformulier',
	'description'    => 'Alle veldtypes, om de opmaak van het thema te controleren. Niet op een openbare pagina zetten.',
	'labelPlacement' => 'top_label',
	'button'         => array(
		'type' => 'text',
		'text' => 'Versturen',
	),
	'fields'         => array(
		array(
			'id'         => 1,
			'type'       => 'name',
			'label'      => 'Naam',
			'isRequired' => true,
			'nameFormat' => 'advanced',
			'inputs'     => array(
				array(
					'id'       => '1.2',
					'label'    => 'Aanhef',
					'isHidden' => true,
					'choices'  => array(),
				),
				array(
					'id'    => '1.3',
					'label' => 'Voornaam',
				),
				array(
					'id'       => '1.4',
					'label'    => 'Tussenvoegsel',
					'isHidden' => true,
				),
				array(
					'id'    => '1.6',
					'label' => 'Achternaam',
				),
				array(
					'id'       => '1.8',
					'label'    => 'Achtervoegsel',
					'isHidden' => true,
				),
			),
		),
		array(
			'id'         => 2,
			'type'       => 'email',
			'label'      => 'E-mail',
			'isRequired' => true,
		),
		array(
			'id'    => 3,
			'type'  => 'phone',
			'label' => 'Telefoon',
		),
		array(
			'id'          => 4,
			'type'        => 'text',
			'label'       => 'Tekst',
			'description' => 'Een beschrijving onder het veld.',
			'placeholder' => 'Plaatshouder',
		),
		array(
			'id'    => 5,
			'type'  => 'textarea',
			'label' => 'Bericht',
		),
		array(
			'id'    => 6,
			'type'  => 'number',
			'label' => 'Getal',
		),
		array(
			'id'    => 7,
			'type'  => 'website',
			'label' => 'Website',
		),
		array(
			'id'    => 8,
			'type'  => 'section',
			'label' => 'Keuzes',
		),
		array(
			'id'      => 9,
			'type'    => 'select',
			'label'   => 'Keuzelijst',
			'choices' => $hm_toolkit_choices,
		),
		array(
			'id'      => 10,
			'type'    => 'multiselect',
			'label'   => 'Meerdere keuzes',
			'choices' => $hm_toolkit_choices,
		),
		array(
			'id'      => 11,
			'type'    => 'radio',
			'label'   => 'Keuzerondjes',
			'choices' => $hm_toolkit_choices,
		),
		array(
			'id'      => 12,
			'type'    => 'checkbox',
			'label'   => 'Vinkjes',
			'choices' => $hm_toolkit_choices,
			'inputs'  => array(
				array(
					'id'    => '12.1',
					'label' => 'Optie een',
				),
				array(
					'id'    => '12.2',
					'label' => 'Optie twee',
				),
				array(
					'id'    => '12.3',
					'label' => 'Optie drie',
				),
			),
		),
		array(
			'id'    => 13,
			'type'  => 'section',
			'label' => 'Overig',
		),
		array(
			'id'         => 14,
			'type'       => 'date',
			'label'      => 'Datum',
			'dateType'   => 'datepicker',
			'dateFormat' => 'dmy_dash',
		),
		array(
			'id'         => 15,
			'type'       => 'time',
			'label'      => 'Tijd',
			'timeFormat' => '24',
			'inputs'     => array(
				array(
					'id'    => '15.1',
					'label' => 'UU',
				),
				array(
					'id'    => '15.2',
					'label' => 'MM',
				),
				array(
					'id'    => '15.3',
					'label' => 'AM/PM',
				),
			),
		),
		array(
			'id'          => 16,
			'type'        => 'address',
			'label'       => 'Adres',
			'addressType' => 'international',
			'inputs'      => array(
				array(
					'id'    => '16.1',
					'label' => 'Straat en huisnummer',
				),
				array(
					'id'       => '16.2',
					'label'    => 'Adresregel 2',
					'isHidden' => true,
				),
				array(
					'id'    => '16.3',
					'label' => 'Plaats',
				),
				array(
					'id'       => '16.4',
					'label'    => 'Provincie',
					'isHidden' => true,
				),
				array(
					'id'    => '16.5',
					'label' => 'Postcode',
				),
				array(
					'id'    => '16.6',
					'label' => 'Land',
				),
			),
		),
		array(
			'id'    => 17,
			'type'  => 'fileupload',
			'label' => 'Bestand',
		),
		array(
			'id'    => 18,
			'type'  => 'list',
			'label' => 'Lijst',
		),
		array(
			'id'      => 19,
			'type'    => 'html',
			'label'   => 'HTML',
			'content' => '<p>Een vrij HTML-blok met <a href="#">een link</a>.</p>',
		),
		array(
			'id'    => 20,
			'type'  => 'hidden',
			'label' => 'Verborgen',
		),
		array(
			'id'            => 21,
			'type'          => 'consent',
			'label'         => 'Toestemming',
			'checkboxLabel' => 'Ik ga akkoord met de privacyverklaring.',
			'description'   => '',
			'isRequired'    => true,
			'inputs'        => array(
				array(
					'id'    => '21.1',
					'label' => 'Toestemming',
				),
				array(
					'id'       => '21.2',
					'label'    => 'Tekst',
					'isHidden' => true,
				),
				array(
					'id'       => '21.3',
					'label'    => 'Beschrijving',
					'isHidden' => true,
				),
			),
		),
	),
);
