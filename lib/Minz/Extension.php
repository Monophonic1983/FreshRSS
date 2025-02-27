<?php

/**
 * The extension base class.
 */
abstract class Minz_Extension {
	/** @var string */
	private $name;
	/** @var string */
	private $entrypoint;
	/** @var string */
	private $path;
	/** @var string */
	private $author;
	/** @var string */
	private $description;
	/** @var string */
	private $version;
	/** @var 'system'|'user' */
	private $type;
	/** @var string */
	private $config_key = 'extensions';
	/** @var array<string,mixed>|null */
	private $user_configuration;
	/** @var array<string,mixed>|null */
	private $system_configuration;

	/** @var array{0:'system',1:'user'} */
	public static $authorized_types = array(
		'system',
		'user',
	);

	/** @var bool */
	private $is_enabled;

	/**
	 * The constructor to assign specific information to the extension.
	 *
	 * Available fields are:
	 * - name: the name of the extension (required).
	 * - entrypoint: the extension class name (required).
	 * - path: the pathname to the extension files (required).
	 * - author: the name and / or email address of the extension author.
	 * - description: a short description to describe the extension role.
	 * - version: a version for the current extension.
	 * - type: "system" or "user" (default).
	 *
	 * @param array{'name':string,'entrypoint':string,'path':string,'author'?:string,'description'?:string,'version'?:string,'type'?:'system'|'user'} $meta_info
	 * contains information about the extension.
	 */
	final public function __construct(array $meta_info) {
		$this->name = $meta_info['name'];
		$this->entrypoint = $meta_info['entrypoint'];
		$this->path = $meta_info['path'];
		$this->author = isset($meta_info['author']) ? $meta_info['author'] : '';
		$this->description = isset($meta_info['description']) ? $meta_info['description'] : '';
		$this->version = isset($meta_info['version']) ? $meta_info['version'] : '0.1';
		$this->setType(isset($meta_info['type']) ? $meta_info['type'] : 'user');

		$this->is_enabled = false;
	}

	/**
	 * Used when installing an extension (e.g. update the database scheme).
	 *
	 * @return string|true true if the extension has been installed or a string explaining the problem.
	 */
	public function install() {
		return true;
	}

	/**
	 * Used when uninstalling an extension (e.g. revert the database scheme to
	 * cancel changes from install).
	 *
	 * @return string|true true if the extension has been uninstalled or a string explaining the problem.
	 */
	public function uninstall() {
		return true;
	}

	/**
	 * Call at the initialization of the extension (i.e. when the extension is
	 * enabled by the extension manager).
	 * @return void
	 */
	abstract public function init();

	/**
	 * Set the current extension to enable.
	 */
	public final function enable(): void {
		$this->is_enabled = true;
	}

	/**
	 * Return if the extension is currently enabled.
	 *
	 * @return bool true if extension is enabled, false otherwise.
	 */
	public final function isEnabled(): bool {
		return $this->is_enabled;
	}

	/**
	 * Return the content of the configure view for the current extension.
	 *
	 * @return string|false html content from ext_dir/configure.phtml, false if it does not exist.
	 */
	public final function getConfigureView() {
		$filename = $this->path . '/configure.phtml';
		if (!file_exists($filename)) {
			return false;
		}

		ob_start();
		include($filename);
		return ob_get_clean();
	}

	/**
	 * Handle the configure action.
	 * @return void
	 */
	public function handleConfigureAction() {}

	/**
	 * Getters and setters.
	 */
	public final function getName(): string {
		return $this->name;
	}
	public final function getEntrypoint(): string {
		return $this->entrypoint;
	}
	public final function getPath(): string {
		return $this->path;
	}
	public final function getAuthor(): string {
		return $this->author;
	}
	public final function getDescription(): string {
		return $this->description;
	}
	public final function getVersion(): string {
		return $this->version;
	}
	/** @return 'system'|'user' */
	public final function getType() {
		return $this->type;
	}

	/** @param 'user'|'system' $type */
	private function setType(string $type): void {
		if (!in_array($type, ['user', 'system'])) {
			throw new Minz_ExtensionException('invalid `type` info', $this->name);
		}
		$this->type = $type;
	}

	/**
	 * Return the url for a given file.
	 *
	 * @param string $filename name of the file to serve.
	 * @param 'css'|'js' $type the type (js or css) of the file to serve.
	 * @param bool $isStatic indicates if the file is a static file or a user file. Default is static.
	 * @return string url corresponding to the file.
	 */
	public final function getFileUrl(string $filename, string $type, bool $isStatic = true): string {
		if ($isStatic) {
			$dir = basename($this->path);
			$file_name_url = urlencode("{$dir}/static/{$filename}");
			$mtime = @filemtime("{$this->path}/static/{$filename}");
		} else {
			$username = Minz_User::name();
			$path = USERS_PATH . "/{$username}/{$this->config_key}/{$this->getName()}/{$filename}";
			$file_name_url = urlencode("{$username}/{$this->config_key}/{$this->getName()}/{$filename}");
			$mtime = @filemtime($path);
		}

		return Minz_Url::display("/ext.php?f={$file_name_url}&amp;t={$type}&amp;{$mtime}", 'php');
	}

	/**
	 * Register a controller in the Dispatcher.
	 *
	 * @param string $base_name the base name of the controller. Final name will be FreshExtension_<base_name>_Controller.
	 */
	public final function registerController(string $base_name): void {
		Minz_Dispatcher::registerController($base_name, $this->path);
	}

	/**
	 * Register the views in order to be accessible by the application.
	 */
	public final function registerViews(): void {
		Minz_View::addBasePathname($this->path);
	}

	/**
	 * Register i18n files from ext_dir/i18n/
	 */
	public final function registerTranslates(): void {
		$i18n_dir = $this->path . '/i18n';
		Minz_Translate::registerPath($i18n_dir);
	}

	/**
	 * Register a new hook.
	 *
	 * @param string $hook_name the hook name (must exist).
	 * @param callable $hook_function the function name to call (must be callable).
	 */
	public final function registerHook(string $hook_name, $hook_function): void {
		Minz_ExtensionManager::addHook($hook_name, $hook_function);
	}

	/** @param 'system'|'user' $type */
	private function isConfigurationEnabled($type): bool {
		if (!class_exists('FreshRSS_Context', false)) {
			return false;
		}

		switch ($type) {
			case 'system': return FreshRSS_Context::$system_conf !== null;
			case 'user': return FreshRSS_Context::$user_conf !== null;
		}
	}

	/** @param 'system'|'user' $type */
	private function isExtensionConfigured($type): bool {
		switch ($type) {
			case 'system':
				$conf = FreshRSS_Context::$user_conf;
				break;
			case 'user':
				$conf = FreshRSS_Context::$system_conf;
				break;
		}

		if ($conf === null || !$conf->hasParam($this->config_key)) {
			return false;
		}

		$extensions = $conf->{$this->config_key};
		return array_key_exists($this->getName(), $extensions);
	}

	/**
	 * @param 'system'|'user' $type
	 * @return array<string,mixed>
	 */
	private function getConfiguration(string $type): array {
		if (!$this->isConfigurationEnabled($type)) {
			return [];
		}

		if (!$this->isExtensionConfigured($type)) {
			return [];
		}

		$conf = "{$type}_conf";
		return FreshRSS_Context::$$conf->{$this->config_key}[$this->getName()];
	}

	/**
	 * @return array<string,mixed>
	 */
	public final function getSystemConfiguration(): array {
		return $this->getConfiguration('system');
	}

	/**
	 * @return array<string,mixed>
	 */
	public final function getUserConfiguration(): array {
		return $this->getConfiguration('user');
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public final function getSystemConfigurationValue(string $key, $default = null) {
		if (!is_array($this->system_configuration)) {
			$this->system_configuration = $this->getSystemConfiguration();
		}

		if (array_key_exists($key, $this->system_configuration)) {
			return $this->system_configuration[$key];
		}
		return $default;
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public final function getUserConfigurationValue(string $key, $default = null) {
		if (!is_array($this->user_configuration)) {
			$this->user_configuration = $this->getUserConfiguration();
		}

		if (array_key_exists($key, $this->user_configuration)) {
			return $this->user_configuration[$key];
		}
		return $default;
	}

	/**
	 * @param 'system'|'user' $type
	 * @param array<string,mixed> $configuration
	 */
	private function setConfiguration(string $type, array $configuration): void {
		$conf = "{$type}_conf";

		if (FreshRSS_Context::$$conf->hasParam($this->config_key)) {
			$extensions = FreshRSS_Context::$$conf->{$this->config_key};
		} else {
			$extensions = [];
		}
		$extensions[$this->getName()] = $configuration;

		FreshRSS_Context::$$conf->{$this->config_key} = $extensions;
		FreshRSS_Context::$$conf->save();
	}

	/** @param array<string,mixed> $configuration */
	public final function setSystemConfiguration(array $configuration): void {
		$this->setConfiguration('system', $configuration);
		$this->system_configuration = $configuration;
	}

	/** @param array<string,mixed> $configuration */
	public final function setUserConfiguration(array $configuration): void {
		$this->setConfiguration('user', $configuration);
		$this->user_configuration = $configuration;
	}

	/** @param 'system'|'user' $type */
	private function removeConfiguration(string $type): void {
		if (!$this->isConfigurationEnabled($type)) {
			return;
		}

		if (!$this->isExtensionConfigured($type)) {
			return;
		}

		$conf = "{$type}_conf";
		$extensions = FreshRSS_Context::$$conf->{$this->config_key};
		unset($extensions[$this->getName()]);
		if (empty($extensions)) {
			$extensions = null;
		}

		FreshRSS_Context::$$conf->{$this->config_key} = $extensions;
		FreshRSS_Context::$$conf->save();
	}

	public final function removeSystemConfiguration(): void {
		$this->removeConfiguration('system');
		$this->system_configuration = null;
	}

	public final function removeUserConfiguration(): void {
		$this->removeConfiguration('user');
		$this->user_configuration = null;
	}

	public final function saveFile(string $filename, string $content): void {
		$username = Minz_User::name();
		$path = USERS_PATH . "/{$username}/{$this->config_key}/{$this->getName()}";

		if (!file_exists($path)) {
			mkdir($path, 0777, true);
		}

		file_put_contents("{$path}/{$filename}", $content);
	}

	public final function removeFile(string $filename): void {
		$username = Minz_User::name();
		$path = USERS_PATH . "/{$username}/{$this->config_key}/{$this->getName()}/{$filename}";

		if (file_exists($path)) {
			unlink($path);
		}
	}
}
