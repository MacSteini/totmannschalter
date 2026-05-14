<?php

declare(strict_types=1);

// phpcs:ignoreFile
// Generated totman runtime UI. Edit only the setup-code value below before first setup.
// Docker and managed hosting may instead set TOTMAN_UI_SETUP_CODE in the server environment.
// Build provenance is available through Totman\RuntimeUi\Bundle\BundleManifest::data().

namespace Totman\RuntimeUi\Config;

// Before first setup, replace the empty string with a one-time setup code.
// Docker and managed hosting may instead set TOTMAN_UI_SETUP_CODE in the server environment.
const TOTMAN_UI_SETUP_CODE = '';

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Security\SetupAccessResult;

final class AdminAuthApplicationResult
{
    public function __construct(
        private readonly SetupAccessResult $access,
        private readonly AdminAuthViewModel $view,
        private readonly string $notice = '',
    ) {
    }

    public static function preview(AdminAuthViewModel $view): self
    {
        return new self(SetupAccessResult::allow(), $view);
    }

    public function access(): SetupAccessResult
    {
        return $this->access;
    }

    public function view(): AdminAuthViewModel
    {
        return $this->view;
    }

    public function notice(): string
    {
        return $this->notice;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Http\FirstRunRequest;
use Totman\RuntimeUi\Security\AdminAuthService;
use Totman\RuntimeUi\Security\AdminSessionState;
use Totman\RuntimeUi\Security\SetupAccessResult;
use Totman\RuntimeUi\Security\SetupSessionState;
use Totman\RuntimeUi\Security\UiPrivateConfig;
use Totman\RuntimeUi\Security\UiPrivateConfigStore;

final class AdminAuthApplicationService
{
    public function __construct(
        private readonly AdminAuthService $authService = new AdminAuthService(),
        private readonly AdminAuthViewModelBuilder $viewModelBuilder = new AdminAuthViewModelBuilder(),
    ) {
    }

    public function preview(
        string $stateDir,
        DiscoveryResult $discovered,
        AdminSessionState $adminSession,
        string $expectedSetupCode = '',
    ): AdminAuthApplicationResult {
        $configResult = $this->store($stateDir)->loadResult();
        if ($configResult->blocksAdmin()) {
            return AdminAuthApplicationResult::preview(AdminAuthViewModel::privateConfigBlocked());
        }

        $view = $this->view($discovered, $configResult->config(), $adminSession);
        if ($view->showAdministrationDisabled()) {
            return AdminAuthApplicationResult::preview($view);
        }

        if (!$configResult->config()->hasAdminCredential() && $expectedSetupCode === '') {
            return AdminAuthApplicationResult::preview(AdminAuthViewModel::setupLocked());
        }

        return AdminAuthApplicationResult::preview($view);
    }

    public function handle(
        string $stateDir,
        DiscoveryResult $discovered,
        FirstRunRequest $request,
        string $expectedSetupCode,
        SetupSessionState $setupSession,
        AdminSessionState $adminSession,
    ): AdminAuthApplicationResult {
        $store = $this->store($stateDir);
        $configResult = $store->loadResult();
        if ($configResult->blocksAdmin() && !$request->isLogout()) {
            $access = SetupAccessResult::denied('private_ui_config_blocked', $configResult->message());
            return new AdminAuthApplicationResult($access, AdminAuthViewModel::privateConfigBlocked());
        }

        $access = $this->handleRequest($store, $configResult->config(), $discovered, $request, $expectedSetupCode, $setupSession, $adminSession);
        $updated = $store->loadResult();
        $notice = $access->allowed() ? 'Admin access state updated.' : '';
        if (!$updated->config()->hasAdminCredential() && $expectedSetupCode === '') {
            return new AdminAuthApplicationResult($access, AdminAuthViewModel::setupLocked(), $notice);
        }

        return new AdminAuthApplicationResult(
            $access,
            $updated->blocksAdmin() ? AdminAuthViewModel::privateConfigBlocked() : $this->view($discovered, $updated->config(), $adminSession),
            $notice
        );
    }

    private function handleRequest(
        UiPrivateConfigStore $store,
        UiPrivateConfig $config,
        DiscoveryResult $discovered,
        FirstRunRequest $request,
        string $expectedSetupCode,
        SetupSessionState $setupSession,
        AdminSessionState $adminSession,
    ): SetupAccessResult {
        if ($request->isLogout()) {
            return $this->authService->logout($adminSession);
        }

        if ($discovered->mode() === 'blocked') {
            return SetupAccessResult::denied('config_blocked', 'Configuration discovery is blocked by an unreadable or invalid runtime config.');
        }

        $webUiEnabled = $this->normaliseWebUiEnabled($discovered->effectiveMainConfig()['web_ui_enabled'] ?? null);
        if ($request->isCreateAdmin()) {
            if ($webUiEnabled === false || ($discovered->mode() === 'existing' && $webUiEnabled !== true)) {
                return SetupAccessResult::denied('administration_disabled', 'Browser administration is disabled by web_ui_enabled.');
            }

            return $this->authService->createAdmin($store, $request->authInput(), $expectedSetupCode, $request->setupCode(), $setupSession, $adminSession);
        }

        if ($webUiEnabled === false) {
            return SetupAccessResult::denied('administration_disabled', 'Browser administration is disabled by web_ui_enabled.');
        }

        if ($discovered->mode() === 'existing' && $webUiEnabled !== true) {
            return SetupAccessResult::denied('administration_disabled', 'Browser administration is disabled by web_ui_enabled.');
        }

        if ($request->isLogin()) {
            return $this->authService->login($config, $request->authInput(), $adminSession);
        }

        if ($request->isReauth()) {
            return $this->authService->reauth($config, $request->authInput(), $adminSession);
        }

        return SetupAccessResult::denied('auth_action_unknown', 'The requested admin auth action is not available.');
    }

    private function view(DiscoveryResult $discovered, UiPrivateConfig $config, AdminSessionState $adminSession): AdminAuthViewModel
    {
        return $this->viewModelBuilder->build(
            $discovered->mode(),
            $discovered->effectiveMainConfig()['web_ui_enabled'] ?? null,
            $config,
            $adminSession
        );
    }

    private function store(string $stateDir): UiPrivateConfigStore
    {
        return UiPrivateConfigStore::forStateDir($stateDir);
    }

    private function normaliseWebUiEnabled(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'on', 'yes' => true,
                '0', 'false', 'off', 'no', '' => false,
                default => null,
            };
        }

        return null;
    }
}

namespace Totman\RuntimeUi\Application;

final class AdminAuthViewModel
{
    public const ACCESS_MISSING = 'access_missing';
    public const SIGN_IN_REQUIRED = 'sign_in_required';
    public const SIGNED_IN = 'signed_in';
    public const ADMINISTRATION_DISABLED = 'administration_disabled';
    public const CONFIG_BLOCKED = 'config_blocked';
    public const PRIVATE_CONFIG_BLOCKED = 'private_config_blocked';
    public const SETUP_LOCKED = 'setup_locked';

    public function __construct(
        private readonly string $status,
        private readonly string $username = '',
    ) {
    }

    public static function accessMissing(): self
    {
        return new self(self::ACCESS_MISSING);
    }

    public static function signInRequired(): self
    {
        return new self(self::SIGN_IN_REQUIRED);
    }

    public static function signedIn(string $username): self
    {
        return new self(self::SIGNED_IN, $username);
    }

    public static function administrationDisabled(): self
    {
        return new self(self::ADMINISTRATION_DISABLED);
    }

    public static function configBlocked(): self
    {
        return new self(self::CONFIG_BLOCKED);
    }

    public static function privateConfigBlocked(): self
    {
        return new self(self::PRIVATE_CONFIG_BLOCKED);
    }

    public static function setupLocked(): self
    {
        return new self(self::SETUP_LOCKED);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function showCreateAdmin(): bool
    {
        return $this->status === self::ACCESS_MISSING;
    }

    public function showLogin(): bool
    {
        return $this->status === self::SIGN_IN_REQUIRED;
    }

    public function showSignedIn(): bool
    {
        return $this->status === self::SIGNED_IN;
    }

    public function showAdministrationDisabled(): bool
    {
        return $this->status === self::ADMINISTRATION_DISABLED;
    }

    public function showConfigBlocked(): bool
    {
        return $this->status === self::CONFIG_BLOCKED;
    }

    public function showPrivateConfigBlocked(): bool
    {
        return $this->status === self::PRIVATE_CONFIG_BLOCKED;
    }

    public function showSetupLocked(): bool
    {
        return $this->status === self::SETUP_LOCKED;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Security\AdminSessionState;
use Totman\RuntimeUi\Security\UiPrivateConfig;

final class AdminAuthViewModelBuilder
{
    public function build(
        string $discoveryMode,
        mixed $webUiEnabled,
        UiPrivateConfig $uiConfig,
        AdminSessionState $adminSession,
    ): AdminAuthViewModel {
        if ($discoveryMode === 'blocked') {
            return AdminAuthViewModel::configBlocked();
        }

        $normalisedWebUiEnabled = $this->normaliseWebUiEnabled($webUiEnabled);
        if ($normalisedWebUiEnabled === false || ($discoveryMode === 'existing' && $normalisedWebUiEnabled !== true)) {
            return AdminAuthViewModel::administrationDisabled();
        }

        if (!$uiConfig->hasAdminCredential()) {
            return AdminAuthViewModel::accessMissing();
        }

        if ($adminSession->authenticated()) {
            return AdminAuthViewModel::signedIn($adminSession->username());
        }

        return AdminAuthViewModel::signInRequired();
    }

    private function normaliseWebUiEnabled(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'on', 'yes' => true,
                '0', 'false', 'off', 'no', '' => false,
                default => null,
            };
        }

        return null;
    }
}

namespace Totman\RuntimeUi\Application;

final class AdminCommand
{
    public const READ_ONLY = 'read-only';
    public const WRITE = 'write';
    public const DANGER = 'danger';

    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $classification,
    ) {
        if (!in_array($classification, [self::READ_ONLY, self::WRITE, self::DANGER], true)) {
            throw new \InvalidArgumentException('Unsupported admin command classification.');
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function classification(): string
    {
        return $this->classification;
    }

    public function readOnly(): bool
    {
        return $this->classification === self::READ_ONLY;
    }

    public function danger(): bool
    {
        return $this->classification === self::DANGER;
    }

    public function requiresAdminSession(): bool
    {
        return true;
    }

    public function requiresCsrf(): bool
    {
        return $this->danger();
    }

    public function requiresRateLimit(): bool
    {
        return $this->danger();
    }

    public function requiresReauth(): bool
    {
        return $this->danger();
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Security\AdminReauthPolicy;
use Totman\RuntimeUi\Security\AdminSessionState;
use Totman\RuntimeUi\Security\SetupAccessResult;

final class AdminCommandAccessPolicy
{
    public function __construct(
        private readonly AdminCommandCatalog $catalog = new AdminCommandCatalog(),
        private readonly AdminReauthPolicy $reauthPolicy = new AdminReauthPolicy(),
    ) {
    }

    public function evaluate(string $commandKey, AdminSessionState $adminSession, int $now): SetupAccessResult
    {
        $command = $this->catalog->get($commandKey);
        if (!$command instanceof AdminCommand) {
            return SetupAccessResult::denied('admin_command_unknown', 'Unknown admin command.');
        }

        if ($command->requiresAdminSession() && !$adminSession->authenticated()) {
            return SetupAccessResult::denied('admin_auth_required', 'An authenticated admin session is required.');
        }

        if ($command->requiresReauth()) {
            return $this->reauthPolicy->evaluate($adminSession, $now);
        }

        return SetupAccessResult::allow();
    }
}

namespace Totman\RuntimeUi\Application;

final class AdminCommandCatalog
{
    public const READ_RUNTIME_SUMMARY = 'read_runtime_summary';
    public const VIEW_LOGS = 'view_logs';
    public const VIEW_FILE_ALIASES = 'view_file_aliases';
    public const PREVIEW_HMAC_ROTATION = 'preview_hmac_rotation';
    public const PREVIEW_RUNTIME_RESET = 'preview_runtime_reset';
    public const PREVIEW_LOG_CLEAR = 'preview_log_clear';
    public const PREVIEW_FILE_ALIAS_DELETION = 'preview_file_alias_deletion';

    /** @var array<string, AdminCommand>|null */
    private ?array $commands = null;

    /**
     * @return list<AdminCommand>
     */
    public function all(): array
    {
        return array_values($this->commands());
    }

    public function get(string $key): ?AdminCommand
    {
        return $this->commands()[$key] ?? null;
    }

    /**
     * @return array<string, AdminCommand>
     */
    private function commands(): array
    {
        if ($this->commands !== null) {
            return $this->commands;
        }

        $this->commands = [
            self::READ_RUNTIME_SUMMARY => new AdminCommand(self::READ_RUNTIME_SUMMARY, 'Runtime summary', AdminCommand::READ_ONLY),
            self::VIEW_LOGS => new AdminCommand(self::VIEW_LOGS, 'View logs', AdminCommand::READ_ONLY),
            self::VIEW_FILE_ALIASES => new AdminCommand(self::VIEW_FILE_ALIASES, 'File aliases', AdminCommand::READ_ONLY),
            self::PREVIEW_HMAC_ROTATION => new AdminCommand(self::PREVIEW_HMAC_ROTATION, 'Preview HMAC rotation', AdminCommand::DANGER),
            self::PREVIEW_RUNTIME_RESET => new AdminCommand(self::PREVIEW_RUNTIME_RESET, 'Preview runtime reset', AdminCommand::DANGER),
            self::PREVIEW_LOG_CLEAR => new AdminCommand(self::PREVIEW_LOG_CLEAR, 'Preview log clear', AdminCommand::DANGER),
            self::PREVIEW_FILE_ALIAS_DELETION => new AdminCommand(self::PREVIEW_FILE_ALIAS_DELETION, 'Preview file alias deletion', AdminCommand::DANGER),
        ];

        return $this->commands;
    }
}

namespace Totman\RuntimeUi\Application;

final class AdminInspectionViewModel
{
    public function __construct(
        private readonly bool $available,
        private readonly string $notice = '',
        private readonly ?RuntimeSummary $summary = null,
        private readonly ?RuntimeLogTail $logTail = null,
        private readonly ?FileAliasInventory $fileAliases = null,
        private readonly ?MaintenanceCommandResult $maintenanceCommand = null,
    ) {
    }

    public static function unavailable(string $notice): self
    {
        return new self(false, $notice);
    }

    public static function fromReadModels(
        RuntimeSummary $summary,
        RuntimeLogTail $logTail,
        FileAliasInventory $fileAliases,
        ?MaintenanceCommandResult $maintenanceCommand = null,
    ): self {
        return new self(true, '', $summary, $logTail, $fileAliases, $maintenanceCommand);
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function notice(): string
    {
        return $this->notice;
    }

    public function summary(): ?RuntimeSummary
    {
        return $this->summary;
    }

    public function logTail(): ?RuntimeLogTail
    {
        return $this->logTail;
    }

    public function fileAliases(): ?FileAliasInventory
    {
        return $this->fileAliases;
    }

    public function maintenanceCommand(): ?MaintenanceCommandResult
    {
        return $this->maintenanceCommand;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Security\AdminSessionState;

final class DangerCommandDryRunService
{
    public function __construct(
        private readonly AdminCommandCatalog $catalog = new AdminCommandCatalog(),
        private readonly AdminCommandAccessPolicy $accessPolicy = new AdminCommandAccessPolicy(),
    ) {
    }

    public function preview(
        string $commandKey,
        AdminSessionState $adminSession,
        bool $csrfValid,
        bool $rateLimitAllowed,
        int $now,
        ?string $targetAlias = null,
    ): DangerCommandPreview {
        $command = $this->catalog->get($commandKey);
        if (!$command instanceof AdminCommand) {
            return new DangerCommandPreview($commandKey, 'Unknown command', false, ['Unknown admin command.'], []);
        }

        if (!$command->danger()) {
            return new DangerCommandPreview($commandKey, $command->label(), false, ['Command is not a danger command.'], []);
        }

        $blockers = [];
        $access = $this->accessPolicy->evaluate($commandKey, $adminSession, $now);
        if (!$access->allowed()) {
            $blockers[] = $access->message();
        }

        if (!$csrfValid) {
            $blockers[] = 'Valid CSRF token is required before previewing this action.';
        }

        if (!$rateLimitAllowed) {
            $blockers[] = 'Danger-command rate limit is currently exceeded.';
        }

        return new DangerCommandPreview(
            $command->key(),
            $command->label(),
            $blockers === [],
            $blockers,
            $this->plan($command->key(), $targetAlias)
        );
    }

    /**
     * @return list<string>
     */
    private function plan(string $commandKey, ?string $targetAlias): array
    {
        return match ($commandKey) {
            AdminCommandCatalog::PREVIEW_HMAC_ROTATION => [
                'A new HMAC secret would be generated server-side.',
                'Existing signed confirmation, ACK, and download links would stop validating.',
                'No secret is generated during this dry-run preview.',
            ],
            AdminCommandCatalog::PREVIEW_RUNTIME_RESET => [
                'Runtime state would be removed so scheduling can start from a clean state.',
                'Configuration files and recipient definitions would remain untouched.',
                'No state file is modified during this dry-run preview.',
            ],
            AdminCommandCatalog::PREVIEW_LOG_CLEAR => [
                'The runtime log file would be truncated or removed.',
                'Configuration files and runtime state would remain untouched.',
                'No log file is modified during this dry-run preview.',
            ],
            AdminCommandCatalog::PREVIEW_FILE_ALIAS_DELETION => [
                'The selected file alias would be removed from recipient configuration.',
                'Referenced download files would not be deleted automatically.',
                'Dry-run target alias: ' . ($targetAlias !== null && $targetAlias !== '' ? $targetAlias : '[not selected]') . '.',
            ],
            default => [],
        };
    }
}

namespace Totman\RuntimeUi\Application;

final class DangerCommandPreview
{
    /**
     * @param list<string> $blockers
     * @param list<string> $plan
     */
    public function __construct(
        private readonly string $commandKey,
        private readonly string $label,
        private readonly bool $allowed,
        private readonly array $blockers,
        private readonly array $plan,
    ) {
    }

    public function commandKey(): string
    {
        return $this->commandKey;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    /**
     * @return list<string>
     */
    public function blockers(): array
    {
        return $this->blockers;
    }

    /**
     * @return list<string>
     */
    public function plan(): array
    {
        return $this->plan;
    }
}

namespace Totman\RuntimeUi\Application;

final class FileAliasInventory
{
    /**
     * @param list<FileAliasInventoryItem> $items
     * @param list<string> $issues
     */
    public function __construct(
        private readonly string $downloadBaseDir,
        private readonly array $items,
        private readonly array $issues,
    ) {
    }

    public function downloadBaseDir(): string
    {
        return $this->downloadBaseDir;
    }

    /**
     * @return list<FileAliasInventoryItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return list<string>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function item(string $alias): ?FileAliasInventoryItem
    {
        foreach ($this->items as $item) {
            if ($item->alias() === $alias) {
                return $item;
            }
        }

        return null;
    }
}

namespace Totman\RuntimeUi\Application;

final class FileAliasInventoryItem
{
    /**
     * @param list<string> $issues
     */
    public function __construct(
        private readonly string $alias,
        private readonly string $relativePath,
        private readonly bool $fileExists,
        private readonly int $normalReferences,
        private readonly int $singleUseReferences,
        private readonly array $issues = [],
    ) {
    }

    public function alias(): string
    {
        return $this->alias;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function fileExists(): bool
    {
        return $this->fileExists;
    }

    public function normalReferences(): int
    {
        return $this->normalReferences;
    }

    public function singleUseReferences(): int
    {
        return $this->singleUseReferences;
    }

    /**
     * @return list<string>
     */
    public function issues(): array
    {
        return $this->issues;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\ConfigDiscovery;
use Totman\RuntimeUi\Config\RecipientConfigImporter;
use Totman\RuntimeUi\Deployment\DeploymentContext;

final class FileAliasInventoryReader
{
    public function __construct(
        private readonly ConfigDiscovery $discovery = new ConfigDiscovery(),
        private readonly RecipientConfigImporter $recipientImporter = new RecipientConfigImporter(),
        private readonly RuntimeSummaryReader $summaryReader = new RuntimeSummaryReader(),
    ) {
    }

    public function read(string $stateDir, DeploymentContext $context): FileAliasInventory
    {
        $discovered = $this->discovery->discover($stateDir);
        $recipients = $this->recipientImporter->import($discovered);
        $summary = $this->summaryReader->read($stateDir, $context);
        $downloadBaseDir = $summary->paths()['download_base_dir'] ?? rtrim($stateDir, '/') . '/downloads';
        $referenceCounts = $this->referenceCounts($recipients->recipients());
        $issues = $recipients->issues();
        $items = [];

        foreach ($recipients->files() as $alias => $relativePath) {
            $itemIssues = [];
            if (preg_match('/^[a-z0-9_-]+$/', $alias) !== 1) {
                $itemIssues[] = 'Alias key is invalid.';
            }

            if (!is_string($relativePath) || trim($relativePath) === '' || str_starts_with($relativePath, '/')) {
                $itemIssues[] = 'Alias path must be a non-empty relative path.';
                $relativePath = is_string($relativePath) ? $relativePath : '';
            }

            $items[] = new FileAliasInventoryItem(
                (string)$alias,
                $relativePath,
                $relativePath !== '' && is_file(rtrim($downloadBaseDir, '/') . '/' . ltrim($relativePath, '/')),
                $referenceCounts[$alias]['normal'] ?? 0,
                $referenceCounts[$alias]['single_use'] ?? 0,
                $itemIssues
            );
        }

        foreach ($referenceCounts as $alias => $counts) {
            if (!array_key_exists($alias, $recipients->files())) {
                $issues[] = "recipient references unknown file alias {$alias}";
            }
        }

        return new FileAliasInventory($downloadBaseDir, $items, array_values(array_unique($issues)));
    }

    /**
     * @param array<int, mixed> $recipients
     * @return array<string, array{normal: int, single_use: int}>
     */
    private function referenceCounts(array $recipients): array
    {
        $counts = [];
        foreach ($recipients as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ([3 => 'normal', 4 => 'single_use'] as $field => $kind) {
                if (!isset($row[$field]) || !is_array($row[$field])) {
                    continue;
                }

                foreach ($row[$field] as $alias) {
                    if (!is_string($alias)) {
                        continue;
                    }

                    $counts[$alias] ??= ['normal' => 0, 'single_use' => 0];
                    $counts[$alias][$kind]++;
                }
            }
        }

        return $counts;
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunAction
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly bool $visible = true,
        private readonly bool $disabled = false,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function visible(): bool
    {
        return $this->visible;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunDraftLoadResult
{
    public const LOADED = 'loaded';
    public const MISSING = 'missing';
    public const CORRUPT = 'corrupt';

    public function __construct(
        private readonly string $status,
        private readonly FirstRunDraftState $state,
        private readonly string $message = '',
    ) {
    }

    public static function loaded(FirstRunDraftState $state): self
    {
        return new self(self::LOADED, $state);
    }

    public static function missing(): self
    {
        return new self(self::MISSING, new FirstRunDraftState());
    }

    public static function corrupt(string $message): self
    {
        return new self(self::CORRUPT, new FirstRunDraftState(), $message);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function state(): FirstRunDraftState
    {
        return $this->state;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function notice(): string
    {
        if ($this->status !== self::CORRUPT || $this->message === '') {
            return '';
        }

        return $this->message;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Config\MainConfigImporter;
use Totman\RuntimeUi\Config\RecipientConfigImporter;
use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Preflight\FirstRunPreflight;

final class FirstRunDraftPreflightGate
{
    public function __construct(
        private readonly MainConfigDraftBuilder $mainConfigDraftBuilder = new MainConfigDraftBuilder(),
        private readonly RecipientConfigDraftBuilder $recipientConfigDraftBuilder = new RecipientConfigDraftBuilder(),
        private readonly MainConfigImporter $mainImporter = new MainConfigImporter(),
        private readonly RecipientConfigImporter $recipientImporter = new RecipientConfigImporter(),
        private readonly FirstRunPreflight $preflight = new FirstRunPreflight(),
    ) {
    }

    public function evaluate(
        string $stateDir,
        DeploymentContext $context,
        DiscoveryResult $discovered,
        FirstRunDraftState $draft
    ): FirstRunDraftPreflightResult {
        $input = $draft->toInput();
        $mainConfig = $this->mainConfigDraftBuilder->build($stateDir, $context, $input, $discovered);
        $recipientConfig = $this->recipientConfigDraftBuilder->build($input, $discovered);
        $main = $this->mainImporter->importConfig($mainConfig, $context);
        $recipients = $this->recipientImporter->importConfig(
            $recipientConfig->files(),
            $recipientConfig->messages(),
            $recipientConfig->recipients()
        );

        return new FirstRunDraftPreflightResult(
            $mainConfig,
            $recipientConfig,
            $this->preflight->check($main, $recipients, $context)
        );
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Preflight\PreflightResult;

final class FirstRunDraftPreflightResult
{
    /**
     * @param array<string, mixed> $mainConfig
     */
    public function __construct(
        private readonly array $mainConfig,
        private readonly RecipientConfigDraft $recipientConfig,
        private readonly PreflightResult $preflight,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function mainConfig(): array
    {
        return $this->mainConfig;
    }

    public function recipientConfig(): RecipientConfigDraft
    {
        return $this->recipientConfig;
    }

    public function preflight(): PreflightResult
    {
        return $this->preflight;
    }

    public function canWriteRuntime(): bool
    {
        return $this->preflight->status() !== 'FAIL';
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Setup\FirstRunStepCatalog;

final class FirstRunDraftState
{
    /**
     * @param array<string, string|bool|null> $values
     */
    public function __construct(
        private readonly array $values = [],
        private readonly string $activeStep = FirstRunStepCatalog::CREATE_OR_IMPORT,
        private readonly int $updatedAt = 0,
        private readonly bool $dirty = false,
    ) {
    }

    public static function fromInput(
        FirstRunInput $input,
        string $activeStep = FirstRunStepCatalog::CREATE_OR_IMPORT,
        ?int $updatedAt = null
    ): self {
        $values = [];
        self::addString($values, 'base_url', $input->publicUrl());
        self::addString($values, 'mail_from', $input->mailFrom());
        self::addString($values, 'to_self', $input->operatorMailbox());
        self::addString($values, 'sendmail_path', $input->sendmailPath());
        self::addString($values, 'recipient_name', $input->recipientName());
        self::addString($values, 'recipient_mailbox', $input->recipientMailbox());
        self::addString($values, 'message_subject', $input->messageSubject());
        self::addString($values, 'message_body', $input->messageBody());
        if ($input->webUiEnabled() !== null) {
            $values['web_ui_enabled'] = $input->webUiEnabled();
        }
        self::addString($values, 'download_alias', $input->downloadAlias());
        self::addString($values, 'download_path', $input->downloadPath());
        if ($input->downloadSingleUse() || $input->downloadAlias() !== '' || $input->downloadPath() !== '') {
            $values['download_single_use'] = $input->downloadSingleUse();
        }

        return new self($values, $activeStep, $updatedAt ?? time(), true);
    }

    public function toInput(): FirstRunInput
    {
        return new FirstRunInput(
            $this->string('base_url'),
            $this->string('mail_from'),
            $this->string('to_self'),
            $this->string('sendmail_path'),
            $this->string('recipient_name'),
            $this->string('recipient_mailbox'),
            $this->string('message_subject'),
            $this->string('message_body'),
            $this->nullableBool('web_ui_enabled'),
            $this->string('download_alias'),
            $this->string('download_path'),
            $this->bool('download_single_use')
        );
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values) && $this->values[$key] !== null;
    }

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    public function bool(string $key): bool
    {
        return ($this->values[$key] ?? false) === true;
    }

    public function nullableBool(string $key): ?bool
    {
        $value = $this->values[$key] ?? null;
        return is_bool($value) ? $value : null;
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function activeStep(): string
    {
        return $this->activeStep;
    }

    public function updatedAt(): int
    {
        return $this->updatedAt;
    }

    public function dirty(): bool
    {
        return $this->dirty;
    }

    public function withActiveStep(string $activeStep, ?int $updatedAt = null): self
    {
        return new self($this->values, $activeStep, $updatedAt ?? time(), $this->dirty);
    }

    /**
     * @param array<string, string|bool|null> $values
     */
    private static function addString(array &$values, string $key, string $value): void
    {
        if ($value !== '') {
            $values[$key] = $value;
        }
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunDraftStore
{
    private const SESSION_KEY = 'totman_ui_first_run_draft';

    private FirstRunDraftState $fallbackState;
    private bool $fallbackLoaded;

    /**
     * @param array<string, string|bool|null>|null $fallbackValues
     */
    public function __construct(?array $fallbackValues = null)
    {
        $this->fallbackState = new FirstRunDraftState($fallbackValues ?? []);
        $this->fallbackLoaded = $fallbackValues !== null;
    }

    public function load(): FirstRunDraftState
    {
        return $this->loadResult()->state();
    }

    public function loadResult(): FirstRunDraftLoadResult
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!array_key_exists(self::SESSION_KEY, $_SESSION)) {
                return FirstRunDraftLoadResult::missing();
            }

            $values = $_SESSION[self::SESSION_KEY];
            if (!is_array($values)) {
                return FirstRunDraftLoadResult::corrupt('Saved setup draft was unreadable and has been ignored.');
            }

            return $this->fromStoredValues($values);
        }

        return $this->fallbackLoaded
            ? FirstRunDraftLoadResult::loaded($this->fallbackState)
            : FirstRunDraftLoadResult::missing();
    }

    public function save(FirstRunDraftState $state): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = $this->toStoredValues($state);
            return;
        }

        $this->fallbackState = $state;
        $this->fallbackLoaded = true;
    }

    public function clear(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::SESSION_KEY]);
            return;
        }

        $this->fallbackState = new FirstRunDraftState();
        $this->fallbackLoaded = false;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, string|bool|null>
     */
    private function normalise(array $values): array
    {
        $normalised = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || (!is_string($value) && !is_bool($value) && $value !== null)) {
                continue;
            }

            $normalised[$key] = $value;
        }

        return $normalised;
    }

    /**
     * @param array<mixed> $stored
     */
    private function fromStoredValues(array $stored): FirstRunDraftLoadResult
    {
        if (isset($stored['values']) && is_array($stored['values'])) {
            return FirstRunDraftLoadResult::loaded(new FirstRunDraftState(
                $this->normalise($stored['values']),
                is_string($stored['active_step'] ?? null) ? $stored['active_step'] : 'create-or-import',
                is_int($stored['updated_at'] ?? null) ? $stored['updated_at'] : 0,
                ($stored['dirty'] ?? false) === true
            ));
        }

        if (array_key_exists('values', $stored) && !is_array($stored['values'])) {
            return FirstRunDraftLoadResult::corrupt('Saved setup draft values were invalid and have been ignored.');
        }

        return FirstRunDraftLoadResult::loaded(new FirstRunDraftState($this->normalise($stored)));
    }

    /**
     * @return array{values: array<string, string|bool|null>, active_step: string, updated_at: int, dirty: bool}
     */
    private function toStoredValues(FirstRunDraftState $state): array
    {
        return [
            'values' => $state->values(),
            'active_step' => $state->activeStep(),
            'updated_at' => $state->updatedAt(),
            'dirty' => $state->dirty(),
        ];
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunField
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $step,
        private readonly string $value,
        private readonly bool $required,
        private readonly bool $readOnly,
        private readonly string $source,
        private readonly array $errors = [],
        private readonly string $control = 'text',
        private readonly string $hint = '',
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function step(): string
    {
        return $this->step;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function readOnly(): bool
    {
        return $this->readOnly;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function control(): string
    {
        return $this->control;
    }

    public function hint(): string
    {
        return $this->hint;
    }

    public function domId(): string
    {
        return 'wizard-field-' . str_replace('_', '-', $this->key);
    }

    public function hintId(): string
    {
        return $this->domId() . '-hint';
    }

    public function errorId(): string
    {
        return $this->domId() . '-error';
    }

    public function describedBy(): string
    {
        $ids = [];
        if ($this->hint !== '') {
            $ids[] = $this->hintId();
        }

        if ($this->errors !== []) {
            $ids[] = $this->errorId();
        }

        return implode(' ', $ids);
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunHiddenFieldPolicy
{
    private const DENIED_KEYS = [
        'setup_code',
        'csrf_token',
        'admin_username',
        'admin_password',
        'admin_password_confirm',
        'login_username',
        'login_password',
        'reauth_password',
        'hmac_secret_hex',
    ];

    /**
     * @return list<FirstRunField>
     */
    public function fieldsToPreserve(FirstRunViewModel $view): array
    {
        $current = [];
        foreach ($view->currentStepFields() as $field) {
            $current[$field->key()] = true;
        }

        $hidden = [];
        foreach ($view->fieldModels() as $field) {
            if (isset($current[$field->key()]) || in_array($field->key(), self::DENIED_KEYS, true)) {
                continue;
            }

            $hidden[] = $field;
        }

        return $hidden;
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunInput
{
    public function __construct(
        private readonly string $publicUrl = '',
        private readonly string $mailFrom = '',
        private readonly string $operatorMailbox = '',
        private readonly string $sendmailPath = '',
        private readonly string $recipientName = '',
        private readonly string $recipientMailbox = '',
        private readonly string $messageSubject = '',
        private readonly string $messageBody = '',
        private readonly ?bool $webUiEnabled = null,
        private readonly string $downloadAlias = '',
        private readonly string $downloadPath = '',
        private readonly bool $downloadSingleUse = false,
    ) {
    }

    /**
     * @param array<string, string> $post
     */
    public static function fromPost(array $post): self
    {
        return new self(
            self::clean($post['base_url'] ?? ''),
            self::clean($post['mail_from'] ?? ''),
            self::clean($post['to_self'] ?? ''),
            self::clean($post['sendmail_path'] ?? ''),
            self::clean($post['recipient_name'] ?? ''),
            self::clean($post['recipient_mailbox'] ?? ''),
            self::clean($post['message_subject'] ?? ''),
            self::clean($post['message_body'] ?? ''),
            array_key_exists('web_ui_enabled', $post) ? self::truthy($post['web_ui_enabled']) : null,
            self::clean($post['download_alias'] ?? ''),
            self::clean($post['download_path'] ?? ''),
            self::truthy($post['download_single_use'] ?? ''),
        );
    }

    public function publicUrl(): string
    {
        return $this->publicUrl;
    }

    public function mailFrom(): string
    {
        return $this->mailFrom;
    }

    public function operatorMailbox(): string
    {
        return $this->operatorMailbox;
    }

    public function sendmailPath(): string
    {
        return $this->sendmailPath;
    }

    public function recipientName(): string
    {
        return $this->recipientName;
    }

    public function recipientMailbox(): string
    {
        return $this->recipientMailbox;
    }

    public function messageSubject(): string
    {
        return $this->messageSubject;
    }

    public function messageBody(): string
    {
        return $this->messageBody;
    }

    public function webUiEnabled(): ?bool
    {
        return $this->webUiEnabled;
    }

    public function downloadAlias(): string
    {
        return $this->downloadAlias;
    }

    public function downloadPath(): string
    {
        return $this->downloadPath;
    }

    public function downloadSingleUse(): bool
    {
        return $this->downloadSingleUse;
    }

    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\0"], '', $value));
    }

    private static function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;

final class FirstRunInputValidator
{
    /**
     * @return list<string>
     */
    public function validate(DiscoveryResult $discovered, FirstRunInput $input): array
    {
        $errors = [];

        if ($discovered->mode() === 'fresh') {
            $errors = array_merge($errors, $this->requireFields([
                'base_url' => $input->publicUrl(),
                'mail_from' => $input->mailFrom(),
                'to_self' => $input->operatorMailbox(),
                'sendmail_path' => $input->sendmailPath(),
                'recipient_name' => $input->recipientName(),
                'recipient_mailbox' => $input->recipientMailbox(),
                'message_subject' => $input->messageSubject(),
                'message_body' => $input->messageBody(),
            ], ' is required for fresh setup.'));
        } elseif ($input->recipientName() !== '' || $input->recipientMailbox() !== '') {
            $errors = array_merge($errors, $this->requireFields([
                'recipient_name' => $input->recipientName(),
                'recipient_mailbox' => $input->recipientMailbox(),
                'message_subject' => $input->messageSubject(),
                'message_body' => $input->messageBody(),
            ], ' is required for recipient replacement.'));
        }

        if (($input->downloadAlias() === '') !== ($input->downloadPath() === '')) {
            $errors[] = 'download_alias and download_path must be supplied together.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $fields
     * @return list<string>
     */
    private function requireFields(array $fields, string $suffix): array
    {
        $errors = [];
        foreach ($fields as $key => $value) {
            if ($value === '') {
                $errors[] = $key . $suffix;
            }
        }

        return $errors;
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunSaveResult
{
    /**
     * @param list<string> $writtenFiles
     * @param list<string> $errors
     */
    public function __construct(
        private readonly bool $saved,
        private readonly array $writtenFiles,
        private readonly array $errors,
        private readonly FirstRunViewModel $view,
    ) {
    }

    public function saved(): bool
    {
        return $this->saved;
    }

    /**
     * @return list<string>
     */
    public function writtenFiles(): array
    {
        return $this->writtenFiles;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function view(): FirstRunViewModel
    {
        return $this->view;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\ConfigDiscovery;
use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Config\MainConfigImporter;
use Totman\RuntimeUi\Config\MainConfigImport;
use Totman\RuntimeUi\Config\MainConfigWriter;
use Totman\RuntimeUi\Config\RecipientConfigImport;
use Totman\RuntimeUi\Config\RecipientConfigImporter;
use Totman\RuntimeUi\Config\RecipientConfigWriter;
use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Preflight\FirstRunPreflight;
use Totman\RuntimeUi\Setup\FirstRunFlow;
use Totman\RuntimeUi\Setup\FirstRunOrchestrator;

final class FirstRunSetupService
{
    public function __construct(
        private readonly ConfigDiscovery $discovery = new ConfigDiscovery(),
        private readonly MainConfigImporter $mainImporter = new MainConfigImporter(),
        private readonly RecipientConfigImporter $recipientImporter = new RecipientConfigImporter(),
        private readonly FirstRunPreflight $preflight = new FirstRunPreflight(),
        private readonly FirstRunOrchestrator $orchestrator = new FirstRunOrchestrator(),
        private readonly FirstRunViewModelBuilder $viewModelBuilder = new FirstRunViewModelBuilder(),
        private readonly FirstRunInputValidator $inputValidator = new FirstRunInputValidator(),
        private readonly MainConfigDraftBuilder $mainConfigDraftBuilder = new MainConfigDraftBuilder(),
        private readonly RecipientConfigDraftBuilder $recipientConfigDraftBuilder = new RecipientConfigDraftBuilder(),
        private readonly FirstRunDraftPreflightGate $draftPreflightGate = new FirstRunDraftPreflightGate(),
        private readonly MainConfigWriter $mainWriter = new MainConfigWriter(),
        private readonly RecipientConfigWriter $recipientWriter = new RecipientConfigWriter(),
        private readonly RuntimeUiTextCatalog $text = new RuntimeUiTextCatalog(),
    ) {
    }

    public function preview(
        string $stateDir,
        DeploymentContext $context,
        string $notice = '',
        array $errors = [],
        ?FirstRunDraftState $draft = null
    ): FirstRunViewModel {
        $discovered = $this->discovery->discover($stateDir);
        [$sourceMain] = $this->imports($stateDir, $context, $discovered, null);
        [$effectiveMain, $effectiveRecipients] = $this->imports($stateDir, $context, $discovered, $draft);
        $preflight = $this->preflight->check($effectiveMain, $effectiveRecipients, $context);
        $flow = $this->orchestrator->evaluate($discovered, $effectiveMain, $effectiveRecipients, $preflight);

        return $this->viewModelBuilder->build(
            $stateDir,
            $context,
            $discovered,
            $sourceMain,
            $preflight,
            $flow,
            $notice,
            $errors,
            $draft
        );
    }

    public function save(string $stateDir, DeploymentContext $context, FirstRunInput $input): FirstRunSaveResult
    {
        return $this->saveRuntime($stateDir, $context, FirstRunDraftState::fromInput($input));
    }

    public function discover(string $stateDir): DiscoveryResult
    {
        return $this->discovery->discover($stateDir);
    }

    public function flow(string $stateDir, DeploymentContext $context, ?FirstRunDraftState $draft = null): FirstRunFlow
    {
        $discovered = $this->discovery->discover($stateDir);
        [$main, $recipients] = $this->imports($stateDir, $context, $discovered, $draft);
        $preflight = $this->preflight->check($main, $recipients, $context);

        return $this->orchestrator->evaluate($discovered, $main, $recipients, $preflight);
    }

    public function saveRuntime(string $stateDir, DeploymentContext $context, FirstRunDraftState $draft): FirstRunSaveResult
    {
        $discovered = $this->discovery->discover($stateDir);
        $input = $draft->toInput();
        $errors = $this->inputValidator->validate($discovered, $input);
        if ($errors !== []) {
            return new FirstRunSaveResult(false, [], $errors, $this->preview($stateDir, $context, '', $errors, $draft));
        }

        $draftPreflight = $this->draftPreflightGate->evaluate($stateDir, $context, $discovered, $draft);
        if (!$draftPreflight->canWriteRuntime()) {
            $errors = ['Draft preflight must be resolved before writing runtime files.'];
            return new FirstRunSaveResult(false, [], $errors, $this->preview($stateDir, $context, '', $errors, $draft));
        }

        if (!is_dir($stateDir . '/downloads')) {
            mkdir($stateDir . '/downloads', 0700, true);
        }

        $written = [];
        $mainConfig = $draftPreflight->mainConfig();
        $recipientConfig = $draftPreflight->recipientConfig();
        $written[] = $this->mainWriter->write($stateDir, $mainConfig);
        $written[] = $this->recipientWriter->write(
            $stateDir,
            $recipientConfig->files(),
            $recipientConfig->messages(),
            $recipientConfig->recipients(),
            (string)$mainConfig['recipients_file']
        );

        return new FirstRunSaveResult(true, $written, [], $this->preview($stateDir, $context, $this->text->get('notice.config_saved')));
    }

    /**
     * @return array{0: MainConfigImport, 1: RecipientConfigImport}
     */
    private function imports(
        string $stateDir,
        DeploymentContext $context,
        DiscoveryResult $discovered,
        ?FirstRunDraftState $draft
    ): array {
        if ($draft === null) {
            return [
                $this->mainImporter->import($discovered, $context),
                $this->recipientImporter->import($discovered),
            ];
        }

        $input = $draft->toInput();
        $mainConfig = $this->mainConfigDraftBuilder->build($stateDir, $context, $input, $discovered);
        $recipientConfig = $this->recipientConfigDraftBuilder->build($input, $discovered);

        return [
            $this->mainImporter->importConfig($mainConfig, $context),
            $this->recipientImporter->importConfig(
                $recipientConfig->files(),
                $recipientConfig->messages(),
                $recipientConfig->recipients()
            ),
        ];
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunStep
{
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $status,
        private readonly bool $current,
        private readonly bool $complete,
        private readonly bool $blocked,
        private readonly string $description = '',
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function current(): bool
    {
        return $this->current;
    }

    public function complete(): bool
    {
        return $this->complete;
    }

    public function blocked(): bool
    {
        return $this->blocked;
    }

    public function description(): string
    {
        return $this->description;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Setup\FirstRunStepCatalog;

final class FirstRunStepValidator
{
    /**
     * @return list<string>
     */
    public function validate(DiscoveryResult $discovered, FirstRunDraftState $draft, string $step): array
    {
        $input = $draft->toInput();

        return match ($step) {
            FirstRunStepCatalog::PUBLIC_URL => $this->requiredWhenFresh($discovered, [
                'base_url' => $input->publicUrl(),
            ]),
            FirstRunStepCatalog::MAIL_DELIVERY => $this->requiredWhenFresh($discovered, [
                'mail_from' => $input->mailFrom(),
                'sendmail_path' => $input->sendmailPath(),
            ]),
            FirstRunStepCatalog::OPERATOR_MAILBOX => $this->requiredWhenFresh($discovered, [
                'to_self' => $input->operatorMailbox(),
            ]),
            FirstRunStepCatalog::FIRST_RECIPIENT => $this->recipientErrors($discovered, $input),
            FirstRunStepCatalog::FIRST_MESSAGE => $this->messageErrors($discovered, $input),
            FirstRunStepCatalog::OPTIONAL_DOWNLOAD => $this->downloadErrors($input),
            FirstRunStepCatalog::REVIEW, FirstRunStepCatalog::PREFLIGHT, FirstRunStepCatalog::SAVE => $this->reviewErrors($discovered, $input),
            default => [],
        };
    }

    /**
     * @param array<string, string> $fields
     * @return list<string>
     */
    private function requiredWhenFresh(DiscoveryResult $discovered, array $fields): array
    {
        if ($discovered->mode() !== 'fresh') {
            return [];
        }

        return $this->requireFields($fields, ' is required before this setup step can continue.');
    }

    /**
     * @return list<string>
     */
    private function recipientErrors(DiscoveryResult $discovered, FirstRunInput $input): array
    {
        if ($discovered->mode() === 'fresh' || $input->recipientName() !== '' || $input->recipientMailbox() !== '') {
            return $this->requireFields([
                'recipient_name' => $input->recipientName(),
                'recipient_mailbox' => $input->recipientMailbox(),
            ], ' is required before the recipient step can continue.');
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function messageErrors(DiscoveryResult $discovered, FirstRunInput $input): array
    {
        if ($discovered->mode() === 'fresh' || $input->messageSubject() !== '' || $input->messageBody() !== '') {
            return $this->requireFields([
                'message_subject' => $input->messageSubject(),
                'message_body' => $input->messageBody(),
            ], ' is required before the message step can continue.');
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function downloadErrors(FirstRunInput $input): array
    {
        if (($input->downloadAlias() === '') !== ($input->downloadPath() === '')) {
            return ['download_alias and download_path must be supplied together before the download step can continue.'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function reviewErrors(DiscoveryResult $discovered, FirstRunInput $input): array
    {
        $errors = [];
        if ($discovered->mode() === 'fresh') {
            $errors = array_merge($errors, $this->requireFields([
                'base_url' => $input->publicUrl(),
                'mail_from' => $input->mailFrom(),
                'to_self' => $input->operatorMailbox(),
                'sendmail_path' => $input->sendmailPath(),
                'recipient_name' => $input->recipientName(),
                'recipient_mailbox' => $input->recipientMailbox(),
                'message_subject' => $input->messageSubject(),
                'message_body' => $input->messageBody(),
            ], ' is required before runtime files can be written.'));
        }

        return array_merge($errors, $this->downloadErrors($input));
    }

    /**
     * @param array<string, string> $fields
     * @return list<string>
     */
    private function requireFields(array $fields, string $suffix): array
    {
        $errors = [];
        foreach ($fields as $key => $value) {
            if ($value === '') {
                $errors[] = $key . $suffix;
            }
        }

        return $errors;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Preflight\PreflightCheck;

final class FirstRunViewModel
{
    /**
     * @param array<string, string> $fields
     * @param array<string, bool> $fieldReadOnly
     * @param list<FirstRunField> $fieldModels
     * @param list<FirstRunStep> $steps
     * @param list<FirstRunAction> $actions
     * @param list<FirstRunField> $currentStepFields
     * @param list<FirstRunField> $reviewFields
     * @param list<PreflightCheck> $preflightChecks
     * @param list<string> $errors
     */
    public function __construct(
        private readonly string $stateDir,
        private readonly string $mode,
        private readonly string $currentStep,
        private readonly string $preflightStatus,
        private readonly bool $pathFieldsReadOnly,
        private readonly string $notice,
        private readonly array $fields,
        private readonly array $fieldReadOnly,
        private readonly array $fieldModels,
        private readonly array $steps,
        private readonly array $actions,
        private readonly array $currentStepFields,
        private readonly array $reviewFields,
        private readonly array $preflightChecks,
        private readonly array $errors = [],
    ) {
    }

    public function stateDir(): string
    {
        return $this->stateDir;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function currentStep(): string
    {
        return $this->currentStep;
    }

    public function preflightStatus(): string
    {
        return $this->preflightStatus;
    }

    public function pathFieldsReadOnly(): bool
    {
        return $this->pathFieldsReadOnly;
    }

    public function notice(): string
    {
        return $this->notice;
    }

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $key): string
    {
        return $this->fields[$key] ?? '';
    }

    public function fieldReadOnly(string $key): bool
    {
        return $this->fieldReadOnly[$key] ?? false;
    }

    /**
     * @return list<FirstRunField>
     */
    public function fieldModels(): array
    {
        return $this->fieldModels;
    }

    /**
     * @return list<FirstRunStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return list<FirstRunAction>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * @return list<FirstRunField>
     */
    public function currentStepFields(): array
    {
        return $this->currentStepFields;
    }

    /**
     * @return list<FirstRunField>
     */
    public function reviewFields(): array
    {
        return $this->reviewFields;
    }

    /**
     * @return list<PreflightCheck>
     */
    public function preflightChecks(): array
    {
        return $this->preflightChecks;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Config\MainConfigImport;
use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Preflight\PreflightResult;
use Totman\RuntimeUi\Setup\FirstRunFlow;
use Totman\RuntimeUi\Setup\FirstRunStepCatalog;

final class FirstRunViewModelBuilder
{
    public function __construct(
        private readonly FirstRunStepCatalog $stepCatalog = new FirstRunStepCatalog(),
        private readonly RuntimeUiTextCatalog $text = new RuntimeUiTextCatalog(),
    ) {
    }

    /**
     * @param list<string> $errors
     */
    public function build(
        string $stateDir,
        DeploymentContext $context,
        DiscoveryResult $discovered,
        MainConfigImport $main,
        PreflightResult $preflight,
        FirstRunFlow $flow,
        string $notice = '',
        array $errors = [],
        ?FirstRunDraftState $draft = null,
    ): FirstRunViewModel {
        $fields = [
            'base_url' => $this->draftString($draft, 'base_url', (string)($main->field('base_url')->value() ?? '')),
            'mail_from' => $this->draftString($draft, 'mail_from', (string)($main->field('mail_from')->value() ?? '')),
            'to_self' => $this->draftString($draft, 'to_self', $this->firstStringValue($main->field('to_self')->value())),
            'sendmail_path' => $this->draftString($draft, 'sendmail_path', (string)($main->field('sendmail_path')->value() ?? '')),
            'recipient_name' => $this->draftString($draft, 'recipient_name', $this->firstRecipientValue($discovered, 0)),
            'recipient_mailbox' => $this->draftString($draft, 'recipient_mailbox', $this->firstRecipientValue($discovered, 1)),
            'message_subject' => $this->draftString($draft, 'message_subject', $this->firstMessageValue($discovered, 'subject', '[totman] Message')),
            'message_body' => $this->draftString($draft, 'message_body', $this->firstMessageValue($discovered, 'body', 'Hello {RECIPIENT_NAME}')),
            'web_ui_enabled' => $this->draftBool($draft, 'web_ui_enabled', $main->field('web_ui_enabled')->value() === true) ? '1' : '',
            'download_alias' => $this->draftString($draft, 'download_alias', ''),
            'download_path' => $this->draftString($draft, 'download_path', ''),
            'download_single_use' => $this->draftBool($draft, 'download_single_use', false) ? '1' : '',
        ];
        $readOnly = [
            'state_dir' => $context->pathFieldsAreReadOnly(),
            'download_base_dir' => $context->pathFieldsAreReadOnly(),
        ];
        $currentStep = $this->currentStep($flow, $draft);
        $fieldModels = $this->fieldModels($fields, $readOnly, $main, $draft, $errors, $discovered);
        $reviewFields = $fieldModels;

        return new FirstRunViewModel(
            $stateDir,
            $flow->mode(),
            $currentStep,
            $flow->preflightStatus(),
            $context->pathFieldsAreReadOnly(),
            $notice,
            $fields,
            $readOnly,
            $fieldModels,
            $this->steps($flow, $currentStep),
            $this->actions($currentStep, $flow->preflightStatus()),
            $this->currentStepFields($fieldModels, $currentStep, $reviewFields),
            $reviewFields,
            $preflight->checks(),
            $errors
        );
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, bool> $readOnly
     * @param list<string> $errors
     * @return list<FirstRunField>
     */
    private function fieldModels(
        array $fields,
        array $readOnly,
        MainConfigImport $main,
        ?FirstRunDraftState $draft,
        array $errors,
        DiscoveryResult $discovered
    ): array {
        return [
            $this->field('base_url', 'public-url', $fields, true, false, $this->source($main, $draft, 'base_url'), $errors),
            $this->field('mail_from', 'mail-delivery', $fields, true, false, $this->source($main, $draft, 'mail_from'), $errors),
            $this->field('sendmail_path', 'mail-delivery', $fields, true, false, $this->source($main, $draft, 'sendmail_path'), $errors),
            $this->field('to_self', 'operator-mailbox', $fields, true, false, $this->source($main, $draft, 'to_self'), $errors),
            $this->field('recipient_name', 'first-recipient', $fields, true, false, $this->recipientSource($discovered, $draft, 'recipient_name'), $errors),
            $this->field('recipient_mailbox', 'first-recipient', $fields, true, false, $this->recipientSource($discovered, $draft, 'recipient_mailbox'), $errors),
            $this->field('message_subject', 'first-message', $fields, true, false, $this->recipientSource($discovered, $draft, 'message_subject'), $errors),
            $this->field('message_body', 'first-message', $fields, true, false, $this->recipientSource($discovered, $draft, 'message_body'), $errors, 'textarea'),
            $this->field('web_ui_enabled', 'review', $fields, false, false, $this->source($main, $draft, 'web_ui_enabled'), $errors, 'checkbox'),
            $this->field('download_alias', 'optional-download', $fields, false, false, $this->recipientSource($discovered, $draft, 'download_alias'), $errors),
            $this->field('download_path', 'optional-download', $fields, false, (bool)($readOnly['download_base_dir'] ?? false), $this->recipientSource($discovered, $draft, 'download_path'), $errors),
            $this->field('download_single_use', 'optional-download', $fields, false, false, $this->recipientSource($discovered, $draft, 'download_single_use'), $errors, 'checkbox'),
        ];
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $errors
     */
    private function field(string $key, string $step, array $fields, bool $required, bool $readOnly, string $source, array $errors, string $control = 'text'): FirstRunField
    {
        return new FirstRunField(
            $key,
            $this->text->get('field.' . $key . '.label'),
            $step,
            $fields[$key] ?? '',
            $required,
            $readOnly,
            $source,
            $this->fieldErrors($key, $errors),
            $control,
            $this->text->get('field.' . $key . '.hint')
        );
    }

    /**
     * @param list<string> $errors
     * @return list<string>
     */
    private function fieldErrors(string $key, array $errors): array
    {
        $matched = [];
        foreach ($errors as $error) {
            if (str_starts_with($error, $key . ' ') || str_starts_with($error, $key . '_')) {
                $matched[] = $error;
            }
        }

        return $matched;
    }

    /**
     * @return list<FirstRunStep>
     */
    private function steps(FirstRunFlow $flow, string $currentStep): array
    {
        $steps = [];
        $currentIndex = array_search($currentStep, $flow->steps(), true);
        $currentIndex = is_int($currentIndex) ? $currentIndex : 0;

        foreach ($flow->steps() as $index => $key) {
            $current = $key === $currentStep;
            $blocked = !$flow->canSave() && $key === 'save';
            $complete = $index < $currentIndex;
            $status = $blocked ? 'blocked' : ($current ? 'current' : ($complete ? 'complete' : 'pending'));
            $steps[] = new FirstRunStep($key, $this->stepTitle($key), $status, $current, $complete, $blocked, $this->stepDescription($key));
        }

        return $steps;
    }

    private function currentStep(FirstRunFlow $flow, ?FirstRunDraftState $draft): string
    {
        if ($draft !== null && $draft->dirty() && in_array($draft->activeStep(), $flow->steps(), true)) {
            return $draft->activeStep();
        }

        return $flow->currentStep();
    }

    /**
     * @return list<FirstRunAction>
     */
    private function actions(string $currentStep, string $preflightStatus): array
    {
        return [
            new FirstRunAction('update_draft', $this->text->get('action.update_draft'), $currentStep !== FirstRunStepCatalog::COMPLETE),
            new FirstRunAction('discard_draft', $this->text->get('action.discard_draft'), $currentStep !== FirstRunStepCatalog::COMPLETE),
            new FirstRunAction('previous_step', $this->text->get('action.previous_step'), !in_array($currentStep, [FirstRunStepCatalog::DISCOVER, FirstRunStepCatalog::COMPLETE], true)),
            new FirstRunAction('next_step', $this->text->get('action.next_step'), !in_array($currentStep, [FirstRunStepCatalog::SAVE, FirstRunStepCatalog::COMPLETE], true)),
            new FirstRunAction('save_runtime', $this->text->get('action.save_runtime'), $currentStep === FirstRunStepCatalog::SAVE, $preflightStatus === 'FAIL'),
        ];
    }

    /**
     * @param list<FirstRunField> $fieldModels
     * @param list<FirstRunField> $reviewFields
     * @return list<FirstRunField>
     */
    private function currentStepFields(array $fieldModels, string $currentStep, array $reviewFields): array
    {
        if ($currentStep === FirstRunStepCatalog::REVIEW) {
            return $reviewFields;
        }

        if (in_array($currentStep, [FirstRunStepCatalog::PREFLIGHT, FirstRunStepCatalog::SAVE, FirstRunStepCatalog::COMPLETE], true)) {
            return [];
        }

        $fields = [];
        foreach ($fieldModels as $field) {
            if ($field->step() === $currentStep) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function stepTitle(string $key): string
    {
        return $this->stepCatalog->title($key);
    }

    private function stepDescription(string $key): string
    {
        return $this->text->get('step.' . $key . '.description');
    }

    private function source(MainConfigImport $main, ?FirstRunDraftState $draft, string $key): string
    {
        if ($draft !== null && $draft->has($key)) {
            return 'draft';
        }

        return $main->field($key)->source();
    }

    private function recipientSource(DiscoveryResult $discovered, ?FirstRunDraftState $draft, string $key): string
    {
        if ($draft !== null && $draft->has($key)) {
            return 'draft';
        }

        if ($discovered->liveRecipientConfig() !== []) {
            return 'live';
        }

        if ($discovered->distRecipientConfig() !== []) {
            return 'dist';
        }

        return 'generated';
    }

    private function draftString(?FirstRunDraftState $draft, string $key, string $fallback): string
    {
        if ($draft !== null && $draft->has($key)) {
            return $draft->string($key);
        }

        return $fallback;
    }

    private function draftBool(?FirstRunDraftState $draft, string $key, bool $fallback): bool
    {
        if ($draft !== null && $draft->has($key)) {
            return $draft->bool($key);
        }

        return $fallback;
    }

    private function firstStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item)) {
                    return $item;
                }
            }
        }

        return '';
    }

    private function firstRecipientValue(DiscoveryResult $discovered, int $index): string
    {
        $rows = $discovered->liveRecipientConfig()['recipients'] ?? $discovered->distRecipientConfig()['recipients'] ?? [];
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0]) || !isset($rows[0][$index]) || !is_string($rows[0][$index])) {
            return '';
        }

        return $rows[0][$index];
    }

    private function firstMessageValue(DiscoveryResult $discovered, string $key, string $default): string
    {
        $messages = $discovered->liveRecipientConfig()['messages'] ?? $discovered->distRecipientConfig()['messages'] ?? [];
        if (!is_array($messages)) {
            return $default;
        }

        $first = reset($messages);
        if (!is_array($first) || !isset($first[$key]) || !is_string($first[$key])) {
            return $default;
        }

        return $first[$key];
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Setup\FirstRunStepCatalog;

final class FirstRunWizardApplicationService
{
    public function __construct(
        private readonly FirstRunSetupService $setupService = new FirstRunSetupService(),
        private readonly FirstRunStepValidator $stepValidator = new FirstRunStepValidator(),
        private readonly FirstRunWizardNavigator $navigator = new FirstRunWizardNavigator(),
        private readonly RuntimeUiTextCatalog $text = new RuntimeUiTextCatalog(),
    ) {
    }

    public function handle(
        string $stateDir,
        DeploymentContext $context,
        FirstRunWizardCommand $command,
        FirstRunDraftState $draft
    ): FirstRunWizardResult {
        if ($command->updatesDraft()) {
            $updatedDraft = FirstRunDraftState::fromInput($command->input(), $draft->activeStep());
            $errors = $this->stepValidator->validate(
                $this->setupService->discover($stateDir),
                $updatedDraft,
                $updatedDraft->activeStep()
            );

            return new FirstRunWizardResult(
                $this->setupService->preview($stateDir, $context, $this->text->get('notice.draft_saved'), $errors, $updatedDraft),
                $updatedDraft,
                draftChanged: true
            );
        }

        if ($command->movesNext()) {
            $updatedDraft = FirstRunDraftState::fromInput($command->input(), $draft->activeStep());
            $discovered = $this->setupService->discover($stateDir);
            $navigation = $this->navigator->next($discovered, $this->setupService->flow($stateDir, $context, $updatedDraft), $updatedDraft);

            return new FirstRunWizardResult(
                $this->setupService->preview($stateDir, $context, $navigation->moved() ? 'Moved to the next step.' : '', $navigation->errors(), $navigation->draft()),
                $navigation->draft(),
                draftChanged: true
            );
        }

        if ($command->movesPrevious()) {
            $updatedDraft = FirstRunDraftState::fromInput($command->input(), $draft->activeStep());
            $navigation = $this->navigator->previous($this->setupService->flow($stateDir, $context, $updatedDraft), $updatedDraft);

            return new FirstRunWizardResult(
                $this->setupService->preview($stateDir, $context, 'Moved to the previous step.', draft: $navigation->draft()),
                $navigation->draft(),
                draftChanged: true
            );
        }

        if ($command->savesRuntime()) {
            $flow = $this->setupService->flow($stateDir, $context, $draft);
            $activeDraft = $this->navigator->normalise($draft, $flow);
            if ($activeDraft->activeStep() !== FirstRunStepCatalog::SAVE) {
                $errors = ['Runtime files can only be written from the save step.'];
                return new FirstRunWizardResult(
                    $this->setupService->preview($stateDir, $context, '', $errors, $activeDraft),
                    $activeDraft,
                    runtimeSaveAttempted: true
                );
            }

            $save = $this->setupService->saveRuntime($stateDir, $context, $draft);
            if (!$save->saved() && in_array('Draft preflight must be resolved before writing runtime files.', $save->errors(), true)) {
                $preflightDraft = $activeDraft->withActiveStep(FirstRunStepCatalog::PREFLIGHT);

                return new FirstRunWizardResult(
                    $this->setupService->preview($stateDir, $context, '', $save->errors(), $preflightDraft),
                    $preflightDraft,
                    draftChanged: true,
                    runtimeSaveAttempted: true
                );
            }

            $completeDraft = $save->saved() ? $activeDraft->withActiveStep(FirstRunStepCatalog::COMPLETE) : $activeDraft;

            return new FirstRunWizardResult(
                $save->saved()
                    ? $this->setupService->preview($stateDir, $context, $this->text->get('notice.config_saved'), draft: $completeDraft)
                    : $save->view(),
                $completeDraft,
                draftChanged: $save->saved(),
                runtimeSaveAttempted: true,
                runtimeSaved: $save->saved()
            );
        }

        return new FirstRunWizardResult(
            $this->setupService->preview($stateDir, $context, draft: $draft),
            $draft
        );
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunWizardCommand
{
    public const PREVIEW = 'preview';
    public const UPDATE_DRAFT = 'update_draft';
    public const NEXT_STEP = 'next_step';
    public const PREVIOUS_STEP = 'previous_step';
    public const SAVE_RUNTIME = 'save_runtime';

    private function __construct(
        private readonly string $intent,
        private readonly FirstRunInput $input,
    ) {
    }

    public static function preview(): self
    {
        return new self(self::PREVIEW, new FirstRunInput());
    }

    public static function updateDraft(FirstRunInput $input): self
    {
        return new self(self::UPDATE_DRAFT, $input);
    }

    public static function nextStep(FirstRunInput $input): self
    {
        return new self(self::NEXT_STEP, $input);
    }

    public static function previousStep(FirstRunInput $input): self
    {
        return new self(self::PREVIOUS_STEP, $input);
    }

    public static function saveRuntime(): self
    {
        return new self(self::SAVE_RUNTIME, new FirstRunInput());
    }

    public function intent(): string
    {
        return $this->intent;
    }

    public function input(): FirstRunInput
    {
        return $this->input;
    }

    public function updatesDraft(): bool
    {
        return $this->intent === self::UPDATE_DRAFT;
    }

    public function movesNext(): bool
    {
        return $this->intent === self::NEXT_STEP;
    }

    public function movesPrevious(): bool
    {
        return $this->intent === self::PREVIOUS_STEP;
    }

    public function savesRuntime(): bool
    {
        return $this->intent === self::SAVE_RUNTIME;
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunWizardNavigationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        private readonly FirstRunDraftState $draft,
        private readonly array $errors = [],
    ) {
    }

    public function draft(): FirstRunDraftState
    {
        return $this->draft;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function moved(): bool
    {
        return $this->errors === [];
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Setup\FirstRunFlow;

final class FirstRunWizardNavigator
{
    public function __construct(private readonly FirstRunStepValidator $stepValidator = new FirstRunStepValidator())
    {
    }

    public function normalise(FirstRunDraftState $draft, FirstRunFlow $flow): FirstRunDraftState
    {
        return $draft->withActiveStep($this->currentStep($draft, $flow), $draft->updatedAt());
    }

    public function next(DiscoveryResult $discovered, FirstRunFlow $flow, FirstRunDraftState $draft): FirstRunWizardNavigationResult
    {
        $current = $this->currentStep($draft, $flow);
        $normalised = $draft->withActiveStep($current, $draft->updatedAt());
        $errors = $this->stepValidator->validate($discovered, $normalised, $current);
        if ($errors !== []) {
            return new FirstRunWizardNavigationResult($normalised, $errors);
        }

        return new FirstRunWizardNavigationResult($normalised->withActiveStep($this->offsetStep($flow, $current, 1)));
    }

    public function previous(FirstRunFlow $flow, FirstRunDraftState $draft): FirstRunWizardNavigationResult
    {
        $current = $this->currentStep($draft, $flow);
        $normalised = $draft->withActiveStep($current, $draft->updatedAt());

        return new FirstRunWizardNavigationResult($normalised->withActiveStep($this->offsetStep($flow, $current, -1)));
    }

    private function currentStep(FirstRunDraftState $draft, FirstRunFlow $flow): string
    {
        if ($draft->dirty() && in_array($draft->activeStep(), $flow->steps(), true)) {
            return $draft->activeStep();
        }

        return $flow->currentStep();
    }

    private function offsetStep(FirstRunFlow $flow, string $current, int $offset): string
    {
        $steps = $flow->steps();
        $index = array_search($current, $steps, true);
        $index = is_int($index) ? $index : 0;
        $nextIndex = max(0, min(count($steps) - 1, $index + $offset));

        return $steps[$nextIndex];
    }
}

namespace Totman\RuntimeUi\Application;

final class FirstRunWizardResult
{
    public function __construct(
        private readonly FirstRunViewModel $view,
        private readonly FirstRunDraftState $draft,
        private readonly bool $draftChanged = false,
        private readonly bool $runtimeSaveAttempted = false,
        private readonly bool $runtimeSaved = false,
    ) {
    }

    public function view(): FirstRunViewModel
    {
        return $this->view;
    }

    public function draft(): FirstRunDraftState
    {
        return $this->draft;
    }

    public function draftChanged(): bool
    {
        return $this->draftChanged;
    }

    public function runtimeSaveAttempted(): bool
    {
        return $this->runtimeSaveAttempted;
    }

    public function runtimeSaved(): bool
    {
        return $this->runtimeSaved;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Config\MainConfigImporter;
use Totman\RuntimeUi\Contracts\RuntimeFileNames;
use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Security\HmacSecretGenerator;

final class MainConfigDraftBuilder
{
    public function __construct(
        private readonly MainConfigImporter $mainImporter = new MainConfigImporter(),
        private readonly HmacSecretGenerator $hmacSecretGenerator = new HmacSecretGenerator(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $stateDir, DeploymentContext $context, FirstRunInput $input, DiscoveryResult $discovered): array
    {
        $current = $discovered->effectiveMainConfig();
        $mainImport = $this->mainImporter->import($discovered, $context);
        $hmac = $mainImport->field('hmac_secret_hex');

        return [
            'state_dir' => $this->value('', $current, 'state_dir', $context->fixedStateDir() ?? $stateDir),
            'lib_file' => $this->value('', $current, 'lib_file', 'totman-lib.php'),
            'l18n_dir_name' => $this->value('', $current, 'l18n_dir_name', 'l18n'),
            'lock_file' => $this->value('', $current, 'lock_file', 'totman.lock'),
            'log_file_name' => $this->value('', $current, 'log_file_name', 'totman.log'),
            'recipients_file' => $this->value('', $current, 'recipients_file', RuntimeFileNames::RECIPIENTS_LIVE),
            'state_file' => $this->value('', $current, 'state_file', 'totman.json'),
            'web_file' => $this->value('', $current, 'web_file', 'totman.php'),
            'web_css_file' => $this->value('', $current, 'web_css_file', 'totman.css'),
            'download_base_dir' => $this->value('', $current, 'download_base_dir', $context->fixedDownloadDir() ?? $stateDir . '/downloads'),
            'download_valid_days' => $this->value('', $current, 'download_valid_days', 180),
            'download_rate_limit_enabled' => $this->value('', $current, 'download_rate_limit_enabled', true),
            'download_rate_limit_max_requests' => $this->value('', $current, 'download_rate_limit_max_requests', 20),
            'download_rate_limit_window_seconds' => $this->value('', $current, 'download_rate_limit_window_seconds', 60),
            'download_lease_seconds' => $this->value('', $current, 'download_lease_seconds', 300),
            'base_url' => $this->value($input->publicUrl(), $current, 'base_url', ''),
            'hmac_secret_hex' => (!$hmac->invalid() && !$hmac->placeholder() && is_string($hmac->value())) ? $hmac->value() : $this->hmacSecretGenerator->generateHex(),
            'web_ui_enabled' => $input->webUiEnabled() ?? (array_key_exists('web_ui_enabled', $current) ? (bool)$current['web_ui_enabled'] : true),
            'check_interval_seconds' => $this->value('', $current, 'check_interval_seconds', 86400),
            'confirm_window_seconds' => $this->value('', $current, 'confirm_window_seconds', 7200),
            'remind_every_seconds' => $this->value('', $current, 'remind_every_seconds', 1800),
            'escalate_grace_seconds' => $this->value('', $current, 'escalate_grace_seconds', 300),
            'missed_cycles_before_fire' => $this->value('', $current, 'missed_cycles_before_fire', 1),
            'escalate_ack_enabled' => $this->value('', $current, 'escalate_ack_enabled', true),
            'escalate_ack_remind_every_seconds' => $this->value('', $current, 'escalate_ack_remind_every_seconds', 3600),
            'escalate_ack_max_reminds' => $this->value('', $current, 'escalate_ack_max_reminds', 3),
            'stealth_neutral_for_invalid' => $this->value('', $current, 'stealth_neutral_for_invalid', true),
            'stealth_level_2_neutral_on_stale' => $this->value('', $current, 'stealth_level_2_neutral_on_stale', true),
            'show_success_details' => $this->value('', $current, 'show_success_details', true),
            'rate_limit_enabled' => $this->value('', $current, 'rate_limit_enabled', true),
            'rate_limit_dir' => $this->value('', $current, 'rate_limit_dir', null),
            'rate_limit_max_requests' => $this->value('', $current, 'rate_limit_max_requests', 30),
            'rate_limit_window_seconds' => $this->value('', $current, 'rate_limit_window_seconds', 60),
            'ip_mode' => $this->value('', $current, 'ip_mode', 'remote_addr'),
            'trusted_proxies' => $this->value('', $current, 'trusted_proxies', ['127.0.0.1', '::1']),
            'trusted_proxy_header' => $this->value('', $current, 'trusted_proxy_header', 'X-Forwarded-For'),
            'sendmail_path' => $this->value($input->sendmailPath(), $current, 'sendmail_path', ''),
            'to_self' => $this->mailboxListValue($input->operatorMailbox(), $current, 'to_self'),
            'operator_alert_interval_hours' => $this->value('', $current, 'operator_alert_interval_hours', 2),
            'mail_from' => $this->value($input->mailFrom(), $current, 'mail_from', ''),
            'reply_to' => $this->value('', $current, 'reply_to', ''),
            'subject_reminder' => $this->value('', $current, 'subject_reminder', '[totman] Please confirm you are safe'),
            'mail_timezone' => $this->value('', $current, 'mail_timezone', 'Europe/London'),
            'mail_date_format' => $this->value('', $current, 'mail_date_format', 'j F Y'),
            'mail_time_format' => $this->value('', $current, 'mail_time_format', 'H:i:s'),
            'mail_datetime_format' => $this->value('', $current, 'mail_datetime_format', 'l, j F Y, H:i:s e'),
            'body_reminder' => $this->value('', $current, 'body_reminder', "Hello,\n\nThis is a reminder to confirm that you are safe and able to respond.\n\nPlease click this link to confirm:\n{CONFIRM_URL}\n\nYou must click the confirmation link no later than this deadline.\nConfirmation deadline: {DEADLINE_ISO}\n"),
            'log_mode' => $this->value('', $current, 'log_mode', 'both'),
            'log_file' => $this->value('', $current, 'log_file', null),
        ];
    }

    /**
     * @param array<string, mixed> $current
     */
    private function value(string $inputValue, array $current, string $key, mixed $default): mixed
    {
        if ($inputValue !== '') {
            return $inputValue;
        }

        if (!array_key_exists($key, $current)) {
            return $default;
        }

        $value = $current[$key];
        if (is_array($value)) {
            return $this->firstStringValue($value) !== '' ? $value : $default;
        }

        return $value === '' ? $default : $value;
    }

    /**
     * @param array<string, mixed> $current
     * @return list<string>
     */
    private function mailboxListValue(string $inputValue, array $current, string $key): array
    {
        if ($inputValue !== '') {
            return [$inputValue];
        }

        $value = $current[$key] ?? [];
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $mailboxes = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $mailboxes[] = $item;
            }
        }

        return $mailboxes;
    }

    private function firstStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item)) {
                    return $item;
                }
            }
        }

        return '';
    }
}

namespace Totman\RuntimeUi\Application;

final class MaintenanceCommandResult
{
    public const PREVIEW = 'preview';
    public const EXECUTE = 'execute';
    public const BLOCKED = 'blocked';
    public const PREVIEWED = 'previewed';
    public const EXECUTED = 'executed';
    public const ROLLED_BACK = 'rolled_back';
    public const FAILED = 'failed';

    /**
     * @param list<string> $blockers
     * @param list<string> $plan
     * @param list<string> $effects
     */
    public function __construct(
        private readonly string $commandKey,
        private readonly string $label,
        private readonly string $phase,
        private readonly string $status,
        private readonly array $blockers,
        private readonly array $plan,
        private readonly array $effects = [],
    ) {
    }

    public function commandKey(): string
    {
        return $this->commandKey;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function allowed(): bool
    {
        return $this->blockers === [] && $this->status !== self::BLOCKED;
    }

    /**
     * @return list<string>
     */
    public function blockers(): array
    {
        return $this->blockers;
    }

    /**
     * @return list<string>
     */
    public function plan(): array
    {
        return $this->plan;
    }

    /**
     * @return list<string>
     */
    public function effects(): array
    {
        return $this->effects;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Security\AdminSessionState;

final class MaintenanceCommandService
{
    public function __construct(
        private readonly AdminCommandCatalog $catalog = new AdminCommandCatalog(),
        private readonly AdminCommandAccessPolicy $accessPolicy = new AdminCommandAccessPolicy(),
        private readonly MaintenanceRuntimeMutator $runtimeMutator = new MaintenanceRuntimeMutator(),
    ) {
    }

    public function handle(
        string $commandKey,
        string $phase,
        AdminSessionState $adminSession,
        bool $csrfValid,
        bool $rateLimitAllowed,
        bool $confirmed,
        int $now,
        ?string $targetAlias = null,
        ?string $stateDir = null,
    ): MaintenanceCommandResult {
        $command = $this->catalog->get($commandKey);
        if (!$command instanceof AdminCommand) {
            return new MaintenanceCommandResult($commandKey, 'Unknown command', $phase, MaintenanceCommandResult::BLOCKED, ['Unknown admin command.'], []);
        }

        if (!$command->danger()) {
            return new MaintenanceCommandResult($commandKey, $command->label(), $phase, MaintenanceCommandResult::BLOCKED, ['Command is not a maintenance command.'], []);
        }

        $phase = $phase === MaintenanceCommandResult::EXECUTE ? MaintenanceCommandResult::EXECUTE : MaintenanceCommandResult::PREVIEW;
        $blockers = $this->blockers($commandKey, $adminSession, $csrfValid, $rateLimitAllowed, $now);
        if ($phase === MaintenanceCommandResult::EXECUTE && !$confirmed) {
            $blockers[] = 'Explicit confirmation is required before executing this action.';
        }

        if ($blockers !== []) {
            return new MaintenanceCommandResult($command->key(), $command->label(), $phase, MaintenanceCommandResult::BLOCKED, $blockers, $this->plan($command->key(), $targetAlias));
        }

        if ($phase === MaintenanceCommandResult::PREVIEW) {
            return new MaintenanceCommandResult($command->key(), $command->label(), $phase, MaintenanceCommandResult::PREVIEWED, [], $this->plan($command->key(), $targetAlias));
        }

        if (!in_array($command->key(), $this->executableCommandKeys(), true)) {
            return new MaintenanceCommandResult(
                $command->key(),
                $command->label(),
                $phase,
                MaintenanceCommandResult::BLOCKED,
                ['Execution handler is not implemented yet.'],
                $this->plan($command->key(), $targetAlias)
            );
        }

        if ($stateDir === null || $stateDir === '') {
            return new MaintenanceCommandResult($command->key(), $command->label(), $phase, MaintenanceCommandResult::BLOCKED, ['Runtime state directory is required before executing this action.'], $this->plan($command->key(), $targetAlias));
        }

        try {
            $effects = $this->executeCommand($command->key(), $stateDir, $targetAlias);
        } catch (\RuntimeException $error) {
            $status = str_contains($error->getMessage(), 'restored') ? MaintenanceCommandResult::ROLLED_BACK : MaintenanceCommandResult::FAILED;

            return new MaintenanceCommandResult($command->key(), $command->label(), $phase, $status, [$error->getMessage()], $this->plan($command->key(), $targetAlias));
        }

        return new MaintenanceCommandResult($command->key(), $command->label(), $phase, MaintenanceCommandResult::EXECUTED, [], $this->plan($command->key(), $targetAlias), $effects);
    }

    /**
     * @return list<string>
     */
    private function blockers(string $commandKey, AdminSessionState $adminSession, bool $csrfValid, bool $rateLimitAllowed, int $now): array
    {
        $blockers = [];
        $access = $this->accessPolicy->evaluate($commandKey, $adminSession, $now);
        if (!$access->allowed()) {
            $blockers[] = $access->message();
        }

        if (!$csrfValid) {
            $blockers[] = 'Valid CSRF token is required before previewing or executing this action.';
        }

        if (!$rateLimitAllowed) {
            $blockers[] = 'Maintenance-command rate limit is currently exceeded.';
        }

        return $blockers;
    }

    /**
     * @return list<string>
     */
    private function executeCommand(string $commandKey, string $stateDir, ?string $targetAlias): array
    {
        if ($commandKey === AdminCommandCatalog::PREVIEW_HMAC_ROTATION) {
            return $this->runtimeMutator->rotateHmac($stateDir);
        }

        if ($commandKey === AdminCommandCatalog::PREVIEW_RUNTIME_RESET) {
            return $this->runtimeMutator->resetRuntime($stateDir);
        }

        if ($commandKey === AdminCommandCatalog::PREVIEW_LOG_CLEAR) {
            return $this->runtimeMutator->clearActivityLog($stateDir);
        }

        return $this->runtimeMutator->deleteFileAlias($stateDir, (string)$targetAlias);
    }

    /**
     * @return list<string>
     */
    private function executableCommandKeys(): array
    {
        return [
            AdminCommandCatalog::PREVIEW_HMAC_ROTATION,
            AdminCommandCatalog::PREVIEW_RUNTIME_RESET,
            AdminCommandCatalog::PREVIEW_LOG_CLEAR,
            AdminCommandCatalog::PREVIEW_FILE_ALIAS_DELETION,
        ];
    }

    /**
     * @return list<string>
     */
    private function plan(string $commandKey, ?string $targetAlias): array
    {
        return match ($commandKey) {
            AdminCommandCatalog::PREVIEW_HMAC_ROTATION => [
                'A new HMAC secret is generated server-side.',
                'Existing signed confirmation, ACK, and download links stop validating.',
                'Runtime state is reset so the next cycle starts with the new secret.',
            ],
            AdminCommandCatalog::PREVIEW_RUNTIME_RESET => [
                'Runtime state is replaced so scheduling starts from a clean state.',
                'Configuration files and recipient definitions remain untouched.',
                'One-time download leases are cleared.',
            ],
            AdminCommandCatalog::PREVIEW_LOG_CLEAR => [
                'The configured runtime log file is truncated only after the log path is verified as safe.',
                'Configuration files and runtime state remain untouched.',
            ],
            AdminCommandCatalog::PREVIEW_FILE_ALIAS_DELETION => [
                'The selected file alias is removed from recipient configuration.',
                'Recipient references to that alias are removed.',
                'Target alias: ' . ($targetAlias !== null && $targetAlias !== '' ? $targetAlias : '[not selected]') . '.',
            ],
            default => [],
        };
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\ConfigDiscovery;
use Totman\RuntimeUi\Config\MainConfigWriter;
use Totman\RuntimeUi\Config\RecipientConfigWriter;
use Totman\RuntimeUi\Contracts\RuntimeFileNames;
use Totman\RuntimeUi\Security\HmacSecretGenerator;

final class MaintenanceRuntimeMutator
{
    public function __construct(
        private readonly ConfigDiscovery $discovery = new ConfigDiscovery(),
        private readonly MainConfigWriter $mainWriter = new MainConfigWriter(),
        private readonly RecipientConfigWriter $recipientWriter = new RecipientConfigWriter(),
        private readonly HmacSecretGenerator $hmacSecretGenerator = new HmacSecretGenerator(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function rotateHmac(string $stateDir): array
    {
        $discovered = $this->discovery->discover($stateDir);
        if (!$discovered->mainLiveStatus()->loaded()) {
            throw new \RuntimeException('Loaded live main config is required before HMAC rotation.');
        }

        $config = $discovered->effectiveMainConfig();
        $config['hmac_secret_hex'] = $this->hmacSecretGenerator->generateHex();
        $mainPath = $discovered->mainLiveStatus()->path();
        $statePath = $this->statePath($stateDir, $config);
        $mainBackup = $this->backupFile($mainPath);
        $stateBackup = $this->backupFile($statePath);

        try {
            $this->mainWriter->write($stateDir, $config);
            $this->resetRuntimeState($statePath, $this->lockPath($stateDir, $config), $config);
        } catch (\Throwable $error) {
            try {
                $this->restoreFile($mainBackup, $mainPath);
                $this->restoreFile($stateBackup, $statePath);
            } catch (\Throwable $rollbackError) {
                throw new \RuntimeException('HMAC rotation failed and rollback also failed: ' . $rollbackError->getMessage(), 0, $error);
            }

            throw new \RuntimeException('HMAC rotation failed; previous configuration was restored. Original error: ' . $error->getMessage(), 0, $error);
        }

        return [
            'Main config HMAC secret was replaced.',
            'Runtime state was reset with a new confirmation token.',
            'Existing signed confirmation, ACK, and download links no longer match the active secret.',
        ];
    }

    /**
     * @return list<string>
     */
    public function resetRuntime(string $stateDir): array
    {
        $config = $this->liveMainConfig($stateDir);
        $statePath = $this->statePath($stateDir, $config);
        $stateBackup = $this->backupFile($statePath);

        try {
            $this->resetRuntimeState($statePath, $this->lockPath($stateDir, $config), $config);
        } catch (\Throwable $error) {
            try {
                $this->restoreFile($stateBackup, $statePath);
            } catch (\Throwable $rollbackError) {
                throw new \RuntimeException('Runtime reset failed and rollback also failed: ' . $rollbackError->getMessage(), 0, $error);
            }

            throw new \RuntimeException('Runtime reset failed; previous state was restored. Original error: ' . $error->getMessage(), 0, $error);
        }

        return [
            'Runtime state was reset with a new confirmation token.',
            'Escalation progress and one-time download leases were cleared.',
            'Configuration, recipients, logs, and download files were not changed.',
        ];
    }

    /**
     * @return list<string>
     */
    public function clearActivityLog(string $stateDir): array
    {
        $config = $this->liveMainConfig($stateDir);
        $path = $this->logPath($stateDir, $config);
        if (!$this->logPathIsSafe($path, $stateDir)) {
            throw new \RuntimeException('The activity log path is not safe for maintenance here.');
        }

        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Log directory is not writable: ' . $dir);
        }

        if (is_file($path) && !is_writable($path)) {
            throw new \RuntimeException('Log file is not writable: ' . $path);
        }

        $fh = fopen($path, 'c');
        if ($fh === false) {
            throw new \RuntimeException('Could not open log file: ' . $path);
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                throw new \RuntimeException('Could not lock log file: ' . $path);
            }

            if (!ftruncate($fh, 0)) {
                throw new \RuntimeException('Could not clear log file: ' . $path);
            }

            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return [
            'Configured runtime log was cleared.',
            'Configuration, recipients, runtime state, and download files were not changed.',
        ];
    }

    /**
     * @return list<string>
     */
    public function deleteFileAlias(string $stateDir, string $alias): array
    {
        $alias = trim($alias);
        if ($alias === '') {
            throw new \RuntimeException('File alias is required before deletion.');
        }

        $discovered = $this->discovery->discover($stateDir);
        if (!$discovered->recipientLiveStatus()->loaded()) {
            throw new \RuntimeException('Loaded live recipients config is required before file-alias deletion.');
        }

        $data = $discovered->liveRecipientConfig();
        $files = $this->arrayValue($data['files'] ?? []);
        if (!array_key_exists($alias, $files)) {
            throw new \RuntimeException('Unknown file alias: ' . $alias);
        }

        $relativePath = (string)$files[$alias];
        unset($files[$alias]);
        $messages = $this->arrayValue($data['messages'] ?? []);
        $recipients = $this->removeAliasFromRecipients($this->arrayValue($data['recipients'] ?? []), $alias);
        $recipientPath = $discovered->recipientLiveStatus()->path();
        $recipientBackup = $this->backupFile($recipientPath);

        try {
            $this->recipientWriter->write($stateDir, $files, $messages, $recipients, basename($recipientPath) ?: RuntimeFileNames::RECIPIENTS_LIVE);
        } catch (\Throwable $error) {
            $this->restoreFile($recipientBackup, $recipientPath);
            throw $error;
        }

        $effects = [
            'File alias was removed from recipient configuration.',
            'Recipient download references to the alias were removed.',
        ];

        try {
            $deletePath = $this->safeDownloadDeletePath($this->liveMainConfig($stateDir), $alias, $relativePath);
            if ($deletePath === null) {
                $effects[] = 'Download file was already missing.';
            } elseif (!unlink($deletePath)) {
                $effects[] = 'Download file deletion needs manual review: ' . $relativePath;
            } else {
                $effects[] = 'Download file was deleted.';
            }
        } catch (\RuntimeException $error) {
            $effects[] = 'Download file deletion needs manual review: ' . $error->getMessage();
        }

        return $effects;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resetRuntimeState(string $statePath, string $lockPath, array $config): void
    {
        $secret = (string)($config['hmac_secret_hex'] ?? '');
        if (preg_match('/^[a-f0-9]{32,}$/i', $secret) !== 1 || strlen($secret) % 2 !== 0) {
            throw new \RuntimeException('Valid HMAC secret is required before runtime state reset.');
        }

        $lock = fopen($lockPath, 'c+');
        if ($lock === false) {
            throw new \RuntimeException('Could not open state lock: ' . $lockPath);
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Could not acquire state lock.');
            }

            $now = time();
            $check = max(1, (int)($config['check_interval_seconds'] ?? 86400));
            $window = max(1, (int)($config['confirm_window_seconds'] ?? 7200));
            $state = [
                'runtime' => [
                    'version' => 1,
                    'created_at' => $now,
                    'last_tick_at' => $now,
                    'cycle_start_at' => $now,
                    'last_confirm_at' => 0,
                    'missed_cycles' => 0,
                    'missed_cycle_deadline' => null,
                    'token' => $this->makeToken($secret),
                    'next_check_at' => $now + $check,
                    'deadline_at' => $now + $check + $window,
                    'next_reminder_at' => $now + $check,
                    'escalation_event_at' => null,
                    'operator_alerts' => [],
                    'escalation_delivery' => [],
                    'escalated_sent_at' => null,
                    'escalate_ack_token' => null,
                    'escalate_ack_recipients' => [],
                    'escalate_ack_at' => null,
                    'escalate_ack_sent_count' => 0,
                    'escalate_ack_next_at' => null,
                ],
                'downloads' => [],
            ];

            $this->atomicWrite($statePath, json_encode($state, JSON_THROW_ON_ERROR));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array{id: string, sig: string}
     */
    private function makeToken(string $secretHex): array
    {
        $id = bin2hex(random_bytes(16));
        $secret = hex2bin($secretHex);
        if ($secret === false) {
            throw new \RuntimeException('Invalid HMAC secret.');
        }

        return [
            'id' => $id,
            'sig' => hash_hmac('sha256', $id, $secret),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function liveMainConfig(string $stateDir): array
    {
        $discovered = $this->discovery->discover($stateDir);
        if (!$discovered->mainLiveStatus()->loaded()) {
            throw new \RuntimeException('Loaded live main config is required before maintenance execution.');
        }

        return $discovered->effectiveMainConfig();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function logPath(string $stateDir, array $config): string
    {
        $configured = $config['log_file'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return rtrim($stateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename((string)($config['log_file_name'] ?? 'totman.log'));
    }

    private function logPathIsSafe(string $path, string $stateDir): bool
    {
        $base = basename($path);
        if ($path === '' || $stateDir === '' || is_dir($path) || str_starts_with($base, '.')) {
            return false;
        }

        if (preg_match('/\.(php|phtml|phar|bak)$/i', $base) === 1) {
            return false;
        }

        if (preg_match('/(secret|password|credential|token|key|backup)/i', $base) === 1) {
            return false;
        }

        return $this->pathIsInsideDir($path, $stateDir);
    }

    private function pathIsInsideDir(string $path, string $dir): bool
    {
        $realDir = realpath($dir);
        $pathDir = realpath(dirname($path));
        if ($realDir === false || $pathDir === false) {
            return false;
        }

        $realDir = rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $pathDir = rtrim($pathDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($pathDir, $realDir);
    }

    /**
     * @return array<mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<mixed> $recipients
     * @return array<mixed>
     */
    private function removeAliasFromRecipients(array $recipients, string $alias): array
    {
        $updated = [];
        foreach ($recipients as $recipient) {
            if (!is_array($recipient)) {
                $updated[] = $recipient;
                continue;
            }

            $base = array_slice($recipient, 0, 3);
            $normal = $this->aliasListWithout($recipient[3] ?? [], $alias);
            $singleUse = $this->aliasListWithout($recipient[4] ?? [], $alias);
            if ($normal !== [] || $singleUse !== []) {
                $base[] = $normal;
            }

            if ($singleUse !== []) {
                $base[] = $singleUse;
            }

            $updated[] = $base;
        }

        return $updated;
    }

    /**
     * @return list<string>
     */
    private function aliasListWithout(mixed $value, string $alias): array
    {
        if (!is_array($value)) {
            return [];
        }

        $filtered = [];
        foreach ($value as $candidate) {
            if (!is_string($candidate) || $candidate === $alias || $candidate === '') {
                continue;
            }

            $filtered[] = $candidate;
        }

        return array_values(array_unique($filtered));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function safeDownloadDeletePath(array $config, string $alias, string $relativePath): ?string
    {
        if (!$this->relativeDownloadPathIsSafe($relativePath)) {
            throw new \RuntimeException('Download file for alias ' . $alias . ' is not safe to delete.');
        }

        $base = realpath(rtrim((string)($config['download_base_dir'] ?? ''), DIRECTORY_SEPARATOR));
        if ($base === false || !is_dir($base) || !is_readable($base)) {
            throw new \RuntimeException('Download file for alias ' . $alias . ' is not safe to delete.');
        }

        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $candidate = $base . DIRECTORY_SEPARATOR . ltrim(str_replace('\\', '/', $relativePath), DIRECTORY_SEPARATOR);
        if (is_link($candidate) || is_dir($candidate)) {
            throw new \RuntimeException('Download file for alias ' . $alias . ' is not safe to delete.');
        }

        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }

        if (!is_file($real) || is_link($real) || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Download file for alias ' . $alias . ' is not safe to delete.');
        }

        $name = basename($real);
        if (str_starts_with($name, '.') || preg_match('/\.(php|phtml|phar)$/i', $name) === 1 || preg_match('/(secret|password|credential|token|key|backup)/i', $name) === 1) {
            throw new \RuntimeException('Download file for alias ' . $alias . ' is not safe to delete.');
        }

        if (!is_writable(dirname($real))) {
            throw new \RuntimeException('Could not delete download file for alias ' . $alias . ': ' . $relativePath);
        }

        return $real;
    }

    private function relativeDownloadPathIsSafe(string $relativePath): bool
    {
        $normalised = str_replace('\\', '/', $relativePath);

        return $normalised !== ''
            && !str_starts_with($normalised, '/')
            && !str_contains($normalised, '../')
            && !str_contains($normalised, '/..')
            && $normalised !== '..'
            && !str_contains($normalised, "\0");
    }

    /**
     * @param array<string, mixed> $config
     */
    private function statePath(string $stateDir, array $config): string
    {
        return rtrim($stateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename((string)($config['state_file'] ?? 'totman.json'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function lockPath(string $stateDir, array $config): string
    {
        return rtrim($stateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename((string)($config['lock_file'] ?? 'totman.lock'));
    }

    private function backupFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $dir = dirname($path) . DIRECTORY_SEPARATOR . '.totman-ui-backups';
        if (!is_dir($dir) && !mkdir($dir, 0770, true)) {
            throw new \RuntimeException('Could not create backup directory: ' . $dir);
        }

        $backup = $dir . DIRECTORY_SEPARATOR . basename($path) . '.' . gmdate('Ymd-His') . '.' . bin2hex(random_bytes(4)) . '.bak';
        if (!copy($path, $backup)) {
            throw new \RuntimeException('Could not create backup for ' . $path);
        }

        @chmod($backup, 0660);

        return $backup;
    }

    private function restoreFile(?string $backup, string $target): void
    {
        if ($backup === null) {
            if (is_file($target) && !unlink($target)) {
                throw new \RuntimeException('Could not remove partially written file: ' . $target);
            }

            return;
        }

        if (!copy($backup, $target)) {
            throw new \RuntimeException('Could not restore backup for ' . $target);
        }

        @chmod($target, 0660);
    }

    private function atomicWrite(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Directory is not writable: ' . $dir);
        }

        $tmp = tempnam($dir, '.totman-tmp-');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temporary file in ' . $dir);
        }

        try {
            if (file_put_contents($tmp, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Could not write temporary file: ' . $tmp);
            }

            if (!rename($tmp, $path)) {
                throw new \RuntimeException('Could not replace file: ' . $path);
            }

            @chmod($path, 0660);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\ConfigDiscovery;
use Totman\RuntimeUi\Http\FirstRunRequestMapper;
use Totman\RuntimeUi\Http\PrototypeController;
use Totman\RuntimeUi\Http\PrototypeRenderer;
use Totman\RuntimeUi\Http\RuntimeUiMode;
use Totman\RuntimeUi\Config\MainConfigWriter;
use Totman\RuntimeUi\Config\RecipientConfigWriter;
use Totman\RuntimeUi\Security\AdminSessionStore;
use Totman\RuntimeUi\Security\PrototypeCsrfPolicy;
use Totman\RuntimeUi\Security\PrototypeRateLimitPolicy;
use Totman\RuntimeUi\Security\PrototypeSaveIntentPolicy;
use Totman\RuntimeUi\Security\SetupAccessPolicy;
use Totman\RuntimeUi\Security\SetupSessionStore;

final class PrototypeApplicationFactory
{
    private readonly FirstRunSetupService $setupService;
    private readonly FirstRunWizardApplicationService $wizardService;
    private readonly PrototypeRenderer $renderer;
    private readonly RuntimeUiTextCatalog $text;

    public function __construct(
        ?FirstRunSetupService $setupService = null,
        private readonly FirstRunRequestMapper $requestMapper = new FirstRunRequestMapper(),
        private readonly SetupAccessPolicy $accessPolicy = new SetupAccessPolicy(),
        private readonly PrototypeRateLimitPolicy $rateLimitPolicy = new PrototypeRateLimitPolicy(),
        private readonly PrototypeCsrfPolicy $csrfPolicy = new PrototypeCsrfPolicy(),
        private readonly PrototypeSaveIntentPolicy $saveIntentPolicy = new PrototypeSaveIntentPolicy(),
        private readonly AdminAuthApplicationService $adminAuthService = new AdminAuthApplicationService(),
        ?FirstRunWizardApplicationService $wizardService = null,
        private readonly SetupSessionStore $sessionStore = new SetupSessionStore(),
        private readonly AdminSessionStore $adminSessionStore = new AdminSessionStore(),
        private readonly FirstRunDraftStore $draftStore = new FirstRunDraftStore(),
        private readonly ConfigDiscovery $discovery = new ConfigDiscovery(),
        private readonly RuntimeSummaryReader $runtimeSummaryReader = new RuntimeSummaryReader(),
        private readonly RuntimeLogReader $runtimeLogReader = new RuntimeLogReader(),
        private readonly FileAliasInventoryReader $fileAliasInventoryReader = new FileAliasInventoryReader(),
        private readonly MaintenanceCommandService $maintenanceCommandService = new MaintenanceCommandService(),
        ?PrototypeRenderer $renderer = null,
        private readonly string $expectedSetupCode = '',
        private readonly string $runtimeUiMode = RuntimeUiMode::PROTOTYPE,
    ) {
        $runtimeUiMode = RuntimeUiMode::normalise($this->runtimeUiMode);
        $this->text = new RuntimeUiTextCatalog($runtimeUiMode);
        $generatedByLine = $runtimeUiMode === RuntimeUiMode::PRODUCT
            ? 'Generated by the totman runtime UI.'
            : 'Generated by the totman runtime UI.';
        $this->setupService = $setupService ?? new FirstRunSetupService(
            viewModelBuilder: new FirstRunViewModelBuilder(text: $this->text),
            mainWriter: new MainConfigWriter($generatedByLine),
            recipientWriter: new RecipientConfigWriter($generatedByLine),
            text: $this->text
        );
        $this->wizardService = $wizardService ?? new FirstRunWizardApplicationService(
            setupService: $this->setupService,
            text: $this->text
        );
        $this->renderer = $renderer ?? new PrototypeRenderer(text: $this->text);
    }

    public function controller(): PrototypeController
    {
        return new PrototypeController($this->pageService(), $this->requestMapper, $this->renderer);
    }

    private function pageService(): PrototypePageApplicationService
    {
        return new PrototypePageApplicationService(
            $this->setupService,
            $this->accessPolicy,
            $this->rateLimitPolicy,
            $this->csrfPolicy,
            $this->saveIntentPolicy,
            $this->adminAuthService,
            $this->wizardService,
            $this->sessionStore,
            $this->adminSessionStore,
            $this->draftStore,
            $this->discovery,
            $this->runtimeSummaryReader,
            $this->runtimeLogReader,
            $this->fileAliasInventoryReader,
            $this->maintenanceCommandService,
            $this->expectedSetupCode,
            $this->text
        );
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\ConfigDiscovery;
use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Http\FirstRunRequest;
use Totman\RuntimeUi\Security\AdminSessionStore;
use Totman\RuntimeUi\Security\PrototypeCsrfPolicy;
use Totman\RuntimeUi\Security\PrototypeRateLimitPolicy;
use Totman\RuntimeUi\Security\PrototypeSaveIntentPolicy;
use Totman\RuntimeUi\Security\SetupAccessPolicy;
use Totman\RuntimeUi\Security\SetupSessionStore;

final class PrototypePageApplicationService
{
    public function __construct(
        private readonly FirstRunSetupService $setupService = new FirstRunSetupService(),
        private readonly SetupAccessPolicy $accessPolicy = new SetupAccessPolicy(),
        private readonly PrototypeRateLimitPolicy $rateLimitPolicy = new PrototypeRateLimitPolicy(),
        private readonly PrototypeCsrfPolicy $csrfPolicy = new PrototypeCsrfPolicy(),
        private readonly PrototypeSaveIntentPolicy $saveIntentPolicy = new PrototypeSaveIntentPolicy(),
        private readonly AdminAuthApplicationService $adminAuthService = new AdminAuthApplicationService(),
        private readonly FirstRunWizardApplicationService $wizardService = new FirstRunWizardApplicationService(),
        private readonly SetupSessionStore $sessionStore = new SetupSessionStore(),
        private readonly AdminSessionStore $adminSessionStore = new AdminSessionStore(),
        private readonly FirstRunDraftStore $draftStore = new FirstRunDraftStore(),
        private readonly ConfigDiscovery $discovery = new ConfigDiscovery(),
        private readonly RuntimeSummaryReader $runtimeSummaryReader = new RuntimeSummaryReader(),
        private readonly RuntimeLogReader $runtimeLogReader = new RuntimeLogReader(),
        private readonly FileAliasInventoryReader $fileAliasInventoryReader = new FileAliasInventoryReader(),
        private readonly MaintenanceCommandService $maintenanceCommandService = new MaintenanceCommandService(),
        private readonly string $expectedSetupCode = '',
        private readonly RuntimeUiTextCatalog $text = new RuntimeUiTextCatalog(),
    ) {
    }

    public function handle(string $stateDir, DeploymentContext $context, FirstRunRequest $request): PrototypePageResult
    {
        $sessionResult = $this->sessionStore->loadResult();
        $adminSessionResult = $this->adminSessionStore->loadResult();
        $draftResult = $this->draftStore->loadResult();
        $session = $sessionResult->state();
        $adminSession = $adminSessionResult->state();
        $draft = $draftResult->state();
        $stateNotice = $this->notice($sessionResult->notice(), $adminSessionResult->notice(), $draftResult->notice());
        $discovered = $this->discovery->discover($stateDir);
        $adminAuth = $this->adminAuthService->preview($stateDir, $discovered, $adminSession, $this->expectedSetupCode)->view();
        $csrfToken = $this->csrfPolicy->ensureToken($session);
        $adminInspection = $this->adminInspection($stateDir, $context, $adminAuth);

        $rateLimit = $this->rateLimitPolicy->evaluate($request, $stateDir, time());
        if (!$rateLimit->allowed()) {
            $this->sessionStore->save($session);
            return new PrototypePageResult($this->setupService->preview($stateDir, $context, $stateNotice, draft: $draft), $rateLimit, $csrfToken, $adminAuth, $adminInspection);
        }

        $csrf = $this->csrfPolicy->evaluate($request, $session);
        if (!$csrf->allowed()) {
            $this->sessionStore->save($session);
            return new PrototypePageResult($this->setupService->preview($stateDir, $context, $stateNotice, draft: $draft), $csrf, $csrfToken, $adminAuth, $adminInspection);
        }

        if ($request->isAuthAction()) {
            $authResult = $this->adminAuthService->handle($stateDir, $discovered, $request, $this->expectedSetupCode, $session, $adminSession);
            $this->sessionStore->save($session);
            $this->adminSessionStore->save($adminSession);

            return new PrototypePageResult(
                $this->setupService->preview($stateDir, $context, $this->notice($stateNotice, $authResult->notice()), draft: $draft),
                $authResult->access(),
                $csrfToken,
                $authResult->view(),
                $this->adminInspection($stateDir, $context, $authResult->view())
            );
        }

        $access = $this->accessPolicy->evaluate($discovered, $request, $this->expectedSetupCode, $session, $adminSession);
        $this->sessionStore->save($session);
        $this->adminSessionStore->save($adminSession);
        if (!$access->allowed()) {
            return new PrototypePageResult($this->setupService->preview($stateDir, $context, $stateNotice, draft: $draft), $access, $csrfToken, $adminAuth, $adminInspection);
        }

        if ($request->isAdminCommand()) {
            $inspection = $this->adminInspection(
                $stateDir,
                $context,
                $adminAuth,
                $this->maintenanceCommandService->handle(
                    $request->adminCommandKey(),
                    $request->adminCommandPhase(),
                    $adminSession,
                    csrfValid: true,
                    rateLimitAllowed: true,
                    confirmed: $request->confirmAdminCommand(),
                    now: time(),
                    targetAlias: $request->adminCommandTargetAlias(),
                    stateDir: $stateDir
                )
            );

            return new PrototypePageResult(
                $this->setupService->preview($stateDir, $context, $stateNotice, draft: $draft),
                $access,
                $csrfToken,
                $adminAuth,
                $inspection
            );
        }

        if ($request->isDiscardDraft()) {
            $this->draftStore->clear();
            $emptyDraft = new FirstRunDraftState();

            return new PrototypePageResult(
                $this->setupService->preview($stateDir, $context, $this->text->get('notice.draft_discarded'), draft: $emptyDraft),
                $access,
                $csrfToken,
                $adminAuth,
                $adminInspection
            );
        }

        if ($request->isUpdateDraft() || $request->isNextStep() || $request->isPreviousStep()) {
            $wizard = $this->wizardService->handle($stateDir, $context, $this->wizardCommand($request), $draft);
            $this->saveDraftIfChanged($wizard);

            return new PrototypePageResult($wizard->view(), $access, $csrfToken, $adminAuth, $adminInspection);
        }

        $saveIntent = $this->saveIntentPolicy->evaluate($request);
        if (!$saveIntent->allowed()) {
            return new PrototypePageResult($this->setupService->preview($stateDir, $context, $stateNotice, draft: $draft), $saveIntent, $csrfToken, $adminAuth, $adminInspection);
        }

        if ($request->isRuntimeSave()) {
            $wizard = $this->wizardService->handle($stateDir, $context, FirstRunWizardCommand::saveRuntime(), $draft);
            $this->saveDraftIfChanged($wizard);

            return new PrototypePageResult($wizard->view(), $access, $csrfToken, $adminAuth, $adminInspection);
        }

        $wizard = $this->wizardService->handle($stateDir, $context, FirstRunWizardCommand::preview(), $draft);

        return new PrototypePageResult($wizard->view(), $access, $csrfToken, $adminAuth, $adminInspection);
    }

    private function wizardCommand(FirstRunRequest $request): FirstRunWizardCommand
    {
        if ($request->isNextStep()) {
            return FirstRunWizardCommand::nextStep($request->input());
        }

        if ($request->isPreviousStep()) {
            return FirstRunWizardCommand::previousStep($request->input());
        }

        return FirstRunWizardCommand::updateDraft($request->input());
    }

    private function saveDraftIfChanged(FirstRunWizardResult $wizard): void
    {
        if ($wizard->draftChanged()) {
            $this->draftStore->save($wizard->draft());
        }
    }

    private function notice(string ...$notices): string
    {
        $notices = array_values(array_filter($notices, static fn (string $notice): bool => $notice !== ''));

        return implode(' ', $notices);
    }

    private function adminInspection(
        string $stateDir,
        DeploymentContext $context,
        AdminAuthViewModel $adminAuth,
        ?MaintenanceCommandResult $maintenanceCommand = null,
    ): AdminInspectionViewModel {
        if (!$adminAuth->showSignedIn()) {
            return AdminInspectionViewModel::unavailable('Runtime inspection is available after admin sign-in.');
        }

        return AdminInspectionViewModel::fromReadModels(
            $this->runtimeSummaryReader->read($stateDir, $context),
            $this->runtimeLogReader->read($stateDir, $context),
            $this->fileAliasInventoryReader->read($stateDir, $context),
            $maintenanceCommand
        );
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Security\SetupAccessResult;

final class PrototypePageResult
{
    public function __construct(
        private readonly FirstRunViewModel $view,
        private readonly SetupAccessResult $access,
        private readonly string $csrfToken,
        private readonly AdminAuthViewModel $adminAuth,
        private readonly AdminInspectionViewModel $adminInspection,
    ) {
    }

    public function view(): FirstRunViewModel
    {
        return $this->view;
    }

    public function access(): SetupAccessResult
    {
        return $this->access;
    }

    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    public function adminAuth(): AdminAuthViewModel
    {
        return $this->adminAuth;
    }

    public function adminInspection(): AdminInspectionViewModel
    {
        return $this->adminInspection;
    }
}

namespace Totman\RuntimeUi\Application;

final class RecipientConfigDraft
{
    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $messages
     * @param array<int, mixed> $recipients
     */
    public function __construct(
        private readonly array $files,
        private readonly array $messages,
        private readonly array $recipients,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<int, mixed>
     */
    public function recipients(): array
    {
        return $this->recipients;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\DiscoveryResult;

final class RecipientConfigDraftBuilder
{
    public function build(FirstRunInput $input, DiscoveryResult $discovered): RecipientConfigDraft
    {
        $files = $this->topLevelArray($discovered, 'files');
        $messages = $this->topLevelArray($discovered, 'messages');
        $recipients = $this->topLevelArray($discovered, 'recipients');

        if ($input->downloadAlias() !== '' && $input->downloadPath() !== '') {
            $files[$input->downloadAlias()] = $input->downloadPath();
        }

        if ($input->messageSubject() !== '' || $input->messageBody() !== '') {
            $messages['default'] = [
                'subject' => $this->value($input->messageSubject(), $messages['default'] ?? [], 'subject', '[totman] Message'),
                'body' => $this->value($input->messageBody(), $messages['default'] ?? [], 'body', 'Hello {RECIPIENT_NAME}'),
            ];
            if ($input->downloadSingleUse()) {
                $messages['default']['single_use_notice'] = 'Please save this file straight away. This download link works only once.';
            }
        }

        if ($input->recipientName() !== '' || $input->recipientMailbox() !== '') {
            $row = [
                $input->recipientName(),
                $input->recipientMailbox(),
                'default',
            ];
            if ($input->downloadAlias() !== '' && $input->downloadPath() !== '') {
                if ($input->downloadSingleUse()) {
                    $row[3] = [];
                    $row[4] = [$input->downloadAlias()];
                } else {
                    $row[3] = [$input->downloadAlias()];
                }
            }
            $recipients = [$row];
        }

        return new RecipientConfigDraft($files, $messages, $recipients);
    }

    /**
     * @return array<mixed>
     */
    private function topLevelArray(DiscoveryResult $discovered, string $key): array
    {
        $value = $discovered->liveRecipientConfig()[$key] ?? $discovered->distRecipientConfig()[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $current
     */
    private function value(string $inputValue, array $current, string $key, mixed $default): mixed
    {
        if ($inputValue !== '') {
            return $inputValue;
        }

        if (!array_key_exists($key, $current)) {
            return $default;
        }

        $value = $current[$key];
        if (is_array($value)) {
            return $this->firstStringValue($value) !== '' ? $value : $default;
        }

        return $value === '' ? $default : $value;
    }

    private function firstStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item)) {
                    return $item;
                }
            }
        }

        return '';
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Security\SecretRedactor;

final class RuntimeLogReader
{
    private const MAX_LINES = 200;
    private const MAX_BYTES = 65536;

    public function __construct(
        private readonly RuntimeSummaryReader $summaryReader = new RuntimeSummaryReader(),
        private readonly SecretRedactor $redactor = new SecretRedactor(),
    ) {
    }

    public function read(string $stateDir, DeploymentContext $context): RuntimeLogTail
    {
        $summary = $this->summaryReader->read($stateDir, $context);
        $path = $summary->paths()['log_file'] ?? rtrim($stateDir, '/') . '/totman.log';

        if (!is_file($path)) {
            return new RuntimeLogTail($path, 'missing', 'Log file is not present yet.', [], 0);
        }

        if (!is_readable($path)) {
            return new RuntimeLogTail($path, 'unavailable', 'Log file exists but is not readable.', [], 0);
        }

        $size = filesize($path);
        if ($size === false) {
            return new RuntimeLogTail($path, 'unavailable', 'Log file size could not be read.', [], 0);
        }

        $bytes = min($size, self::MAX_BYTES);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return new RuntimeLogTail($path, 'unavailable', 'Log file could not be opened.', [], 0);
        }

        try {
            if ($size > $bytes) {
                fseek($handle, -$bytes, SEEK_END);
            }

            $content = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if ($content === false) {
            return new RuntimeLogTail($path, 'unavailable', 'Log file could not be read.', [], 0);
        }

        if ($size > $bytes) {
            $firstNewline = strpos($content, "\n");
            if ($firstNewline !== false) {
                $content = substr($content, $firstNewline + 1);
            }
        }

        $lines = preg_split('/\R/', rtrim($content, "\r\n"));
        if ($lines === false || $lines === ['']) {
            $lines = [];
        }

        $lines = array_slice($lines, -self::MAX_LINES);
        $lines = array_map(fn (string $line): string => $this->redactor->redactText($line), $lines);

        return new RuntimeLogTail($path, 'loaded', 'Log tail loaded.', $lines, $bytes);
    }
}

namespace Totman\RuntimeUi\Application;

final class RuntimeLogTail
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        private readonly string $path,
        private readonly string $status,
        private readonly string $message,
        private readonly array $lines,
        private readonly int $bytesRead,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function bytesRead(): int
    {
        return $this->bytesRead;
    }
}

namespace Totman\RuntimeUi\Application;

final class RuntimeStateFileStatus
{
    public function __construct(
        private readonly string $path,
        private readonly string $status,
        private readonly string $message,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Preflight\PreflightResult;

final class RuntimeSummary
{
    /**
     * @param array<string, string> $paths
     * @param array<string, mixed> $redactedConfig
     * @param list<string> $issues
     */
    public function __construct(
        private readonly string $mode,
        private readonly string $mainSource,
        private readonly string $recipientSource,
        private readonly PreflightResult $preflight,
        private readonly RuntimeStateFileStatus $stateFile,
        private readonly array $paths,
        private readonly array $redactedConfig,
        private readonly array $issues,
    ) {
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function mainSource(): string
    {
        return $this->mainSource;
    }

    public function recipientSource(): string
    {
        return $this->recipientSource;
    }

    public function preflight(): PreflightResult
    {
        return $this->preflight;
    }

    public function stateFile(): RuntimeStateFileStatus
    {
        return $this->stateFile;
    }

    /**
     * @return array<string, string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @return array<string, mixed>
     */
    public function redactedConfig(): array
    {
        return $this->redactedConfig;
    }

    /**
     * @return list<string>
     */
    public function issues(): array
    {
        return $this->issues;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Config\ConfigDiscovery;
use Totman\RuntimeUi\Config\MainConfigImporter;
use Totman\RuntimeUi\Config\RecipientConfigImporter;
use Totman\RuntimeUi\Deployment\DeploymentContext;
use Totman\RuntimeUi\Preflight\FirstRunPreflight;
use Totman\RuntimeUi\Security\SecretRedactor;

final class RuntimeSummaryReader
{
    public function __construct(
        private readonly ConfigDiscovery $discovery = new ConfigDiscovery(),
        private readonly MainConfigImporter $mainImporter = new MainConfigImporter(),
        private readonly RecipientConfigImporter $recipientImporter = new RecipientConfigImporter(),
        private readonly FirstRunPreflight $preflight = new FirstRunPreflight(),
        private readonly SecretRedactor $redactor = new SecretRedactor(),
    ) {
    }

    public function read(string $stateDir, DeploymentContext $context): RuntimeSummary
    {
        $discovered = $this->discovery->discover($stateDir);
        $main = $this->mainImporter->import($discovered, $context);
        $recipients = $this->recipientImporter->import($discovered);
        $preflight = $this->preflight->check($main, $recipients, $context);
        $paths = $this->paths($stateDir, $discovered->effectiveMainConfig());

        return new RuntimeSummary(
            $discovered->mode(),
            $discovered->mainSource(),
            $discovered->recipientSource(),
            $preflight,
            $this->stateFileStatus($paths['state_file']),
            $paths,
            $this->redactor->redactArray($discovered->effectiveMainConfig()),
            $discovered->issues()
        );
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    private function paths(string $stateDir, array $config): array
    {
        $stateRoot = $this->stringValue($config['state_dir'] ?? null, $stateDir);
        $stateFile = $this->stringValue($config['state_file'] ?? null, 'totman.json');
        $downloadBaseDir = $this->stringValue($config['download_base_dir'] ?? null, $stateRoot . '/downloads');
        $logFile = $this->stringValue($config['log_file'] ?? null, '');
        if ($logFile === '') {
            $logFile = $this->stringValue($config['log_file_name'] ?? null, 'totman.log');
        }

        return [
            'state_dir' => $stateRoot,
            'state_file' => $this->resolvePath($stateRoot, $stateFile),
            'download_base_dir' => $this->resolvePath($stateRoot, $downloadBaseDir),
            'log_file' => $this->resolvePath($stateRoot, $logFile),
        ];
    }

    private function stateFileStatus(string $path): RuntimeStateFileStatus
    {
        if (!is_file($path)) {
            return new RuntimeStateFileStatus($path, 'missing', 'State file is not present yet.');
        }

        if (!is_readable($path)) {
            return new RuntimeStateFileStatus($path, 'unavailable', 'State file exists but is not readable.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return new RuntimeStateFileStatus($path, 'unavailable', 'State file could not be read.');
        }

        if (trim($content) === '') {
            return new RuntimeStateFileStatus($path, 'corrupt', 'State file is empty.');
        }

        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new RuntimeStateFileStatus($path, 'corrupt', 'State file is not valid JSON.');
        }

        if (!is_array($decoded)) {
            return new RuntimeStateFileStatus($path, 'corrupt', 'State file does not contain an object.');
        }

        return new RuntimeStateFileStatus($path, 'loaded', 'State file is readable.');
    }

    private function resolvePath(string $baseDir, string $path): string
    {
        if ($path === '') {
            return $baseDir;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($baseDir, '/') . '/' . ltrim($path, '/');
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        if (!is_string($value) || $value === '') {
            return $fallback;
        }

        return $value;
    }
}

namespace Totman\RuntimeUi\Application;

use Totman\RuntimeUi\Http\RuntimeUiMode;

final class RuntimeUiTextCatalog
{
    public function __construct(private readonly string $runtimeUiMode = RuntimeUiMode::PROTOTYPE)
    {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $text = [
            'page.title' => 'totman runtime UI',
            'product.name' => 'totman',
            'header.language' => 'Language',
            'header.toggle_theme' => 'Toggle theme',
            'footer.label' => 'Footer',
            'footer.documentation' => 'Documentation',
            'setup.initial_title' => 'Initial setup',
            'setup.locked' => 'Setup is locked until a setup code is configured.',
            'setup.locked_help' => 'Edit the deployed UI PHP file on the server and set TOTMAN_UI_SETUP_CODE near the top, then reload this page. Docker or managed hosting can set the same value in the server environment instead.',
            'setup.code' => 'Setup Code',
            'setup.code_help' => 'One-time code configured before first setup.',
            'setup.username' => 'Username',
            'setup.username_help' => 'Admin username for this browser UI.',
            'setup.password' => 'Password',
            'setup.password_help' => 'Minimum length: 10 characters.',
            'setup.repeat_password' => 'Repeat Password',
            'setup.repeat_password_help' => 'Password repetition catches typing mistakes.',
            'password.show' => 'Show',
            'password.hide' => 'Hide',
            'setup.data_directory' => 'Data directory',
            'setup.data_directory_default' => '/var/lib/totman',
            'setup.data_directory_help' => 'Default runtime state directory. Set TOTMAN_STATE_DIR on the server before loading this page if another path is needed.',
            'setup.create_access' => 'Create access',
            'notice.draft_saved' => 'Prototype draft saved.',
            'notice.draft_discarded' => 'Prototype draft discarded.',
            'notice.config_saved' => 'Prototype config saved.',
            'summary.state_dir' => 'State directory',
            'summary.state_dir_hidden' => 'Configured runtime state directory',
            'summary.mode' => 'Mode',
            'summary.current_step' => 'Current step',
            'summary.preflight' => 'Preflight',
            'summary.path_fields' => 'Path fields',
            'summary.paths_read_only' => 'Read-only in this context',
            'summary.paths_editable' => 'Editable in this context',
            'section.summary' => 'Summary',
            'section.steps' => 'Setup steps',
            'section.admin_access' => 'Admin access',
            'section.runtime_inspection' => 'Runtime inspection',
            'section.wizard_step' => 'Wizard step',
            'section.setup_details' => 'Setup details',
            'admin.unavailable_after_signin' => 'Runtime inspection is available after admin sign-in.',
            'admin.config_blocked' => 'Admin access is unavailable while configuration discovery is blocked.',
            'admin.private_config_blocked' => 'Admin access is unavailable because the private UI config is unreadable or invalid.',
            'admin.disabled' => 'Browser administration is disabled. Set web_ui_enabled to true in the effective runtime configuration, then reload this page.',
            'admin.signed_in_as' => 'Signed in as {username}.',
            'admin.reenter_password' => 'Re-enter password',
            'admin.sign_in' => 'Sign in',
            'admin.sign_out' => 'Sign out',
            'admin.reauthenticate' => 'Reauthenticate',
            'admin.username' => 'Username',
            'admin.password' => 'Password',
            'admin.username_help' => 'Use the admin name for this browser UI.',
            'admin.password_help' => 'Enter the password for this browser UI account.',
            'admin.new_password_help' => 'Use at least 10 characters. The password is stored only as a hash.',
            'admin.repeat_password_help' => 'Repeat the password to avoid a typo.',
            'admin.reauth_help' => 'Required before sensitive maintenance actions.',
            'admin.setup_code' => 'Setup code',
            'admin.create_username' => 'Admin username',
            'admin.create_password' => 'Admin password',
            'admin.repeat_password' => 'Repeat admin password',
            'admin.create_access' => 'Create admin access',
            'inspection.unavailable' => 'Runtime inspection is unavailable.',
            'inspection.summary' => 'Runtime summary',
            'inspection.main_source' => 'Main config source',
            'inspection.recipient_source' => 'Recipient source',
            'inspection.state_file' => 'State file',
            'inspection.paths' => 'Runtime paths',
            'inspection.log_tail' => 'Log tail',
            'inspection.file_aliases' => 'File aliases',
            'inspection.download_base_dir' => 'Download base directory',
            'inspection.no_aliases' => 'No file aliases configured.',
            'inspection.alias_normal' => 'normal',
            'inspection.alias_single_use' => 'single-use',
            'inspection.alias_file' => 'file',
            'inspection.file_present' => 'present',
            'inspection.file_missing' => 'missing',
            'inspection.unavailable_after_signin_intro' => 'Inspect runtime status and maintenance actions after admin sign-in.',
            'danger.heading' => 'Maintenance commands',
            'danger.none_selected' => 'No maintenance command selected.',
            'danger.status' => 'Status',
            'danger.phase' => 'Phase',
            'danger.effects' => 'Effects',
            'danger.allowed' => 'Allowed',
            'danger.blocked' => 'Blocked',
            'danger.hmac_rotation' => 'Preview HMAC rotation',
            'danger.runtime_reset' => 'Preview runtime reset',
            'danger.log_clear' => 'Preview log clear',
            'danger.file_alias_deletion' => 'Preview file alias deletion',
            'danger.preview' => 'Preview',
            'danger.execute' => 'Execute',
            'danger.confirm_execute' => 'Confirm execution',
            'danger.alias' => 'Alias',
            'field.required' => 'Required',
            'field.source' => 'Source',
            'field.base_url.label' => 'Public URL',
            'field.base_url.hint' => 'The HTTPS URL recipients will open from mail links.',
            'field.mail_from.label' => 'Mail from',
            'field.mail_from.hint' => 'The sender mailbox used for runtime mail.',
            'field.sendmail_path.label' => 'Sendmail path',
            'field.sendmail_path.hint' => 'The executable command the runtime uses to hand mail to the server.',
            'field.to_self.label' => 'Operator mailbox',
            'field.to_self.hint' => 'The mailbox that receives operator warnings and setup diagnostics.',
            'field.recipient_name.label' => 'Recipient name',
            'field.recipient_name.hint' => 'The display name for the first recipient.',
            'field.recipient_mailbox.label' => 'Recipient mailbox',
            'field.recipient_mailbox.hint' => 'The mailbox that receives escalation mail.',
            'field.message_subject.label' => 'Message subject',
            'field.message_subject.hint' => 'The subject line for the first escalation message.',
            'field.message_body.label' => 'Message body',
            'field.message_body.hint' => 'The body for the first escalation message. Runtime placeholders may be used.',
            'field.web_ui_enabled.label' => 'Enable Web UI after setup',
            'field.web_ui_enabled.hint' => 'Controls browser administration after setup. Manual runtime operation remains independent.',
            'field.download_alias.label' => 'Optional download alias',
            'field.download_alias.hint' => 'A short name for an optional private download. Leave empty if no download is needed.',
            'field.download_path.label' => 'Optional download path',
            'field.download_path.hint' => 'The relative private file path under the download directory.',
            'field.download_single_use.label' => 'Single-use download',
            'field.download_single_use.hint' => 'Marks the optional download as usable only once.',
            'action.update_draft' => 'Save draft',
            'action.discard_draft' => 'Discard draft',
            'action.previous_step' => 'Back',
            'action.next_step' => 'Continue',
            'action.save_runtime' => 'Write runtime files',
            'step.discover.description' => 'Inspect the runtime directory and decide whether setup can continue.',
            'step.repair-blocking-problem.description' => 'Repair unreadable or broken configuration before browser setup continues.',
            'step.create-or-import.description' => 'Start from templates or import the existing runtime files without changing them.',
            'step.public-url.description' => 'Set the public HTTPS URL used in generated mail links.',
            'step.mail-delivery.description' => 'Configure the sender identity and local mail hand-off command.',
            'step.operator-mailbox.description' => 'Set the operator mailbox for warnings and setup diagnostics.',
            'step.first-recipient.description' => 'Define the first recipient for escalation mail.',
            'step.first-message.description' => 'Define the first recipient-specific escalation message.',
            'step.optional-download.description' => 'Attach an optional private download to the first recipient.',
            'step.review.description' => 'Review non-secret values and their sources before preflight.',
            'step.preflight.description' => 'Check the current draft before runtime files can be written.',
            'step.save.description' => 'Write runtime files only after confirmation and passing preflight.',
            'step.complete.description' => 'The first-run flow has finished.',
            'wizard.setup_code' => 'Setup code',
            'wizard.setup_code_help' => 'Enter the setup code from the server environment to authorise setup changes.',
            'wizard.no_fields' => 'No fields are required on this step.',
            'wizard.review' => 'Review',
            'wizard.preflight' => 'Preflight',
            'wizard.save' => 'Save runtime files',
            'wizard.save_notice' => 'Runtime files will only be written after this confirmation and a passing draft preflight.',
            'wizard.confirm_save' => 'Confirm save',
            'wizard.complete' => 'First-run setup is complete.',
            'preflight.fix' => 'Fix',
            'preflight.status_ok' => 'OK',
            'preflight.status_warn' => 'WARN',
            'preflight.status_fail' => 'FAIL',
            'source.live' => 'Live config',
            'source.dist' => 'Template default',
            'source.draft' => 'Draft override',
            'source.generated' => 'Generated default',
            'source.missing' => 'Missing',
        ];

        if ($this->runtimeUiMode === RuntimeUiMode::PRODUCT) {
            $text['page.title'] = 'totman runtime UI';
            $text['notice.draft_saved'] = 'Draft saved.';
            $text['notice.draft_discarded'] = 'Draft discarded.';
            $text['notice.config_saved'] = 'Runtime config saved.';
        }

        return $text;
    }

    public function get(string $key): string
    {
        return $this->all()[$key] ?? $key;
    }

    public function productMode(): bool
    {
        return $this->runtimeUiMode === RuntimeUiMode::PRODUCT;
    }

    public function format(string $key, string $placeholder, string $value): string
    {
        return str_replace('{' . $placeholder . '}', $value, $this->get($key));
    }

    /**
     * @return list<string>
     */
    public static function requiredKeys(): array
    {
        return [
            'page.title',
            'product.name',
            'notice.draft_saved',
            'notice.config_saved',
            'section.summary',
            'summary.state_dir_hidden',
            'section.admin_access',
            'section.runtime_inspection',
            'header.language',
            'header.toggle_theme',
            'footer.documentation',
            'setup.initial_title',
            'setup.locked',
            'setup.locked_help',
            'setup.create_access',
            'field.base_url.label',
            'field.message_body.hint',
            'action.save_runtime',
            'inspection.summary',
            'inspection.log_tail',
            'inspection.file_aliases',
            'inspection.alias_normal',
            'inspection.alias_single_use',
            'danger.runtime_reset',
            'danger.file_alias_deletion',
            'danger.confirm_execute',
            'preflight.fix',
        ];
    }
}

namespace Totman\RuntimeUi\Config;

final class ConfigCoverageResult
{
    /**
     * @param array<string, string> $classified
     * @param list<string> $unclassified
     */
    public function __construct(
        private readonly array $classified,
        private readonly array $unclassified,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function classified(): array
    {
        return $this->classified;
    }

    /**
     * @return list<string>
     */
    public function unclassified(): array
    {
        return $this->unclassified;
    }

    public function classification(string $key): ?string
    {
        return $this->classified[$key] ?? null;
    }
}

namespace Totman\RuntimeUi\Config;

use Totman\RuntimeUi\Contracts\RuntimeFileNames;

final class ConfigDiscovery
{
    public function discover(string $stateDir): DiscoveryResult
    {
        $stateDir = rtrim($stateDir, DIRECTORY_SEPARATOR);
        $issues = [];

        if (!is_dir($stateDir)) {
            $missingMainLive = ConfigFileStatus::missing('main-live', $stateDir . DIRECTORY_SEPARATOR . RuntimeFileNames::MAIN_LIVE);
            $missingMainDist = ConfigFileStatus::missing('main-dist', $stateDir . DIRECTORY_SEPARATOR . RuntimeFileNames::MAIN_DIST);
            $missingRecipientLive = ConfigFileStatus::missing('recipient-live', $stateDir . DIRECTORY_SEPARATOR . RuntimeFileNames::RECIPIENTS_LIVE);
            $missingRecipientDist = ConfigFileStatus::missing('recipient-dist', $stateDir . DIRECTORY_SEPARATOR . RuntimeFileNames::RECIPIENTS_DIST);

            return new DiscoveryResult($stateDir, $missingMainLive, $missingMainDist, $missingRecipientLive, $missingRecipientDist, [], [], [], [], 'none', 'none', [], null, [
                DiscoveryIssue::blocked('state_dir_missing', 'state directory does not exist', $stateDir),
            ]);
        }

        [$mainLiveStatus, $liveConfig, $liveIssue] = $this->loadConfig(
            'main-live',
            $stateDir . DIRECTORY_SEPARATOR . RuntimeFileNames::MAIN_LIVE
        );
        [$mainDistStatus, $distConfig, $distIssue] = $this->loadConfig(
            'main-dist',
            $stateDir . DIRECTORY_SEPARATOR . RuntimeFileNames::MAIN_DIST
        );

        foreach ([$liveIssue, $distIssue] as $issue) {
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        $mainSource = $this->sourceName($liveConfig !== null, $distConfig !== null);
        $effective = array_replace($distConfig ?? [], $liveConfig ?? []);

        $configuredRecipientsFile = $this->normaliseRuntimeFilename(
            $effective['recipients_file'] ?? RuntimeFileNames::RECIPIENTS_LIVE
        );

        $recipientLivePath = $stateDir . DIRECTORY_SEPARATOR . $configuredRecipientsFile;
        $recipientDistPath = $stateDir . DIRECTORY_SEPARATOR . RuntimeFileNames::RECIPIENTS_DIST;
        [$recipientLiveStatus, $recipientLiveConfig, $recipientLiveIssue] = $this->loadConfig('recipient-live', $recipientLivePath);
        [$recipientDistStatus, $recipientDistConfig, $recipientDistIssue] = $this->loadConfig('recipient-dist', $recipientDistPath);
        foreach ([$recipientLiveIssue, $recipientDistIssue] as $issue) {
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        $recipientSource = $this->sourceName($recipientLiveStatus->loaded(), $recipientDistStatus->loaded());

        if ($mainSource !== 'none' && $recipientSource === 'none') {
            $issues[] = DiscoveryIssue::missing(
                'recipients_missing',
                'no live or template recipient configuration found',
                $recipientLivePath
            );
        }

        return new DiscoveryResult(
            $stateDir,
            $mainLiveStatus,
            $mainDistStatus,
            $recipientLiveStatus,
            $recipientDistStatus,
            $liveConfig ?? [],
            $distConfig ?? [],
            $recipientLiveConfig ?? [],
            $recipientDistConfig ?? [],
            $mainSource,
            $recipientSource,
            $effective,
            $configuredRecipientsFile,
            $issues,
        );
    }

    /**
     * @return array{0: ConfigFileStatus, 1: ?array<string, mixed>, 2: ?DiscoveryIssue}
     */
    private function loadConfig(string $role, string $path): array
    {
        if (!is_file($path)) {
            return [ConfigFileStatus::missing($role, $path), null, null];
        }

        try {
            $value = require $path;
        } catch (\Throwable $error) {
            return [
                ConfigFileStatus::invalid($role, $path, 'cannot be loaded'),
                null,
                DiscoveryIssue::blocked('config_load_failed', 'config cannot be loaded: ' . basename($path), $path),
            ];
        }

        if (!is_array($value)) {
            return [
                ConfigFileStatus::invalid($role, $path, 'does not return an array'),
                null,
                DiscoveryIssue::blocked('config_not_array', 'config does not return an array: ' . basename($path), $path),
            ];
        }

        return [ConfigFileStatus::loadedFile($role, $path), $value, null];
    }

    private function sourceName(bool $hasLive, bool $hasDist): string
    {
        if ($hasLive && $hasDist) {
            return 'live+dist';
        }

        if ($hasLive) {
            return 'live';
        }

        if ($hasDist) {
            return 'dist';
        }

        return 'none';
    }

    private function normaliseRuntimeFilename(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return RuntimeFileNames::RECIPIENTS_LIVE;
        }

        return basename($value);
    }
}

namespace Totman\RuntimeUi\Config;

final class ConfigFileStatus
{
    public function __construct(
        private readonly string $role,
        private readonly string $path,
        private readonly bool $exists,
        private readonly bool $loaded,
        private readonly bool $validArray,
        private readonly ?string $error = null,
    ) {
    }

    public static function missing(string $role, string $path): self
    {
        return new self($role, $path, false, false, false);
    }

    public static function loadedFile(string $role, string $path): self
    {
        return new self($role, $path, true, true, true);
    }

    public static function invalid(string $role, string $path, string $error): self
    {
        return new self($role, $path, true, false, false, $error);
    }

    public function role(): string
    {
        return $this->role;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basename(): string
    {
        return basename($this->path);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function loaded(): bool
    {
        return $this->loaded;
    }

    public function validArray(): bool
    {
        return $this->validArray;
    }

    public function error(): ?string
    {
        return $this->error;
    }
}

namespace Totman\RuntimeUi\Config;

final class DiscoveryIssue
{
    public function __construct(
        private readonly string $severity,
        private readonly string $code,
        private readonly string $message,
        private readonly ?string $path = null,
    ) {
    }

    public static function blocked(string $code, string $message, ?string $path = null): self
    {
        return new self('blocked', $code, $message, $path);
    }

    public static function missing(string $code, string $message, ?string $path = null): self
    {
        return new self('missing', $code, $message, $path);
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function toLegacyString(): string
    {
        return $this->severity . ': ' . $this->message;
    }
}

namespace Totman\RuntimeUi\Config;

final class DiscoveryResult
{
    /**
     * @param array<string, mixed> $effectiveMainConfig
     * @param list<DiscoveryIssue> $issues
     */
    public function __construct(
        private readonly string $stateDir,
        private readonly ConfigFileStatus $mainLiveStatus,
        private readonly ConfigFileStatus $mainDistStatus,
        private readonly ConfigFileStatus $recipientLiveStatus,
        private readonly ConfigFileStatus $recipientDistStatus,
        private readonly array $liveMainConfig,
        private readonly array $distMainConfig,
        private readonly array $liveRecipientConfig,
        private readonly array $distRecipientConfig,
        private readonly string $mainSource,
        private readonly string $recipientSource,
        private readonly array $effectiveMainConfig,
        private readonly ?string $configuredRecipientsFile,
        private readonly array $issues,
    ) {
    }

    public function stateDir(): string
    {
        return $this->stateDir;
    }

    public function mode(): string
    {
        if ($this->hasBlockingIssue()) {
            return 'blocked';
        }

        if ($this->mainSource === 'none') {
            return 'fresh';
        }

        return 'existing';
    }

    public function mainSource(): string
    {
        return $this->mainSource;
    }

    public function mainLiveStatus(): ConfigFileStatus
    {
        return $this->mainLiveStatus;
    }

    public function mainDistStatus(): ConfigFileStatus
    {
        return $this->mainDistStatus;
    }

    public function recipientSource(): string
    {
        return $this->recipientSource;
    }

    public function recipientLiveStatus(): ConfigFileStatus
    {
        return $this->recipientLiveStatus;
    }

    public function recipientDistStatus(): ConfigFileStatus
    {
        return $this->recipientDistStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function liveMainConfig(): array
    {
        return $this->liveMainConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function distMainConfig(): array
    {
        return $this->distMainConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function liveRecipientConfig(): array
    {
        return $this->liveRecipientConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function distRecipientConfig(): array
    {
        return $this->distRecipientConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function effectiveMainConfig(): array
    {
        return $this->effectiveMainConfig;
    }

    public function configuredRecipientsFile(): ?string
    {
        return $this->configuredRecipientsFile;
    }

    /**
     * @return list<string>
     */
    public function issues(): array
    {
        return array_map(
            static fn (DiscoveryIssue $issue): string => $issue->toLegacyString(),
            $this->issues
        );
    }

    /**
     * @return list<DiscoveryIssue>
     */
    public function issueObjects(): array
    {
        return $this->issues;
    }

    private function hasBlockingIssue(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity() === 'blocked') {
                return true;
            }
        }

        return false;
    }
}

namespace Totman\RuntimeUi\Config;

final class ImportedField
{
    public function __construct(
        private readonly string $key,
        private readonly mixed $value,
        private readonly string $source,
        private readonly bool $critical,
        private readonly bool $editable,
        private readonly bool $placeholder,
        private readonly bool $invalid,
        private readonly bool $serverGeneratedAvailable = false,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function critical(): bool
    {
        return $this->critical;
    }

    public function editable(): bool
    {
        return $this->editable;
    }

    public function placeholder(): bool
    {
        return $this->placeholder;
    }

    public function invalid(): bool
    {
        return $this->invalid;
    }

    public function serverGeneratedAvailable(): bool
    {
        return $this->serverGeneratedAvailable;
    }

    public function needsOperatorInput(): bool
    {
        return $this->critical && !$this->serverGeneratedAvailable && ($this->placeholder || $this->invalid || $this->source === 'missing');
    }
}

namespace Totman\RuntimeUi\Config;

final class MainConfigCoverage
{
    public const FIRST_RUN_WIZARD = 'first-run-wizard';
    public const RUNTIME_DEFAULTED = 'runtime-defaulted';
    public const READ_ONLY = 'read-only';
    public const GENERATED = 'generated';
    public const DEFERRED = 'deferred';

    /** @var array<string, self::*> */
    private const CLASSIFICATIONS = [
        'base_url' => self::FIRST_RUN_WIZARD,
        'state_dir' => self::FIRST_RUN_WIZARD,
        'download_base_dir' => self::FIRST_RUN_WIZARD,
        'sendmail_path' => self::FIRST_RUN_WIZARD,
        'to_self' => self::FIRST_RUN_WIZARD,
        'mail_from' => self::FIRST_RUN_WIZARD,
        'web_ui_enabled' => self::FIRST_RUN_WIZARD,
        'hmac_secret_hex' => self::GENERATED,
        'lib_file' => self::READ_ONLY,
        'l18n_dir_name' => self::READ_ONLY,
        'lock_file' => self::READ_ONLY,
        'log_file_name' => self::READ_ONLY,
        'recipients_file' => self::READ_ONLY,
        'state_file' => self::READ_ONLY,
        'web_file' => self::READ_ONLY,
        'web_css_file' => self::READ_ONLY,
        'download_valid_days' => self::RUNTIME_DEFAULTED,
        'download_rate_limit_enabled' => self::RUNTIME_DEFAULTED,
        'download_rate_limit_max_requests' => self::RUNTIME_DEFAULTED,
        'download_rate_limit_window_seconds' => self::RUNTIME_DEFAULTED,
        'download_lease_seconds' => self::RUNTIME_DEFAULTED,
        'check_interval_seconds' => self::RUNTIME_DEFAULTED,
        'confirm_window_seconds' => self::RUNTIME_DEFAULTED,
        'remind_every_seconds' => self::RUNTIME_DEFAULTED,
        'escalate_grace_seconds' => self::RUNTIME_DEFAULTED,
        'missed_cycles_before_fire' => self::RUNTIME_DEFAULTED,
        'escalate_ack_enabled' => self::RUNTIME_DEFAULTED,
        'escalate_ack_remind_every_seconds' => self::RUNTIME_DEFAULTED,
        'escalate_ack_max_reminds' => self::RUNTIME_DEFAULTED,
        'stealth_neutral_for_invalid' => self::RUNTIME_DEFAULTED,
        'stealth_level_2_neutral_on_stale' => self::RUNTIME_DEFAULTED,
        'show_success_details' => self::RUNTIME_DEFAULTED,
        'rate_limit_enabled' => self::RUNTIME_DEFAULTED,
        'rate_limit_dir' => self::RUNTIME_DEFAULTED,
        'rate_limit_max_requests' => self::RUNTIME_DEFAULTED,
        'rate_limit_window_seconds' => self::RUNTIME_DEFAULTED,
        'ip_mode' => self::RUNTIME_DEFAULTED,
        'trusted_proxies' => self::RUNTIME_DEFAULTED,
        'trusted_proxy_header' => self::RUNTIME_DEFAULTED,
        'operator_alert_interval_hours' => self::RUNTIME_DEFAULTED,
        'mail_timezone' => self::RUNTIME_DEFAULTED,
        'mail_date_format' => self::RUNTIME_DEFAULTED,
        'mail_time_format' => self::RUNTIME_DEFAULTED,
        'mail_datetime_format' => self::RUNTIME_DEFAULTED,
        'log_mode' => self::RUNTIME_DEFAULTED,
        'log_file' => self::RUNTIME_DEFAULTED,
        'reply_to' => self::DEFERRED,
        'subject_reminder' => self::DEFERRED,
        'body_reminder' => self::DEFERRED,
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function check(array $config): ConfigCoverageResult
    {
        $classified = [];
        $unclassified = [];

        foreach (array_keys($config) as $key) {
            $classification = self::CLASSIFICATIONS[$key] ?? null;
            if ($classification === null) {
                $unclassified[] = $key;
                continue;
            }

            $classified[$key] = $classification;
        }

        return new ConfigCoverageResult($classified, $unclassified);
    }
}

namespace Totman\RuntimeUi\Config;

final class MainConfigImport
{
    /**
     * @param array<string, ImportedField> $fields
     */
    public function __construct(private readonly array $fields)
    {
    }

    /**
     * @return array<string, ImportedField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $key): ImportedField
    {
        if (!isset($this->fields[$key])) {
            throw new \InvalidArgumentException('Unknown imported field: ' . $key);
        }

        return $this->fields[$key];
    }

    /**
     * @return list<ImportedField>
     */
    public function fieldsNeedingOperatorInput(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (ImportedField $field): bool => $field->needsOperatorInput()
        ));
    }
}

namespace Totman\RuntimeUi\Config;

use Totman\RuntimeUi\Contracts\RuntimeFileNames;
use Totman\RuntimeUi\Deployment\DeploymentContext;

final class MainConfigImporter
{
    /** @var array<string, array{critical: bool, default?: mixed, server_generated?: bool}> */
    private const FIELD_RULES = [
        'base_url' => ['critical' => true],
        'mail_from' => ['critical' => true],
        'to_self' => ['critical' => true],
        'sendmail_path' => ['critical' => true],
        'hmac_secret_hex' => ['critical' => true, 'server_generated' => true],
        'recipients_file' => ['critical' => true, 'default' => RuntimeFileNames::RECIPIENTS_LIVE],
        'web_ui_enabled' => ['critical' => false, 'default' => false],
        'state_dir' => ['critical' => false],
        'download_base_dir' => ['critical' => false],
        'lib_file' => ['critical' => false, 'default' => 'totman-lib.php'],
        'web_file' => ['critical' => false, 'default' => 'totman.php'],
        'web_css_file' => ['critical' => false, 'default' => 'totman.css'],
    ];

    public function import(DiscoveryResult $discovery, DeploymentContext $context): MainConfigImport
    {
        return $this->importResolved($context, static function (string $key, array $rule) use ($discovery): array {
            return self::resolveValue($key, $rule, $discovery);
        });
    }

    /**
     * @param array<string, mixed> $config
     */
    public function importConfig(array $config, DeploymentContext $context, string $source = 'draft'): MainConfigImport
    {
        return $this->importResolved($context, static function (string $key, array $rule) use ($config, $source): array {
            if (array_key_exists($key, $config)) {
                return [$config[$key], $source];
            }

            if (array_key_exists('default', $rule)) {
                return [$rule['default'], 'generated'];
            }

            return [null, 'missing'];
        });
    }

    /**
     * @param callable(string, array{critical: bool, default?: mixed, server_generated?: bool}): array{0: mixed, 1: string} $resolver
     */
    private function importResolved(DeploymentContext $context, callable $resolver): MainConfigImport
    {
        $fields = [];
        foreach (self::FIELD_RULES as $key => $rule) {
            [$value, $source] = $resolver($key, $rule);
            $serverGenerated = (bool)($rule['server_generated'] ?? false);
            $critical = $rule['critical'];
            $pathField = in_array($key, ['state_dir', 'download_base_dir', 'lib_file', 'web_file', 'web_css_file'], true);

            $fields[$key] = new ImportedField(
                $key,
                $value,
                $source,
                $critical,
                !$pathField || !$context->pathFieldsAreReadOnly(),
                $this->isPlaceholder($key, $value),
                $this->isInvalid($key, $value),
                $serverGenerated
            );
        }

        return new MainConfigImport($fields);
    }

    /**
     * @param array{critical: bool, default?: mixed, server_generated?: bool} $rule
     * @return array{0: mixed, 1: string}
     */
    private static function resolveValue(string $key, array $rule, DiscoveryResult $discovery): array
    {
        if (array_key_exists($key, $discovery->liveMainConfig())) {
            return [$discovery->liveMainConfig()[$key], 'live'];
        }

        if (array_key_exists($key, $discovery->distMainConfig())) {
            return [$discovery->distMainConfig()[$key], 'dist'];
        }

        if (array_key_exists('default', $rule)) {
            return [$rule['default'], 'generated'];
        }

        return [null, 'missing'];
    }

    private function isPlaceholder(string $key, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            if ($value === []) {
                return true;
            }

            foreach ($value as $item) {
                if ($this->isPlaceholder($key, $item)) {
                    return true;
                }
            }

            return false;
        }

        if (!is_string($value)) {
            return false;
        }

        $lower = strtolower($value);

        return str_contains($lower, 'example.com')
            || str_contains($lower, 'example.invalid')
            || str_contains($lower, 'recipient@example')
            || str_contains($lower, 'operator@example')
            || str_contains($lower, 'change-me')
            || str_contains($lower, 'localhost');
    }

    private function isInvalid(string $key, mixed $value): bool
    {
        return match ($key) {
            'base_url' => !is_string($value) || !str_starts_with($value, 'https://'),
            'mail_from' => !is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false,
            'to_self' => !$this->hasValidMailbox($value),
            'sendmail_path', 'recipients_file', 'state_dir', 'download_base_dir', 'lib_file', 'web_file', 'web_css_file' => !is_string($value) || $value === '',
            'hmac_secret_hex' => !is_string($value) || preg_match('/^[a-f0-9]{64}$/i', $value) !== 1,
            'web_ui_enabled' => !is_bool($value),
            default => false,
        };
    }

    private function hasValidMailbox(mixed $value): bool
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item) || filter_var($item, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
        }

        return $value !== [];
    }
}

namespace Totman\RuntimeUi\Config;

use Totman\RuntimeUi\Contracts\RuntimeFileNames;

final class MainConfigWriter
{
    /** @var list<list<string>> */
    private const GROUPS = [
        [
            'state_dir',
            'lib_file',
            'l18n_dir_name',
            'lock_file',
            'log_file_name',
            'recipients_file',
            'state_file',
            'web_file',
            'web_css_file',
            'download_base_dir',
        ],
        [
            'base_url',
            'hmac_secret_hex',
            'web_ui_enabled',
        ],
        [
            'sendmail_path',
            'mail_from',
            'reply_to',
            'to_self',
        ],
    ];

    public function __construct(
        private readonly string $generatedByLine = 'Generated by the totman runtime UI.'
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function write(string $stateDir, array $config): string
    {
        if (!is_dir($stateDir)) {
            throw new \RuntimeException('State directory does not exist: ' . $stateDir);
        }

        $path = rtrim($stateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . RuntimeFileNames::MAIN_LIVE;
        $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(6));
        $bytes = file_put_contents($tmpPath, $this->render($config), LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException('Unable to write temporary config: ' . $tmpPath);
        }

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Unable to replace config: ' . $path);
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function render(array $config): string
    {
        $ordered = $this->orderedConfig($config);
        $lines = [
            '<?php',
            '',
            '/**',
            ' * ' . $this->generatedByLine,
            ' * This file is runtime-compatible; template comments are not preserved.',
            ' */',
            '',
            'declare(strict_types=1);',
            '',
            'return [',
        ];

        foreach ($ordered as $key => $value) {
            $lines[] = "'" . $key . "' => " . $this->export($value) . ',';
        }

        $lines[] = '];';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function orderedConfig(array $config): array
    {
        $ordered = [];
        foreach (self::GROUPS as $group) {
            foreach ($group as $key) {
                if (array_key_exists($key, $config)) {
                    $ordered[$key] = $config[$key];
                }
            }
        }

        $remaining = array_diff_key($config, $ordered);
        ksort($remaining);

        return $ordered + $remaining;
    }

    private function export(mixed $value): string
    {
        return var_export($value, true);
    }
}

namespace Totman\RuntimeUi\Config;

final class RecipientConfigCoverage
{
    /**
     * @param array<string, mixed> $template
     */
    public function check(array $template): ConfigCoverageResult
    {
        $classified = [];
        $unclassified = [];

        foreach (['files', 'messages', 'recipients'] as $key) {
            if (!array_key_exists($key, $template)) {
                $unclassified[] = $key;
                continue;
            }

            $classified[$key] = $key;
        }

        foreach (array_keys($template) as $key) {
            if (isset($classified[$key])) {
                continue;
            }

            $unclassified[] = $key;
        }

        $this->classifyMessageFeatures($template['messages'] ?? null, $classified, $unclassified);
        $this->classifyRecipientFeatures($template['recipients'] ?? null, $classified, $unclassified);

        return new ConfigCoverageResult($classified, array_values(array_unique($unclassified)));
    }

    /**
     * @param array<string, string> $classified
     * @param list<string> $unclassified
     */
    private function classifyMessageFeatures(mixed $messages, array &$classified, array &$unclassified): void
    {
        if (!is_array($messages) || $messages === []) {
            $unclassified[] = 'messages.entries';
            return;
        }

        $hasAckBlock = false;
        $hasDownloadLinks = false;
        $hasSingleUseNotice = false;

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $body = is_string($message['body'] ?? null) ? $message['body'] : '';
            $hasAckBlock = $hasAckBlock || str_contains($body, '{ACK_BLOCK}');
            $hasDownloadLinks = $hasDownloadLinks || str_contains($body, '{DOWNLOAD_LINKS}');
            $hasSingleUseNotice = $hasSingleUseNotice || is_string($message['single_use_notice'] ?? null);
        }

        $this->classifyRequiredFeature($hasAckBlock, 'messages.ack_block', $classified, $unclassified);
        $this->classifyRequiredFeature($hasDownloadLinks, 'messages.download_links', $classified, $unclassified);
        $this->classifyRequiredFeature($hasSingleUseNotice, 'messages.single_use_notice', $classified, $unclassified);
    }

    /**
     * @param array<string, string> $classified
     * @param list<string> $unclassified
     */
    private function classifyRecipientFeatures(mixed $recipients, array &$classified, array &$unclassified): void
    {
        if (!is_array($recipients) || $recipients === []) {
            $unclassified[] = 'recipients.entries';
            return;
        }

        $hasNormalDownloads = false;
        $hasSingleUseDownloads = false;

        foreach ($recipients as $recipient) {
            if (!is_array($recipient)) {
                continue;
            }

            $hasNormalDownloads = $hasNormalDownloads || (isset($recipient[3]) && is_array($recipient[3]) && $recipient[3] !== []);
            $hasSingleUseDownloads = $hasSingleUseDownloads || (isset($recipient[4]) && is_array($recipient[4]) && $recipient[4] !== []);
        }

        $this->classifyRequiredFeature($hasNormalDownloads, 'recipients.normal_downloads', $classified, $unclassified);
        $this->classifyRequiredFeature($hasSingleUseDownloads, 'recipients.single_use_downloads', $classified, $unclassified);
    }

    /**
     * @param array<string, string> $classified
     * @param list<string> $unclassified
     */
    private function classifyRequiredFeature(bool $present, string $feature, array &$classified, array &$unclassified): void
    {
        if ($present) {
            $classified[$feature] = 'template-feature';
            return;
        }

        $unclassified[] = $feature;
    }
}

namespace Totman\RuntimeUi\Config;

final class RecipientConfigImport
{
    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $messages
     * @param array<int, mixed> $recipients
     * @param array<string, string> $sources
     * @param list<string> $issues
     */
    public function __construct(
        private readonly array $files,
        private readonly array $messages,
        private readonly array $recipients,
        private readonly array $sources,
        private readonly array $issues,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<int, mixed>
     */
    public function recipients(): array
    {
        return $this->recipients;
    }

    public function sourceFor(string $key): string
    {
        return $this->sources[$key] ?? 'missing';
    }

    /**
     * @return list<string>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function readyForFirstRecipient(): bool
    {
        return $this->issues === [];
    }
}

namespace Totman\RuntimeUi\Config;

final class RecipientConfigImporter
{
    public function import(DiscoveryResult $discovery): RecipientConfigImport
    {
        $sources = [];
        $files = $this->resolveTopLevel('files', $discovery, $sources);
        $messages = $this->resolveTopLevel('messages', $discovery, $sources);
        $recipients = $this->resolveTopLevel('recipients', $discovery, $sources);

        return $this->importResolved($files, $messages, $recipients, $sources);
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $messages
     * @param array<int, mixed> $recipients
     */
    public function importConfig(array $files, array $messages, array $recipients, string $source = 'draft'): RecipientConfigImport
    {
        return $this->importResolved($files, $messages, $recipients, [
            'files' => $source,
            'messages' => $source,
            'recipients' => $source,
        ]);
    }

    /**
     * @param array<string, string> $sources
     */
    private function importResolved(mixed $files, mixed $messages, mixed $recipients, array $sources): RecipientConfigImport
    {
        $issues = [
            ...$this->validateFiles($files),
            ...$this->validateMessages($messages),
            ...$this->validateRecipients($recipients, $messages, $files),
        ];

        return new RecipientConfigImport(
            is_array($files) ? $files : [],
            is_array($messages) ? $messages : [],
            is_array($recipients) ? $recipients : [],
            $sources,
            $issues
        );
    }

    /**
     * @param array<string, string> $sources
     */
    private function resolveTopLevel(string $key, DiscoveryResult $discovery, array &$sources): mixed
    {
        if (array_key_exists($key, $discovery->liveRecipientConfig())) {
            $sources[$key] = 'live';
            return $discovery->liveRecipientConfig()[$key];
        }

        if (array_key_exists($key, $discovery->distRecipientConfig())) {
            $sources[$key] = 'dist';
            return $discovery->distRecipientConfig()[$key];
        }

        $sources[$key] = 'missing';
        return null;
    }

    /**
     * @return list<string>
     */
    private function validateFiles(mixed $files): array
    {
        if (!is_array($files)) {
            return ['files must be an array'];
        }

        $issues = [];
        foreach ($files as $alias => $path) {
            if (!is_string($alias) || preg_match('/^[a-z0-9_-]+$/', $alias) !== 1) {
                $issues[] = 'files contains an invalid alias';
            }

            if (!is_string($path) || trim($path) === '' || str_starts_with($path, '/')) {
                $issues[] = "files alias {$alias} must use a non-empty relative path";
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function validateMessages(mixed $messages): array
    {
        if (!is_array($messages) || $messages === []) {
            return ['messages must contain at least one entry'];
        }

        $issues = [];
        foreach ($messages as $key => $entry) {
            if (!is_string($key) || preg_match('/^[a-z0-9_-]+$/', $key) !== 1) {
                $issues[] = 'messages contains an invalid key';
            }

            if (!is_array($entry) || !is_string($entry['subject'] ?? null) || trim($entry['subject']) === '' || !is_string($entry['body'] ?? null) || trim($entry['body']) === '') {
                $issues[] = "message {$key} must contain non-empty subject and body";
                continue;
            }

            if (array_key_exists('single_use_notice', $entry) && !is_string($entry['single_use_notice'])) {
                $issues[] = "message {$key} single_use_notice must be a string";
            }
        }

        return $issues;
    }

    /**
     * @param mixed $recipients
     * @param mixed $messages
     * @param mixed $files
     * @return list<string>
     */
    private function validateRecipients(mixed $recipients, mixed $messages, mixed $files): array
    {
        if (!is_array($recipients) || $recipients === []) {
            return ['recipients must contain at least one entry'];
        }

        $messageKeys = is_array($messages) ? array_keys($messages) : [];
        $fileAliases = is_array($files) ? array_keys($files) : [];
        $issues = [];

        foreach ($recipients as $index => $row) {
            if (!is_array($row) || array_is_list($row) === false || count($row) < 3) {
                $issues[] = "recipient entry #{$index} must be a list with at least three values";
                continue;
            }

            [$name, $mailbox, $messageKey] = $row;
            if (!is_string($name) || trim($name) === '') {
                $issues[] = "recipient entry #{$index} must contain a name";
            }

            if (!is_string($mailbox) || !$this->validMailbox($mailbox) || $this->placeholder($mailbox)) {
                $issues[] = "recipient entry #{$index} must contain a real mailbox";
            }

            if (!is_string($messageKey) || !in_array($messageKey, $messageKeys, true)) {
                $issues[] = "recipient entry #{$index} must reference an existing message";
            }

            foreach ([3 => 'normal', 4 => 'single-use'] as $field => $label) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                if (!is_array($row[$field]) || array_is_list($row[$field]) === false) {
                    $issues[] = "recipient entry #{$index} {$label} file aliases must be a list";
                    continue;
                }

                foreach ($row[$field] as $alias) {
                    if (!is_string($alias) || !in_array($alias, $fileAliases, true)) {
                        $issues[] = "recipient entry #{$index} references an unknown {$label} file alias";
                    }
                }
            }
        }

        return $issues;
    }

    private function validMailbox(string $mailbox): bool
    {
        $address = trim(str_replace(["\r", "\n"], '', $mailbox));
        if (preg_match('/^<([^>]+)>$/', $address, $match) === 1) {
            $address = $match[1];
        } elseif (preg_match('/^(.*)<([^>]+)>$/', $address, $match) === 1) {
            $address = $match[2];
        }

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function placeholder(string $value): bool
    {
        $lower = strtolower($value);

        return str_contains($lower, 'example.com')
            || str_contains($lower, 'example.invalid')
            || str_contains($lower, 'recipient@example');
    }
}

namespace Totman\RuntimeUi\Config;

use Totman\RuntimeUi\Contracts\RuntimeFileNames;

final class RecipientConfigWriter
{
    public function __construct(
        private readonly string $generatedByLine = 'Generated by the totman runtime UI.'
    ) {
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $messages
     * @param array<int, mixed> $recipients
     */
    public function write(
        string $stateDir,
        array $files,
        array $messages,
        array $recipients,
        string $fileName = RuntimeFileNames::RECIPIENTS_LIVE
    ): string {
        if (!is_dir($stateDir)) {
            throw new \RuntimeException('State directory does not exist: ' . $stateDir);
        }

        $path = rtrim($stateDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($fileName);
        $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(6));
        $bytes = file_put_contents($tmpPath, $this->render($files, $messages, $recipients), LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException('Unable to write temporary recipient config: ' . $tmpPath);
        }

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Unable to replace recipient config: ' . $path);
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $messages
     * @param array<int, mixed> $recipients
     */
    public function render(array $files, array $messages, array $recipients): string
    {
        $config = [
            'files' => $files,
            'messages' => $messages,
            'recipients' => $recipients,
        ];

        $lines = [
            '<?php',
            '',
            '/**',
            ' * ' . $this->generatedByLine,
            ' * This file is runtime-compatible; template comments are not preserved.',
            ' */',
            '',
            'declare(strict_types=1);',
            '',
            'return [',
        ];

        foreach ($config as $key => $value) {
            $lines[] = "'" . $key . "' => " . $this->export($value) . ',';
        }

        $lines[] = '];';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function export(mixed $value): string
    {
        return var_export($value, true);
    }
}

namespace Totman\RuntimeUi\Contracts;

final class RuntimeFileNames
{
    public const MAIN_LIVE = 'totman.inc.php';
    public const MAIN_DIST = 'totman.inc.dist.php';
    public const RECIPIENTS_LIVE = 'totman-recipients.php';
    public const RECIPIENTS_DIST = 'totman-recipients.dist.php';

    private function __construct()
    {
    }
}

namespace Totman\RuntimeUi\Deployment;

final class DeploymentCapabilities
{
    /**
     * @param list<string> $setupSources
     */
    public function __construct(
        private readonly bool $pathFieldsReadOnly,
        private readonly bool $browserSetupExpected,
        private readonly bool $manualVolumeEditsExpected,
        private readonly array $setupSources,
    ) {
    }

    public function pathFieldsReadOnly(): bool
    {
        return $this->pathFieldsReadOnly;
    }

    public function browserSetupExpected(): bool
    {
        return $this->browserSetupExpected;
    }

    public function manualVolumeEditsExpected(): bool
    {
        return $this->manualVolumeEditsExpected;
    }

    /**
     * @return list<string>
     */
    public function setupSources(): array
    {
        return $this->setupSources;
    }
}

namespace Totman\RuntimeUi\Deployment;

final class DeploymentContext
{
    public const CLASSIC = 'classic-hosting';
    public const DOCKER = 'docker';
    public const PODMAN = 'podman';

    public function __construct(
        private readonly string $kind,
        private readonly ?string $fixedStateDir = null,
        private readonly ?string $fixedDownloadDir = null,
    ) {
        if (!in_array($kind, [self::CLASSIC, self::DOCKER, self::PODMAN], true)) {
            throw new \InvalidArgumentException('Unsupported deployment context: ' . $kind);
        }
    }

    public static function classic(): self
    {
        return new self(self::CLASSIC);
    }

    public static function docker(string $stateDir = '/var/lib/totman', string $downloadDir = '/var/lib/totman/downloads'): self
    {
        return new self(self::DOCKER, $stateDir, $downloadDir);
    }

    public static function podman(string $stateDir = '/var/lib/totman', string $downloadDir = '/var/lib/totman/downloads'): self
    {
        return new self(self::PODMAN, $stateDir, $downloadDir);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function fixedStateDir(): ?string
    {
        return $this->fixedStateDir;
    }

    public function fixedDownloadDir(): ?string
    {
        return $this->fixedDownloadDir;
    }

    public function pathFieldsAreReadOnly(): bool
    {
        return $this->capabilities()->pathFieldsReadOnly();
    }

    public function capabilities(): DeploymentCapabilities
    {
        $fixedPaths = $this->fixedStateDir !== null || $this->fixedDownloadDir !== null;

        if ($this->kind === self::CLASSIC) {
            return new DeploymentCapabilities(
                $fixedPaths,
                true,
                false,
                ['manual-config', 'browser-ui']
            );
        }

        return new DeploymentCapabilities(
            true,
            true,
            false,
            ['environment', 'browser-ui']
        );
    }
}

namespace Totman\RuntimeUi\Http;

use Totman\RuntimeUi\Application\FirstRunInput;
use Totman\RuntimeUi\Security\AdminAuthInput;

final class FirstRunRequest
{
    public const PREVIEW = 'preview';
    public const UPDATE_DRAFT = 'update_draft';
    public const NEXT_STEP = 'next_step';
    public const PREVIOUS_STEP = 'previous_step';
    public const DISCARD_DRAFT = 'discard_draft';
    public const SAVE_RUNTIME = 'save_runtime';
    public const CREATE_ADMIN = 'create_admin';
    public const LOGIN = 'login';
    public const LOGOUT = 'logout';
    public const REAUTH = 'reauth';
    public const ADMIN_COMMAND = 'admin_command';

    private function __construct(
        private readonly string $intent,
        private readonly FirstRunInput $input,
        private readonly string $setupCode = '',
        private readonly string $csrfToken = '',
        private readonly bool $confirmSave = false,
        private readonly ?AdminAuthInput $authInput = null,
        private readonly string $adminCommandKey = '',
        private readonly string $adminCommandTargetAlias = '',
        private readonly string $adminCommandPhase = 'preview',
        private readonly bool $confirmAdminCommand = false,
    ) {
    }

    public static function preview(): self
    {
        return new self(self::PREVIEW, new FirstRunInput());
    }

    public static function updateDraft(FirstRunInput $input, string $setupCode = '', string $csrfToken = ''): self
    {
        return new self(self::UPDATE_DRAFT, $input, $setupCode, $csrfToken);
    }

    public static function nextStep(FirstRunInput $input, string $setupCode = '', string $csrfToken = ''): self
    {
        return new self(self::NEXT_STEP, $input, $setupCode, $csrfToken);
    }

    public static function previousStep(FirstRunInput $input, string $setupCode = '', string $csrfToken = ''): self
    {
        return new self(self::PREVIOUS_STEP, $input, $setupCode, $csrfToken);
    }

    public static function discardDraft(string $setupCode = '', string $csrfToken = ''): self
    {
        return new self(self::DISCARD_DRAFT, new FirstRunInput(), $setupCode, $csrfToken);
    }

    public static function saveRuntime(FirstRunInput $input, string $setupCode = '', string $csrfToken = '', bool $confirmSave = false): self
    {
        return new self(self::SAVE_RUNTIME, $input, $setupCode, $csrfToken, $confirmSave);
    }

    public static function save(FirstRunInput $input, string $setupCode = '', string $csrfToken = '', bool $confirmSave = false): self
    {
        return self::saveRuntime($input, $setupCode, $csrfToken, $confirmSave);
    }

    public static function createAdmin(AdminAuthInput $authInput, string $setupCode = '', string $csrfToken = ''): self
    {
        return new self(self::CREATE_ADMIN, new FirstRunInput(), $setupCode, $csrfToken, authInput: $authInput);
    }

    public static function login(AdminAuthInput $authInput, string $csrfToken = ''): self
    {
        return new self(self::LOGIN, new FirstRunInput(), csrfToken: $csrfToken, authInput: $authInput);
    }

    public static function logout(string $csrfToken = ''): self
    {
        return new self(self::LOGOUT, new FirstRunInput(), csrfToken: $csrfToken);
    }

    public static function reauth(AdminAuthInput $authInput, string $csrfToken = ''): self
    {
        return new self(self::REAUTH, new FirstRunInput(), csrfToken: $csrfToken, authInput: $authInput);
    }

    public static function adminCommand(
        string $commandKey,
        string $targetAlias = '',
        string $csrfToken = '',
        string $phase = 'preview',
        bool $confirmed = false
    ): self {
        return new self(
            self::ADMIN_COMMAND,
            new FirstRunInput(),
            csrfToken: $csrfToken,
            adminCommandKey: $commandKey,
            adminCommandTargetAlias: $targetAlias,
            adminCommandPhase: $phase,
            confirmAdminCommand: $confirmed
        );
    }

    public function intent(): string
    {
        return $this->intent;
    }

    public function isSave(): bool
    {
        return $this->isRuntimeSave();
    }

    public function isUpdateDraft(): bool
    {
        return $this->intent === self::UPDATE_DRAFT;
    }

    public function isNextStep(): bool
    {
        return $this->intent === self::NEXT_STEP;
    }

    public function isPreviousStep(): bool
    {
        return $this->intent === self::PREVIOUS_STEP;
    }

    public function isDiscardDraft(): bool
    {
        return $this->intent === self::DISCARD_DRAFT;
    }

    public function isRuntimeSave(): bool
    {
        return $this->intent === self::SAVE_RUNTIME;
    }

    public function isCreateAdmin(): bool
    {
        return $this->intent === self::CREATE_ADMIN;
    }

    public function isLogin(): bool
    {
        return $this->intent === self::LOGIN;
    }

    public function isLogout(): bool
    {
        return $this->intent === self::LOGOUT;
    }

    public function isReauth(): bool
    {
        return $this->intent === self::REAUTH;
    }

    public function isAdminCommand(): bool
    {
        return $this->intent === self::ADMIN_COMMAND;
    }

    public function isAuthAction(): bool
    {
        return $this->isCreateAdmin() || $this->isLogin() || $this->isLogout() || $this->isReauth();
    }

    public function isStateChanging(): bool
    {
        return $this->isUpdateDraft() || $this->isNextStep() || $this->isPreviousStep() || $this->isDiscardDraft() || $this->isRuntimeSave() || $this->isAuthAction() || $this->isAdminCommand();
    }

    public function input(): FirstRunInput
    {
        return $this->input;
    }

    public function setupCode(): string
    {
        return $this->setupCode;
    }

    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    public function confirmSave(): bool
    {
        return $this->confirmSave;
    }

    public function authInput(): AdminAuthInput
    {
        return $this->authInput ?? new AdminAuthInput();
    }

    public function adminCommandKey(): string
    {
        return $this->adminCommandKey;
    }

    public function adminCommandTargetAlias(): string
    {
        return $this->adminCommandTargetAlias;
    }

    public function adminCommandPhase(): string
    {
        return $this->adminCommandPhase;
    }

    public function confirmAdminCommand(): bool
    {
        return $this->confirmAdminCommand;
    }
}

namespace Totman\RuntimeUi\Http;

use Totman\RuntimeUi\Application\FirstRunInput;
use Totman\RuntimeUi\Security\AdminAuthInput;

final class FirstRunRequestMapper
{
    /**
     * @param array<string, string> $post
     */
    public function map(string $method, array $post = []): FirstRunRequest
    {
        if (strtoupper($method) !== 'POST') {
            return FirstRunRequest::preview();
        }

        $input = FirstRunInput::fromPost($post);
        $setupCode = $this->clean($post['setup_code'] ?? '');
        $csrfToken = $this->clean($post['csrf_token'] ?? '');
        $action = $this->clean($post['action'] ?? FirstRunRequest::SAVE_RUNTIME);
        $authInput = $this->authInput($post);

        if ($action === FirstRunRequest::UPDATE_DRAFT) {
            return FirstRunRequest::updateDraft($input, $setupCode, $csrfToken);
        }

        if ($action === FirstRunRequest::NEXT_STEP) {
            return FirstRunRequest::nextStep($input, $setupCode, $csrfToken);
        }

        if ($action === FirstRunRequest::PREVIOUS_STEP) {
            return FirstRunRequest::previousStep($input, $setupCode, $csrfToken);
        }

        if ($action === FirstRunRequest::DISCARD_DRAFT) {
            return FirstRunRequest::discardDraft($setupCode, $csrfToken);
        }

        if ($action === FirstRunRequest::CREATE_ADMIN) {
            return FirstRunRequest::createAdmin($authInput, $setupCode, $csrfToken);
        }

        if ($action === FirstRunRequest::LOGIN) {
            return FirstRunRequest::login($authInput, $csrfToken);
        }

        if ($action === FirstRunRequest::LOGOUT) {
            return FirstRunRequest::logout($csrfToken);
        }

        if ($action === FirstRunRequest::REAUTH) {
            return FirstRunRequest::reauth($authInput, $csrfToken);
        }

        if ($action === FirstRunRequest::ADMIN_COMMAND) {
            return FirstRunRequest::adminCommand(
                $this->clean($post['admin_command'] ?? ''),
                $this->clean($post['admin_command_target_alias'] ?? ''),
                $csrfToken,
                $this->clean($post['admin_command_phase'] ?? 'preview'),
                $this->truthy($post['confirm_admin_command'] ?? '')
            );
        }

        return FirstRunRequest::saveRuntime(
            $input,
            $setupCode,
            $csrfToken,
            $this->truthy($post['confirm_save'] ?? '')
        );
    }

    /**
     * @param array<string, string> $post
     */
    private function authInput(array $post): AdminAuthInput
    {
        return new AdminAuthInput(
            $this->clean($post['admin_username'] ?? ''),
            $this->clean($post['admin_password'] ?? ''),
            $this->clean($post['admin_password_confirm'] ?? ''),
            $this->clean($post['login_username'] ?? ''),
            $this->clean($post['login_password'] ?? ''),
            $this->clean($post['reauth_password'] ?? '')
        );
    }

    private function clean(string $value): string
    {
        return trim(str_replace(["\r", "\0"], '', $value));
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}

namespace Totman\RuntimeUi\Http;

final class ProductRuntimeContextAdapter
{
    public const DEFAULT_STATE_DIR = '/var/lib/totman';

    public function __construct(private readonly PrototypeEnvironmentFactory $environmentFactory = new PrototypeEnvironmentFactory())
    {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $env
     */
    public function fromArrays(
        array $server,
        array $post,
        array $env,
        string $defaultStateDir = self::DEFAULT_STATE_DIR
    ): PrototypeEnvironment {
        return $this->environmentFactory->fromArrays(
            [],
            $server,
            $post,
            $env,
            $defaultStateDir,
            RuntimeUiMode::PRODUCT
        );
    }
}

namespace Totman\RuntimeUi\Http;

use Totman\RuntimeUi\Application\PrototypePageApplicationService;
use Totman\RuntimeUi\Deployment\DeploymentContext;

final class PrototypeController
{
    public function __construct(
        private readonly PrototypePageApplicationService $pageService,
        private readonly FirstRunRequestMapper $requestMapper = new FirstRunRequestMapper(),
        private readonly PrototypeRenderer $renderer = new PrototypeRenderer(),
    ) {
    }

    /**
     * @param array<string, string> $post
     */
    public function handle(string $stateDir, DeploymentContext $context, string $method, array $post = []): string
    {
        $request = $this->requestMapper->map($method, $post);
        $result = $this->pageService->handle($stateDir, $context, $request);

        return $this->renderer->render($result->view(), $result->access(), $result->csrfToken(), $result->adminAuth(), $result->adminInspection());
    }
}

namespace Totman\RuntimeUi\Http;

use Totman\RuntimeUi\Deployment\DeploymentContext;

final class PrototypeEnvironment
{
    /**
     * @param array<string, string> $post
     */
    public function __construct(
        private readonly string $stateDir,
        private readonly string $stateDirSource,
        private readonly string $runtimeUiMode,
        private readonly DeploymentContext $context,
        private readonly string $method,
        private readonly array $post,
        private readonly string $expectedSetupCode,
    ) {
    }

    public function stateDir(): string
    {
        return $this->stateDir;
    }

    public function stateDirSource(): string
    {
        return $this->stateDirSource;
    }

    public function runtimeUiMode(): string
    {
        return $this->runtimeUiMode;
    }

    public function context(): DeploymentContext
    {
        return $this->context;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, string>
     */
    public function post(): array
    {
        return $this->post;
    }

    public function expectedSetupCode(): string
    {
        return $this->expectedSetupCode;
    }
}

namespace Totman\RuntimeUi\Http;

use Totman\RuntimeUi\Deployment\DeploymentContext;

final class PrototypeEnvironmentFactory
{
    public const QUERY_STATE_DIR = 'query';
    public const ENV_STATE_DIR = 'environment';
    public const PRODUCT_ENV_STATE_DIR = 'product-environment';
    public const DEFAULT_STATE_DIR = 'default';

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $env
     */
    public function fromArrays(
        array $query,
        array $server,
        array $post,
        array $env,
        string $defaultStateDir,
        string $runtimeUiMode = RuntimeUiMode::PROTOTYPE,
    ): PrototypeEnvironment {
        $runtimeUiMode = RuntimeUiMode::normalise($runtimeUiMode);
        [$stateDir, $source] = $this->stateDir($query, $env, $defaultStateDir, $runtimeUiMode);
        $contextName = $this->contextName($query, $env, $runtimeUiMode);

        return new PrototypeEnvironment(
            $stateDir,
            $source,
            $runtimeUiMode,
            $this->context($contextName, $stateDir),
            $this->string($server['REQUEST_METHOD'] ?? null) ?: 'GET',
            $this->post($post),
            $this->setupCode($env, $runtimeUiMode)
        );
    }

    public function ensureStateDirectory(string $stateDir): void
    {
        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0700, true);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $env
     * @return array{0: string, 1: string}
     */
    private function stateDir(array $query, array $env, string $defaultStateDir, string $runtimeUiMode): array
    {
        if ($runtimeUiMode === RuntimeUiMode::PROTOTYPE) {
            $queryStateDir = $this->string($query['state_dir'] ?? null);
            if ($queryStateDir !== '') {
                return [$queryStateDir, self::QUERY_STATE_DIR];
            }
        }

        if ($runtimeUiMode === RuntimeUiMode::PRODUCT) {
            $productStateDir = $this->string($env['TOTMAN_STATE_DIR'] ?? null);
            if ($productStateDir !== '') {
                return [$productStateDir, self::PRODUCT_ENV_STATE_DIR];
            }

            return [$defaultStateDir, self::DEFAULT_STATE_DIR];
        }

        $envStateDir = $this->string($env['TOTMAN_STATE_DIR'] ?? null);
        if ($envStateDir !== '') {
            return [$envStateDir, self::ENV_STATE_DIR];
        }

        return [$defaultStateDir, self::DEFAULT_STATE_DIR];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $env
     */
    private function contextName(array $query, array $env, string $runtimeUiMode): string
    {
        if ($runtimeUiMode === RuntimeUiMode::PROTOTYPE) {
            $queryContext = $this->string($query['context'] ?? null);
            if ($queryContext !== '') {
                return $queryContext;
            }
        }

        if ($runtimeUiMode === RuntimeUiMode::PRODUCT) {
            return $this->string($env['TOTMAN_UI_DEPLOYMENT_CONTEXT'] ?? null) ?: 'classic';
        }

        return $this->string($env['TOTMAN_UI_DEPLOYMENT_CONTEXT'] ?? null) ?: 'classic';
    }

    /**
     * @param array<string, mixed> $env
     */
    private function setupCode(array $env, string $runtimeUiMode): string
    {
        if ($runtimeUiMode === RuntimeUiMode::PRODUCT) {
            $productSetupCode = $this->string($env['TOTMAN_UI_SETUP_CODE'] ?? null);
            if ($productSetupCode !== '') {
                return $productSetupCode;
            }

            return '';
        }

        return $this->string($env['TOTMAN_UI_SETUP_CODE'] ?? null);
    }

    private function context(string $contextName, string $stateDir): DeploymentContext
    {
        return match ($contextName) {
            'docker' => DeploymentContext::docker($stateDir, $stateDir . '/downloads'),
            'podman' => DeploymentContext::podman($stateDir, $stateDir . '/downloads'),
            default => DeploymentContext::classic(),
        };
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function post(array $post): array
    {
        $normalised = [];
        foreach ($post as $key => $value) {
            $normalised[$key] = is_scalar($value) ? (string)$value : '';
        }

        return $normalised;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}

namespace Totman\RuntimeUi\Http;

use Totman\RuntimeUi\Application\FirstRunField;
use Totman\RuntimeUi\Application\AdminCommandCatalog;
use Totman\RuntimeUi\Application\AdminAuthViewModel;
use Totman\RuntimeUi\Application\AdminInspectionViewModel;
use Totman\RuntimeUi\Application\FirstRunHiddenFieldPolicy;
use Totman\RuntimeUi\Application\FirstRunViewModel;
use Totman\RuntimeUi\Application\MaintenanceCommandResult;
use Totman\RuntimeUi\Application\RuntimeUiTextCatalog;
use Totman\RuntimeUi\Security\SetupAccessResult;

final class PrototypeRenderer
{
    private const PRODUCT_LOGO_DATA_URI = 'data:image/png;base64,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';

    public function __construct(
        private readonly FirstRunHiddenFieldPolicy $hiddenFieldPolicy = new FirstRunHiddenFieldPolicy(),
        private readonly RuntimeUiTextCatalog $text = new RuntimeUiTextCatalog(),
    ) {
    }

    public function render(
        FirstRunViewModel $view,
        ?SetupAccessResult $access = null,
        string $csrfToken = '',
        ?AdminAuthViewModel $adminAuth = null,
        ?AdminInspectionViewModel $adminInspection = null,
    ): string {
        $steps = $this->renderSteps($view);
        $errors = '';
        foreach ($view->errors() as $error) {
            $errors .= '<li>' . $this->e($error) . '</li>';
        }

        if ($access !== null && !$access->allowed() && $access->message() !== '') {
            $errors .= '<li>' . $this->e($access->message()) . '</li>';
        }

        $notice = $view->notice() !== '' ? '<p class="notice ok ui-status" role="status">' . $this->e($view->notice()) . '</p>' : '';
        $alert = $errors !== '' ? '<ul class="notice error ui-alert" role="alert">' . $errors . '</ul>' : '';

        if ($this->text->productMode()) {
            return $this->renderDocument($this->renderProductPage(
                $view,
                $csrfToken,
                $adminAuth ?? AdminAuthViewModel::accessMissing(),
                $adminInspection ?? AdminInspectionViewModel::unavailable($this->text->get('admin.unavailable_after_signin')),
                $notice,
                $alert,
            ));
        }

        $authView = $adminAuth ?? AdminAuthViewModel::accessMissing();
        if ($authView->showAdministrationDisabled()) {
            $content = $this->renderHeader($csrfToken, $authView)
                . $notice
                . $alert
                . $this->renderSection('admin-access', $this->text->get('section.admin_access'), $this->renderAdminAccess($csrfToken, $authView))
                . $this->renderFooter();

            return $this->renderDocument($content);
        }

        if ($authView->showLogin()) {
            $content = $this->renderHeader($csrfToken, $authView)
                . $notice
                . $alert
                . $this->renderSection('admin-access', $this->text->get('section.admin_access'), $this->renderAdminAccess($csrfToken, $authView))
                . $this->renderFooter();

            return $this->renderDocument($content);
        }

        $wizard = '<form class="ui-form ui-wizard-form form-grid" method="post">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
' . $this->renderHiddenFields($view) . '
<div class="input-group ui-field ui-setup-code full-width"><label>' . $this->e($this->text->get('wizard.setup_code')) . ' <input name="setup_code"></label></div>
' . $this->renderCurrentStep($view) . '
<div class="action-bar ui-actions full-width">' . $this->renderActions($view) . '</div>
</form>';

        $content = $this->renderHeader($csrfToken, $authView)
            . $notice
            . $alert
            . $this->renderSection('summary', $this->text->get('section.summary'), $this->renderSummary($view))
            . $this->renderSection('steps', $this->text->get('section.steps'), $steps)
            . $this->renderSection('admin-access', $this->text->get('section.admin_access'), $this->renderAdminAccess($csrfToken, $authView))
            . $this->renderSection('runtime-inspection', $this->text->get('section.runtime_inspection'), $this->renderAdminInspection($csrfToken, $adminInspection ?? AdminInspectionViewModel::unavailable($this->text->get('admin.unavailable_after_signin'))))
            . $this->renderSection('wizard', $this->text->get('section.wizard_step'), $wizard)
            . $this->renderFooter();

        return $this->renderDocument($content);
    }

    private function renderProductPage(
        FirstRunViewModel $view,
        string $csrfToken,
        AdminAuthViewModel $adminAuth,
        AdminInspectionViewModel $adminInspection,
        string $notice,
        string $alert,
    ): string {
        if ($adminAuth->showSetupLocked()) {
            return $this->renderHeader($csrfToken, $adminAuth)
                . $notice
                . $alert
                . $this->renderProductSetupLocked()
                . $this->renderFooter();
        }

        if ($adminAuth->showCreateAdmin()) {
            return $this->renderHeader($csrfToken, $adminAuth)
                . $notice
                . $alert
                . $this->renderProductInitialSetup($csrfToken)
                . $this->renderFooter();
        }

        if ($adminAuth->showAdministrationDisabled()) {
            return $this->renderHeader($csrfToken, $adminAuth)
                . $notice
                . $alert
                . $this->renderProductAdministrationDisabled()
                . $this->renderFooter();
        }

        if ($adminAuth->showLogin()) {
            return $this->renderHeader($csrfToken, $adminAuth)
                . $notice
                . $alert
                . $this->renderProductLogin($csrfToken, $adminAuth)
                . $this->renderFooter();
        }

        $activeSection = $adminInspection->available() && $adminAuth->showSignedIn() ? 'runtime' : 'setup';
        $content = $this->renderHeader($csrfToken, $adminAuth)
            . $notice
            . $alert
            . $this->renderMainNavigation($activeSection, $adminInspection->available())
            . ($activeSection === 'runtime'
                ? $this->renderRuntimeArea($csrfToken, $adminAuth, $adminInspection)
                : $this->renderSetupArea($view, $csrfToken, $adminAuth))
            . $this->renderFooter();

        return $content;
    }

    private function renderProductInitialSetup(string $csrfToken): string
    {
        return '<div class="auth-main view-animate">
<section class="card auth-card" aria-labelledby="initial-setup-title">
<h2 id="initial-setup-title">' . $this->e($this->text->get('setup.initial_title')) . '</h2>
<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
' . $this->renderPlainInputGroup('setup-code', 'setup_code', $this->text->get('setup.code'), $this->text->get('setup.code_help'), 'text', 'one-time-code') . '
' . $this->renderPlainInputGroup('setup-user', 'admin_username', $this->text->get('setup.username'), $this->text->get('setup.username_help'), 'text', 'username') . '
' . $this->renderPlainInputGroup('setup-password', 'admin_password', $this->text->get('setup.password'), $this->text->get('setup.password_help'), 'password', 'new-password') . '
' . $this->renderPlainInputGroup('setup-password-confirm', 'admin_password_confirm', $this->text->get('setup.repeat_password'), $this->text->get('setup.repeat_password_help'), 'password', 'new-password') . '
' . $this->renderReadonlyInputGroup('setup-state-dir', $this->text->get('setup.data_directory'), $this->text->get('setup.data_directory_default'), $this->text->get('setup.data_directory_help'), 'full-width') . '
<div class="action-bar full-width"><button type="submit" name="action" value="create_admin" class="btn-primary">' . $this->e($this->text->get('setup.create_access')) . '</button></div>
</form>
</section>
</div>';
    }

    private function renderProductAdministrationDisabled(): string
    {
        return '<div class="auth-main view-animate">
<section class="card auth-card" aria-labelledby="admin-disabled-title">
<h2 id="admin-disabled-title">' . $this->e($this->text->get('section.admin_access')) . '</h2>
<p class="notice neutral" role="status">' . $this->e($this->text->get('admin.disabled')) . '</p>
</section>
</div>';
    }

    private function renderProductLogin(string $csrfToken, AdminAuthViewModel $adminAuth): string
    {
        return '<div class="auth-main view-animate">
<section class="card auth-card" aria-labelledby="admin-login-title">
<h2 id="admin-login-title">' . $this->e($this->text->get('admin.sign_in')) . '</h2>
' . $this->renderAdminAccess($csrfToken, $adminAuth) . '
</section>
</div>';
    }

    private function renderProductSetupLocked(): string
    {
        return '<div class="auth-main view-animate">
<section class="card auth-card" aria-labelledby="initial-setup-title">
<h2 id="initial-setup-title">' . $this->e($this->text->get('setup.initial_title')) . '</h2>
<p class="notice error" role="alert">' . $this->e($this->text->get('setup.locked')) . '</p>
<p class="helper-text">' . $this->e($this->text->get('setup.locked_help')) . '</p>
</section>
</div>';
    }

    private function renderMainNavigation(string $activeSection, bool $runtimeAvailable): string
    {
        $items = [
            ['setup', 'Setup', true],
            ['runtime', 'Runtime', $runtimeAvailable],
            ['maintenance', 'Maintenance', $runtimeAvailable],
        ];
        $buttons = '';
        foreach ($items as [$key, $label, $enabled]) {
            $active = $key === $activeSection;
            $buttons .= '<button type="button" class="nav-item' . ($active ? ' active' : '') . '"'
                . ($active ? ' aria-selected="true"' : ' aria-selected="false"')
                . (!$enabled ? ' disabled' : '')
                . '>' . $this->e($label) . '</button>';
        }

        return '<nav class="ui-main-nav view-animate" role="tablist" aria-label="Main views">' . $buttons . '</nav>';
    }

    private function renderSetupArea(FirstRunViewModel $view, string $csrfToken, AdminAuthViewModel $adminAuth): string
    {
        $wizard = '<form class="ui-form ui-wizard-form form-grid" method="post">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
' . $this->renderHiddenFields($view) . '
' . $this->renderSetupCodeField() . '
' . $this->renderCurrentStep($view) . '
<div class="action-bar ui-actions full-width">' . $this->renderActions($view) . '</div>
</form>';

        return '<section class="ui-section setup-view view-animate" data-section="setup" aria-labelledby="setup-title">
<div class="section-heading">
<p class="section-kicker">First-run setup</p>
<h2 id="setup-title">' . $this->e($this->stepTitle($view, $view->currentStep())) . '</h2>
<p class="section-intro">' . $this->e($this->shortStepDescription($view, $view->currentStep())) . '</p>
</div>
' . $this->renderSteps($view) . '
<div class="setup-layout">
<section class="card setup-step-card" aria-labelledby="setup-current-step">
<h3 id="setup-current-step">' . $this->e($this->text->get('section.setup_details')) . '</h3>
' . $wizard . '
</section>
<aside class="card setup-admin-card" aria-labelledby="setup-admin-access">
<h3 id="setup-admin-access">' . $this->e($this->text->get('section.admin_access')) . '</h3>
' . $this->renderAdminAccess($csrfToken, $adminAuth) . '
</aside>
</div>
</section>';
    }

    private function renderRuntimeArea(string $csrfToken, AdminAuthViewModel $adminAuth, AdminInspectionViewModel $adminInspection): string
    {
        return '<section class="ui-section runtime-view view-animate" data-section="runtime" aria-labelledby="runtime-title">
<div class="section-heading">
<p class="section-kicker">Runtime</p>
<h2 id="runtime-title">' . $this->e($this->text->get('section.runtime_inspection')) . '</h2>
<p class="section-intro">' . $this->e($this->text->get('inspection.unavailable_after_signin_intro')) . '</p>
</div>
<p class="notice ok" role="status">' . $this->e($this->text->format('admin.signed_in_as', 'username', $adminAuth->username())) . '</p>
<div class="card runtime-card">' . $this->renderAdminInspection($csrfToken, $adminInspection) . '</div>
</section>';
    }

    private function renderDocument(string $content): string
    {
        $mode = $this->text->productMode() ? 'product' : 'prototype';

        return '<!doctype html>
<html lang="en-GB">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $this->e($this->text->get('page.title')) . '</title>
' . $this->renderStylesheetLink() . '
<body class="ui-shell mode-' . $this->e($mode) . '" data-runtime-ui-mode="' . $this->e($mode) . '" data-surface="' . $this->e($mode) . '">
<div class="container">
<main class="ui-main">
' . $content . '
</main>
</div>
' . $this->renderScriptLink() . '
</body>
</html>';
    }

    public function stylesheet(): string
    {
        if (!$this->text->productMode()) {
            return '';
        }

        return ':root{--bg-main:#f1f5f9;--bg-surface:#fff;--text-primary:#0f172a;--text-muted:#334155;--bg-card:rgba(255,255,255,.9);--accent:#c1121f;--accent-hover:#9f0f1a;--accent-glow:rgba(193,18,31,.16);--border:#cbd5e1;--success:#15803d;--warning:#2563eb;--danger:#b91c1c;--radius-s:8px;--radius-m:12px;--space-s:.5rem;--space-m:1rem;--space-l:1.6rem}
html[data-theme=dark]{--bg-main:#0b0f1a;--bg-surface:#161e2e;--bg-card:rgba(22,30,46,.92);--text-primary:#f8fafc;--text-muted:#cbd5e1;--accent:#e63946;--accent-hover:#ff5d68;--accent-glow:rgba(230,57,70,.25);--border:#334155;--success:#22c55e;--warning:#60a5fa;--danger:#f87171}
*{box-sizing:border-box;margin:0;padding:0}
body.mode-product{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:var(--bg-main);color:var(--text-primary);line-height:1.55;min-height:100vh}
.mode-product .container{width:min(100% - 3rem,1200px);margin-inline:auto;padding-block:var(--space-l)}
.mode-product .header{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;margin-bottom:var(--space-m);gap:var(--space-m)}
.mode-product .brand{display:flex;align-items:center;gap:1rem;color:inherit;text-decoration:none;border-radius:var(--radius-m)}
.mode-product .brand-logo{width:clamp(56px,9vw,112px);height:auto;display:block}
.mode-product .brand-text h1{font-size:clamp(2rem,1.6rem + 1.8vw,3.4rem);line-height:.95;font-weight:950;letter-spacing:-.02em}
.mode-product .tagline{margin-top:.25rem;color:var(--text-muted);font-size:clamp(.95rem,.85rem + .35vw,1.12rem);font-weight:800}
.mode-product .header-actions{display:flex;align-items:center;justify-content:flex-end;gap:.65rem;flex-wrap:wrap;max-width:min(100%,42rem)}
.mode-product .ui-main-nav{display:flex;flex-wrap:wrap;gap:.35rem;width:max-content;max-width:100%;padding:.4rem;margin:0 0 var(--space-l);background:color-mix(in srgb,var(--bg-surface),var(--bg-main)30%);border:1px solid var(--border);border-radius:999px}
.mode-product .ui-main-nav .nav-item{border:0;border-radius:999px;background:transparent;color:var(--text-muted);min-height:0;padding:.72rem 1.05rem;font-weight:900;transition:background-color .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}
.mode-product .ui-main-nav .nav-item:hover:not(:disabled):not(.active){background:var(--accent-glow);color:var(--accent);transform:translateY(-1px)}
.mode-product .ui-main-nav .nav-item.active{background:var(--accent);color:#fff;box-shadow:0 6px 18px var(--accent-glow)}
.mode-product .ui-main-nav .nav-item:disabled{opacity:.45}
.mode-product .language-menu{position:relative}
.mode-product .language-menu summary{list-style:none;min-height:46px;display:inline-flex;align-items:center;gap:.65rem;border:1.5px solid var(--border);border-radius:var(--radius-m);background:var(--bg-surface);color:var(--text-primary);font-weight:900;font-size:.95rem;padding:.65rem 2.15rem .65rem .9rem;cursor:pointer;user-select:none}
.mode-product .language-menu summary::-webkit-details-marker{display:none}
.mode-product .language-menu summary:after{content:"";position:absolute;right:.9rem;top:50%;width:.55rem;height:.55rem;border-right:2px solid var(--text-muted);border-bottom:2px solid var(--text-muted);transform:translateY(-65%) rotate(45deg);pointer-events:none}
.mode-product .language-menu[open] summary{border-color:var(--accent);box-shadow:0 0 0 4px var(--accent-glow);background:var(--bg-surface)}
.mode-product .language-menu[open] summary:after{transform:translateY(-25%) rotate(225deg)}
.mode-product .language-options{position:absolute;right:0;top:calc(100% + .45rem);z-index:50;min-width:100%;padding:.35rem;border:1px solid var(--border);border-radius:var(--radius-m);background:var(--bg-surface);box-shadow:0 14px 36px rgba(15,23,42,.2);display:grid;gap:.25rem}
.mode-product .language-options button{min-height:0;border:0;border-radius:8px;background:transparent;color:var(--text-primary);font:inherit;font-weight:800;text-align:left;padding:.6rem .75rem;cursor:pointer;justify-content:flex-start;transition:background-color .18s ease,color .18s ease}
.mode-product .language-options button:hover,.mode-product .language-options button[aria-current=true]{background:var(--accent-glow);color:var(--accent)}
.mode-product .theme-toggle{width:46px;height:46px;min-height:46px;padding:0!important;font-size:1.25rem;line-height:1;color:var(--text-primary);font-weight:900}
.mode-product .theme-icon{display:none;line-height:1}
.mode-product .theme-toggle[data-theme-resolved=light] .theme-icon-light,.mode-product .theme-toggle[data-theme-resolved=dark] .theme-icon-dark{display:block}
.mode-product .signout-btn{border-color:rgba(185,28,28,.35)!important;background:rgba(185,28,28,.08)!important;color:var(--danger)!important}
.mode-product .signout-btn:hover:not(:disabled){border-color:var(--danger)!important;background:rgba(185,28,28,.14)!important;color:var(--danger)!important}
.mode-product .card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-m);padding:var(--space-l);margin-bottom:var(--space-l);box-shadow:0 1px 3px rgba(0,0,0,.1)}
.mode-product .card>h2,.mode-product .card>h3{margin-bottom:calc(var(--space-m) + .45rem)}
.mode-product .auth-main{display:grid;place-items:start center;padding-block:clamp(1rem,4vw,3rem)}
.mode-product .auth-card{width:min(100%,760px)}
.mode-product .auth-card .form-grid{margin-top:var(--space-m)}
.mode-product h2{font-size:clamp(1.25rem,1.05rem + .8vw,1.75rem);line-height:1.15}
.mode-product h3{font-size:1.08rem;margin:var(--space-m) 0 .55rem}
.mode-product h4{font-size:1rem;margin:var(--space-m) 0 .45rem}
.mode-product p{margin-bottom:var(--space-m)}
.mode-product .dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:var(--space-s);margin-bottom:var(--space-m)}
.mode-product .stat-card{display:flex;align-items:center;gap:1rem;padding:1rem;margin-bottom:0;background:var(--bg-surface);min-height:86px}
.mode-product .stat-icon{width:42px;height:42px;background:rgba(100,116,139,.14);color:#475569;border-radius:10px;display:grid;place-items:center;font-weight:900;flex:0 0 auto}
html[data-theme=dark] .mode-product .stat-icon{background:rgba(148,163,184,.14);color:#cbd5e1}
.mode-product .stat-content{display:flex;flex-direction:column;gap:2px;min-width:0}
.mode-product .stat-label{font-size:.72rem;font-weight:800;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em}
.mode-product .stat-value{font-size:clamp(1rem,.8rem + 1vw,1.35rem);font-weight:900;overflow-wrap:anywhere}
.mode-product .stat-desc,.mode-product .helper-text,.mode-product .text-muted{color:var(--text-muted);font-size:.85rem}
.mode-product .section-heading{margin:0 0 var(--space-m)}
.mode-product .section-kicker{margin:0 0 .25rem;color:var(--accent);font-size:.78rem;font-weight:950;letter-spacing:.08em;text-transform:uppercase}
.mode-product .section-intro{color:var(--text-muted);max-width:48rem}
.mode-product .setup-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(18rem,24rem);gap:var(--space-l);align-items:start}
.mode-product .setup-step-card{margin-bottom:0}
.mode-product .setup-admin-card{margin-bottom:0}
.mode-product .sub-nav{display:flex;flex-wrap:wrap;gap:1.15rem;padding:0;margin:0 0 var(--space-l);background:transparent;border:0;border-bottom:1px solid var(--border);border-radius:0;box-shadow:none}
.mode-product .setup-step-list{list-style:none;padding-left:0}
.mode-product .setup-step-list .sub-nav-item{list-style:none;border:0;border-bottom:3px solid transparent;border-radius:0;background:transparent;padding:.45rem 0 .65rem;color:var(--text-muted);font-size:.92rem;font-weight:850;transition:color .18s ease,border-color .18s ease}
.mode-product .setup-step-list .sub-nav-item[aria-current=step]{color:var(--accent);border-bottom-color:var(--accent)}
.mode-product .setup-step-list .sub-nav-item.is-complete{color:var(--success)}
.mode-product .setup-step-list .sub-nav-item.is-blocked{color:var(--danger)}
.mode-product .setup-step-list small{display:block;font-size:.72rem;font-weight:800;opacity:.8}
.mode-product .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:var(--space-m)var(--space-l)}
.mode-product .full-width,.mode-product fieldset{grid-column:1/-1}
.mode-product fieldset{border:1px solid var(--border);border-radius:var(--radius-m);padding:var(--space-m);background:var(--bg-surface)}
.mode-product legend{padding:0 .45rem;font-weight:900;color:var(--text-primary)}
.mode-product .input-group{margin-bottom:.95rem}
.mode-product .field-label-row{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:.45rem}
.mode-product .input-group label,.mode-product .group-label,.mode-product label{font-size:1rem;font-weight:850;color:var(--text-muted)}
.mode-product .help-wrap{position:relative;display:inline-flex;align-items:center}
.mode-product .help-trigger{width:1.45rem;height:1.45rem;min-height:0;padding:0!important;border-radius:999px!important;border:1.5px solid var(--border)!important;background:var(--bg-surface)!important;color:var(--text-muted)!important;font-size:.8rem!important;font-weight:950!important;line-height:1}
.mode-product .help-popover{position:absolute;right:0;top:calc(100% + .45rem);z-index:60;display:none;width:min(18rem,80vw);padding:.75rem .85rem;border:1px solid var(--border);border-radius:var(--radius-s);background:var(--bg-surface);color:var(--text-primary);box-shadow:0 14px 36px rgba(15,23,42,.22);font-size:.84rem;font-weight:700}
.mode-product .help-wrap:hover .help-popover,.mode-product .help-wrap:focus-within .help-popover{display:block}
.mode-product .field-meta{margin:.2rem 0 0;color:var(--text-muted);font-size:.78rem;font-weight:750}
.mode-product .field-errors{margin:.45rem 0 0;padding-left:1.1rem;color:var(--danger);font-size:.86rem;font-weight:800}
.mode-product form label{display:grid;gap:.45rem;max-width:44rem}
.mode-product form label:has(input[type=checkbox]){display:inline-flex;align-items:center;gap:.55rem}
.mode-product input,.mode-product textarea,.mode-product select{width:100%;padding:.85rem 1rem;background:var(--bg-main);border:1.5px solid var(--border);border-radius:var(--radius-s);color:var(--text-primary);font:inherit;font-weight:600}
.mode-product .password-input-wrap{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.55rem;align-items:stretch}
.mode-product .password-input-wrap input{min-width:0}
.mode-product .password-toggle{min-height:0!important;padding:.75rem .9rem!important;white-space:nowrap}
.mode-product textarea{min-height:8.5rem;resize:vertical}
.mode-product input[type=checkbox]{width:1.05rem;height:1.05rem;margin:0;accent-color:var(--accent)}
.mode-product input:focus,.mode-product textarea:focus,.mode-product select:focus{outline:none;border-color:var(--accent);background:var(--bg-surface);box-shadow:0 0 0 4px var(--accent-glow)}
.mode-product input[readonly]{opacity:.78}
.mode-product button{border-radius:var(--radius-m);font-weight:700;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;justify-content:center;gap:.6rem;min-height:54px;font:inherit;font-size:1rem;line-height:1.2}
.mode-product button[name=action][value=save_runtime],.mode-product button[name=action][value=create_admin],.mode-product button[name=action][value=login],.mode-product button[name=action][value=admin_command]{background:var(--accent);color:white;border:0;padding:1rem 1.65rem}
.mode-product button[name=action][value=save_runtime]:hover:not(:disabled),.mode-product button[name=action][value=create_admin]:hover:not(:disabled),.mode-product button[name=action][value=login]:hover:not(:disabled),.mode-product button[name=action][value=admin_command]:hover:not(:disabled){background:var(--accent-hover);transform:translateY(-1px)}
.mode-product button:not([name=action]),.mode-product button[name=action][value=update_draft],.mode-product button[name=action][value=discard_draft],.mode-product button[name=action][value=previous_step],.mode-product button[name=action][value=next_step],.mode-product button[name=action][value=reauth],.mode-product button[name=action][value=logout]{background:var(--bg-main);color:var(--text-primary);border:1.5px solid var(--border);padding:.95rem 1.45rem}
.mode-product button:not([name=action]):hover:not(:disabled),.mode-product button[name=action][value=update_draft]:hover:not(:disabled),.mode-product button[name=action][value=discard_draft]:hover:not(:disabled),.mode-product button[name=action][value=previous_step]:hover:not(:disabled),.mode-product button[name=action][value=next_step]:hover:not(:disabled),.mode-product button[name=action][value=reauth]:hover:not(:disabled),.mode-product button[name=action][value=logout]:hover:not(:disabled){border-color:var(--accent);color:var(--accent)}
.mode-product button:disabled{opacity:.55;cursor:not-allowed}
.mode-product :focus-visible{outline:3px solid var(--accent);outline-offset:3px}
.mode-product .action-bar{display:flex;justify-content:flex-end;align-items:center;gap:.8rem;width:100%;margin-block:var(--space-l);flex-wrap:wrap}
.mode-product .notice{padding:.9rem 1rem;border-radius:var(--radius-s);margin-bottom:var(--space-m);font-weight:800}
.mode-product .notice.ok{background:rgba(21,128,61,.12);color:var(--success);border:1px solid rgba(21,128,61,.3)}
.mode-product .notice.error{background:rgba(185,28,28,.12);color:var(--danger);border:1px solid rgba(185,28,28,.3)}
.mode-product .notice.neutral{background:rgba(37,99,235,.12);color:var(--warning);border:1px solid rgba(37,99,235,.3)}
.mode-product .ui-alert{padding-left:2rem}
.mode-product .preflight-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.75rem;margin-top:var(--space-m)}
.mode-product .preflight-item{display:flex;flex-direction:column;gap:.25rem;border:1px solid var(--border);border-left-width:5px;border-radius:var(--radius-s);padding:.75rem;background:var(--bg-surface)}
.mode-product .preflight-item.status-ok{border-left-color:var(--success)}
.mode-product .preflight-item.status-warn{border-left-color:var(--warning)}
.mode-product .preflight-item.status-fail{border-left-color:var(--danger)}
.mode-product .preflight-item small,.mode-product .helper-text,.mode-product .text-muted{color:var(--text-muted);font-size:.85rem}
.mode-product .terminal-card{background:var(--bg-card);color:var(--text-primary)}
.mode-product .log-window{display:block;white-space:normal;border:1px solid var(--border);border-radius:var(--radius-s);background:var(--bg-main);color:var(--text-primary);padding:.35rem;min-height:18rem;max-height:clamp(18rem,52vh,42rem);overflow:auto}
.mode-product .log-line{white-space:pre-wrap;overflow-wrap:anywhere;padding:.42rem .55rem;border-radius:6px}
.mode-product .log-line:nth-child(odd){background:rgba(15,23,42,.035)}
.mode-product .log-line:nth-child(even){background:rgba(193,18,31,.06)}
html[data-theme=dark] .mode-product .log-line:nth-child(odd){background:rgba(248,250,252,.045)}
html[data-theme=dark] .mode-product .log-line:nth-child(even){background:rgba(230,57,70,.12)}
.mode-product .maintenance-danger-zone{background:#fff1f2;border:1px solid #b91c1c;border-left:6px solid #7f1d1d;border-radius:var(--radius-m);padding:var(--space-m);color:#0f172a;margin-top:var(--space-l)}
.mode-product .maintenance-danger-zone h3,.mode-product .maintenance-danger-zone h4{color:#7f1d1d}
@media(prefers-color-scheme:dark){.mode-product .maintenance-danger-zone{background:#2a1014;border-color:#f87171;border-left-color:#fca5a5;color:#f8fafc}.mode-product .maintenance-danger-zone h3,.mode-product .maintenance-danger-zone h4{color:#fecaca}}
.mode-product pre{display:block;white-space:pre-wrap;overflow-wrap:anywhere;border:1px solid var(--border);border-radius:var(--radius-s);background:var(--bg-main);color:var(--text-primary);padding:.75rem;min-height:10rem;max-height:clamp(18rem,52vh,42rem);overflow:auto;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.85rem}
.mode-product code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:var(--bg-main);border:1px solid var(--border);border-radius:6px;padding:.1rem .35rem;font-size:.78rem}
.mode-product dl{display:grid;grid-template-columns:minmax(10rem,16rem)1fr;gap:.45rem 1rem}
.mode-product dt{font-weight:900;color:var(--text-muted)}
.mode-product dd{margin:0;color:var(--text-primary);overflow-wrap:anywhere}
.mode-product ul,.mode-product ol{padding-left:1.2rem}
.mode-product .sub-nav{padding-left:.3rem}
.mode-product li+li{margin-top:.35rem}
.mode-product .sub-nav li+li{margin-top:0}
.mode-product .site-footer{border-top:0;margin-top:3rem;padding-block:clamp(1rem,2.4vw,1.5rem) clamp(1.4rem,3vw,2rem)}
.mode-product .footer-nav{display:flex;flex-wrap:wrap;justify-content:center;gap:.35rem;width:fit-content;max-width:100%;margin-inline:auto;padding:.45rem;background:color-mix(in srgb,var(--bg-surface),var(--bg-main)30%);border:1px solid var(--border);border-radius:999px}
.mode-product .footer-nav>a{color:var(--text-muted);font-weight:700;text-decoration:none;padding:.38rem .65rem;border-radius:999px;transition:background-color .18s ease,color .18s ease}
.mode-product .footer-nav>a:hover,.mode-product .footer-nav>a:focus-visible{background:var(--accent-glow);color:var(--accent)}
@keyframes toggleSelect{0%{transform:scale(.96)}70%{transform:scale(1.025)}100%{transform:scale(1)}}@media(prefers-reduced-motion:reduce){.mode-product *{animation:none!important;transition:none!important}}
@media(max-width:900px){.mode-product .setup-layout{grid-template-columns:1fr}}
@media(max-width:700px){.mode-product .container{width:min(100% - 1rem,1200px)}.mode-product .header{justify-content:center;text-align:center}.mode-product .brand{justify-content:center;width:100%;flex-direction:column;gap:.65rem}.mode-product .brand-logo{width:clamp(84px,24vw,116px)}.mode-product .brand-text{text-align:center}.mode-product .header-actions{width:100%;max-width:none;justify-content:center}.mode-product .ui-main-nav{width:100%;justify-content:center}.mode-product .card{padding:1rem}.mode-product .sub-nav{gap:.8rem;overflow-x:auto;flex-wrap:nowrap}.mode-product .setup-step-list .sub-nav-item{flex:0 0 auto;white-space:nowrap}.mode-product .action-bar{align-items:stretch}.mode-product .action-bar button{width:100%}.mode-product dl{grid-template-columns:1fr}.mode-product .footer-nav{border-radius:24px;width:100%}}' . "\n";
    }

    public function script(): string
    {
        if (!$this->text->productMode()) {
            return '';
        }

        return '(() => {
"use strict";
const themeMedia = window.matchMedia("(prefers-color-scheme: dark)");
const storageKey = "totman_theme_mode";
const storageGet = key => { try { return localStorage.getItem(key); } catch (error) { return null; } };
const storageSet = (key, value) => { try { localStorage.setItem(key, value); } catch (error) {} };
const normalise = mode => ["light","dark"].includes(mode) ? mode : null;
const systemMode = () => themeMedia.matches ? "dark" : "light";
const applyTheme = mode => {
  mode = normalise(mode);
  const resolved = mode || systemMode();
  document.documentElement.setAttribute("data-theme", resolved);
  document.documentElement.dataset.themeMode = mode || "system";
  const toggle = document.getElementById("theme-toggle");
  if (toggle) {
    toggle.dataset.themeResolved = resolved;
    toggle.setAttribute("aria-pressed", resolved === "dark" ? "true" : "false");
  }
};
const toggleTheme = () => {
  const next = (document.documentElement.getAttribute("data-theme") || systemMode()) === "dark" ? "light" : "dark";
  storageSet(storageKey, next);
  applyTheme(next);
};
const langKey = "totman_ui_language";
const applyLanguageLabel = value => {
  const summary = document.querySelector("[data-language-current]");
  const selected = document.querySelector(`[data-ui-lang="${CSS.escape(value)}"]`);
  if (summary && selected) summary.textContent = selected.textContent || value;
  document.querySelectorAll("[data-ui-lang]").forEach(button => button.setAttribute("aria-current", button.dataset.uiLang === value ? "true" : "false"));
};
applyTheme(storageGet(storageKey));
themeMedia.addEventListener?.("change", () => { if (!normalise(storageGet(storageKey))) applyTheme(null); });
document.getElementById("theme-toggle")?.addEventListener("click", toggleTheme);
const storedLang = storageGet(langKey) || "en";
applyLanguageLabel(storedLang);
document.querySelectorAll("[data-ui-lang]").forEach(button => button.addEventListener("click", () => {
  const value = button.dataset.uiLang || "en";
  storageSet(langKey, value);
  applyLanguageLabel(value);
  button.closest("details")?.removeAttribute("open");
}));
document.querySelectorAll("[data-password-toggle]").forEach(button => {
  const input = document.getElementById(button.dataset.passwordToggle || "");
  if (!input) return;
  const showLabel = button.dataset.showLabel || "Show";
  const hideLabel = button.dataset.hideLabel || "Hide";
  const sync = visible => {
    input.type = visible ? "text" : "password";
    button.textContent = visible ? hideLabel : showLabel;
    button.setAttribute("aria-pressed", visible ? "true" : "false");
  };
  sync(input.type === "text");
  button.addEventListener("click", () => sync(input.type === "password"));
});
})();' . "\n";
    }

    private function renderStylesheetLink(): string
    {
        if (!$this->text->productMode()) {
            return '';
        }

        return '<link rel="stylesheet" href="' . $this->e($this->stylesheetUrl()) . '">';
    }

    private function renderScriptLink(): string
    {
        if (!$this->text->productMode()) {
            return '';
        }

        return '<script src="' . $this->e($this->scriptUrl()) . '" defer></script>';
    }

    private function stylesheetUrl(): string
    {
        $scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'totman-ui.php')) ?: 'totman-ui.php';
        $version = substr(hash('sha256', $this->stylesheet()), 0, 12);

        return $scriptName . '?totman_ui_asset=css&v=' . $version;
    }

    private function scriptUrl(): string
    {
        $scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'totman-ui.php')) ?: 'totman-ui.php';
        $version = substr(hash('sha256', $this->script()), 0, 12);

        return $scriptName . '?totman_ui_asset=js&v=' . $version;
    }

    private function renderHeader(string $csrfToken, AdminAuthViewModel $adminAuth): string
    {
        if (!$this->text->productMode()) {
            return '<header class="ui-header"><p class="ui-product">' . $this->e($this->text->get('product.name')) . '</p><h1>' . $this->e($this->text->get('page.title')) . '</h1></header>';
        }

        $signOut = '';
        if ($adminAuth->showSignedIn()) {
            $signOut = '<form method="post" class="header-logout-form"><input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '"><button type="submit" name="action" value="logout" class="btn-secondary compact-btn signout-btn">' . $this->e($this->text->get('admin.sign_out')) . '</button></form>';
        }

        return '<header class="header ui-header view-animate"><div class="brand brand-link"><img class="brand-logo" src="' . self::PRODUCT_LOGO_DATA_URI . '" alt="" aria-hidden="true"><div class="brand-text"><h1>' . $this->e($this->text->get('product.name')) . '</h1><h2 class="tagline">A Deadman’s Switch for E-mail.</h2></div></div><div class="header-actions"><details class="language-menu"><summary aria-label="' . $this->e($this->text->get('header.language')) . '"><span data-language-current>English</span></summary><div class="language-options"><button type="button" data-ui-lang="en" aria-current="true">English</button><button type="button" data-ui-lang="de">Deutsch</button></div></details><button type="button" id="theme-toggle" class="btn-secondary compact-btn theme-toggle" aria-label="' . $this->e($this->text->get('header.toggle_theme')) . '" title="' . $this->e($this->text->get('header.toggle_theme')) . '"><span class="theme-icon theme-icon-light" aria-hidden="true">☀</span><span class="theme-icon theme-icon-dark" aria-hidden="true">◐</span></button>' . $signOut . '</div></header>';
    }

    private function renderFooter(): string
    {
        if (!$this->text->productMode()) {
            return '';
        }

        return '<footer class="site-footer"><nav class="footer-nav" aria-label="' . $this->e($this->text->get('footer.label')) . '"><a href="https://github.com/MacSteini/totmannschalter">GitHub</a><a href="https://github.com/MacSteini/">MacSteini &copy; 2026</a><a href="https://github.com/MacSteini/totmannschalter/tree/main/docs">' . $this->e($this->text->get('footer.documentation')) . '</a></nav></footer>';
    }

    private function renderSection(string $key, string $title, string $content): string
    {
        $safeKey = preg_replace('/[^a-z0-9_-]/', '-', $key) ?? $key;
        $titleId = 'section-' . $safeKey . '-title';

        return '<section class="card ui-panel ui-panel-' . $this->e($key) . ' view-animate" data-section="' . $this->e($key) . '" aria-labelledby="' . $this->e($titleId) . '">
<h2 id="' . $this->e($titleId) . '">' . $this->e($title) . '</h2>
' . $content . '
</section>';
    }

    private function renderSummary(FirstRunViewModel $view): string
    {
        $items = [
            ['D', $this->text->get('summary.state_dir'), $this->summaryStateDir($view)],
            ['M', $this->text->get('summary.mode'), $view->mode()],
            ['S', $this->text->get('summary.current_step'), $this->stepTitle($view, $view->currentStep())],
            ['P', $this->text->get('summary.preflight'), $view->preflightStatus()],
            ['F', $this->text->get('summary.path_fields'), $view->pathFieldsReadOnly() ? $this->text->get('summary.paths_read_only') : $this->text->get('summary.paths_editable')],
        ];

        $cards = '';
        foreach ($items as [$icon, $label, $value]) {
            $cards .= '<article class="stat-card"><div class="stat-icon" aria-hidden="true">' . $this->e($icon) . '</div><div class="stat-content"><span class="stat-label">' . $this->e($label) . '</span><span class="stat-value">' . $this->e($value) . '</span></div></article>';
        }

        return '<div class="dashboard-grid ui-summary">' . $cards . '</div>';
    }

    private function renderSetupCodeField(): string
    {
        return $this->renderPlainInputGroup(
            'setup-code',
            'setup_code',
            $this->text->get('wizard.setup_code'),
            $this->text->get('wizard.setup_code_help'),
        );
    }

    private function renderPlainInputGroup(
        string $id,
        string $name,
        string $label,
        string $hint,
        string $type = 'text',
        string $autocomplete = '',
    ): string {
        $describedBy = $id . '-help';
        $autocompleteAttribute = $autocomplete !== '' ? ' autocomplete="' . $this->e($autocomplete) . '"' : '';

        $input = '<input id="' . $this->e($id) . '" type="' . $this->e($type) . '" name="' . $this->e($name) . '"' . $autocompleteAttribute . ' aria-describedby="' . $this->e($describedBy) . '">';
        if ($type === 'password') {
            $input = '<div class="password-input-wrap">'
                . $input
                . '<button type="button" class="password-toggle" data-password-toggle="' . $this->e($id) . '" aria-controls="' . $this->e($id) . '" aria-pressed="false" data-show-label="' . $this->e($this->text->get('password.show')) . '" data-hide-label="' . $this->e($this->text->get('password.hide')) . '">' . $this->e($this->text->get('password.show')) . '</button>'
                . '</div>';
        }

        return '<div class="input-group ui-field">'
            . $this->labelRow($id, $label)
            . $input
            . $this->helperText($describedBy, $hint)
            . '</div>';
    }

    private function renderReadonlyInputGroup(
        string $id,
        string $label,
        string $value,
        string $hint,
        string $class = '',
    ): string {
        $describedBy = $id . '-help';
        $classAttribute = trim('input-group ui-field ' . $class);

        return '<div class="' . $this->e($classAttribute) . '">'
            . $this->labelRow($id, $label)
            . '<input id="' . $this->e($id) . '" type="text" value="' . $this->e($value) . '" readonly aria-describedby="' . $this->e($describedBy) . '">'
            . $this->helperText($describedBy, $hint)
            . '</div>';
    }

    private function renderAdminAccess(string $csrfToken, AdminAuthViewModel $adminAuth): string
    {
        if ($adminAuth->showConfigBlocked()) {
            return '<p>' . $this->e($this->text->get('admin.config_blocked')) . '</p>';
        }

        if ($adminAuth->showPrivateConfigBlocked()) {
            return '<p>' . $this->e($this->text->get('admin.private_config_blocked')) . '</p>';
        }

        if ($adminAuth->showAdministrationDisabled()) {
            return '<p>' . $this->e($this->text->get('admin.disabled')) . '</p>';
        }

        if ($adminAuth->showSignedIn()) {
            return '<p role="status">' . $this->e($this->text->format('admin.signed_in_as', 'username', $adminAuth->username())) . '</p>
<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
' . $this->renderPlainInputGroup('admin-reauth-password', 'reauth_password', $this->text->get('admin.reenter_password'), $this->text->get('admin.reauth_help'), 'password', 'current-password') . '
<div class="action-bar full-width"><button type="submit" name="action" value="reauth" class="btn-primary">' . $this->e($this->text->get('admin.reauthenticate')) . '</button><button type="submit" name="action" value="logout" class="btn-secondary">' . $this->e($this->text->get('admin.sign_out')) . '</button></div>
</form>';
        }

        if ($adminAuth->showLogin()) {
            return '<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
' . $this->renderPlainInputGroup('admin-login-username', 'login_username', $this->text->get('admin.username'), $this->text->get('admin.username_help'), 'text', 'username') . '
' . $this->renderPlainInputGroup('admin-login-password', 'login_password', $this->text->get('admin.password'), $this->text->get('admin.password_help'), 'password', 'current-password') . '
<div class="action-bar full-width"><button type="submit" name="action" value="login" class="btn-primary">' . $this->e($this->text->get('admin.sign_in')) . '</button></div>
</form>';
        }

        return '<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
' . $this->renderPlainInputGroup('admin-setup-code', 'setup_code', $this->text->get('admin.setup_code'), $this->text->get('wizard.setup_code_help')) . '
' . $this->renderPlainInputGroup('admin-create-username', 'admin_username', $this->text->get('admin.create_username'), $this->text->get('admin.username_help'), 'text', 'username') . '
' . $this->renderPlainInputGroup('admin-create-password', 'admin_password', $this->text->get('admin.create_password'), $this->text->get('admin.new_password_help'), 'password', 'new-password') . '
' . $this->renderPlainInputGroup('admin-create-password-confirm', 'admin_password_confirm', $this->text->get('admin.repeat_password'), $this->text->get('admin.repeat_password_help'), 'password', 'new-password') . '
<div class="action-bar full-width"><button type="submit" name="action" value="create_admin" class="btn-primary">' . $this->e($this->text->get('admin.create_access')) . '</button></div>
</form>';
    }

    private function renderAdminInspection(string $csrfToken, AdminInspectionViewModel $inspection): string
    {
        if (!$inspection->available()) {
            return '<p>' . $this->e($inspection->notice()) . '</p>';
        }

        $summary = $inspection->summary();
        $logTail = $inspection->logTail();
        $aliases = $inspection->fileAliases();
        if ($summary === null || $logTail === null || $aliases === null) {
            return '<p>' . $this->e($this->text->get('inspection.unavailable')) . '</p>';
        }

        return '<section>
<h3>' . $this->e($this->text->get('inspection.summary')) . '</h3>
<dl>
<dt>' . $this->e($this->text->get('summary.mode')) . '</dt><dd>' . $this->e($summary->mode()) . '</dd>
<dt>' . $this->e($this->text->get('inspection.main_source')) . '</dt><dd>' . $this->e($summary->mainSource()) . '</dd>
<dt>' . $this->e($this->text->get('inspection.recipient_source')) . '</dt><dd>' . $this->e($summary->recipientSource()) . '</dd>
<dt>' . $this->e($this->text->get('summary.preflight')) . '</dt><dd>' . $this->e($summary->preflight()->status()) . '</dd>
<dt>' . $this->e($this->text->get('inspection.state_file')) . '</dt><dd>' . $this->e($summary->stateFile()->status()) . ' - ' . $this->e($summary->stateFile()->message()) . '</dd>
</dl>
' . $this->renderRuntimePaths($summary->paths()) . '
<h3>' . $this->e($this->text->get('inspection.log_tail')) . '</h3>
<p>' . $this->e($logTail->status()) . ': ' . $this->e($logTail->message()) . '</p>
' . ($logTail->lines() !== [] ? '<div class="log-window terminal-card">' . $this->renderLogLines($logTail->lines()) . '</div>' : '') . '
<h3>' . $this->e($this->text->get('inspection.file_aliases')) . '</h3>
' . $this->renderFileAliases($aliases) . '
<section class="maintenance-danger-zone">
<h3>' . $this->e($this->text->get('danger.heading')) . '</h3>
' . $this->renderDangerPreview($inspection) . '
' . $this->renderDangerPreviewForms($csrfToken) . '
</section>
</section>';
    }

    /**
     * @param array<string, string> $paths
     */
    private function renderRuntimePaths(array $paths): string
    {
        $items = '';
        foreach ($paths as $key => $path) {
            $items .= '<dt>' . $this->e($key) . '</dt><dd>' . $this->e($path) . '</dd>';
        }

        return '<h3>' . $this->e($this->text->get('inspection.paths')) . '</h3><dl>' . $items . '</dl>';
    }

    private function renderFileAliases(\Totman\RuntimeUi\Application\FileAliasInventory $aliases): string
    {
        $items = '';
        foreach ($aliases->items() as $item) {
            $fileState = $item->fileExists() ? $this->text->get('inspection.file_present') : $this->text->get('inspection.file_missing');
            $items .= '<li><code>' . $this->e($item->alias()) . '</code> -> ' . $this->e($item->relativePath())
                . ' <small>' . $this->e($this->text->get('inspection.alias_normal')) . ': ' . $item->normalReferences()
                . ', ' . $this->e($this->text->get('inspection.alias_single_use')) . ': ' . $item->singleUseReferences()
                . ', ' . $this->e($this->text->get('inspection.alias_file')) . ': ' . $this->e($fileState) . '</small>'
                . ($item->issues() !== [] ? '<ul><li>' . $this->e(implode('</li><li>', $item->issues())) . '</li></ul>' : '')
                . '</li>';
        }

        $issues = '';
        foreach ($aliases->issues() as $issue) {
            $issues .= '<li>' . $this->e($issue) . '</li>';
        }

        return '<p>' . $this->e($this->text->get('inspection.download_base_dir')) . ': ' . $this->e($aliases->downloadBaseDir()) . '</p>'
            . ($items !== '' ? '<ul>' . $items . '</ul>' : '<p>' . $this->e($this->text->get('inspection.no_aliases')) . '</p>')
            . ($issues !== '' ? '<ul role="alert">' . $issues . '</ul>' : '');
    }

    private function renderDangerPreview(AdminInspectionViewModel $inspection): string
    {
        $result = $inspection->maintenanceCommand();
        if ($result === null) {
            return '<p>' . $this->e($this->text->get('danger.none_selected')) . '</p>';
        }

        $blockers = '';
        foreach ($result->blockers() as $blocker) {
            $blockers .= '<li>' . $this->e($blocker) . '</li>';
        }

        $plan = '';
        foreach ($result->plan() as $step) {
            $plan .= '<li>' . $this->e($step) . '</li>';
        }

        $effects = '';
        foreach ($result->effects() as $effect) {
            $effects .= '<li>' . $this->e($effect) . '</li>';
        }

        return '<section><h4>' . $this->e($result->label()) . '</h4>'
            . '<p>' . $this->e($this->text->get('danger.phase')) . ': ' . $this->e($result->phase()) . '</p>'
            . '<p>' . $this->e($this->text->get('danger.status')) . ': ' . $this->e($this->maintenanceStatusLabel($result)) . '</p>'
            . ($blockers !== '' ? '<ul role="alert">' . $blockers . '</ul>' : '')
            . '<ol>' . $plan . '</ol>'
            . ($effects !== '' ? '<h5>' . $this->e($this->text->get('danger.effects')) . '</h5><ul>' . $effects . '</ul>' : '')
            . '</section>';
    }

    private function renderDangerPreviewForms(string $csrfToken): string
    {
        $commands = [
            AdminCommandCatalog::PREVIEW_HMAC_ROTATION => $this->text->get('danger.hmac_rotation'),
            AdminCommandCatalog::PREVIEW_RUNTIME_RESET => $this->text->get('danger.runtime_reset'),
            AdminCommandCatalog::PREVIEW_LOG_CLEAR => $this->text->get('danger.log_clear'),
            AdminCommandCatalog::PREVIEW_FILE_ALIAS_DELETION => $this->text->get('danger.file_alias_deletion'),
        ];

        $forms = '';
        foreach ($commands as $command => $label) {
            $forms .= '<form class="action-bar" method="post">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
<input type="hidden" name="admin_command" value="' . $this->e($command) . '">
<input type="hidden" name="admin_command_phase" value="' . MaintenanceCommandResult::PREVIEW . '">
' . ($command === AdminCommandCatalog::PREVIEW_FILE_ALIAS_DELETION ? '<label>' . $this->e($this->text->get('danger.alias')) . ' <input name="admin_command_target_alias"></label> ' : '') . '
<button type="submit" name="action" value="admin_command" class="btn-secondary">' . $this->e($label) . '</button>
</form>
<form class="action-bar danger-action-row" method="post">
<input type="hidden" name="csrf_token" value="' . $this->e($csrfToken) . '">
<input type="hidden" name="admin_command" value="' . $this->e($command) . '">
<input type="hidden" name="admin_command_phase" value="' . MaintenanceCommandResult::EXECUTE . '">
' . ($command === AdminCommandCatalog::PREVIEW_FILE_ALIAS_DELETION ? '<label>' . $this->e($this->text->get('danger.alias')) . ' <input name="admin_command_target_alias"></label> ' : '') . '
<label><input type="checkbox" name="confirm_admin_command" value="1"> ' . $this->e($this->text->get('danger.confirm_execute')) . '</label>
<button type="submit" name="action" value="admin_command" class="btn-secondary danger">' . $this->e($this->text->get('danger.execute')) . ': ' . $this->e($label) . '</button>
</form>';
        }

        return $forms;
    }

    /**
     * @param list<string> $lines
     */
    private function renderLogLines(array $lines): string
    {
        $html = '';
        foreach ($lines as $line) {
            $html .= '<div class="log-line">' . $this->e($line) . '</div>';
        }

        return $html;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function summaryStateDir(FirstRunViewModel $view): string
    {
        return $this->text->productMode() ? $this->text->get('summary.state_dir_hidden') : $view->stateDir();
    }

    private function renderField(FirstRunField $field): string
    {
        $readOnly = $field->readOnly() ? ' readonly' : '';
        $requiredAttribute = $field->required() ? ' required aria-required="true"' : '';
        $invalid = $field->errors() !== [] ? ' aria-invalid="true"' : '';
        $describedBy = $field->describedBy() !== '' ? ' aria-describedby="' . $this->e($field->describedBy()) . '"' : '';
        $required = $field->required() ? $this->text->get('field.required') : '';
        $source = $this->text->get('field.source') . ': ' . $this->sourceLabel($field->source());
        $meta = '<p class="field-meta">' . $this->e(trim($required . ($required !== '' ? ' · ' : '') . $source)) . '</p>';
        $label = $this->labelRow($field->domId(), $field->label());

        if ($field->control() === 'textarea') {
            return '<div class="input-group ui-field">' . $label . '<textarea id="' . $this->e($field->domId())
                . '" name="' . $this->e($field->key()) . '"' . $readOnly . $requiredAttribute . $invalid . $describedBy . '>'
                . $this->e($field->value()) . '</textarea>' . $this->fieldHint($field) . $this->fieldErrors($field) . $meta . '</div>';
        }

        if ($field->control() === 'checkbox') {
            $checked = $field->value() === '1' ? ' checked' : '';
            return '<div class="input-group ui-field checkbox-field">' . $label . '<input id="' . $this->e($field->domId())
                . '" type="checkbox" name="' . $this->e($field->key())
                . '" value="1"' . $checked . $requiredAttribute . $invalid . $describedBy . '>' . $this->fieldHint($field) . $this->fieldErrors($field) . $meta . '</div>';
        }

        return '<div class="input-group ui-field">' . $label . '<input id="' . $this->e($field->domId())
            . '" name="' . $this->e($field->key())
            . '" value="' . $this->e($field->value()) . '"' . $readOnly . $requiredAttribute . $invalid . $describedBy
            . '>' . $this->fieldHint($field) . $this->fieldErrors($field) . $meta . '</div>';
    }

    private function renderCurrentStep(FirstRunViewModel $view): string
    {
        if ($view->currentStep() === 'review') {
            return $this->renderReview($view);
        }

        if ($view->currentStep() === 'preflight') {
            return $this->renderPreflight($view);
        }

        if ($view->currentStep() === 'save') {
            return $this->renderSaveStep($view);
        }

        if ($view->currentStep() === 'complete') {
            return '<p role="status">' . $this->e($this->text->get('wizard.complete')) . '</p>';
        }

        $fields = $view->currentStepFields();
        if ($fields === []) {
            return '<fieldset class="full-width"><legend>' . $this->e($this->stepTitle($view, $view->currentStep())) . '</legend>' . $this->stepDescription($view, $view->currentStep()) . '<p>' . $this->e($this->text->get('wizard.no_fields')) . '</p></fieldset>';
        }

        $inputs = '<fieldset class="full-width"><legend>' . $this->e($this->stepTitle($view, $view->currentStep())) . '</legend>' . $this->stepDescription($view, $view->currentStep());
        foreach ($fields as $field) {
            $inputs .= $this->renderField($field);
        }

        return $inputs . '</fieldset>';
    }

    private function renderReview(FirstRunViewModel $view): string
    {
        $items = '';
        foreach ($view->reviewFields() as $field) {
            $items .= '<dt>' . $this->e($field->label()) . '</dt><dd>' . $this->e($field->value())
                . ' <small>' . $this->e($this->text->get('field.source')) . ': ' . $this->e($this->sourceLabel($field->source())) . '</small></dd>';
        }

        return '<fieldset class="full-width"><legend>' . $this->e($this->text->get('wizard.review')) . '</legend><dl>' . $items . '</dl></fieldset>';
    }

    private function renderPreflight(FirstRunViewModel $view): string
    {
        return '<fieldset class="preflight-card full-width"><legend>' . $this->e($this->text->get('wizard.preflight')) . '</legend><div class="preflight-grid">' . $this->preflightItems($view) . '</div></fieldset>';
    }

    private function renderSaveStep(FirstRunViewModel $view): string
    {
        return '<fieldset class="preflight-card full-width"><legend>' . $this->e($this->text->get('wizard.save')) . '</legend><p>' . $this->e($this->text->get('wizard.save_notice')) . '</p>'
            . '<div class="preflight-grid">' . $this->preflightItems($view) . '</div>'
            . '<label>' . $this->e($this->text->get('wizard.confirm_save')) . ' <input type="checkbox" name="confirm_save" value="1"></label></fieldset>';
    }

    private function preflightItems(FirstRunViewModel $view): string
    {
        $checks = '';
        foreach ($view->preflightChecks() as $check) {
            $checks .= '<div class="preflight-item status-' . $this->e($check->status()) . '"><strong>' . $this->e($this->statusLabel($check->status())) . '</strong> '
                . '<code>' . $this->e($check->code()) . '</code>: ' . $this->e($check->message())
                . ($check->fix() !== '' ? ' <small>' . $this->e($this->text->get('preflight.fix')) . ': ' . $this->e($check->fix()) . '</small>' : '')
                . '</div>';
        }

        return $checks;
    }

    private function renderActions(FirstRunViewModel $view): string
    {
        $buttons = '';
        foreach ($view->actions() as $action) {
            if (!$action->visible()) {
                continue;
            }

            $buttons .= '<button type="submit" name="action" value="' . $this->e($action->key()) . '" class="' . $this->e($this->actionClass($action->key())) . '"'
                . ($action->disabled() ? ' disabled' : '')
                . '>' . $this->e($action->label()) . '</button>';
        }

        return $buttons;
    }

    private function actionClass(string $key): string
    {
        return in_array($key, ['save_runtime', 'next_step'], true) ? 'btn-primary' : 'btn-secondary';
    }

    private function renderHiddenFields(FirstRunViewModel $view): string
    {
        $hidden = '';
        foreach ($this->hiddenFieldPolicy->fieldsToPreserve($view) as $field) {
            $hidden .= '<input type="hidden" name="' . $this->e($field->key()) . '" value="' . $this->e($field->value()) . '">';
        }

        return $hidden;
    }

    private function renderSteps(FirstRunViewModel $view): string
    {
        $items = '';
        foreach ($view->steps() as $step) {
            $marker = $step->current() ? ' aria-current="step"' : '';
            $classes = ['sub-nav-item'];
            if ($step->complete()) {
                $classes[] = 'is-complete';
            }
            if ($step->blocked()) {
                $classes[] = 'is-blocked';
            }
            $items .= '<li class="' . $this->e(implode(' ', $classes)) . '"' . $marker . '><strong>' . $this->e($step->title()) . '</strong> '
                . '<small>' . $this->e($step->status()) . '</small>'
                . '</li>';
        }

        return '<ol class="sub-nav setup-step-list">' . $items . '</ol>';
    }

    private function stepTitle(FirstRunViewModel $view, string $key): string
    {
        foreach ($view->steps() as $step) {
            if ($step->key() === $key) {
                return $step->title();
            }
        }

        return $key;
    }

    private function stepDescription(FirstRunViewModel $view, string $key): string
    {
        foreach ($view->steps() as $step) {
            if ($step->key() === $key && $step->description() !== '') {
                return '<p>' . $this->e($step->description()) . '</p>';
            }
        }

        return '';
    }

    private function shortStepDescription(FirstRunViewModel $view, string $key): string
    {
        foreach ($view->steps() as $step) {
            if ($step->key() === $key && $step->description() !== '') {
                $description = $step->description();

                return rtrim(strtok($description, '.') ?: $description, '.') . '.';
            }
        }

        return $this->text->get('wizard.no_fields');
    }

    private function labelRow(string $id, string $label, string $tooltip = ''): string
    {
        return '<div class="field-label-row"><label for="' . $this->e($id) . '">' . $this->e($label) . '</label>'
            . ($tooltip !== '' ? $this->helpTrigger($id, $tooltip) : '')
            . '</div>';
    }

    private function helpTrigger(string $id, string $hint): string
    {
        return '<span class="help-wrap"><button type="button" class="help-trigger" aria-label="' . $this->e($hint) . '" aria-describedby="' . $this->e($id) . '-popover">?</button><span id="' . $this->e($id) . '-popover" class="help-popover" role="tooltip">' . $this->e($hint) . '</span></span>';
    }

    private function helperText(string $id, string $hint): string
    {
        return $hint !== '' ? '<p id="' . $this->e($id) . '" class="helper-text">' . $this->e($hint) . '</p>' : '';
    }

    private function fieldHint(FirstRunField $field): string
    {
        if ($field->hint() === '') {
            return '';
        }

        return '<p id="' . $this->e($field->hintId()) . '" class="helper-text">' . $this->e($field->hint()) . '</p>';
    }

    private function fieldErrors(FirstRunField $field): string
    {
        if ($field->errors() === []) {
            return '';
        }

        $items = '';
        foreach ($field->errors() as $error) {
            $items .= '<li>' . $this->e($error) . '</li>';
        }

        return '<ul id="' . $this->e($field->errorId()) . '" class="field-errors" role="alert">' . $items . '</ul>';
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'live' => $this->text->get('source.live'),
            'dist' => $this->text->get('source.dist'),
            'draft' => $this->text->get('source.draft'),
            'generated' => $this->text->get('source.generated'),
            'missing' => $this->text->get('source.missing'),
            default => $source,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'OK' => $this->text->get('preflight.status_ok'),
            'WARN' => $this->text->get('preflight.status_warn'),
            'FAIL' => $this->text->get('preflight.status_fail'),
            default => $status,
        };
    }

    private function maintenanceStatusLabel(MaintenanceCommandResult $result): string
    {
        if ($result->status() === MaintenanceCommandResult::BLOCKED) {
            return $this->text->get('danger.blocked');
        }

        return $result->status();
    }
}

namespace Totman\RuntimeUi\Http;

final class RuntimeUiMode
{
    public const PROTOTYPE = 'prototype';
    public const PRODUCT = 'product';

    private function __construct()
    {
    }

    public static function normalise(string $mode): string
    {
        return $mode === self::PRODUCT ? self::PRODUCT : self::PROTOTYPE;
    }
}

namespace Totman\RuntimeUi\Preflight;

use Totman\RuntimeUi\Config\ImportedField;
use Totman\RuntimeUi\Config\MainConfigImport;
use Totman\RuntimeUi\Config\RecipientConfigImport;
use Totman\RuntimeUi\Deployment\DeploymentContext;

final class FirstRunPreflight
{
    public function check(MainConfigImport $main, RecipientConfigImport $recipients, DeploymentContext $context): PreflightResult
    {
        $checks = [
            $this->fieldCheck($main->field('base_url'), 'public_url', 'Public URL is usable.', 'Set a real HTTPS public URL.', $context),
            $this->fieldCheck($main->field('mail_from'), 'mail_from', 'Mail sender identity is usable.', 'Set one real sender mailbox.', $context),
            $this->fieldCheck($main->field('to_self'), 'operator_mailbox', 'Operator mailbox is usable.', 'Set at least one real operator mailbox.', $context),
            $this->fieldCheck($main->field('sendmail_path'), 'delivery_command', 'Mail delivery command is configured.', 'Configure sendmail or SMTP delivery.', $context),
            $this->hmacCheck($main->field('hmac_secret_hex'), $context),
            $this->pathCheck($main->field('state_dir'), 'state_dir', $context),
            $this->pathCheck($main->field('download_base_dir'), 'download_base_dir', $context),
            $this->recipientCheck($recipients, $context),
        ];

        return new PreflightResult($checks);
    }

    private function fieldCheck(ImportedField $field, string $code, string $okMessage, string $fix, DeploymentContext $context): PreflightCheck
    {
        if ($field->placeholder() || $field->invalid() || $field->source() === 'missing') {
            return PreflightCheck::fail($code, $field->key() . ' is missing, invalid, or still placeholder-like.', $fix, $context->kind());
        }

        return PreflightCheck::ok($code, $okMessage, $context->kind());
    }

    private function hmacCheck(ImportedField $field, DeploymentContext $context): PreflightCheck
    {
        if ($field->source() === 'missing' && $field->serverGeneratedAvailable()) {
            return PreflightCheck::warn('hmac_will_generate', 'HMAC secret will be generated server-side before save.', 'Continue to save so the server can generate the HMAC secret.', $context->kind());
        }

        if ($field->invalid() || $field->placeholder()) {
            return PreflightCheck::fail('hmac_secret', 'HMAC secret is invalid.', 'Generate a new server-side HMAC secret.', $context->kind());
        }

        return PreflightCheck::ok('hmac_secret', 'HMAC secret is present.', $context->kind());
    }

    private function pathCheck(ImportedField $field, string $code, DeploymentContext $context): PreflightCheck
    {
        if ($context->pathFieldsAreReadOnly()) {
            return PreflightCheck::ok($code, $field->key() . ' is controlled by the deployment context.', $context->kind());
        }

        if ($field->invalid() || $field->placeholder() || $field->source() === 'missing') {
            return PreflightCheck::fail($code, $field->key() . ' is missing or invalid.', 'Set a usable path for classic hosting.', $context->kind());
        }

        return PreflightCheck::ok($code, $field->key() . ' is configured.', $context->kind());
    }

    private function recipientCheck(RecipientConfigImport $recipients, DeploymentContext $context): PreflightCheck
    {
        if ($recipients->readyForFirstRecipient()) {
            return PreflightCheck::ok('recipients', 'Recipient configuration is usable.', $context->kind());
        }

        return PreflightCheck::fail('recipients', 'Recipient configuration is incomplete or invalid.', 'Create at least one real recipient and message, then fix listed recipient issues.', $context->kind());
    }
}

namespace Totman\RuntimeUi\Preflight;

final class PreflightCheck
{
    public function __construct(
        private readonly string $status,
        private readonly string $code,
        private readonly string $message,
        private readonly string $fix,
        private readonly string $context,
    ) {
    }

    public static function ok(string $code, string $message, string $context): self
    {
        return new self('OK', $code, $message, '', $context);
    }

    public static function warn(string $code, string $message, string $fix, string $context): self
    {
        return new self('WARN', $code, $message, $fix, $context);
    }

    public static function fail(string $code, string $message, string $fix, string $context): self
    {
        return new self('FAIL', $code, $message, $fix, $context);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function fix(): string
    {
        return $this->fix;
    }

    public function context(): string
    {
        return $this->context;
    }
}

namespace Totman\RuntimeUi\Preflight;

final class PreflightResult
{
    /**
     * @param list<PreflightCheck> $checks
     */
    public function __construct(private readonly array $checks)
    {
    }

    /**
     * @return list<PreflightCheck>
     */
    public function checks(): array
    {
        return $this->checks;
    }

    public function status(): string
    {
        $status = 'OK';
        foreach ($this->checks as $check) {
            if ($check->status() === 'FAIL') {
                return 'FAIL';
            }

            if ($check->status() === 'WARN') {
                $status = 'WARN';
            }
        }

        return $status;
    }

    public function hasCode(string $code): bool
    {
        foreach ($this->checks as $check) {
            if ($check->code() === $code) {
                return true;
            }
        }

        return false;
    }
}

namespace Totman\RuntimeUi\Security;

final class AdminAuthInput
{
    public function __construct(
        private readonly string $adminUsername = '',
        private readonly string $adminPassword = '',
        private readonly string $adminPasswordConfirm = '',
        private readonly string $loginUsername = '',
        private readonly string $loginPassword = '',
        private readonly string $reauthPassword = '',
    ) {
    }

    public function adminUsername(): string
    {
        return $this->adminUsername;
    }

    public function adminPassword(): string
    {
        return $this->adminPassword;
    }

    public function adminPasswordConfirm(): string
    {
        return $this->adminPasswordConfirm;
    }

    public function loginUsername(): string
    {
        return $this->loginUsername;
    }

    public function loginPassword(): string
    {
        return $this->loginPassword;
    }

    public function reauthPassword(): string
    {
        return $this->reauthPassword;
    }
}

namespace Totman\RuntimeUi\Security;

final class AdminAuthService
{
    public function __construct(
        private readonly SetupCodeVerifier $setupCodeVerifier = new SetupCodeVerifier(),
        private readonly SessionSecurity $sessionSecurity = new SessionSecurity(),
    ) {
    }

    public function createAdmin(
        UiPrivateConfigStore $store,
        AdminAuthInput $input,
        string $expectedSetupCode,
        string $suppliedSetupCode,
        SetupSessionState $setupSession,
        AdminSessionState $adminSession,
        ?string $now = null,
    ): SetupAccessResult {
        $configResult = $store->loadResult();
        if ($configResult->blocksAdmin()) {
            return SetupAccessResult::denied('private_ui_config_blocked', $configResult->message());
        }

        if ($configResult->config()->hasAdminCredential()) {
            return SetupAccessResult::denied('admin_exists', 'Admin access already exists. Sign in instead.');
        }

        if ($expectedSetupCode === '' || !$this->setupCodeVerifier->verify($expectedSetupCode, $suppliedSetupCode)) {
            return SetupAccessResult::denied('setup_code_required', 'A valid setup code is required before creating admin access.');
        }

        if ($input->adminPassword() !== $input->adminPasswordConfirm()) {
            return SetupAccessResult::denied('admin_password_mismatch', 'Admin password confirmation does not match.');
        }

        try {
            $credential = AdminCredential::create($input->adminUsername(), $input->adminPassword(), $now);
        } catch (\InvalidArgumentException $exception) {
            return SetupAccessResult::denied('admin_credentials_invalid', $exception->getMessage());
        }

        $store->save((new UiPrivateConfig())->withAdminCredential($credential));
        $setupSession->markSetupVerified();
        $adminSession->markAuthenticated($credential->username(), time());
        $this->sessionSecurity->regenerateIfActive();

        return SetupAccessResult::allow();
    }

    public function login(UiPrivateConfig $config, AdminAuthInput $input, AdminSessionState $adminSession): SetupAccessResult
    {
        $credential = $config->adminCredential();
        if ($credential === null) {
            return SetupAccessResult::denied('admin_missing', 'Admin access has not been created yet.');
        }

        if (!hash_equals($credential->username(), $input->loginUsername()) || !$credential->verifyPassword($input->loginPassword())) {
            return SetupAccessResult::denied('login_failed', 'Admin username or password is incorrect.');
        }

        $adminSession->markAuthenticated($credential->username(), time());
        $this->sessionSecurity->regenerateIfActive();

        return SetupAccessResult::allow();
    }

    public function logout(AdminSessionState $adminSession): SetupAccessResult
    {
        $adminSession->clearAuthentication();
        $this->sessionSecurity->regenerateIfActive();

        return SetupAccessResult::allow();
    }

    public function reauth(UiPrivateConfig $config, AdminAuthInput $input, AdminSessionState $adminSession): SetupAccessResult
    {
        if (!$adminSession->authenticated()) {
            return SetupAccessResult::denied('admin_auth_required', 'An authenticated admin session is required before reauthentication.');
        }

        $credential = $config->adminCredential();
        if ($credential === null) {
            return SetupAccessResult::denied('admin_missing', 'Admin access has not been created yet.');
        }

        if (!hash_equals($credential->username(), $adminSession->username()) || !$credential->verifyPassword($input->reauthPassword())) {
            return SetupAccessResult::denied('reauth_failed', 'Admin password is incorrect.');
        }

        $adminSession->markReauthenticated(time());
        $this->sessionSecurity->regenerateIfActive();

        return SetupAccessResult::allow();
    }
}

namespace Totman\RuntimeUi\Security;

final class AdminCredential
{
    public const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private readonly string $username,
        private readonly string $passwordHash,
        private readonly string $createdAt,
        private readonly string $updatedAt,
    ) {
        if ($this->username === '') {
            throw new \InvalidArgumentException('Admin username must not be empty.');
        }

        if ($this->passwordHash === '') {
            throw new \InvalidArgumentException('Admin password hash must not be empty.');
        }
    }

    public static function create(string $username, string $password, ?string $now = null): self
    {
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('Admin username must not be empty.');
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException('Admin password must contain at least 10 characters.');
        }

        $timestamp = $now ?? gmdate('c');
        $hash = password_hash($password, PASSWORD_DEFAULT);

        return new self($username, $hash, $timestamp, $timestamp);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $username = $data['username'] ?? null;
        $passwordHash = $data['password_hash'] ?? null;
        $createdAt = $data['created_at'] ?? null;
        $updatedAt = $data['updated_at'] ?? null;
        if (!is_string($username) || !is_string($passwordHash) || !is_string($createdAt) || !is_string($updatedAt)) {
            return null;
        }

        try {
            return new self($username, $passwordHash, $createdAt, $updatedAt);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function username(): string
    {
        return $this->username;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'password_hash' => $this->passwordHash,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

namespace Totman\RuntimeUi\Security;

final class AdminReauthPolicy
{
    public function __construct(private readonly int $ttlSeconds = 300)
    {
    }

    public function evaluate(AdminSessionState $adminSession, int $now): SetupAccessResult
    {
        if (!$adminSession->authenticated()) {
            return SetupAccessResult::denied('admin_auth_required', 'An authenticated admin session is required before reauthentication.');
        }

        if (!$adminSession->reauthenticatedWithin($now, $this->ttlSeconds)) {
            return SetupAccessResult::denied('reauth_required', 'Recent admin reauthentication is required before this action.');
        }

        return SetupAccessResult::allow();
    }
}

namespace Totman\RuntimeUi\Security;

final class AdminSessionLoadResult
{
    public const LOADED = 'loaded';
    public const MISSING = 'missing';
    public const CORRUPT = 'corrupt';

    public function __construct(
        private readonly string $status,
        private readonly AdminSessionState $state,
        private readonly string $message = '',
    ) {
    }

    public static function loaded(AdminSessionState $state): self
    {
        return new self(self::LOADED, $state);
    }

    public static function missing(): self
    {
        return new self(self::MISSING, new AdminSessionState());
    }

    public static function corrupt(string $message): self
    {
        return new self(self::CORRUPT, new AdminSessionState(), $message);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function state(): AdminSessionState
    {
        return $this->state;
    }

    public function notice(): string
    {
        if ($this->status !== self::CORRUPT || $this->message === '') {
            return '';
        }

        return $this->message;
    }
}

namespace Totman\RuntimeUi\Security;

final class AdminSessionState
{
    public function __construct(
        private bool $authenticated = false,
        private string $username = '',
        private int $authenticatedAt = 0,
        private int $reauthenticatedAt = 0,
    ) {
    }

    public function authenticated(): bool
    {
        return $this->authenticated;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function authenticatedAt(): int
    {
        return $this->authenticatedAt;
    }

    public function reauthenticatedAt(): int
    {
        return $this->reauthenticatedAt;
    }

    public function markAuthenticated(string $username, int $now): void
    {
        $this->authenticated = true;
        $this->username = $username;
        $this->authenticatedAt = $now;
        $this->reauthenticatedAt = 0;
    }

    public function markReauthenticated(int $now): void
    {
        if (!$this->authenticated) {
            return;
        }

        $this->reauthenticatedAt = $now;
    }

    public function reauthenticatedWithin(int $now, int $ttlSeconds): bool
    {
        if (!$this->authenticated || $this->reauthenticatedAt <= 0) {
            return false;
        }

        return $this->reauthenticatedAt >= ($now - $ttlSeconds);
    }

    public function clearAuthentication(): void
    {
        $this->authenticated = false;
        $this->username = '';
        $this->authenticatedAt = 0;
        $this->reauthenticatedAt = 0;
    }
}

namespace Totman\RuntimeUi\Security;

final class AdminSessionStore
{
    private const KEY_AUTHENTICATED = 'totman_ui_admin_authenticated';
    private const KEY_USERNAME = 'totman_ui_admin_username';
    private const KEY_AUTHENTICATED_AT = 'totman_ui_admin_authenticated_at';
    private const KEY_REAUTHENTICATED_AT = 'totman_ui_admin_reauthenticated_at';

    public function __construct(private ?AdminSessionState $fallbackState = null)
    {
    }

    public function load(): AdminSessionState
    {
        return $this->loadResult()->state();
    }

    public function loadResult(): AdminSessionLoadResult
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $keys = [
                self::KEY_AUTHENTICATED,
                self::KEY_USERNAME,
                self::KEY_AUTHENTICATED_AT,
                self::KEY_REAUTHENTICATED_AT,
            ];
            $hasAny = false;
            foreach ($keys as $key) {
                $hasAny = $hasAny || array_key_exists($key, $_SESSION);
            }

            if (!$hasAny) {
                return AdminSessionLoadResult::missing();
            }

            $authenticated = $_SESSION[self::KEY_AUTHENTICATED] ?? false;
            $username = $_SESSION[self::KEY_USERNAME] ?? '';
            $authenticatedAt = $_SESSION[self::KEY_AUTHENTICATED_AT] ?? 0;
            $reauthenticatedAt = $_SESSION[self::KEY_REAUTHENTICATED_AT] ?? 0;
            if (!is_bool($authenticated) || !is_string($username) || !is_int($authenticatedAt) || !is_int($reauthenticatedAt)) {
                return AdminSessionLoadResult::corrupt('Admin session state was unreadable and has been reset.');
            }

            return AdminSessionLoadResult::loaded(new AdminSessionState($authenticated, $username, $authenticatedAt, $reauthenticatedAt));
        }

        if ($this->fallbackState === null) {
            $this->fallbackState = new AdminSessionState();
            return AdminSessionLoadResult::missing();
        }

        return AdminSessionLoadResult::loaded($this->fallbackState);
    }

    public function save(AdminSessionState $state): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::KEY_AUTHENTICATED] = $state->authenticated();
            $_SESSION[self::KEY_USERNAME] = $state->username();
            $_SESSION[self::KEY_AUTHENTICATED_AT] = $state->authenticatedAt();
            $_SESSION[self::KEY_REAUTHENTICATED_AT] = $state->reauthenticatedAt();
            return;
        }

        $this->fallbackState = $state;
    }
}

namespace Totman\RuntimeUi\Security;

final class CsrfTokens
{
    public function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function verify(string $expected, string $provided): bool
    {
        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }
}

namespace Totman\RuntimeUi\Security;

final class HmacSecretGenerator
{
    public function generateHex(): string
    {
        return bin2hex(random_bytes(32));
    }
}

namespace Totman\RuntimeUi\Security;

final class InMemoryRateLimiter
{
    /** @var array<string, list<int>> */
    private array $hits = [];

    public function allow(string $key, int $limit, int $windowSeconds, int $now): bool
    {
        $cutoff = $now - $windowSeconds;
        $this->hits[$key] = array_values(array_filter(
            $this->hits[$key] ?? [],
            static fn (int $timestamp): bool => $timestamp > $cutoff
        ));

        if (count($this->hits[$key]) >= $limit) {
            return false;
        }

        $this->hits[$key][] = $now;

        return true;
    }
}

namespace Totman\RuntimeUi\Security;

use Totman\RuntimeUi\Http\FirstRunRequest;

final class PrototypeCsrfPolicy
{
    public function __construct(private readonly CsrfTokens $csrfTokens = new CsrfTokens())
    {
    }

    public function ensureToken(SetupSessionState $sessionState): string
    {
        if ($sessionState->csrfToken() === '') {
            $sessionState->setCsrfToken($this->csrfTokens->issue());
        }

        return $sessionState->csrfToken();
    }

    public function evaluate(FirstRunRequest $request, SetupSessionState $sessionState): SetupAccessResult
    {
        if (!$request->isStateChanging()) {
            return SetupAccessResult::allow();
        }

        if (!$this->csrfTokens->verify($sessionState->csrfToken(), $request->csrfToken())) {
            return SetupAccessResult::denied('csrf_required', 'A valid CSRF token is required before saving configuration.');
        }

        return SetupAccessResult::allow();
    }
}

namespace Totman\RuntimeUi\Security;

use Totman\RuntimeUi\Http\FirstRunRequest;

final class PrototypeRateLimitPolicy
{
    public function __construct(
        private readonly InMemoryRateLimiter $rateLimiter = new InMemoryRateLimiter(),
        private readonly int $limit = 5,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function evaluate(FirstRunRequest $request, string $scope, int $now): SetupAccessResult
    {
        if (!$request->isStateChanging() || $request->isLogout()) {
            return SetupAccessResult::allow();
        }

        [$bucket, $message] = $this->bucket($request);
        $key = 'totman-ui-' . $bucket . ':' . hash('sha256', $scope);
        if (!$this->rateLimiter->allow($key, $this->limit, $this->windowSeconds, $now)) {
            return SetupAccessResult::denied('rate_limited', $message);
        }

        return SetupAccessResult::allow();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function bucket(FirstRunRequest $request): array
    {
        if ($request->isUpdateDraft()) {
            return ['draft-update', 'Too many draft update attempts. Try again later.'];
        }

        if ($request->isRuntimeSave()) {
            return ['runtime-save', 'Too many runtime-save attempts. Try again later.'];
        }

        if ($request->isCreateAdmin()) {
            return ['admin-create', 'Too many admin creation attempts. Try again later.'];
        }

        if ($request->isLogin() || $request->isReauth()) {
            return ['auth-attempts', 'Too many auth attempts. Try again later.'];
        }

        if ($request->isAdminCommand()) {
            return ['admin-command', 'Too many admin command attempts. Try again later.'];
        }

        return ['state-change', 'Too many state-changing attempts. Try again later.'];
    }
}

namespace Totman\RuntimeUi\Security;

use Totman\RuntimeUi\Http\FirstRunRequest;

final class PrototypeSaveIntentPolicy
{
    public function evaluate(FirstRunRequest $request): SetupAccessResult
    {
        if (!$request->isRuntimeSave()) {
            return SetupAccessResult::allow();
        }

        if (!$request->confirmSave()) {
            return SetupAccessResult::denied('save_confirmation_required', 'Explicit save confirmation is required before writing runtime configuration.');
        }

        return SetupAccessResult::allow();
    }
}

namespace Totman\RuntimeUi\Security;

final class SecretRedactor
{
    private const REDACTED = '[redacted]';

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function redactArray(array $data): array
    {
        $redacted = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactArray($value) : $value;
        }

        return $redacted;
    }

    public function redactText(string $text): string
    {
        $text = preg_replace('/((?:secret|password|token|setup_code|hmac)[a-z0-9_-]*\s*[:=]\s*)[^\s]+/i', '$1' . self::REDACTED, $text) ?? $text;

        return preg_replace('/\b[a-f0-9]{64}\b/i', self::REDACTED, $text) ?? $text;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        return str_contains($lower, 'secret')
            || str_contains($lower, 'password')
            || str_contains($lower, 'token')
            || str_contains($lower, 'setup_code');
    }
}

namespace Totman\RuntimeUi\Security;

final class SessionSecurity
{
    public function regenerateIfActive(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return session_regenerate_id(true);
    }
}

namespace Totman\RuntimeUi\Security;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Http\FirstRunRequest;

final class SetupAccessPolicy
{
    public function __construct(
        private readonly SetupCodeVerifier $setupCodeVerifier = new SetupCodeVerifier(),
        private readonly SessionSecurity $sessionSecurity = new SessionSecurity(),
    ) {
    }

    public function evaluate(
        DiscoveryResult $discovered,
        FirstRunRequest $request,
        string $expectedSetupCode = '',
        ?SetupSessionState $sessionState = null,
        ?AdminSessionState $adminSessionState = null,
    ): SetupAccessResult {
        $sessionState ??= new SetupSessionState();
        $adminSessionState ??= new AdminSessionState();

        if ($discovered->mode() === 'fresh' || !$discovered->mainLiveStatus()->loaded()) {
            if (!$request->isRuntimeSave()) {
                return SetupAccessResult::allow();
            }

            if ($sessionState->setupVerified()) {
                return SetupAccessResult::allow();
            }

            if ($expectedSetupCode === '' || !$this->setupCodeVerifier->verify($expectedSetupCode, $request->setupCode())) {
                return SetupAccessResult::denied('setup_code_required', 'A valid setup code is required before saving first-run configuration.');
            }

            $sessionState->markSetupVerified();
            $this->sessionSecurity->regenerateIfActive();

            return SetupAccessResult::allow();
        }

        if ($discovered->mode() === 'blocked') {
            return SetupAccessResult::denied('config_blocked', 'Configuration discovery is blocked by an unreadable or invalid runtime config.');
        }

        if (($discovered->effectiveMainConfig()['web_ui_enabled'] ?? null) !== true) {
            return SetupAccessResult::denied('administration_disabled', 'Browser administration is disabled by web_ui_enabled.');
        }

        if (!$adminSessionState->authenticated()) {
            return SetupAccessResult::denied('admin_auth_required', 'An authenticated admin session is required before browser administration.');
        }

        return SetupAccessResult::allow();
    }
}

namespace Totman\RuntimeUi\Security;

final class SetupAccessResult
{
    public function __construct(
        private readonly bool $allowed,
        private readonly string $code = 'allowed',
        private readonly string $message = '',
    ) {
    }

    public static function allow(): self
    {
        return new self(true);
    }

    public static function denied(string $code, string $message): self
    {
        return new self(false, $code, $message);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }
}

namespace Totman\RuntimeUi\Security;

final class SetupCodeVerifier
{
    public function verify(string $expected, string $provided): bool
    {
        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }
}

namespace Totman\RuntimeUi\Security;

final class SetupSessionLoadResult
{
    public const LOADED = 'loaded';
    public const MISSING = 'missing';
    public const CORRUPT = 'corrupt';

    public function __construct(
        private readonly string $status,
        private readonly SetupSessionState $state,
        private readonly string $message = '',
    ) {
    }

    public static function loaded(SetupSessionState $state): self
    {
        return new self(self::LOADED, $state);
    }

    public static function missing(): self
    {
        return new self(self::MISSING, new SetupSessionState());
    }

    public static function corrupt(string $message): self
    {
        return new self(self::CORRUPT, new SetupSessionState(), $message);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function state(): SetupSessionState
    {
        return $this->state;
    }

    public function notice(): string
    {
        if ($this->status !== self::CORRUPT || $this->message === '') {
            return '';
        }

        return $this->message;
    }
}

namespace Totman\RuntimeUi\Security;

final class SetupSessionState
{
    public function __construct(
        private bool $setupVerified = false,
        private string $csrfToken = '',
    ) {
    }

    public function setupVerified(): bool
    {
        return $this->setupVerified;
    }

    public function markSetupVerified(): void
    {
        $this->setupVerified = true;
    }

    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    public function setCsrfToken(string $csrfToken): void
    {
        $this->csrfToken = $csrfToken;
    }
}

namespace Totman\RuntimeUi\Security;

final class SetupSessionStore
{
    private const KEY_SETUP_VERIFIED = 'totman_ui_setup_verified';
    private const KEY_CSRF_TOKEN = 'totman_ui_csrf_token';

    public function __construct(private ?SetupSessionState $fallbackState = null)
    {
    }

    public function load(): SetupSessionState
    {
        return $this->loadResult()->state();
    }

    public function loadResult(): SetupSessionLoadResult
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $hasVerified = array_key_exists(self::KEY_SETUP_VERIFIED, $_SESSION);
            $hasCsrf = array_key_exists(self::KEY_CSRF_TOKEN, $_SESSION);
            if (!$hasVerified && !$hasCsrf) {
                return SetupSessionLoadResult::missing();
            }

            $verified = $_SESSION[self::KEY_SETUP_VERIFIED] ?? false;
            $csrf = $_SESSION[self::KEY_CSRF_TOKEN] ?? '';
            if (!is_bool($verified) || !is_string($csrf)) {
                return SetupSessionLoadResult::corrupt('Setup session state was unreadable and has been reset.');
            }

            return SetupSessionLoadResult::loaded(new SetupSessionState($verified, $csrf));
        }

        if ($this->fallbackState === null) {
            $this->fallbackState = new SetupSessionState();
            return SetupSessionLoadResult::missing();
        }

        return SetupSessionLoadResult::loaded($this->fallbackState);
    }

    public function save(SetupSessionState $state): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::KEY_SETUP_VERIFIED] = $state->setupVerified();
            $_SESSION[self::KEY_CSRF_TOKEN] = $state->csrfToken();
            return;
        }

        $this->fallbackState = $state;
    }
}

namespace Totman\RuntimeUi\Security;

final class UiPrivateConfig
{
    public function __construct(private readonly ?AdminCredential $adminCredential = null)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $admin = $data['admin'] ?? null;
        if (!is_array($admin)) {
            return new self();
        }

        return new self(AdminCredential::fromArray($admin));
    }

    public function hasAdminCredential(): bool
    {
        return $this->adminCredential !== null;
    }

    public function adminCredential(): ?AdminCredential
    {
        return $this->adminCredential;
    }

    public function withAdminCredential(AdminCredential $credential): self
    {
        return new self($credential);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'admin' => $this->adminCredential?->toArray(),
        ];
    }
}

namespace Totman\RuntimeUi\Security;

final class UiPrivateConfigLoadResult
{
    public const LOADED = 'loaded';
    public const MISSING = 'missing';
    public const CORRUPT = 'corrupt';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        private readonly string $status,
        private readonly UiPrivateConfig $config,
        private readonly string $message = '',
    ) {
    }

    public static function loaded(UiPrivateConfig $config): self
    {
        return new self(self::LOADED, $config);
    }

    public static function missing(): self
    {
        return new self(self::MISSING, new UiPrivateConfig());
    }

    public static function corrupt(string $message): self
    {
        return new self(self::CORRUPT, new UiPrivateConfig(), $message);
    }

    public static function unavailable(string $message): self
    {
        return new self(self::UNAVAILABLE, new UiPrivateConfig(), $message);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function config(): UiPrivateConfig
    {
        return $this->config;
    }

    public function blocksAdmin(): bool
    {
        return $this->status === self::CORRUPT || $this->status === self::UNAVAILABLE;
    }

    public function message(): string
    {
        return $this->message;
    }
}

namespace Totman\RuntimeUi\Security;

final class UiPrivateConfigStore
{
    public const DEFAULT_FILE_NAME = '.totman-ui.php';

    public function __construct(private readonly string $path)
    {
    }

    public static function forStateDir(string $stateDir): self
    {
        return new self(rtrim($stateDir, '/') . '/' . self::DEFAULT_FILE_NAME);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function load(): UiPrivateConfig
    {
        return $this->loadResult()->config();
    }

    public function loadResult(): UiPrivateConfigLoadResult
    {
        if (!is_file($this->path)) {
            return UiPrivateConfigLoadResult::missing();
        }

        if (!is_readable($this->path)) {
            return UiPrivateConfigLoadResult::unavailable('Private UI config is not readable.');
        }

        try {
            $data = (static function (string $path): mixed {
                return require $path;
            })($this->path);
        } catch (\Throwable) {
            return UiPrivateConfigLoadResult::corrupt('Private UI config could not be parsed.');
        }

        if (!is_array($data)) {
            return UiPrivateConfigLoadResult::corrupt('Private UI config did not return an array.');
        }

        return UiPrivateConfigLoadResult::loaded(UiPrivateConfig::fromArray($data));
    }

    public function save(UiPrivateConfig $config): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create private UI config directory: ' . $dir);
        }

        $tmp = tempnam($dir, '.totman-ui.');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create private UI config temporary file.');
        }

        $content = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "// Generated by the totman runtime UI. Do not commit this private file.\n"
            . 'return ' . var_export($config->toArray(), true) . ";\n";

        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            @unlink($tmp);
            throw new \RuntimeException('Could not write private UI config temporary file.');
        }

        @chmod($tmp, 0600);
        if (!rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not replace private UI config atomically.');
        }

        @chmod($this->path, 0600);
    }
}

namespace Totman\RuntimeUi\Setup;

final class FirstRunFlow
{
    /**
     * @param list<string> $steps
     */
    public function __construct(
        private readonly string $mode,
        private readonly string $currentStep,
        private readonly array $steps,
        private readonly bool $canSave,
        private readonly bool $administrationEnabledAfterSetup,
        private readonly string $preflightStatus,
    ) {
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function currentStep(): string
    {
        return $this->currentStep;
    }

    /**
     * @return list<string>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function canSave(): bool
    {
        return $this->canSave;
    }

    public function administrationEnabledAfterSetup(): bool
    {
        return $this->administrationEnabledAfterSetup;
    }

    public function preflightStatus(): string
    {
        return $this->preflightStatus;
    }
}

namespace Totman\RuntimeUi\Setup;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Config\MainConfigImport;
use Totman\RuntimeUi\Config\RecipientConfigImport;
use Totman\RuntimeUi\Preflight\PreflightResult;

final class FirstRunOrchestrator
{
    public function __construct(private readonly FirstRunStepCatalog $stepCatalog = new FirstRunStepCatalog())
    {
    }

    public function evaluate(
        DiscoveryResult $discovery,
        MainConfigImport $main,
        RecipientConfigImport $recipients,
        PreflightResult $preflight
    ): FirstRunFlow {
        if ($discovery->mode() === 'blocked') {
            return new FirstRunFlow(
                'blocked',
                FirstRunStepCatalog::REPAIR_BLOCKING_PROBLEM,
                $this->stepCatalog->stepsForMode('blocked'),
                false,
                false,
                $preflight->status()
            );
        }

        $mode = $discovery->mode() === 'fresh' ? 'fresh' : 'existing';
        $currentStep = $this->currentStep($mode, $main, $recipients, $preflight);
        $webUiEnabled = $main->field('web_ui_enabled')->value() === true;

        return new FirstRunFlow(
            $mode,
            $currentStep,
            $this->stepCatalog->stepsForMode($mode),
            $preflight->status() !== 'FAIL',
            $webUiEnabled,
            $preflight->status()
        );
    }

    private function currentStep(string $mode, MainConfigImport $main, RecipientConfigImport $recipients, PreflightResult $preflight): string
    {
        if ($mode === 'fresh') {
            return FirstRunStepCatalog::CREATE_OR_IMPORT;
        }

        foreach ($main->fieldsNeedingOperatorInput() as $field) {
            return match ($field->key()) {
                'base_url' => FirstRunStepCatalog::PUBLIC_URL,
                'mail_from', 'sendmail_path' => FirstRunStepCatalog::MAIL_DELIVERY,
                'to_self' => FirstRunStepCatalog::OPERATOR_MAILBOX,
                default => FirstRunStepCatalog::REVIEW,
            };
        }

        if (!$recipients->readyForFirstRecipient()) {
            return FirstRunStepCatalog::FIRST_RECIPIENT;
        }

        if ($preflight->status() === 'FAIL') {
            return FirstRunStepCatalog::PREFLIGHT;
        }

        return FirstRunStepCatalog::SAVE;
    }
}

namespace Totman\RuntimeUi\Setup;

use Totman\RuntimeUi\Config\DiscoveryResult;
use Totman\RuntimeUi\Deployment\DeploymentContext;

final class FirstRunPlanner
{
    public function __construct(private readonly FirstRunStepCatalog $stepCatalog = new FirstRunStepCatalog())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function plan(DiscoveryResult $discovery, DeploymentContext $context): array
    {
        return [
            'mode' => $discovery->mode(),
            'deployment_context' => $context->kind(),
            'path_fields_read_only' => $context->pathFieldsAreReadOnly(),
            'fixed_state_dir' => $context->fixedStateDir(),
            'fixed_download_dir' => $context->fixedDownloadDir(),
            'browser_setup_expected' => $context->capabilities()->browserSetupExpected(),
            'manual_volume_edits_expected' => $context->capabilities()->manualVolumeEditsExpected(),
            'setup_sources' => $context->capabilities()->setupSources(),
            'steps' => $this->stepCatalog->stepsForMode($discovery->mode()),
            'issues' => $discovery->issues(),
        ];
    }
}

namespace Totman\RuntimeUi\Setup;

final class FirstRunStepCatalog
{
    public const DISCOVER = 'discover';
    public const REPAIR_BLOCKING_PROBLEM = 'repair-blocking-problem';
    public const CREATE_OR_IMPORT = 'create-or-import';
    public const PUBLIC_URL = 'public-url';
    public const MAIL_DELIVERY = 'mail-delivery';
    public const OPERATOR_MAILBOX = 'operator-mailbox';
    public const FIRST_RECIPIENT = 'first-recipient';
    public const FIRST_MESSAGE = 'first-message';
    public const OPTIONAL_DOWNLOAD = 'optional-download';
    public const REVIEW = 'review';
    public const PREFLIGHT = 'preflight';
    public const SAVE = 'save';
    public const COMPLETE = 'complete';

    /** @return list<string> */
    public function stepsForMode(string $mode): array
    {
        if ($mode === 'blocked') {
            return [
                self::DISCOVER,
                self::REPAIR_BLOCKING_PROBLEM,
            ];
        }

        return [
            self::DISCOVER,
            self::CREATE_OR_IMPORT,
            self::PUBLIC_URL,
            self::MAIL_DELIVERY,
            self::OPERATOR_MAILBOX,
            self::FIRST_RECIPIENT,
            self::FIRST_MESSAGE,
            self::OPTIONAL_DOWNLOAD,
            self::REVIEW,
            self::PREFLIGHT,
            self::SAVE,
            self::COMPLETE,
        ];
    }

    public function title(string $key): string
    {
        return match ($key) {
            self::DISCOVER => 'Discover runtime',
            self::REPAIR_BLOCKING_PROBLEM => 'Repair blocking problem',
            self::CREATE_OR_IMPORT => 'Create or import',
            self::PUBLIC_URL => 'Public URL',
            self::MAIL_DELIVERY => 'Mail delivery',
            self::OPERATOR_MAILBOX => 'Operator mailbox',
            self::FIRST_RECIPIENT => 'First recipient',
            self::FIRST_MESSAGE => 'First message',
            self::OPTIONAL_DOWNLOAD => 'Optional download',
            self::REVIEW => 'Review',
            self::PREFLIGHT => 'Preflight',
            self::SAVE => 'Save runtime files',
            self::COMPLETE => 'Complete',
            default => $key,
        };
    }
}

namespace Totman\RuntimeUi\Bundle;

use Totman\RuntimeUi\Application\PrototypeApplicationFactory;
use Totman\RuntimeUi\Application\RuntimeUiTextCatalog;
use Totman\RuntimeUi\Http\ProductRuntimeContextAdapter;
use Totman\RuntimeUi\Http\PrototypeEnvironmentFactory;

use Totman\RuntimeUi\Http\PrototypeRenderer;


final class BundleManifest
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        return
array (
  'entry_mode' => 'product bundle',
  'runtime_ui_mode' => 'product',
  'source_revision' => 'b18b3ac',
  'source_files' =>
  array (
    0 => 'src/Application/AdminAuthApplicationResult.php',
    1 => 'src/Application/AdminAuthApplicationService.php',
    2 => 'src/Application/AdminAuthViewModel.php',
    3 => 'src/Application/AdminAuthViewModelBuilder.php',
    4 => 'src/Application/AdminCommand.php',
    5 => 'src/Application/AdminCommandAccessPolicy.php',
    6 => 'src/Application/AdminCommandCatalog.php',
    7 => 'src/Application/AdminInspectionViewModel.php',
    8 => 'src/Application/DangerCommandDryRunService.php',
    9 => 'src/Application/DangerCommandPreview.php',
    10 => 'src/Application/FileAliasInventory.php',
    11 => 'src/Application/FileAliasInventoryItem.php',
    12 => 'src/Application/FileAliasInventoryReader.php',
    13 => 'src/Application/FirstRunAction.php',
    14 => 'src/Application/FirstRunDraftLoadResult.php',
    15 => 'src/Application/FirstRunDraftPreflightGate.php',
    16 => 'src/Application/FirstRunDraftPreflightResult.php',
    17 => 'src/Application/FirstRunDraftState.php',
    18 => 'src/Application/FirstRunDraftStore.php',
    19 => 'src/Application/FirstRunField.php',
    20 => 'src/Application/FirstRunHiddenFieldPolicy.php',
    21 => 'src/Application/FirstRunInput.php',
    22 => 'src/Application/FirstRunInputValidator.php',
    23 => 'src/Application/FirstRunSaveResult.php',
    24 => 'src/Application/FirstRunSetupService.php',
    25 => 'src/Application/FirstRunStep.php',
    26 => 'src/Application/FirstRunStepValidator.php',
    27 => 'src/Application/FirstRunViewModel.php',
    28 => 'src/Application/FirstRunViewModelBuilder.php',
    29 => 'src/Application/FirstRunWizardApplicationService.php',
    30 => 'src/Application/FirstRunWizardCommand.php',
    31 => 'src/Application/FirstRunWizardNavigationResult.php',
    32 => 'src/Application/FirstRunWizardNavigator.php',
    33 => 'src/Application/FirstRunWizardResult.php',
    34 => 'src/Application/MainConfigDraftBuilder.php',
    35 => 'src/Application/MaintenanceCommandResult.php',
    36 => 'src/Application/MaintenanceCommandService.php',
    37 => 'src/Application/MaintenanceRuntimeMutator.php',
    38 => 'src/Application/PrototypeApplicationFactory.php',
    39 => 'src/Application/PrototypePageApplicationService.php',
    40 => 'src/Application/PrototypePageResult.php',
    41 => 'src/Application/RecipientConfigDraft.php',
    42 => 'src/Application/RecipientConfigDraftBuilder.php',
    43 => 'src/Application/RuntimeLogReader.php',
    44 => 'src/Application/RuntimeLogTail.php',
    45 => 'src/Application/RuntimeStateFileStatus.php',
    46 => 'src/Application/RuntimeSummary.php',
    47 => 'src/Application/RuntimeSummaryReader.php',
    48 => 'src/Application/RuntimeUiTextCatalog.php',
    49 => 'src/Config/ConfigCoverageResult.php',
    50 => 'src/Config/ConfigDiscovery.php',
    51 => 'src/Config/ConfigFileStatus.php',
    52 => 'src/Config/DiscoveryIssue.php',
    53 => 'src/Config/DiscoveryResult.php',
    54 => 'src/Config/ImportedField.php',
    55 => 'src/Config/MainConfigCoverage.php',
    56 => 'src/Config/MainConfigImport.php',
    57 => 'src/Config/MainConfigImporter.php',
    58 => 'src/Config/MainConfigWriter.php',
    59 => 'src/Config/RecipientConfigCoverage.php',
    60 => 'src/Config/RecipientConfigImport.php',
    61 => 'src/Config/RecipientConfigImporter.php',
    62 => 'src/Config/RecipientConfigWriter.php',
    63 => 'src/Contracts/RuntimeFileNames.php',
    64 => 'src/Deployment/DeploymentCapabilities.php',
    65 => 'src/Deployment/DeploymentContext.php',
    66 => 'src/Http/FirstRunRequest.php',
    67 => 'src/Http/FirstRunRequestMapper.php',
    68 => 'src/Http/ProductRuntimeContextAdapter.php',
    69 => 'src/Http/PrototypeController.php',
    70 => 'src/Http/PrototypeEnvironment.php',
    71 => 'src/Http/PrototypeEnvironmentFactory.php',
    72 => 'src/Http/PrototypeRenderer.php',
    73 => 'src/Http/RuntimeUiMode.php',
    74 => 'src/Preflight/FirstRunPreflight.php',
    75 => 'src/Preflight/PreflightCheck.php',
    76 => 'src/Preflight/PreflightResult.php',
    77 => 'src/Security/AdminAuthInput.php',
    78 => 'src/Security/AdminAuthService.php',
    79 => 'src/Security/AdminCredential.php',
    80 => 'src/Security/AdminReauthPolicy.php',
    81 => 'src/Security/AdminSessionLoadResult.php',
    82 => 'src/Security/AdminSessionState.php',
    83 => 'src/Security/AdminSessionStore.php',
    84 => 'src/Security/CsrfTokens.php',
    85 => 'src/Security/HmacSecretGenerator.php',
    86 => 'src/Security/InMemoryRateLimiter.php',
    87 => 'src/Security/PrototypeCsrfPolicy.php',
    88 => 'src/Security/PrototypeRateLimitPolicy.php',
    89 => 'src/Security/PrototypeSaveIntentPolicy.php',
    90 => 'src/Security/SecretRedactor.php',
    91 => 'src/Security/SessionSecurity.php',
    92 => 'src/Security/SetupAccessPolicy.php',
    93 => 'src/Security/SetupAccessResult.php',
    94 => 'src/Security/SetupCodeVerifier.php',
    95 => 'src/Security/SetupSessionLoadResult.php',
    96 => 'src/Security/SetupSessionState.php',
    97 => 'src/Security/SetupSessionStore.php',
    98 => 'src/Security/UiPrivateConfig.php',
    99 => 'src/Security/UiPrivateConfigLoadResult.php',
    100 => 'src/Security/UiPrivateConfigStore.php',
    101 => 'src/Setup/FirstRunFlow.php',
    102 => 'src/Setup/FirstRunOrchestrator.php',
    103 => 'src/Setup/FirstRunPlanner.php',
    104 => 'src/Setup/FirstRunStepCatalog.php',
  ),
  'excluded' =>
  array (
    0 => 'product runtime files',
    1 => 'secrets',
    2 => 'runtime state',
    3 => '.totman-ui.php',
    4 => 'generated configs',
  ),
);
    }
}

final class PrototypeBundle
{
    public static function run(): void
    {
        $runtimeUiMode ='product';

        if (($_GET['totman_ui_asset'] ?? '') === 'css') {
            self::sendSecurityHeaders();
            self::serveStylesheet($runtimeUiMode);
            return;
        }

        if (($_GET['totman_ui_asset'] ?? '') === 'js') {
            self::sendSecurityHeaders();
            self::serveScript($runtimeUiMode);
            return;
        }

        self::sendSecurityHeaders();
        self::startSession();

        $env =[
            'TOTMAN_UI_DEPLOYMENT_CONTEXT' => getenv('TOTMAN_UI_DEPLOYMENT_CONTEXT') ?: '',
            'TOTMAN_STATE_DIR' => getenv('TOTMAN_STATE_DIR') ?: '',
            'TOTMAN_UI_SETUP_CODE' => getenv('TOTMAN_UI_SETUP_CODE') ?: \Totman\RuntimeUi\Config\TOTMAN_UI_SETUP_CODE,
        ];
        $environmentFactory = new PrototypeEnvironmentFactory();
        $environment = $runtimeUiMode === 'product'
            ? (new ProductRuntimeContextAdapter($environmentFactory))->fromArrays($_SERVER, $_POST, $env)
            : $environmentFactory->fromArrays($_GET, $_SERVER, $_POST, $env, __DIR__ . '/var/runtime', $runtimeUiMode);
        $environmentFactory->ensureStateDirectory($environment->stateDir());

        $controller = (new PrototypeApplicationFactory(expectedSetupCode: $environment->expectedSetupCode(), runtimeUiMode: $runtimeUiMode))->controller();
        echo $controller->handle($environment->stateDir(), $environment->context(), $environment->method(), $environment->post());
    }

    private static function serveStylesheet(string $runtimeUiMode): void
    {
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo (new PrototypeRenderer(text: new RuntimeUiTextCatalog($runtimeUiMode)))->stylesheet();
    }

    private static function serveScript(string $runtimeUiMode): void
    {
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo (new PrototypeRenderer(text: new RuntimeUiTextCatalog($runtimeUiMode)))->script();
    }

    private static function sendSecurityHeaders(): void
    {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
    }

    private static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}

if (!defined('TOTMAN_RUNTIME_UI_BUNDLE_NO_RUN')) {
    PrototypeBundle::run();
}