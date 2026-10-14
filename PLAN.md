# HashtagMedia Toolkit: plan

Opgesteld: 11 oktober 2026

## Doel en uitgangspunten

Eén all-in-one HashtagMedia-plugin die ASE vervangt, de admin opruimt en klanten een betere beleving geeft, alleen voor nieuwe klanten met een custom thema.

- **Licht:** elke module is een apart bestand en laadt alleen als hij aanstaat. Admin-code alleen in de admin, geen frontend-CSS of -JS tenzij een module het echt nodig heeft. Instellingen in één optie, geen eigen databasetabellen.
- **Veilig:** elke module gebruikt nonces, rechtencontroles, gesaneerde invoer en ge-escapete uitvoer.
- **Simpel:** alleen bouwen wat we echt gebruiken. Wat een bestaande plugin goed doet, laten we daar.
- **Gebouwd en onderhouden met Claude Code**, in een git-repo.

## Opbouw: functies toevoegen en verwijderen

De plugin bestaat uit een kleine kern plus losse modules, zodat functies zonder risico toegevoegd en verwijderd kunnen worden.

- **Kern:** laadt modules, beheert de instellingen en levert gedeelde helpers. Verder doet de kern niets.
- **Module = één map** met een vast beschrijvingsbestand: naam, uitleg, standaard aan of uit. De kern vindt nieuwe mappen automatisch; de module verschijnt dan als schakelaar in de instellingen.
- **Toevoegen:** nieuwe map maken met Claude Code volgens hetzelfde sjabloon.
- **Verwijderen:** map weghalen. Elke module heeft een eigen opruimroutine die zijn instellingen en eventuele rollen of rechten netjes verwijdert.
- **Onafhankelijk:** modules gebruiken elkaar niet direct, alleen de kern. Eén module weghalen of uitzetten breekt nooit een andere.
- **Thema's vallen veilig terug:** als een module uit staat of weg is, toont het thema gewoon de content.

## Huidige stack

De plugin vervangt alleen ASE; Elementor Pro verdwijnt door de overstap naar custom thema's. De rest blijft, en de plugin doet niets dubbel.

| Plugin | Besluit | Wat de HashtagMedia-plugin daarbij doet |
| --- | --- | --- |
| ASE | Vervalt | Vervangen door de plugin |
| Elementor Pro | Vervalt | Vervangen door custom thema's uit Claude Design + Claude Code |
| ACF Pro | Blijft | Basis voor content en de frontend-editor |
| Gravity Forms | Blijft | Basisstyling, koppeling met thema, reclame verbergen |
| Yoast | Blijft | SEO, schema en Open Graph; plugin maakt Yoast rustiger voor klanten |
| WP Rocket | Blijft | Caching en prestaties; plugin doet hier niets dubbel |
| Wordfence (free) | Blijft | Loginbeveiliging, 2FA, firewall |
| Complianz | Blijft | Cookiebanner |
| Duplicator | Blijft | Backups en verhuizen |
| ManageWP | Blijft | Centraal overzicht, updates, rapportages |

## Modules

Elf modules, waarvan acht groen; alleen de Gravity Forms-styling, de focuspunt-kiezer en de frontend-editor vragen extra bouw- en testwerk.

| Module | Inhoud | Haalbaarheid | Fase |
| --- | --- | --- | --- |
| Opruimen | Meldingen verzamelen achter één knop in de adminbalk (klant ziet ze niet). Per plugin een profiel dat reclame, upsells, menu-items, widgets en footerteksten weghaalt: Yoast, Wordfence, Complianz, Gravity Forms, Duplicator. Dashboardwidgets en adminbalk opgeschoond, reacties volledig uit. | Groen; profielen vragen licht onderhoud na plugin-updates | 1 |
| Huisstijl en dashboard | Gebrande login en admin, eigen footertekst, welkomstwidget met contact en supportknop (melding per e-mail naar HashtagMedia), omgevingslabel staging/live in de adminbalk, handleiding voor de klant. | Groen | 1 |
| Beheermenu | Menu-items slepen en verbergen; nieuwe items van plugins verschijnen automatisch onderaan. | Groen | 1 |
| Content | Dupliceren als concept, inclusief ACF-velden, Yoast-gegevens, uitgelichte afbeelding en categorieën. SVG-upload met sanitizer, alleen voor beheerders. Gutenberg, blokwidgets en blok-CSS uit. Revisies beperken. Belangrijke pagina's beschermen tegen verwijderen. | Groen | 1 |
| Basisbeveiliging | XML-RPC uit, thema- en plugin-editor uit, WP-versie verbergen. De rest doet Wordfence. | Groen | 1 |
| Klantrol | Eén rol Klant: content, menu's, ACF-optiepagina's, Yoast per pagina en alle formulieren en inzendingen. Kan collega's toevoegen met de Klant-rol; HashtagMedia-beheerders zijn voor de klant onzichtbaar en niet te bewerken. Geen plugins, thema's, instellingen of ongefilterde HTML. HashtagMedia blijft de enige beheerder. | Groen | 1 |
| Instellingen export/import | Standaardconfiguratie als JSON, in één klik op een nieuwe site. Updates via de eigen GitHub-repo. | Groen | 1 |
| Snelkoppelingen | Tabblad en dashboardblok voor beheerders met knoppen naar de juiste plek in andere plugins: Complianz-integraties (GTM), Yoast-webmastertools, Gravity Forms, WP Rocket, Wordfence, Duplicator, FluentSMTP en de ACF-optiepagina. Een knop verschijnt alleen als de plugin actief is; alle links staan in één bestand. | Groen | 1 |
| Afbeeldingen | JPEG en PNG worden bij uploaden WebP, te grote foto's verkleind tot maximaal 2560 px, kwaliteit instelbaar (standaard 82%). Proportionele formaten zonder bijsnijden; het thema laat afbeeldingen passend vullen rond een focuspunt dat per afbeelding gekozen wordt in de mediabibliotheek en de frontend-editor. Helper `hm_image()` zet formaten, focuspunt, lazy loading en afmetingen. Controleert of de server WebP ondersteunt; origineel blijft bewaard. | Groen; focuspunt-kiezer in de mediabibliotheek oranje | 1 |
| Formulieren (Gravity Forms) | Opmaak door GF (Orbital), kleuren uit het thema via `gform_default_styles`. Verzendknop als button, honeypot altijd aan, ACF-veld "formulier", `hm_form()`, testformulier met alle veldtypes. Add-ons aanvullen wanneer ze voor het eerst gebruikt worden. | Groen; gebouwd in 0.11.0 | 2 |
| Frontend-editor | Teksten en afbeeldingen in ACF-velden op de pagina bewerken; layout en secties blijven vast. Inline voor tekst en afbeeldingen, en voor knoppen een klein venster om knoptekst, link (kiezen uit pagina's van de site) en nieuw tabblad aan te passen; de opmaak blijft vast. Zijpaneel via ACF-formulieren voor repeaters en flexibele content. Logboek met oude en nieuwe waarde, cache van de pagina legen via WP Rocket. Laadt alleen voor ingelogde gebruikers met rechten. | Oranje; grootste module | 3 |

## Vormgeving

De plugin volgt de standaardstijl van WordPress; alleen wat de klant als eerste ziet en de frontend-editor krijgen een eigen vormgeving.

- **Instellingen:** één menu-item "HashtagMedia" met tabbladen per onderdeel; per module een schakelaar met één regel uitleg. Alleen WordPress' eigen CSS, geen zware frameworks.
- **Admin-kleurenschema:** een eigen HashtagMedia-schema via de officiële WordPress-functie, zodat de admin eigen voelt maar WordPress blijft.
- **Loginpagina en welkomstwidget:** HashtagMedia-branding.
- **Frontend-editor:** zo intuïtief mogelijk en modern vormgegeven, met een eigen ontwerp. Volledig afgeschermd van de CSS van het thema, zodat geen enkel thema de editor kan verstoren.

## Dashboard

Het dashboard toont de klant in een vaste volgorde vijf blokken; alle standaard- en pluginwidgets verdwijnen.

1. **Welkom en hulp:** welkomsttekst met de bedrijfsnaam, contactblok van HashtagMedia en de supportknop (melding per e-mail).
2. **Snelle acties:** grote knoppen voor Website bewerken (frontend-editor), Nieuw bericht, Media uploaden en Formulierinzendingen.
3. **Laatste formulierinzendingen:** de vijf meest recente, met het aantal ongelezen.
4. **Recent bewerkt:** de laatst aangepaste pagina's en door wie.
5. **Handleiding:** link naar de uitlegpagina of korte video's.

Beheerders zien daarnaast de snelkoppelingen en technische info (WordPress- en PHP-versie). Bewust weggelaten: bezoekersstatistieken en versleepbare blokken.

## Bewust niet bouwen

Deze ideeën vallen af omdat een bestaande plugin het al doet, of omdat het risico groter is dan de winst.

| Idee | Reden |
| --- | --- |
| Rol-editor en medewerkerprofielen | Te veel risico en onderhoud; één Klant-rol is genoeg |
| Login-URL wijzigen | Fragiel met caching en wachtwoordherstel, weinig beveiligingswinst |
| Loginpogingen beperken, 2FA | Wordfence |
| Schema, Open Graph, meta-omschrijvingen | Yoast |
| Cookiebanner | Complianz |
| Centraal overzicht en rapportages | ManageWP |
| Backups, caching | Duplicator, WP Rocket |
| Media vervangen, gebroken links scannen | Foutgevoelig of zwaar voor de server |
| Icon libraries en Google Fonts-optie | Horen in het thema: alleen gebruikte iconen als SVG, lettertypen lokaal |
| Kiesveld voor iconen | Past niet bij een vaste layout; later als losse module als klanten erom vragen |
| Code invoegen in head en body | GTM laden via Complianz, overige pixels via GTM, verificatiecodes in Yoast |
| Bedrijfsgegevens-optiepagina | Hoort in de optiepagina van het thema, naast de thema-specifieke velden; zo heeft de klant één plek |

## Afspraken met de thema's

Elk thema dat Claude Code bouwt, volgt vaste afspraken zodat het naadloos met de plugin samenwerkt. Deze komen in de skill *wp-thema-uit-ontwerp*.

- **Bewerkbare velden:** elk ACF-veld via `hm_field()`, zodat de frontend-editor werkt.
- **Formulieren:** altijd via `hm_form()` en het ACF-veld `formulier`; kleuren via `gform_default_styles`, geen eigen formulier-CSS.
- **Bedrijfsgegevens:** in de optiepagina van het thema (naast logo, knoppen e.d.), nooit hard in de code. De bedrijfsnaam geeft het thema door aan het dashboard via het filter `hm_toolkit_company_name`.
- **Iconen:** alleen de gebruikte iconen als SVG-bestanden in het thema, ingeladen via `hm_icon()` uit de plugin. Geen complete icon libraries.
- **Afbeeldingen:** altijd via `hm_image()`, zodat WebP, focuspunt en lazy loading vanzelf goed gaan.
- **Lettertypen:** lokaal in het thema (woff2), nooit van de servers van Google; sneller en geen AVG-risico.
- **ACF JSON:** veldgroepen in een vaste map in het thema, zodat ze in git staan.
- **Veilig terugvallen:** het thema controleert of de plugin actief is; staat die uit, dan toont de site gewoon de content.
- **Functionaliteit in de plugin, weergave in het thema.**

## Bouwproces en kwaliteit

Het grootste risico is dat één plugin op alle klantsites draait; een bug zit dan overal. Daarom:

- **Git-repo plus Claude Code** voor bouwen en onderhoud.
- **Automatische controle** met PHPCS volgens de WordPress Coding Standards.
- **Testomgeving** met de vaste stack: ACF Pro, Gravity Forms, Yoast, WP Rocket, Wordfence, Complianz.
- **Menselijke review** van beveiligingsgevoelige onderdelen: klantrol, SVG-upload, frontend-editor.
- **Gefaseerd uitrollen:** eerst de eigen site, dan een paar klanten, dan de rest.
- **Na een plugin-update** die reclame terugbrengt: screenshot sturen, alleen dat profielbestand aanpassen.

## Fasering

Drie fasen; na fase 1 is ASE volledig vervangen.

1. **Fase 1, de basis:** opruimen, huisstijl en dashboard, beheermenu, content, basisbeveiliging, klantrol, afbeeldingen, snelkoppelingen, instellingen export/import en de GitHub-updater. Tegelijk de thema-afspraken vastleggen in de skill.
2. **Fase 2, Gravity Forms:** basisstyling, koppeling met het thema en het testformulier.
3. **Fase 3, frontend-editor:** eerst inline bewerken van teksten, afbeeldingen en knoppen, daarna het zijpaneel voor complexe velden en het logboek. Tot die tijd bewerken klanten ACF-velden in de admin.

## Besluiten

- Collega's toevoegen: ja, alleen met de Klant-rol.
- Frontend-editor: alleen teksten en afbeeldingen; secties toevoegen of verplaatsen niet.
- Supportknop: melding per e-mail naar HashtagMedia.
- Omgevingslabel staging/live: ja.
- Naam en prefix: HashtagMedia Toolkit, `hm_`.
- Bedrijfsgegevens: in het thema, niet in de plugin (besloten 12 oktober 2026).
- Code invoegen in head en body: niet bouwen. GTM laden via Complianz, overige pixels via GTM, verificatiecodes in Yoast.
- Gravity Forms: geen eigen formulier-CSS in de toolkit; het thema geeft kleuren door met `gform_default_styles`, zodat GF de varianten (focus, hover, contrast) zelf goed uitrekent. Honeypot altijd aan, knopklasse standaard `knop` met filter, testformulier via een knop (besloten 13 oktober 2026).
- Frontend-editor (besloten 14 oktober 2026): bewerkmodus starten met een knop in de adminbalk; buiten de bewerkmodus ziet de klant de site zoals een bezoeker. Wijzigingen gaan in één keer live met de knop Publiceren; Annuleren gooit alles weg. Tekstvelden met opmaak krijgen een kleine werkbalk (vet, cursief, links, lijstjes; geen koppen of kleuren). Velden van de optiepagina van het thema zijn ook bewerkbaar, met de melding dat de wijziging op elke pagina zichtbaar wordt. Logboek per pagina met oud en nieuw en een knop om terug te zetten, de laatste 50 als post-meta. Alleen desktop en tablet; op de telefoon geen knop. Eerst een ontwerp van bewerkbalk, knopvenster en zijpaneel ter goedkeuring, dan bouwen. Testen met een klein testthema in de cloud (niet in de repo).
