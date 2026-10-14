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
   	'tab'         => __( 'Kort', 'hashtagmedia-toolkit' ), // Optioneel: kortere naam op het tabblad.
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

   Optioneel:
   - `tab_form()` → `false` als het tabblad eigen formulieren heeft (geen knop Opslaan van de kern).
   - `site_settings()` → sleutels die per site verschillen of intern zijn; die gaan nooit mee bij export en import.
   - `import_settings()` → strengere regels bij importeren. Standaard: alleen bekende sleutels, in het type van de standaardwaarde.

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
| `hm_toolkit_shortcuts` | filter | Groepen snelkoppelingen, in de vorm van `modules/snelkoppelingen/links.php`. |
| `hm_toolkit_form_button_class` | filter | Knopklasse van de verzendknop (standaard `knop`). |
| `hm_toolkit_field_attr` | filter | Attributen op een veld van `hm_field()` (module Frontend-editor vult dit). |
| `hm_toolkit_list_attr` | filter | Attributen op het element rond een lijst (herhaler, flexibele inhoud); `hm_list_attr()` roept dit aan met veld, post_id en argumenten, module Frontend-editor vult het. |
| `hm_toolkit_row_attr` | filter | Attributen op één rij van een lijst; `hm_row_attr()` roept dit aan. |
| `hm_toolkit_save_focus` | filter | Focuspunt opslaan vanuit de frontend-editor (module Afbeeldingen doet dit). |

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
- Een nieuwe versie uitbrengen = versienummer verhogen (header én `HM_TOOLKIT_VERSION`), committen, pushen. Sites zien de update daarna vanzelf (module Updates), uiterlijk binnen 12 uur.
- Lokaal testen: `./bin/build-zip.sh` maakt `dist/hashtagmedia-toolkit-X.Y.Z.zip`; oude zips worden daarbij opgeruimd. `dist/` staat niet in git.
- Een oude versie nodig? Download de zip bij de release op GitHub.

## Kwaliteit

```
composer install
composer lint     # PHPCS met WordPress Coding Standards + PHPCompatibilityWP
composer fix      # Automatisch herstelbare fouten oplossen
```

## Module Beheermenu

Hoofdmenu-items slepen (met het toetsenbord: greep focussen en pijltje omhoog/omlaag) en hoofd- of submenu-items verbergen.

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

## Module Snelkoppelingen

Knoppen naar de juiste plek in Complianz, Yoast, Gravity Forms, WP Rocket (ook "Cache legen"), Wordfence, Duplicator, FluentSMTP en de ACF-optiepagina's van het thema. Alleen HashtagMedia-beheerders zien ze: op het tabblad Snelkoppelingen en in een blok op het dashboard (uit te zetten).

- Een knop verschijnt alleen als de plugin actief is.
- **Alle adressen staan in `modules/snelkoppelingen/links.php`.** Wijzigt een plugin zijn adressen na een update, pas dan alleen dat bestand aan.
- De optiepagina's van het thema worden vanzelf gevonden via ACF.

## Module Export en import

- **HashtagMedia-standaard**: `modules/export-import/standaard.json`, in één klik toe te passen op een nieuwe site. Bijwerken: zet een site goed, exporteer, en vervang dit bestand met de export (in een nieuwe versie van de toolkit).
- **Exporteren**: welke modules aanstaan en hun instellingen, als JSON.
- **Importeren**: zo'n bestand uploaden (maximaal 500 KB). Elke module saneert zijn eigen waarden; onbekende modules en sleutels worden overgeslagen.
- **Gaat nooit mee**: wat per site verschilt (contactgegevens, bereikbaarheid, handleiding-link, beschermde pagina's) en interne waarden (rolversie). Die blijven op de site zoals ze zijn.
- Alleen de toolkit; Yoast, WP Rocket, Complianz en Gravity Forms hebben hun eigen export.

## Module Formulieren (Gravity Forms)

- **Honeypot altijd aan** bij elk formulier (`gform_form_post_get_meta`), ook als hij in de formulierinstellingen uitstaat. Een bot die het veld invult, komt bij Spam.
- **Verzendknop** wordt `<button type="submit">` met de knopklasse van het thema erbij. Standaard `knop`; een thema met een andere naam gebruikt het filter `hm_toolkit_form_button_class`. Een afbeeldingsknop blijft ongemoeid.
- **ACF-keuzeveld**: een veld van het type *Select* met de naam `formulier` krijgt vanzelf alle actieve formulieren als keuze (`acf/prepare_field`). Het thema toont het met `hm_form( get_field( 'formulier' ) )`.
- **Testformulier**: tabblad Formulieren, knop "Testformulier aanmaken" (alleen beheerders). Alle veldtypes; bestaat het al, dan opent het.
- **Geen eigen CSS.** Gravity Forms (Orbital) doet de opmaak; het thema geeft zijn kleuren door met `gform_default_styles` (zie `THEMA-AFSPRAKEN.md`). GF rekent zelf de varianten uit (donkerder, lichter, contrast, focusrand).

## Module Frontend-editor (beveiligingsgevoelig)

Teksten, afbeeldingen en knoppen op de pagina zelf bewerken. Werkt alleen met velden die het thema toont met `hm_field()` (zie `THEMA-AFSPRAKEN.md`). Ontwerp: overlay over de site, neutraal in de kleuren van WordPress.

- **Starten**: blauwe knop "Visueel bewerken" (penseel) in de adminbalk, zodat hij niet lijkt op "Pagina bewerken" van WordPress, alleen als er op de pagina iets is dat de gebruiker mag bewerken, en niet op de telefoon (smaller dan 783 px).
- **Bewerken**: tekst, tekstvak, e-mail en URL direct in de pagina; teksteditor met een kleine werkbalk (vet, cursief, link, lijstjes); afbeelding via het mediavenster (Vervangen, Focuspunt); link als knop via een venster met knoptekst, link (pagina zoeken of adres plakken) en nieuw tabblad. Geplakte tekst komt altijd zonder opmaak binnen.
- **Optievelden** (ACF-optiepagina): geel label "Elke pagina" en een waarschuwing dat de wijziging overal zichtbaar wordt.
- **Publiceren**: alle wijzigingen in één verzoek naar `POST /wp-json/hm-toolkit/v1/velden`. Per veld opnieuw gecontroleerd: bestaat het, staat het op het hoogste niveau van een veldgroep, mag de gebruiker het bericht bewerken (`edit_post`) of de optiepagina openen (het recht van die optiepagina), en is de waarde geldig. Eén fout = niets opgeslagen. Saneren per type (`sanitize_text_field`, `wp_kses_post`, `esc_url_raw`, alleen bestaande afbeeldingen). Daarna de cache van WP Rocket legen: de pagina, of de hele site bij een optieveld.
- **Annuleren** gooit alles weg (pagina herladen). Weggaan met openstaande wijzigingen vraagt eerst om bevestiging.
- **Focuspunt**: in de bewerkmodus slaat de kiezer niet meteen op; de foto schuift mee en het punt telt als wijziging voor Publiceren (Annuleren draait het terug). Opslaan doet de module Afbeeldingen via het filter `hm_toolkit_save_focus`; die leegt bij een ander punt de hele cache van WP Rocket. In de mediabibliotheek in de admin slaat de kiezer wel meteen op.
- **Lijsten in het zijpaneel** (herhaler en flexibele inhoud): een klik op de lijst (of op een rij) opent rechts een paneel van 400 px; de site blijft op volle breedte en schuift niet. Een rij is een kaart met titel, korte omschrijving en miniatuur; klik opent de velden (één rij tegelijk). De pagina scrolt de open rij in beeld. Randen zijn rustig: een lijst krijgt alleen bij hover een dunne stippellijn, en bij een open paneel staat er alleen een dunne blauwe rand om het blok dat je bewerkt.
  - **Standaard alleen inhoud**: de klant wijzigt de velden van bestaande rijen, maar voegt niets toe en verplaatst of verwijdert niets, zodat de opmaak heel blijft. Dat geldt altijd voor flexibele inhoud.
  - **Herhaler met `'rijen' => true`** (in `hm_list_attr()`, bijv. een FAQ of een lijst socials): ook onderdeel toevoegen, dupliceren, omhoog/omlaag, verslepen en verwijderen (met bevestiging). Het maximum en minimum van ACF worden gerespecteerd. Let op: dit is een keuze in de editor, geen beveiliging; in de admin kan de klant bij elke herhaler rijen toevoegen.
  - **Velden in een rij**: dezelfde zeven types als op de pagina (tekst, tekstvak, teksteditor, e-mail, URL, afbeelding met Vervangen en Wissen, link). Eén niveau: een lijst in een lijst, en andere veldtypes (keuzelijst, schakelaar, getal), blijven in de admin en worden bij het opslaan **ongemoeid gelaten**.
  - **Live of na Publiceren**: tekst, afbeelding en knop in een bestaande rij veranderen meteen op de pagina; dupliceren, verplaatsen en verwijderen ook. Een nieuwe rij verschijnt pas na Publiceren (het thema maakt de opmaak op de server).
  - **Opslaan**: de hele lijst is één wijziging in dezelfde REST-route. De editor stuurt per rij `from` (uit welke opgeslagen rij hij komt, leeg bij een nieuwe) en alleen de velden die de gebruiker aanraakte; de server haalt de rest uit de oude rij, zodat onbekende subvelden en onaangeraakte teksten niet verloren gaan. Flexibele inhoud: aantal, volgorde en soort van de rijen moeten gelijk blijven, anders wordt alles geweigerd. Nieuwe rijen: maximaal volgens ACF, nooit meer dan 200.
  - **Thema**: `hm_list_attr( 'veldnaam' )` op het element rond de lijst en `hm_row_attr()` op het element van elke rij (zie `THEMA-AFSPRAKEN.md`).
- **Nog niet** (volgend deel): het logboek met terugzetten.
- **Knop in de adminbalk**: staat er altijd (voor wie is ingelogd) en blijft verborgen tot de pagina bewerkbare velden blijkt te hebben. Nodig omdat een thema met `wp_body_open()` (vrijwel elk modern thema) de adminbalk tekent voordat de velden bekend zijn.
- De balk en vensters staan in een Shadow DOM (`assets/ui.css`), zodat het thema ze niet kan verstoren. Op de pagina zelf alleen randen om de velden (`assets/page.css`).
- **Kleuren**: uit de design tokens van WordPress 7 (`--wpds-*`, stylesheet `wp-theme`). Verandert WordPress zijn kleuren, dan volgt de editor vanzelf; op oudere WordPress-versies gelden de terugvalwaarden in de CSS.

## Module Updates

- De pluginkop heeft `Update URI: https://github.com/wlyhashtag/hashtagmedia-toolkit`. WordPress vraagt daardoor via `update_plugins_github.com` of er een update is, en een plugin met dezelfde naam op wordpress.org kan nooit over deze heen.
- De module kijkt naar de nieuwste release op GitHub (geen concept of testversie) en gebruikt alleen de zip `hashtagmedia-toolkit-X.Y.Z.zip` uit deze repo. Gegevens worden 12 uur bewaard (bij een fout 1 uur).
- De update verschijnt onder Plugins, Dashboard › Updates en in ManageWP. Automatische updates aan- of uitzetten gaat per site onder Plugins.
- Tabblad Updates: geïnstalleerde en nieuwste versie, en de knop "Nu controleren".
- **De repo moet openbaar blijven**; anders kunnen sites de releases niet zien.

## Module Opruimen: pluginprofielen

Per plugin staat er één bestand in `modules/opruimen/profiles/`. Brengt een plugin-update reclame terug? Pas dan alleen dat profiel aan. Een nieuw profiel is een nieuw bestand; het verschijnt vanzelf als schakelaar.
