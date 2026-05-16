# totman – Release runbook
![totman](../img/totman-icon.png)

This runbook is for maintainers preparing an approved release. It does not
authorise a release by itself.

## Preconditions

- The product worktree is clean.
- The intended release version and tag have been approved.
- No local runtime files, logs, private UI state, generated reports, sandbox
  files, or agent-only paths are staged.
- Docker publication is approved separately when a GHCR image should be pushed.

## Final product checks

Run from the workspace root:

```sh
find totman -name '*.php' -exec php -l {} \;
git -C totman diff --check
git -C totman archive --format=tar HEAD | tar -tf -
```

The archive must contain `README.md`, `LICENCE`, `totman-ui.php`, the runtime
PHP/CSS files, both `.dist.php` templates, and `l18n/`. It must not contain
`docs/`, `site/`, `img/`, local agent paths, runtime state, logs, reports,
private UI files, or sandbox files.

Before tagging, install the release archive into a temporary runtime directory
and run:

```sh
php totman-tick.php check
```

The check must have no `FAIL` lines for the release fixture.

## Docker checks

Run from `Docker/` with the local build override:

```sh
docker compose -p totman_release_check -f compose.yaml -f compose.build.yaml up --build -d
docker compose -p totman_release_check -f compose.yaml -f compose.build.yaml exec web php /var/lib/totman/totman-tick.php check
docker compose -p totman_release_check -f compose.yaml -f compose.build.yaml down
```

The Docker image must use the integrated `totman-ui.php`, not a patch script.
The image must not contain `.git`, local agent paths, `ui-generator/`,
`totman-refactor/`, generated reports, logs, runtime state, or private UI files.

## Tag and GitHub release

Only after approval:

```sh
git -C totman tag -a vX.Y.Z -m "totman vX.Y.Z"
git -C totman push origin main
git -C totman push origin vX.Y.Z
```

Create the GitHub Release from the approved notes in `docs/Changelog.md` and
attach the slim archive generated from `totman/`.

## GHCR image

Only after Docker publication approval:

```sh
docker build -t ghcr.io/macsteini/totman:X.Y.Z -f Docker/Dockerfile .
docker tag ghcr.io/macsteini/totman:X.Y.Z ghcr.io/macsteini/totman:latest
docker push ghcr.io/macsteini/totman:X.Y.Z
docker push ghcr.io/macsteini/totman:latest
```

Use the same release version as the GitHub Release tag, without the leading
`v`, for `TOTMAN_IMAGE_TAG`.

## Rollback

- If a tag was pushed but no release was published, delete the remote tag only
  after maintainer approval.
- If a GitHub Release was published, mark it as superseded or delete it only
  after maintainer approval.
- If a GHCR image was pushed, publish a corrected tag rather than overwriting
  an already consumed image unless the maintainer explicitly approves the
  replacement.
