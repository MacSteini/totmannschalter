# Contribution guide
![totman](img/totman-xs.png)

This guide defines the expected workflow for pull requests in this repository.
## Scope
- Contributions are welcome for bug fixes, documentation improvements, tests, and focused feature work.
- Useful issue reports, questions, and partial observations are welcome even if you cannot run local checks.
- For breaking changes or larger refactors, open an issue first and agree scope before implementation.
## Workflow (step-by-step)
1. Fork this repository.
2. Create a branch using a descriptive name.
3. Keep the change focused on one concern.
4. Update user-facing documentation in parallel with code changes.
5. Run local checks if you can.
6. Commit with a clear message that describes the change intent.
7. Open a pull request with a concise summary and any checks or observations you can provide.
## Branch naming
- Use one of these prefixes:
	- `feature/<short-topic>`
	- `fix/<short-topic>`
	- `docs/<short-topic>`
	- `chore/<short-topic>`
## Local checks
If you are comfortable running PHP locally, these checks are useful before opening a pull request.
They are not required for reporting a problem, asking a question, or sending a small documentation correction.

For PHP changes, run syntax checks on the files you changed:
```sh
php -l totman-lib.php
php -l totman-tick.php
php -l totman.php
php -l totman.inc.dist.php
php -l totman-recipients.dist.php
```

For install or configuration-flow changes, this deployed-state check is also useful:
```sh
php totman-tick.php check
```
If you cannot run these commands, still open the issue or pull request and say what you changed or observed.
## Pull request checklist
Before submitting, confirm:
1. The change solves one clear problem.
2. Documentation was updated where relevant.
3. Any validation commands you could run are listed.
4. No secrets, credentials, or private keys are included.
5. The pull request description lists affected files and expected behaviour impact.
## Security and secrets
- Never commit secrets, API keys, tokens, private keys, or production credentials.
- Keep example values as placeholders in public files.
- If you discover a security issue, report it privately to the maintainer instead of opening a public exploit description.
## Review process
- Address review comments with focused follow-up commits.
- Keep discussion technical, concise, and evidence-based.
- Mark unresolved risks or assumptions explicitly in the pull request.
