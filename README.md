# HashtagMedia Toolkit

Eén plugin voor alle klantsites van HashtagMedia met een custom thema: een kleine kern plus losse modules. Het volledige plan staat in `PLAN.md`; wat in de plugin en wat in het thema hoort, staat in `THEMA-AFSPRAKEN.md`.

## Opbouw

```
hashtagmedia-toolkit.php   Pluginkop, constanten, start de kern
uninstall.php              Plugin verwijderen: opruimroutine van elke module + optie wissen
includes/                  De kern
  class-plugin.php           Opstarten, upgrade, vertalingen
  class-settings.php         De ene optie hm_toolkit
  class-registry.php         Modules vinden, laden, synchroniseren, opruimen
  class-module.php           Basisklasse voor modules
  class-admin-page.php       Menu "HashtagMedia" met tabbladen (alleen admin)
  functions.php              Gedeelde helpers, zoals hm_toolkit_is_agency_user()
modules/{slug}/            Eén map per module
```

De kern laadt bij elk verzoek alleen de modules die aanstaan. De modulemap wordt alleen doorzocht op het instellingenscherm, bij activeren en na een update.

## Een module toevoegen

1. Maak `modules/{slug}/` met alleen kleine letters, cijfers en streepjes in de naam.
2. `module.php`: de beschrijving.

   ```php
   <?php
   defined( 'ABSPATH' ) || exit;

   return array(
   	'name'        => __( 'Naam', 'hashtagmedia-toolkit' ),
   	'description' => __( 'Eén regel uitleg.', 'hashtagmedia-toolkit' ),
   	'default'     => true, // Standaard aan of uit.
   );
   ```

3. `class-module.php`: de klasse heet altijd `Module`. De namespace volgt uit de slug: `export-import` wordt `HashtagMedia\Toolkit\Modules\ExportImport`.

   ```php
   <?php
   namespace HashtagMedia\Toolkit\Modules\Voorbeeld;

   use HashtagMedia\Toolkit\Module as Base;

   defined( 'ABSPATH' ) || exit;

   final class Module extends Base {
   	public function defaults(): array { return array( 'iets' => true ); }
   	public function boot(): void {}   // Frontend en admin: alleen hooks registreren.
   	public function admin(): void {}  // Alleen admin; laad hier admin/-bestanden.
   	public function has_tab(): bool { return false; }
   	public function render_tab(): void {}
   	public function sanitize( array $input ): array { return array(); }
   }
   ```

4. `uninstall.php`: ruim alles op wat buiten de eigen instellingen valt, zoals rollen, rechten, post-meta en user-meta. De eigen instellingen ruimt de kern op.

## Afspraken

- **Alleen de kern gebruiken, nooit een andere module.** Samenwerken gaat via WordPress-hooks met het prefix `hm_toolkit_`.
- **Namen:** code in de namespace `HashtagMedia\Toolkit`. Globale functies, hooks, opties en handles krijgen het prefix `hm_toolkit_` of `hm-toolkit-`. Alleen de thema-helpers (`hm_image()` e.d.) gebruiken `hm_`.
- **Dashboardwidgets van HashtagMedia** krijgen een id die begint met `hm_toolkit`; Opruimen laat die staan.
- **Geen vertaalde teksten in `boot()` opbouwen.** Die draait vóór `init`; gebruik vertalingen pas in callbacks.
- **Rechten die alleen veilig zijn met de modulecode erbij** worden nooit in de database gezet, maar tijdens het draaien gegeven (`user_has_cap`).
- **Uitzetten** laat de instellingen staan. **Gegevens opruimen** (tabblad Modules) draait `uninstall.php` van de module. **Een module weghalen:** eerst opruimen, dan pas de map verwijderen.
- **HashtagMedia-beheerder** = `manage_options`; zie `hm_toolkit_is_agency_user()`.

## Hooks van de kern en modules

| Hook | Soort | Waarvoor |
| --- | --- | --- |
| `hm_toolkit_modules_loaded` | action | Alle actieve modules zijn geladen. |
| `hm_toolkit_company_name` | filter | Bedrijfsnaam van de klant (standaard de sitenaam). Het thema vult deze vanuit zijn optiepagina. |
| `hm_toolkit_dashboard_actions` | filter | Snelle acties op het dashboard: `label`, `url`, `icon` (dashicon). |
| `hm_toolkit_environment_type` | filter | Omgevingstype (local, development, staging, production). |
| `hm_toolkit_image_attr` | filter | Attributen van `hm_image()` aanpassen. |
| `hm_toolkit_image_focus` | filter | Focuspunt als CSS-waarde (module Afbeeldingen vult dit). |

## Omgevingslabel

Het label in de adminbalk wordt automatisch bepaald aan de hand van het domein:

| Domein | Label |
| --- | --- |
| `klant.nl`, `www.klant.nl` | Live |
| `staging.klant.nl`, `klant.hostingbedrijf.nl` | Staging |
| `localhost`, IP-adres, `.local`, `.test` | Lokaal |

Staat `WP_ENVIRONMENT_TYPE` in `wp-config.php`, dan gaat die altijd voor. Dat is alleen nodig als een live site zelf op een subdomein draait, bijvoorbeeld `shop.klant.nl`:

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' );
```

Het label wordt bij elk verzoek opnieuw bepaald en staat niet in de database, dus na verhuizen met Duplicator klopt het meteen. Filter: `hm_toolkit_environment_type`.

## Versies en releases

- **Automatisch via GitHub** (`.github/workflows/release.yml`): bij elke push naar `main` draait eerst PHPCS. Is het versienummer in `hashtagmedia-toolkit.php` nieuw, dan maakt GitHub zelf de tag `vX.Y.Z` en een release met de zip. Faalt PHPCS, dan komt er geen release.
- Een nieuwe versie uitbrengen = versienummer verhogen (header én `HM_TOOLKIT_VERSION`), committen, pushen.
- Lokaal testen: `./bin/build-zip.sh` maakt `dist/hashtagmedia-toolkit-X.Y.Z.zip`; oude zips worden daarbij opgeruimd. `dist/` staat niet in git.
- Een oude versie nodig? Download de zip bij de release op GitHub.

## Kwaliteit

```
composer install
composer lint     # PHPCS met WordPress Coding Standards + PHPCompatibilityWP
composer fix      # Automatisch herstelbare fouten oplossen
```

## Module Beheermenu

Hoofdmenu-items slepen (of met de pijltjes verplaatsen) en hoofd- of submenu-items verbergen.

- Klanten zien verborgen items helemaal niet.
- HashtagMedia-beheerders vinden ze onder "Alles tonen" onderaan het menu. De keuze wordt per browser onthouden.
- Nieuwe menu-items van plugins komen vanzelf onderaan.

Verbergen is alleen weergave; wat een klant mag, regelt de module Klantrol met rechten.

## Module Content

- **Dupliceren als concept**: alle meta (ACF-velden, Yoast, uitgelichte afbeelding, paginasjabloon) en taxonomieën gaan mee. Action: `hm_toolkit_post_duplicated`.
- **SVG-upload**: alleen voor HashtagMedia-beheerders. Elke SVG gaat door `enshrined/svg-sanitize` (gebundeld in `modules/content/lib/svg-sanitizer`, zie `VERSION.txt`). Lukt saneren niet, dan wordt de upload geweigerd.
- **Gutenberg uit**: klassieke editor, klassieke widgets, geen blok-CSS en globale stijlen op de frontend.
- **Revisies**: standaard maximaal 5 per bericht.
- **Beschermde pagina's**: klanten kunnen ze niet verwijderen. Homepage, berichtenpagina en privacyverklaring zijn altijd beschermd; andere kies je op het tabblad.

## Module Basisbeveiliging

- **XML-RPC uit**: `xmlrpc.php` geeft 403, pingbacks en RSD-link weg.
- **Bestandseditors uit**: thema- en plugin-editor voor iedereen weg (zoals `DISALLOW_FILE_EDIT`).
- **WordPress-versie verborgen**: geen generator-tag, en `?ver=` met de WP-versie wordt een korte hash. `readme.html` kan alleen de server blokkeren.

Loginbeveiliging, 2FA en firewall: Wordfence.

## Module Klantrol (beveiligingsgevoelig)

Eén rol **Klant** (`hm_klant`).

- **In de rol opgeslagen** (veilig zonder deze module): content, media, categorieën, Gravity Forms-inzendingen en formulieren bewerken.
- **Alleen tijdens het draaien** (`user_has_cap`): menu's en widgets, en collega's beheren. Module uit of weg = deze rechten vervallen vanzelf.
- **Collega's**: alleen andere klanten zien, aanmaken, bewerken, verwijderen. `editable_roles` geeft alleen Klant; `map_meta_cap` blokkeert elke andere doelgebruiker; vangnet op `user_register`, `set_user_role` en `add_user_role`. Zichzelf verwijderen of een rol geven kan niet.
- **Nooit**: `unfiltered_html`, plugins, thema's, Customizer, instellingen, export/import, GF-instellingen en formulieren aanmaken/verwijderen.
- **Opruimen**: rol weg, klanten worden Abonnee.

Wijzig je de rechten in `STORED_CAPS`, verhoog dan `ROLE_VERSION`; de rol wordt dan op elke site bijgewerkt.

## Module Afbeeldingen

- **WebP**: JPEG en PNG worden bij uploaden WebP via WordPress' eigen filter `image_editor_output_format`; het origineel blijft bewaard (`original_image`). Kwaliteit instelbaar (standaard 82). Controleert of de server WebP kan maken.
- **Maximaal 2560 px** (`big_image_size_threshold`); 2560 telt ook mee in `srcset`.
- **Formaten**, proportioneel en nooit bijgesneden: 480 (`medium`), 768 (`medium_large`), 1200 (`large`), 1600 (`hm-1600`) en de volledige afbeelding. Het vierkante miniatuur (150) blijft voor de mediabibliotheek; 1536 en 2048 vervallen. Alleen voor nieuwe uploads.
- **Focuspunt** per afbeelding, te kiezen in de mediabibliotheek (klikken of pijltjestoetsen). Opgeslagen als post-meta `_hm_focus`.

Thema-helpers (altijd beschikbaar via de kern; zonder de module een gewone WordPress-afbeelding):

```php
hm_image( $image_id_of_acf_veld );                                   // toont <img> met srcset, sizes, breedte/hoogte, lazy loading
hm_image( $id, array( 'size' => 'full', 'priority' => true ) );     // hero: geen lazy loading, fetchpriority="high"
hm_image( $id, array( 'fit' => 'cover', 'sizes' => '(min-width: 1024px) 50vw, 100vw', 'class' => 'card__img' ) );
$html = hm_get_image( $id, $args );                                 // geeft de HTML terug
$pos  = hm_image_focus( $id );                                      // '30% 65%', voor background-position
```

`fit` zet `object-fit` en het focuspunt als `object-position`; het thema geeft de afbeelding zelf een vaste hoogte of aspect-ratio.

## Module Opruimen: pluginprofielen

Per plugin staat er één bestand in `modules/opruimen/profiles/`. Brengt een plugin-update reclame terug? Pas dan alleen dat profiel aan. Een nieuw profiel is een nieuw bestand; het verschijnt vanzelf als schakelaar.
