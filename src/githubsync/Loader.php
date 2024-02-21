<?php

declare(strict_types=1);

namespace githubsync;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Internet;
use pocketmine\utils\Utils;

use function exec;
use function is_dir;
use function json_decode;
use function mkdir;
use function register_shutdown_function;

final class Loader extends PluginBase {
	private ?\Closure $closure = null;

	protected function onEnable(): void {
		if (Utils::getOS() !== Utils::OS_LINUX) {
			$this->getLogger()->warning("O plugin só pode ser executado em sistemas linux.");
			return;
		}
		$this->getServer()->getCommandMap()->register("github", new command\GithubCommand($this));
	}

	protected function onLoad(): void {
		$this->saveDefaultConfig();
	}

	protected function onDisable(): void {
		if (Utils::getOS() !== Utils::OS_LINUX) {
			return;
		}
		if ($this->closure === null) {
			$status = (bool) $this->getConfig()->get("status", false);

			if (!$status) {
				return;
			}
			$pluginPath = $this->getServer()->getPluginPath();

			if (!is_dir($pluginPath . DIRECTORY_SEPARATOR . ".git")) {
				return;
			}
			register_shutdown_function(function() use ($pluginPath): void {
				exec("git -C \"$pluginPath\" pull --quiet");
				echo PHP_EOL . "A sincronização com o github foi concluída com sucesso!" . PHP_EOL;
			});
		} else {
			register_shutdown_function($this->closure);
		}
	}

	public function getLatestHash(): ?string {
		$repository = $this->getConfig()->get("repository");
		$branch = $this->getConfig()->get("branch");
		$token = $this->getConfig()->get("token");

		if ($repository === null || $branch === null || $token === null) {
			return null;
		}

		$response = Internet::getURL("https://api.github.com/repos/$repository/branches", 10, [
			"Authorization: token $token"
		]);

		if ($response === null || $response->getCode() !== 200) {
			return null;
		}
		$json = json_decode($response->getBody(), true);

		foreach ($json as $branchData) {
			if ($branchData["name"] === $branch) {
				return $branchData["commit"]["sha"] ?? null;
			}
		}
		return null;
	}

	public function resync(bool $force = false): void {
		$repository = $this->getConfig()->get("repository");
		$branch = $this->getConfig()->get("branch");
		$token = $this->getConfig()->get("token");

		if ($repository === null || $branch === null || $token === null) {
			return;
		}
		$pluginPath = $this->getServer()->getPluginPath();

		$this->closure = function() use ($pluginPath, $branch, $repository, $token): void {
			exec("rm -rf \"$pluginPath\"");
			@mkdir($pluginPath);
			exec("git -C \"$pluginPath\" clone -b " . $branch . " https://oauth2:" . $token . "@github.com/" . $repository . ".git .");

			echo PHP_EOL . "A sincronização com o github foi concluída com sucesso!" . PHP_EOL;
		};
		if ($force) {
			$this->getServer()->shutdown();
		}
	}
}