<?php

declare(strict_types=1);

namespace githubsync\command;

use githubsync\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\utils\Git;
use pocketmine\utils\Internet;

use function array_pop;
use function array_shift;
use function count;
use function explode;
use function json_decode;

final class GithubCommand extends Command {
	private const PREFIX = "§l§6GITHUB§r§7 ";

	public function __construct(private Loader $plugin) {
		$this->setPermission(DefaultPermissions::ROOT_CONSOLE);
		parent::__construct("github", "Gerencie a sincronização com o Github.");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if ($sender instanceof Player && !$this->testPermission($sender)) {
			return;
		}
		if (!isset($args[0])) {
			$sender->sendMessage(self::PREFIX . "Utilize §f/github help§7 para visualizar todos os comandos.");
			return;
		}
		switch (array_shift($args)) {
			case "help":
			case "ajuda":
				$sender->sendMessage(self::PREFIX . "Os comandos disponíveis são:");
				$sender->sendMessage("§f /github help§7 Visualize todos os comandos.");
				$sender->sendMessage("§f /github sync <repositório> <token>§7 - Definir o repositório.");
				$sender->sendMessage("§f /github status§7 - Visualizar o status da sincronização.");
				$sender->sendMessage("§f /github resync§7 - Forçar a sincronização do repositório.");
				$sender->sendMessage("§f /github branch <ramificação>§7 - Definir a ramificação.");
				$sender->sendMessage("§f /github enable§7 - Ativar a sincronização com o Github.");
				$sender->sendMessage("§f /github disable§7 - Desativar a sincronização com o Github.");
				break;
			case "sync":
			case "sincronizar":
				$repository = array_shift($args);
				$token = array_shift($args);

				if ($repository === null || $token === null) {
					$sender->sendMessage(self::PREFIX . "Utilize: §f/github sync <repositório> <token>§7.");
					return;
				}

				$repo = explode("/", $repository);

				if (count($repo) !== 2) {
					$sender->sendMessage(self::PREFIX . "O repositório deve ser no formato §f<autor>/<repositório>§7.");
					return;
				}
				$response = Internet::getURL("https://api.github.com/repos/{$repository}/branches", 10, [
					"Authorization: Bearer $token"
				]);

				if ($response === null || $response->getCode() !== 200) {
					$sender->sendMessage(self::PREFIX . "O token informado é inválido.");
					return;
				}
				$response = json_decode($response->getBody(), true);
				$branch = array_pop($response)["name"] ?? "master";

				$this->plugin->getConfig()->set("repository", $repository);
				$this->plugin->getConfig()->set("token", $token);
				$this->plugin->getConfig()->set("branch", $branch);
				$this->plugin->getConfig()->save();

				$sender->sendMessage(self::PREFIX . "O repositório foi definido para §f{$repository}§7 na ramificação §f{$branch}§7.");

				$this->plugin->resync(true);
				break;
			case "status":
				$dirty = false;
				$currentHash = Git::getRepositoryState($this->plugin->getServer()->getDataPath() . "plugins", $dirty);
				$latestHash = $this->plugin->getLatestHash();

				$sender->sendMessage(self::PREFIX . "Informações sobre o repositório:");
				$sender->sendMessage(" ");
				$sender->sendMessage("§fRepositório: §7" . $this->plugin->getConfig()->get("repository", "indefinido"));
				$sender->sendMessage("§fRamificação: §7" . $this->plugin->getConfig()->get("branch", "indefinido"));
				$sender->sendMessage("§fToken: §7" . ($this->plugin->getConfig()->get("token") !== null ? "**********" : "indefinido"));
				$sender->sendMessage("§fStatus da Sincronização: §7" . ($this->plugin->getConfig()->get("status", false) ? "§aativado" : "§cdesativado"));
				$sender->sendMessage("§fHash atual: §7" . ($currentHash !== null ? $currentHash : "indefinido"));
				$sender->sendMessage("§fHash mais recente: §7" . ($latestHash !== null ? $latestHash : "indefinido"));
				break;
			case "resync":
			case "ressincronizar":
				$this->plugin->resync(true);
				break;
			case "branch":
			case "ramificacao":
				$branch = array_shift($args);

				if ($branch === null) {
					$sender->sendMessage(self::PREFIX . "Utilize: §f/github branch <ramificação>§7.");
					return;
				}
				$this->plugin->getConfig()->set("branch", $branch);
				$this->plugin->getConfig()->save();

				$sender->sendMessage(self::PREFIX . "A ramificação foi definida para §f{$branch}§7.");
				$sender->sendMessage(self::PREFIX . "É necessário reiniciar o servidor para que as alterações tenham efeito.");

				$this->plugin->resync();

				break;
			case "enable":
			case "ativar":
				$status = (bool) $this->plugin->getConfig()->get("status", false);

				if ($status) {
					$sender->sendMessage(self::PREFIX . "A sincronização já está ativada.");
					return;
				}
				$this->plugin->getConfig()->set("status", true);
				$this->plugin->getConfig()->save();

				$sender->sendMessage(self::PREFIX . "A sincronização foi ativada com sucesso.");
				break;
			case "disable":
			case "desativar":
				$status = (bool) $this->plugin->getConfig()->get("status", false);

				if (!$status) {
					$sender->sendMessage(self::PREFIX . "A sincronização já está desativada.");
					return;
				}
				$this->plugin->getConfig()->set("status", false);
				$this->plugin->getConfig()->save();

				$sender->sendMessage(self::PREFIX . "A sincronização foi desativada com sucesso.");
				break;
		}
	}
}