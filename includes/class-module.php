<?php
/**
 * Basisklasse voor elke module.
 *
 * Een module is één map in modules/ met drie vaste bestanden:
 *
 * - module.php        geeft een array terug: name, description, default.
 * - class-module.php  class Module extends \HashtagMedia\Toolkit\Module in
 *                     namespace HashtagMedia\Toolkit\Modules\{Slug}.
 * - uninstall.php     opruimroutine voor alles buiten de eigen instellingen
 *                     (rollen, rechten, post- of user-meta).
 *
 * Regels: een module gebruikt alleen de kern, nooit een andere module.
 * In boot() alleen hooks registreren, geen vertaalde teksten opbouwen
 * (die pas in de callbacks), omdat boot() vóór init draait.
 *
 * @package HashtagMedia\Toolkit
 */

namespace HashtagMedia\Toolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Abstracte module.
 */
abstract class Module {

	/**
	 * Slug van de module (naam van de map).
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Map van de module, met slash aan het eind.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Constructor; alleen de kern maakt modules aan.
	 *
	 * @param string $slug Module-slug.
	 * @param string $dir  Map van de module.
	 */
	final public function __construct( string $slug, string $dir ) {
		$this->slug = $slug;
		$this->dir  = trailingslashit( $dir );
	}

	/**
	 * Standaardwaarden voor de instellingen van deze module.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array();
	}

	/**
	 * Draait bij elk verzoek als de module aanstaat (frontend en admin).
	 */
	public function boot(): void {}

	/**
	 * Draait alleen in de admin als de module aanstaat.
	 */
	public function admin(): void {}

	/**
	 * Heeft deze module een eigen tabblad op de instellingenpagina?
	 *
	 * @return bool
	 */
	public function has_tab(): bool {
		return false;
	}

	/**
	 * Inhoud van het tabblad (binnen het formulier van de kern).
	 */
	public function render_tab(): void {}

	/**
	 * Saneert de invoer van het tabblad. Verplicht als has_tab() true is.
	 *
	 * @param array $input Ongesaneerde invoer (al ge-unslasht).
	 * @return array Gesaneerde instellingen.
	 */
	public function sanitize( array $input ): array {
		unset( $input );
		return array();
	}

	/**
	 * Slug van de module.
	 *
	 * @return string
	 */
	final public function slug(): string {
		return $this->slug;
	}

	/**
	 * Alle instellingen, aangevuld met de standaardwaarden.
	 *
	 * @return array
	 */
	final public function settings(): array {
		return wp_parse_args( Settings::module( $this->slug ), $this->defaults() );
	}

	/**
	 * Eén instelling.
	 *
	 * @param string $key      Sleutel.
	 * @param mixed  $fallback Waarde als de sleutel ook geen standaardwaarde heeft.
	 * @return mixed
	 */
	final public function get( string $key, $fallback = null ) {
		$settings = $this->settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Instellingen bijwerken (samengevoegd met wat er al staat).
	 *
	 * @param array $values Gesaneerde waarden.
	 */
	final protected function update( array $values ): void {
		Settings::set_module( $this->slug, array_merge( Settings::module( $this->slug ), $values ) );
	}

	/**
	 * Pad binnen de modulemap.
	 *
	 * @param string $relative Relatief pad.
	 * @return string
	 */
	final public function path( string $relative = '' ): string {
		return $this->dir . ltrim( $relative, '/' );
	}

	/**
	 * URL binnen de modulemap.
	 *
	 * @param string $relative Relatief pad.
	 * @return string
	 */
	final public function url( string $relative = '' ): string {
		return plugins_url( 'modules/' . $this->slug . '/' . ltrim( $relative, '/' ), HM_TOOLKIT_FILE );
	}

	/**
	 * Handle-naam voor scripts en styles van deze module.
	 *
	 * @param string $name Korte naam.
	 * @return string
	 */
	final protected function handle( string $name ): string {
		return 'hm-toolkit-' . $this->slug . '-' . $name;
	}

	/**
	 * Stylesheet uit de modulemap inladen, met de pluginversie als versie.
	 *
	 * @param string   $name     Korte naam.
	 * @param string   $relative Pad binnen de modulemap.
	 * @param string[] $deps     Afhankelijkheden.
	 */
	final protected function enqueue_style( string $name, string $relative, array $deps = array() ): void {
		wp_enqueue_style( $this->handle( $name ), $this->url( $relative ), $deps, HM_TOOLKIT_VERSION );
	}

	/**
	 * Script uit de modulemap inladen, in de footer en met defer.
	 *
	 * @param string   $name     Korte naam.
	 * @param string   $relative Pad binnen de modulemap.
	 * @param string[] $deps     Afhankelijkheden.
	 */
	final protected function enqueue_script( string $name, string $relative, array $deps = array() ): void {
		wp_enqueue_script(
			$this->handle( $name ),
			$this->url( $relative ),
			$deps,
			HM_TOOLKIT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Naam van een formulierveld op het tabblad van deze module.
	 *
	 * @param string $key Sleutel.
	 * @return string
	 */
	final protected function field_name( string $key ): string {
		return 'hm_toolkit[' . $key . ']';
	}

	/**
	 * ID van een formulierveld.
	 *
	 * @param string $key Sleutel.
	 * @return string
	 */
	final protected function field_id( string $key ): string {
		return 'hm-' . $this->slug . '-' . str_replace( '_', '-', $key );
	}

	/**
	 * Een tabelrij met een schakelaar, in de stijl van WordPress.
	 *
	 * @param string $key         Sleutel van de instelling.
	 * @param string $label       Label links.
	 * @param string $text        Tekst naast de schakelaar.
	 * @param string $description Optionele uitleg eronder.
	 */
	final protected function checkbox_row( string $key, string $label, string $text, string $description = '' ): void {
		$id = $this->field_id( $key );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label class="hm-toggle" for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" role="switch" class="hm-toggle__input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="1" <?php checked( (bool) $this->get( $key ) ); ?>>
					<span class="hm-toggle__text"><?php echo esc_html( $text ); ?></span>
				</label>
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Een tabelrij met een tekstveld (text, email of url).
	 *
	 * @param string $key         Sleutel van de instelling.
	 * @param string $label       Label links.
	 * @param string $type        text, email, url of number.
	 * @param string $description Optionele uitleg eronder.
	 * @param string $placeholder Optionele placeholder.
	 */
	final protected function input_row( string $key, string $label, string $type = 'text', string $description = '', string $placeholder = '' ): void {
		$id    = $this->field_id( $key );
		$type  = in_array( $type, array( 'text', 'email', 'url', 'number' ), true ) ? $type : 'text';
		$class = 'number' === $type ? 'small-text' : 'regular-text';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>" class="<?php echo esc_attr( $class ); ?>"<?php echo 'number' === $type ? ' min="0" step="1"' : ''; ?> id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="<?php echo esc_attr( (string) $this->get( $key, '' ) ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo $description ? ' aria-describedby="' . esc_attr( $id . '-description' ) . '"' : ''; ?>>
				<?php if ( '' !== $description ) : ?>
					<p class="description" id="<?php echo esc_attr( $id . '-description' ); ?>"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Een tabelrij met een tekstvak.
	 *
	 * @param string $key         Sleutel van de instelling.
	 * @param string $label       Label links.
	 * @param string $description Optionele uitleg eronder.
	 */
	final protected function textarea_row( string $key, string $label, string $description = '' ): void {
		$id = $this->field_id( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea class="large-text" rows="3" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>"<?php echo $description ? ' aria-describedby="' . esc_attr( $id . '-description' ) . '"' : ''; ?>><?php echo esc_textarea( (string) $this->get( $key, '' ) ); ?></textarea>
				<?php if ( '' !== $description ) : ?>
					<p class="description" id="<?php echo esc_attr( $id . '-description' ); ?>"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saneert schakelaars: alles wat niet aangevinkt is, wordt false.
	 *
	 * @param array    $input Invoer.
	 * @param string[] $keys  Sleutels van de schakelaars.
	 * @return array<string,bool>
	 */
	final protected function sanitize_checkboxes( array $input, array $keys ): array {
		$clean = array();
		foreach ( $keys as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}
		return $clean;
	}
}
