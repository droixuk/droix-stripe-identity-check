<?php
/**
 * Hook loader class.
 *
 * Maintains a list of all hooks that are registered throughout
 * the plugin, and registers them with the WordPress API.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Loader
 *
 * @since 0.0.1
 */
class DSIC_Loader {

	/**
	 * Array of actions to register.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	protected array $actions = array();

	/**
	 * Array of filters to register.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	protected array $filters = array();

	/**
	 * Add a new action to the collection.
	 *
	 * @since 0.0.1
	 * @param string $hook          The name of the WordPress action.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the function to call.
	 * @param int    $priority      Optional. The priority of the action. Default 10.
	 * @param int    $accepted_args Optional. Number of arguments. Default 1.
	 * @return void
	 */
	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection.
	 *
	 * @since 0.0.1
	 * @param string $hook          The name of the WordPress filter.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the function to call.
	 * @param int    $priority      Optional. The priority of the filter. Default 10.
	 * @param int    $accepted_args Optional. Number of arguments. Default 1.
	 * @return void
	 */
	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a hook to the collection.
	 *
	 * @since 0.0.1
	 * @param array  $hooks         The collection of hooks.
	 * @param string $hook          The name of the WordPress hook.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the function to call.
	 * @param int    $priority      The priority of the hook.
	 * @param int    $accepted_args Number of arguments.
	 * @return array The updated collection of hooks.
	 */
	private function add( array $hooks, string $hook, object $component, string $callback, int $priority, int $accepted_args ): array {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
