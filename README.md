# HashtagMedia Toolkit

Eén plugin voor alle klantsites van HashtagMedia met een custom thema: een kleine kern plus losse modules. Het volledige plan staat in `PLAN.md`.

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
| `hm_toolkit_company_name` | filter | Bedrijfsnaam van de klant (standaard de sitenaam). Bedrijfsgegevens vult deze later. |
| `hm_toolkit_dashboard_actions` | filter | Snelle acties op het dashboard: `label`, `url`, `icon` (dashicon). |
| `hm_toolkit_environment_type` | filter | Omgevingstype (local, development, staging, production). |

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

## Module Opruimen: pluginprofielen

Per plugin staat er één bestand in `modules/opruimen/profiles/`. Brengt een plugin-update reclame terug? Pas dan alleen dat profiel aan. Een nieuw profiel is een nieuw bestand; het verschijnt vanzelf als schakelaar.
