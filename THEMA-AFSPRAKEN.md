# Thema-afspraken: wie doet wat

De HashtagMedia Toolkit en het maatwerk thema werken samen. Om dubbel werk (en twee velden voor hetzelfde) te voorkomen, geldt één regel:

> **Werkt het op elke site hetzelfde? Dan in de toolkit. Is het per klant anders? Dan in het thema.**

De skill *wp-thema-uit-ontwerp* volgt deze afspraken. Verandert hier iets, werk de skill dan ook bij.

## Taakverdeling

| Onderwerp | Toolkit (plugin) | Thema |
| --- | --- | --- |
| Admin, dashboard, login, kleurenschema | ✔ (Huisstijl en dashboard, Opruimen, Beheermenu) | – |
| Rol Klant en rechten | ✔ (Klantrol) | – |
| XML-RPC, bestandseditors, WP-versie | ✔ (Basisbeveiliging) | – |
| Blokeditor, blokwidgets, blok-CSS uit | ✔ (Content) | – |
| Dupliceren, SVG-upload, revisies, beschermde pagina's | ✔ (Content) | – |
| WebP, maximaal 2560 px, formaten 480/768/1200/1600 | ✔ (Afbeeldingen) | Geen eigen WebP-filter of extra formaten (hooguit een klein logo-formaat) |
| **Focuspunt** per afbeelding | ✔ (Afbeeldingen, in de mediabibliotheek) | **Geen eigen focusveld**; gebruik `hm_image()` of `hm_image_focus()` |
| `<img>` met srcset, sizes, lazy loading | ✔ `hm_image()` | Kiest de juiste `sizes` en zet `priority` op de hero |
| Optiepagina met bedrijfsgegevens, logo, knoppen, socials, openingstijden | – | ✔ (één plek voor de klant) |
| Bedrijfsnaam in het dashboard | Toont hem | Geeft hem door via `hm_toolkit_company_name` |
| Opmaak, secties, ACF-velden voor inhoud | – | ✔ |
| Emoji-scripts uit, fonts, eigen JS en CSS | – | ✔ |
| Gravity Forms | Honeypot altijd aan, verzendknop als `<button>` met knopklasse, ACF-keuzeveld `formulier`, `hm_form()`, testformulier (module Formulieren) | Kleuren via `gform_default_styles`; knopklasse via `hm_toolkit_form_button_class` als die niet `knop` heet |
| Frontend-editor | Fase 3: `hm_field()` | Tot fase 3: gewoon `get_field()` |

## Toolkit-functies voor het thema

Deze functies bestaan altijd als de toolkit actief is. Staat een module uit, dan doen ze het eenvoudigste dat werkt.

```php
hm_image( $id_of_acf_afbeelding, array(
	'size'     => 'large',          // of 'full' (max 2560)
	'sizes'    => '(min-width: 1024px) 50vw, 100vw',
	'fit'      => 'cover',          // object-fit + focuspunt als object-position
	'priority' => false,            // true op de hero: eager + fetchpriority="high"
	'class'    => 'kaart__img',
) );
$html = hm_get_image( $id, $args ); // geeft de HTML terug
$pos  = hm_image_focus( $id );      // '30% 65%' voor background-position
```

Formulieren (ACF-veld van het type *Select* met de naam `formulier`; de toolkit vult de keuzes):

```php
hm_form( get_field( 'formulier' ) ); // zonder titel en beschrijving, met AJAX
```

Kleuren van Gravity Forms (in het thema, met de waarden van de design tokens; GF rekent de varianten zelf uit):

```php
add_filter( 'gform_default_styles', function () {
	return array(
		'theme'                        => 'orbital',
		'inputPrimaryColor'            => '#1a5f4a', // focus, vinkjes, keuzes
		'inputBorderColor'             => '#c9c9c9',
		'inputColor'                   => '#1d1d1b', // tekst in velden
		'inputBackgroundColor'         => '#ffffff',
		'inputBorderRadius'            => 8,         // px
		'labelColor'                   => '#1d1d1b',
		'buttonPrimaryBackgroundColor' => '#1a5f4a',
		'buttonPrimaryColor'           => '#ffffff',
	);
} );
// Heet de knopklasse van het thema niet 'knop'?
add_filter( 'hm_toolkit_form_button_class', fn() => 'btn btn--primair' );
```

Bedrijfsnaam doorgeven aan het dashboard (in het thema):

```php
add_filter( 'hm_toolkit_company_name', function ( $naam ) {
	$eigen = function_exists( 'get_field' ) ? get_field( 'bedrijfsnaam', 'option' ) : '';
	return $eigen ? $eigen : $naam;
} );
```

## Het thema werkt ook zonder toolkit

Gebruik één eigen helper in het thema die doorgeeft aan de toolkit, met een terugval:

```php
function xx_img( $image, array $args = array() ) {
	if ( function_exists( 'hm_image' ) ) {
		hm_image( $image, $args );
		return;
	}
	$id = is_array( $image ) ? ( $image['ID'] ?? 0 ) : (int) $image;
	echo wp_get_attachment_image( $id, $args['size'] ?? 'large', false, array( 'loading' => 'lazy' ) );
}
```
